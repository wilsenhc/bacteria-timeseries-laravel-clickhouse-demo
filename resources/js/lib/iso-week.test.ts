import { describe, expect, it } from 'vitest';
import { isoWeek, isoWeeksInYear, parseIsoWeek } from './iso-week';

describe('isoWeek', () => {
    it('returns the ISO week and year for a date', () => {
        expect(isoWeek(new Date(2026, 10, 12))).toEqual({
            year: 2026,
            week: 46,
        });
    });

    it('uses the ISO year at the turn of the year', () => {
        expect(isoWeek(new Date(2021, 0, 1))).toEqual({
            year: 2020,
            week: 53,
        });
        expect(isoWeek(new Date(2022, 0, 1))).toEqual({
            year: 2021,
            week: 52,
        });
    });
});

describe('isoWeeksInYear', () => {
    it('handles 52 and 53 week years', () => {
        expect(isoWeeksInYear(2020)).toBe(53);
        expect(isoWeeksInYear(2021)).toBe(52);
        expect(isoWeeksInYear(2026)).toBe(53);
        expect(isoWeeksInYear(2027)).toBe(52);
    });
});

describe('parseIsoWeek', () => {
    it('parses a valid ISO yearweek', () => {
        expect(parseIsoWeek('2026-W46')).toEqual({ year: 2026, week: 46 });
    });

    it('rejects malformed or non-existent weeks', () => {
        expect(parseIsoWeek('2026-W54')).toBeNull();
        expect(parseIsoWeek('2021-W53')).toBeNull();
        expect(parseIsoWeek('2026-46')).toBeNull();
        expect(parseIsoWeek(null)).toBeNull();
    });
});
