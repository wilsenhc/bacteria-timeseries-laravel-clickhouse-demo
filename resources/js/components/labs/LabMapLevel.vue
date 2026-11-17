<script setup lang="ts">
import { computed } from 'vue';
import FilterBadge from '@/components/labs/FilterBadge.vue';
import LevelMark from '@/components/labs/LevelMark.vue';
import { levelState, stateLabels, type LevelState } from '@/lib/lab-map-state';
import type { LabMapLevel, SampleFilter } from '@/types/lab';

const props = defineProps<{
    level: LabMapLevel | null;
    context: string;
    selected?: boolean;
}>();

const emit = defineEmits<{
    select: [level: LabMapLevel];
}>();

const state = computed<LevelState>(() =>
    props.level ? levelState(props.level.samples) : 'empty',
);

const levelFilter = computed<SampleFilter | null>(() => {
    for (const sample of props.level?.samples ?? []) {
        if (sample.summary.filter !== undefined) {
            return sample.summary.filter;
        }
    }

    return null;
});

const ariaLabel = computed(() => {
    if (!props.level) {
        return '';
    }

    const samples = props.level.samples;
    const present = samples.filter(
        (sample) => sample.summary.present_count > 0,
    ).length;

    return [
        props.context,
        `level ${props.level.number}`,
        `${samples.length} samples`,
        `${present} present`,
        stateLabels[state.value],
        levelFilter.value === null
            ? null
            : levelFilter.value.value === null
              ? 'filter placed, no reading'
              : `filter reading ${levelFilter.value.value}`,
        'Open sample details',
    ]
        .filter((part): part is string => part !== null)
        .join(', ');
});
</script>

<template>
    <button
        v-if="level"
        type="button"
        :aria-label="ariaLabel"
        :class="[
            'relative flex h-6 w-full items-center justify-center border-b border-border/40 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset [@media(pointer:coarse)]:h-8',
            selected && 'bg-muted',
        ]"
        @click="emit('select', level)"
    >
        <LevelMark :state="state" :class="selected ? 'scale-125' : undefined" />
        <FilterBadge
            v-if="levelFilter"
            :filter="levelFilter"
            class="absolute top-0.5 right-0.5"
        />
    </button>

    <span
        v-else
        aria-hidden="true"
        class="h-6 border-b border-border/40 [@media(pointer:coarse)]:h-8"
    />
</template>
