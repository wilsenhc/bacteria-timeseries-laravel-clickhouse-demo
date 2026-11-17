<script setup lang="ts">
import type { SampleFilter } from '@/types/lab';

defineProps<{
    filter: SampleFilter;
}>();

const label = (value: number | null): string =>
    value === null
        ? 'Filter placed, no reading this week'
        : `Filter reading ${value}`;
</script>

<template>
    <span
        class="inline-flex items-center gap-0.5 rounded-full border border-amber-500/60 bg-amber-400/15 px-1 py-0.5 font-mono text-[0.55rem] leading-none font-semibold text-amber-700 dark:text-amber-300"
        :aria-label="label(filter.value)"
    >
        <svg
            viewBox="0 0 12 12"
            fill="none"
            stroke="currentColor"
            stroke-width="1"
            stroke-linecap="round"
            class="size-2.5 shrink-0"
            aria-hidden="true"
        >
            <rect x="1.5" y="2" width="9" height="8" rx="1" />
            <path d="M3.5 4.5h5M3.5 6h5M3.5 7.5h5" />
        </svg>
        <span v-if="filter.value !== null">{{ filter.value }}</span>
    </span>
</template>
