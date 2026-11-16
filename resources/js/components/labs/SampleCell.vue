<script setup lang="ts">
import { computed } from 'vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import type { LabMapSample } from '@/types/lab';

const props = defineProps<{
    sample: LabMapSample;
}>();

const emit = defineEmits<{
    select: [sample: LabMapSample];
}>();

const summary = computed(() => props.sample.summary);

const levelClass = computed(() => {
    if (summary.value.highest_level === null && summary.value.count_count > 0) {
        return 'border-sky-500/40 bg-sky-500/5';
    }

    switch (summary.value.highest_level) {
        case 'high':
            return 'border-red-500/60 bg-red-500/10';
        case 'medium':
            return 'border-amber-500/60 bg-amber-500/10';
        case 'low':
            return 'border-emerald-500/60 bg-emerald-500/10';
        default:
            return 'border-border bg-muted/40';
    }
});

const levelLabel = computed(() =>
    summary.value.highest_level
        ? summary.value.highest_level.toUpperCase()
        : summary.value.count_count > 0
          ? 'COUNT'
          : '—',
);

const formatCount = (value: number | null) =>
    value === null
        ? null
        : new Intl.NumberFormat(undefined, {
              notation: 'compact',
              maximumFractionDigits: 1,
          }).format(value);

const countLabel = computed(() => formatCount(summary.value.max_count));

const ariaLabel = computed(() => {
    const parts = [props.sample.code, `${summary.value.present_count} present`];

    if (summary.value.highest_level) {
        parts.push(`highest ${summary.value.highest_level}`);
    }

    if (summary.value.max_count !== null) {
        parts.push(`max count ${summary.value.max_count}`);
    }

    return parts.join(', ');
});
</script>

<template>
    <button
        type="button"
        :aria-label="ariaLabel"
        :class="[
            'group flex min-h-14 flex-col justify-between rounded-md border p-1.5 text-left transition-colors hover:border-foreground/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            levelClass,
        ]"
        @click="emit('select', sample)"
    >
        <div class="flex items-center justify-between gap-1">
            <span
                class="font-mono text-[0.6rem] leading-none text-muted-foreground"
            >
                {{ sample.code }}
            </span>
            <span
                v-if="summary.present_count > 1"
                class="rounded-full bg-background/70 px-1.5 py-0.5 font-mono text-[0.6rem] font-medium"
            >
                {{ summary.present_count }}
            </span>
        </div>

        <div class="mt-2 flex items-end justify-between gap-1">
            <template v-if="summary.single_bacterium">
                <span class="flex min-w-0 items-center gap-1.5">
                    <BacteriaIcon
                        :icon="summary.single_bacterium.icon"
                        class="size-4 shrink-0"
                        :style="{ color: summary.single_bacterium.color }"
                    />
                    <span class="truncate text-[0.65rem] font-medium">
                        {{ summary.single_bacterium.short_name }}
                    </span>
                </span>
                <span
                    v-if="summary.single_bacterium.growth_level"
                    class="font-mono text-[0.6rem] font-semibold uppercase"
                >
                    {{ summary.single_bacterium.growth_level }}
                </span>
                <span v-else class="font-mono text-[0.6rem] font-semibold">
                    {{ formatCount(summary.single_bacterium.count) }}
                </span>
            </template>

            <template v-else-if="summary.present_count > 1">
                <span class="text-[0.65rem] font-semibold uppercase">
                    {{ levelLabel }}
                </span>
                <span
                    v-if="countLabel"
                    class="rounded-full bg-sky-500/10 px-1.5 py-0.5 font-mono text-[0.6rem] font-medium text-sky-700 dark:text-sky-300"
                >
                    {{ countLabel }}
                </span>
            </template>

            <template v-else>
                <span class="text-[0.65rem] text-muted-foreground">Empty</span>
            </template>
        </div>
    </button>
</template>
