<script setup lang="ts">
import FilterBadge from '@/components/labs/FilterBadge.vue';
import LevelMark from '@/components/labs/LevelMark.vue';
import { sampleState, stateLabels, type LevelState } from '@/lib/lab-map-state';

const states: LevelState[] = ['high', 'medium', 'low', 'count', 'empty'];
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground"
    >
        <span
            v-for="state in states"
            :key="state"
            class="inline-flex items-center gap-1.5"
        >
            <LevelMark :state="state" />
            {{ stateLabels[state] }}
        </span>
        <span class="inline-flex items-center gap-1.5">
            <FilterBadge :filter="{ value: null }" />
            Filter
        </span>
    </div>
</template>
