export type GrowthLevel = 'high' | 'medium' | 'low' | 'none';
export type ObservationType = 'level' | 'count';

export type LabSummary = {
    id: number;
    name: string;
    code: string | null;
    row_count: number;
    shelves_per_row: number;
    levels_per_shelf: number;
    levels_count: number;
    samples_count: number;
    created_at: string | null;
};

export type LabDetail = LabSummary & {
    notes: string | null;
};

export type LabForm = {
    id: number;
    name: string;
    code: string | null;
    notes: string | null;
    row_count: number;
    shelves_per_row: number;
    levels_per_shelf: number;
};

export type BacteriaObservation = {
    id: number;
    name: string;
    short_name: string;
    color: string;
    icon: string;
    observation_type: ObservationType;
    growth_level: GrowthLevel | null;
    count: number | null;
};

export type SampleSummary = {
    present_count: number;
    level_count: number;
    count_count: number;
    highest_level: GrowthLevel | null;
    max_count: number | null;
    single: {
        id: number;
        observation_type: ObservationType;
        growth_level: GrowthLevel | null;
        count: number | null;
    } | null;
    filter?: SampleFilter;
};

export type SampleFilter = {
    value: number | null;
};

export type SampleFilterDetails = SampleFilter & {
    placed_week: string;
    placed_at: string;
};

export type LabMapSample = {
    id: number;
    code: string;
    summary: SampleSummary;
};

export type LabMapLevel = {
    id: number;
    number: number;
    samples: LabMapSample[];
};

export type LabMapShelf = {
    id: number;
    number: number;
    levels: LabMapLevel[];
};

export type LabMapRow = {
    id: number;
    number: number;
    shelves: LabMapShelf[];
};

export type LabMapEngine = 'pgsql' | 'clickhouse' | 'clickhouse_snapshot';

export type LabMapTiming = {
    lab_id: number;
    week: string;
    ms: number;
};

export type WeekOption = {
    value: string;
    label: string;
    is_current: boolean;
};
