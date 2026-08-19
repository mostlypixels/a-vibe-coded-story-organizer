import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Guards the keep-in-step rule from documentation/features/rich-text.md: every class
 * RichTextFields::decorativeClasses() can produce needs a rule in app.css, and every
 * colour rule must point at a theme token, never a literal value.
 */

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

const css = readFileSync(path.join(root, 'resources', 'css', 'app.css'), 'utf8');

const registry = readFileSync(
    path.join(root, 'app', 'Support', 'RichTextFields.php'),
    'utf8',
);

function phpStringList(source, constant) {
    const match = source.match(new RegExp(`const ${constant} = \\[([^\\]]*)\\]`));
    if (!match) {
        throw new Error(`could not find ${constant} in RichTextFields.php`);
    }

    return [...match[1].matchAll(/'([a-z]+)'/g)].map((m) => m[1]);
}

const alignments = phpStringList(registry, 'ALIGNMENTS');
const colors = phpStringList(registry, 'TEXT_COLORS');

describe('app.css styles every decorative class RichTextFields can produce', () => {
    it.each(alignments)('has a text-align rule for rt-align-%s', (name) => {
        const rule = new RegExp(`\\.rt-align-${name}\\s*\\{[^}]*text-align:\\s*${name};`);

        expect(css).toMatch(rule);
    });

    it.each(colors)('has a colour rule for rt-color-%s that reads a theme token', (name) => {
        const match = css.match(new RegExp(`\\.rt-color-${name}\\s*\\{([^}]*)\\}`));

        expect(match).not.toBeNull();
        expect(match[1]).toMatch(/color:\s*var\(--color-[\w-]+\)/);
    });

    it('has no rt-align rule for left, which is the unclassed default', () => {
        expect(css).not.toMatch(/\.rt-align-left\b/);
    });
});
