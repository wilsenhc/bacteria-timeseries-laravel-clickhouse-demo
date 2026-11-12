export type IsoWeek = {
    year: number;
    week: number;
};

export function isoWeek(date: Date): IsoWeek {
    const thursday = new Date(
        Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()),
    );
    const dayOfWeek = thursday.getUTCDay() || 7;

    thursday.setUTCDate(thursday.getUTCDate() + 4 - dayOfWeek);

    const year = thursday.getUTCFullYear();
    const yearStart = new Date(Date.UTC(year, 0, 1));

    return {
        year,
        week: Math.ceil(
            ((thursday.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7,
        ),
    };
}

export function isoWeeksInYear(year: number): number {
    const januaryFirst = new Date(Date.UTC(year, 0, 1)).getUTCDay();
    const isLeapYear = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;

    return januaryFirst === 4 || (isLeapYear && januaryFirst === 3) ? 53 : 52;
}

export function parseIsoWeek(value: string | null | undefined): IsoWeek | null {
    const match = value?.match(/^(\d{4})-W(\d{2})$/);

    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const week = Number(match[2]);

    return week >= 1 && week <= isoWeeksInYear(year) ? { year, week } : null;
}
