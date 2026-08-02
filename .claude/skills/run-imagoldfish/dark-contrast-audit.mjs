/**
 * Dark-preset contrast audit.
 *
 * Crawls the app on one running instance and measures every text-bearing element
 * against its effective background, reporting anything under the WCAG floor. Built
 * to find the class of bug the token vocabulary cannot: an element that names no
 * colour at all inherits one that only works on a light page.
 *
 * Usage (server up, dev user's theme_slug set to the preset under test):
 *   node dark-contrast-audit.mjs --origin http://localhost:8000
 */

import { chromium } from 'playwright';

const args = Object.fromEntries(
    process.argv.slice(2).reduce((pairs, token, index, all) => {
        if (token.startsWith('--')) pairs.push([token.slice(2), all[index + 1]]);
        return pairs;
    }, []),
);

const ORIGIN = args.origin ?? 'http://localhost:8000';
const MAX_PAGES = Number(args.max ?? 45);
const EMAIL = args.email ?? 'admin@example.com';
const PASSWORD = args.password ?? 'password';

const SKIP = /\/(logout|download|export\/(project|ebook|site)\/|storage\/|robots\.txt)/;
const SEEDS = ['/dashboard', '/projects', '/admin/settings', '/search?q=Melusine'];

async function login(page) {
    await page.goto(`${ORIGIN}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name=email]', EMAIL);
    await page.fill('input[name=password]', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.click('button[type=submit]'),
    ]);
}

async function crawl(page) {
    const seen = new Set();
    const queue = [...SEEDS];

    while (queue.length > 0 && seen.size < MAX_PAGES) {
        const target = queue.shift();
        if (seen.has(target) || SKIP.test(target)) continue;

        let response;
        try {
            response = await page.goto(ORIGIN + target, { waitUntil: 'domcontentloaded', timeout: 20000 });
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
            ORIGIN,
        );

        for (const link of links.sort()) {
            if (!seen.has(link) && !queue.includes(link)) queue.push(link);
        }
    }

    return [...seen].sort();
}

/**
 * Everything below runs in the page: a canvas normalises any CSS colour notation
 * (the app paints in `oklch()`) to rgba, which is the only reliable way to compare
 * an authored colour with a computed one.
 */
const auditInPage = () => {
    const canvas = document.createElement('canvas').getContext('2d', { willReadFrequently: true });

    /**
     * Paint one pixel and read it back, rather than trusting `ctx.fillStyle` to
     * normalise. Chromium echoes `oklch(...)` back verbatim — which a naive parse
     * happily misreads as three RGB channels — whereas the painted pixel is always
     * sRGB bytes, and is also exactly what the eye gets.
     */
    const toRgb = (value) => {
        if (!value || value === 'transparent') return null;
        canvas.clearRect(0, 0, 1, 1);
        canvas.fillStyle = value;
        canvas.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = canvas.getImageData(0, 0, 1, 1).data;
        return [r, g, b, a / 255];
    };

    const luminance = ([r, g, b]) => {
        const channel = (value) => {
            const scaled = value / 255;
            return scaled <= 0.03928 ? scaled / 12.92 : ((scaled + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
    };

    const ratio = (a, b) => {
        const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);
        return (high + 0.05) / (low + 0.05);
    };

    const composite = (top, bottom) => {
        const alpha = top[3];
        return [
            top[0] * alpha + bottom[0] * (1 - alpha),
            top[1] * alpha + bottom[1] * (1 - alpha),
            top[2] * alpha + bottom[2] * (1 - alpha),
            1,
        ];
    };

    /** First opaque background walking up, compositing any translucent layers on the way. */
    const effectiveBackground = (element) => {
        const stack = [];
        let node = element;
        while (node) {
            const background = toRgb(getComputedStyle(node).backgroundColor);
            if (background && background[3] > 0) {
                stack.push(background);
                if (background[3] === 1) break;
            }
            node = node.parentElement;
        }
        let result = [255, 255, 255, 1];
        for (let i = stack.length - 1; i >= 0; i--) result = composite(stack[i], result);
        return result;
    };

    const describe = (element) => {
        const classes = (element.className || '').toString().trim().split(/\s+/).slice(0, 5).join(' ');
        return element.tagName.toLowerCase() + (classes ? `.${classes}` : '');
    };

    const findings = [];

    for (const element of document.querySelectorAll('*')) {
        // Leaf text only: an ancestor's contrast is its children's problem.
        const ownText = [...element.childNodes]
            .filter((node) => node.nodeType === Node.TEXT_NODE)
            .map((node) => node.textContent.trim())
            .join('');
        if (!ownText) continue;

        const style = getComputedStyle(element);
        if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') continue;

        const box = element.getBoundingClientRect();
        if (box.width === 0 || box.height === 0) continue;

        const color = toRgb(style.color);
        if (!color) continue;

        const background = effectiveBackground(element);
        const foreground = color[3] < 1 ? composite(color, background) : color;
        const measured = ratio(foreground, background);

        const size = parseFloat(style.fontSize);
        const bold = Number(style.fontWeight) >= 700;
        const large = size >= 24 || (size >= 18.66 && bold);
        const floor = large ? 3.0 : 4.5;

        if (measured < floor) {
            findings.push({
                selector: describe(element),
                text: ownText.slice(0, 40),
                color: style.color,
                background: `rgb(${background.slice(0, 3).map(Math.round).join(', ')})`,
                ratio: Number(measured.toFixed(2)),
                floor,
            });
        }
    }

    return findings;
};

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

await login(page);

const painted = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim(),
);
console.log(`--color-primary as painted: ${painted}\n`);

const paths = await crawl(page);
console.log(`crawled ${paths.length} pages\n`);

const all = [];

for (const target of paths) {
    try {
        await page.goto(ORIGIN + target, { waitUntil: 'domcontentloaded', timeout: 30000 });
    } catch {
        continue;
    }
    await page.waitForTimeout(120);

    const findings = await page.evaluate(auditInPage);
    for (const finding of findings) all.push({ ...finding, page: target });
}

await browser.close();

if (all.length === 0) {
    console.log('No text below its WCAG floor on any crawled page.');
} else {
    // Grouped by the colour pair, since one unthemed rule shows up on many pages.
    const groups = new Map();
    for (const finding of all) {
        const key = `${finding.color} on ${finding.background}`;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(finding);
    }

    console.log(`${all.length} findings in ${groups.size} distinct colour pairs:\n`);

    for (const [pair, items] of [...groups.entries()].sort((a, b) => b[1].length - a[1].length)) {
        const worst = Math.min(...items.map((item) => item.ratio));
        const pages = [...new Set(items.map((item) => item.page))];
        console.log(`${pair}`);
        console.log(`  ratio ${worst} (floor ${items[0].floor}) · ${items.length} elements · ${pages.length} pages`);
        console.log(`  e.g. ${items[0].selector}  "${items[0].text}"`);
        console.log(`  pages: ${pages.slice(0, 4).join(', ')}${pages.length > 4 ? ' …' : ''}\n`);
    }
}
