import type { GrowthLevel, LabMapSample, SampleSummary } from '@/types/lab';

export type LevelState = 'high' | 'medium' | 'low' | 'count' | 'empty';

const rank: Record<'high' | 'medium' | 'low', number> = {
    high: 3,
    medium: 2,
    low: 1,
};

export const stateLabels: Record<LevelState, string> = {
    high: 'High',
    medium: 'Medium',
    low: 'Low',
    count: 'Count',
    empty: 'Empty',
};

const growthState = (level: GrowthLevel | null): LevelState | null =>
    level === 'high' || level === 'medium' || level === 'low' ? level : null;

export function sampleState(summary: SampleSummary): LevelState {
    return (
        growthState(summary.highest_level) ??
        (summary.count_count > 0 ? 'count' : 'empty')
    );
}

export function levelState(samples: LabMapSample[]): LevelState {
    let best: LevelState = 'empty';

    for (const sample of samples) {
        const state = sampleState(sample.summary);

        if (state === 'empty') {
            continue;
        }

        if (state === 'count') {
            if (best === 'empty') {
                best = 'count';
            }

            continue;
        }

        if (best === 'high' || best === 'medium' || best === 'low') {
            if (rank[state] > rank[best]) {
                best = state;
            }
        } else {
            best = state;
        }
    }

    return best;
}
