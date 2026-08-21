/**
 * Computed-style regression gate for the theme-switcher sweep (plan task 11).
 *
 * Crawls the same list of pages on two running instances of the app and compares the
 * computed colour of every element, so a "rename-only" sweep can be *proved* inert
 * rather than eyeballed. Not part of the app or its test suite — a one-off harness kept
 * next to the browser driver because it is the only other thing that drives Chromium.
 *
 * Usage (both servers already up):
 *   node computed-style-diff.mjs --before http://localhost:8100 --after http://localhost:8000
 *
 * Writes before.json / after.json / diff.json next to itself under `chromium_cli/diff/`
 * and prints a grouped summary.
 */

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const args = Object.fromEntries(
    process.argv.slice(2).reduce((pairs, token, index, all) => {
        if (token.startsWith('--')) pairs.push([token.slice(2), all[index + 1]]);
        return pairs;
    }, []),
);

const BEFORE = args.before ?? 'http://localhost:8100';
const AFTER = args.after ?? 'http://localhost:8000';
const MAX_PAGES = Number(args.max ?? 45);
const EMAIL = args.email ?? 'admin@example.com';
const PASSWORD = args.password ?? 'password';

const outputDir = path.join(path.dirname(fileURLToPath(import.meta.url)), 'chromium_cli', 'diff');
mkdirSync(outputDir, { recursive: true });

/** Anything that logs out, mutates, or streams a file rather than rendering a page. */
const SKIP = /\/(logout|download|export\/(project|ebook|site)\/|storage\/|robots\.txt)/;

const SEEDS = ['/projects', '/admin/settings', '/search?q=Melusine'];

