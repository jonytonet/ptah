import { describe, expect, it } from 'vitest';
import { moneyDisplay, moneyFromTyping } from '../../core/crud/form';

describe('a money field where empty means "not informed" (1.59.2)', () => {
    it('shows empty instead of R$ 0,00 when the column takes null', () => {
        expect(moneyDisplay(null, true)).toBe('');
        expect(moneyDisplay('', true)).toBe('');
        expect(moneyDisplay(undefined, true)).toBe('');
        // A real zero is still shown.
        expect(moneyDisplay(0, true)).toBe('R$ 0,00');
        expect(moneyDisplay('1253.08', true)).toBe('R$ 1.253,08');
    });

    it('erasing every digit leaves the field empty; typing still formats as cents', () => {
        expect(moneyFromTyping('', true)).toBe('');
        expect(moneyFromTyping('R$ ', true)).toBe('');
        expect(moneyFromTyping('12345', true)).toBe('R$ 123,45');
    });

    it('a column that does not take null keeps the old behaviour', () => {
        expect(moneyDisplay(null)).toBe('R$ 0,00');
        expect(moneyDisplay('')).toBe('R$ 0,00');
        expect(moneyFromTyping('')).toBe('R$ 0,00');
        expect(moneyFromTyping('7')).toBe('R$ 0,07');
    });
});
