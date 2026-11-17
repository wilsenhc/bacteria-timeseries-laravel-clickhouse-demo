<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { engine as engineRoute } from '@/routes/labs';
import type { LabMapEngine, LabMapTiming } from '@/types/lab';

const props = defineProps<{
    engine: LabMapEngine;
    labId: number;
    week: string;
    timings: Record<string, LabMapTiming>;
}>();

const options: { value: LabMapEngine; label: string }[] = [
    { value: 'pgsql', label: 'PostgreSQL' },
    { value: 'clickhouse', label: 'ClickHouse' },
    { value: 'clickhouse_snapshot', label: 'ClickHouse snapshot' },
];

const activeOption = computed(
    () => options.find((option) => option.value === props.engine) ?? options[0],
);

const timing = (value: LabMapEngine): string | null => {
    const entry = props.timings[value];

    if (
        entry === undefined ||
        entry.lab_id !== props.labId ||
        entry.week !== props.week
    ) {
        return null;
    }

    return entry.ms >= 1000
        ? `${(entry.ms / 1000).toFixed(2)} s`
        : `${Math.round(entry.ms)} ms`;
};

const switchTo = (value: unknown) => {
    if (typeof value !== 'string' || value === props.engine) {
        return;
    }

    router.post(engineRoute.url(), { engine: value }, { preserveScroll: true });
};
</script>

<template>
    <div class="flex items-center gap-2">
        <span class="tech shrink-0 uppercase">Engine</span>
        <Select :model-value="engine" @update:model-value="switchTo">
            <SelectTrigger
                size="sm"
                class="w-[230px]"
                aria-label="Lab map query engine"
            >
                <span class="truncate">{{ activeOption.label }}</span>
                <span
                    v-if="timing(engine)"
                    class="font-mono text-[0.65rem] text-muted-foreground"
                >
                    · {{ timing(engine) }}
                </span>
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                    <span
                        v-if="timing(option.value)"
                        class="font-mono text-[0.65rem] text-muted-foreground"
                    >
                        · {{ timing(option.value) }}
                    </span>
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
