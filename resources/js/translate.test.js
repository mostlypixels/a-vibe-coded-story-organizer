import { describe, expect, it } from 'vitest';
import { translate } from './translate.js';

describe('translate', () => {
    it('returns the translation that Laravel supplied for the key', () => {
        expect(translate({ Saved: 'Enregistré' }, 'Saved')).toBe('Enregistré');
    });

    it('falls back to the key, like __() does for a missing translation', () => {
        expect(translate({}, 'Saved')).toBe('Saved');
        expect(translate(undefined, 'Saved')).toBe('Saved');
    });
});