async function login(page, origin) {
    await page.goto(`${origin}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name=email]', EMAIL);
    await page.fill('input[name=password]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('button[type=submit]')]);
}

/** BFS over same-origin GET links, returning paths in a deterministic order. */
async function crawl(page, origin) {
    const seen = new Set();
    const queue = [...SEEDS];

    while (queue.length > 0 && seen.size < MAX_PAGES) {
        const target = queue.shift();
        if (seen.has(target) || SKIP.test(target)) continue;

        let response;
        try {
            response = await page.goto(origin + target, { waitUntil: 'domcontentloaded', timeout: 20000 });
        } catch {
            continue;
        }
        if (!response || response.status() !== 200) continue;

        seen.add(target);

        const links = await page.evaluate(
            (base) =>
                [...document.querySelectorAll('a[href]')]
                    .map((a) => a.getAttribute('href'))
                    .filter((href) => href && (href.startsWith('/') || href.startsWith(base)))
                    .map((href) => (href.startsWith(base) ? href.slice(base.length) : href))
                    .filter((href) => href.startsWith('/') && !href.startsWith('//')),
            origin,
        );

        for (const link of links.sort()) {
            if (!seen.has(link) && !queue.includes(link)) queue.push(link);
        }
    }

    return [...seen].sort();
}

/** `--color-primary` as painted, per origin — proves which preset each side rendered. */
const presetsSeen = new Map();

async function capture(page, origin, paths) {
    const captured = {};

    for (const target of paths) {
        try {
            await page.goto(origin + target, { waitUntil: 'domcontentloaded', timeout: 30000 });
        } catch (error) {
            console.error(`  ! ${origin}${target}: ${error.message.split('\n')[0]}`);
            captured[target] = null;
            continue;
        }
        await page.waitForTimeout(150);

        // Which preset actually painted. The gate compares Daylight against `master`;
        // a signed-in user whose `theme_slug` is a dark preset would make every page
        // differ for a reason that is not the sweep.
        const painted = await page.evaluate(() =>
            getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim(),
        );
        if (!presetsSeen.has(painted)) presetsSeen.set(painted, []);
        presetsSeen.get(painted).push(`${origin}${target}`);

        captured[target] = await page.evaluate(() => {
            /**
             * Only properties that actually paint. A `border-left-color` on an element
             * with `border-left-width: 0` is invisible, and comparing it would drown the
             * report in the base-layer border shim's removal — a computed-value change
             * that moves no pixel.
             */
            const read = (element, pseudo) => {
                const style = getComputedStyle(element, pseudo);
                const values = {};
                const get = (property) => style.getPropertyValue(property);

                if (pseudo && get('content') === 'none') return values;

                values.color = get('color');

                const background = get('background-color');
                if (background !== 'rgba(0, 0, 0, 0)') values['background-color'] = background;

                for (const side of ['top', 'right', 'bottom', 'left']) {
                    if (get(`border-${side}-style`) !== 'none' && parseFloat(get(`border-${side}-width`)) > 0) {
                        values[`border-${side}-color`] = get(`border-${side}-color`);
                    }
                }

                if (get('outline-style') !== 'none' && parseFloat(get('outline-width')) > 0) {
                    values['outline-color'] = get('outline-color');
                }

                if (get('text-decoration-line') !== 'none') {
                    values['text-decoration-color'] = get('text-decoration-color');
                }

                const shadow = get('box-shadow');
                if (shadow !== 'none') values['box-shadow'] = shadow;

                if (element.namespaceURI === 'http://www.w3.org/2000/svg' || element.closest('svg')) {
                    values.fill = get('fill');
                    values.stroke = get('stroke');
                }

                return values;
            };

            return [...document.querySelectorAll('body *')].map((element, index) => ({
                key: `${index}:${element.tagName.toLowerCase()}`,
                label: (element.className && typeof element.className === 'string'
                    ? element.className
                    : ''
                ).slice(0, 120),
                own: read(element, null),
                before: read(element, '::before'),
                after: read(element, '::after'),
            }));
        });
    }

    return captured;
}

function diff(before, after) {
    const differences = [];

    for (const target of Object.keys(before)) {
        const left = before[target];
        const right = after[target];

        if (!left || !right) {
            differences.push({ path: target, kind: 'page-missing' });
            continue;
        }
        if (left.length !== right.length) {
            differences.push({ path: target, kind: 'element-count', before: left.length, after: right.length });
            continue;
        }

        for (let index = 0; index < left.length; index++) {
            if (left[index].key !== right[index].key) {
                differences.push({ path: target, kind: 'structure', at: index });
                break;
            }

            for (const bucket of ['own', 'before', 'after']) {
                const properties = new Set([
                    ...Object.keys(left[index][bucket]),
                    ...Object.keys(right[index][bucket]),
                ]);

                for (const property of properties) {
                    const was = left[index][bucket][property] ?? '(unset)';
                    const now = right[index][bucket][property] ?? '(unset)';
                    if (was !== now) {
                        differences.push({
                            path: target,
                            key: left[index].key,
                            bucket,
                            property,
                            before: was,
                            after: now,
                            beforeClass: left[index].label,
                            afterClass: right[index].label,
                        });
                    }
                }
            }
        }
    }

    return differences;
}

const browser = await chromium.launch();

const beforePage = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await login(beforePage, BEFORE);
const paths = await crawl(beforePage, BEFORE);
console.log(`crawled ${paths.length} pages`);
const beforeStyles = await capture(beforePage, BEFORE, paths);

const afterPage = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await login(afterPage, AFTER);
const afterStyles = await capture(afterPage, AFTER, paths);

await browser.close();

console.log('\n--color-primary as painted:');
for (const [value, where] of presetsSeen) {
    console.log(`  ${value || '(unset)'}  ×${where.length}  e.g. ${where[0]}`);
}

const differences = diff(beforeStyles, afterStyles);

writeFileSync(path.join(outputDir, 'paths.json'), JSON.stringify(paths, null, 2));
writeFileSync(path.join(outputDir, 'before.json'), JSON.stringify(beforeStyles));
writeFileSync(path.join(outputDir, 'after.json'), JSON.stringify(afterStyles));
writeFileSync(path.join(outputDir, 'diff.json'), JSON.stringify(differences, null, 2));

const grouped = new Map();
for (const difference of differences) {
    const signature = difference.kind
        ? `${difference.kind}`
        : `${difference.bucket}.${difference.property}: ${difference.before}  ->  ${difference.after}`;
    if (!grouped.has(signature)) grouped.set(signature, []);
    grouped.get(signature).push(difference);
}

console.log(`\n${differences.length} differing declarations, ${grouped.size} distinct changes\n`);
for (const [signature, items] of [...grouped].sort((a, b) => b[1].length - a[1].length)) {
    console.log(`[${String(items.length).padStart(4)}] ${signature}`);
    console.log(`         e.g. ${items[0].path}  <${items[0].key}>  class="${(items[0].beforeClass ?? '').slice(0, 90)}"`);
}
