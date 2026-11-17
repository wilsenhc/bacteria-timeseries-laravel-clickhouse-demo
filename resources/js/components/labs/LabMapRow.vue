<script setup lang="ts">
import { computed } from 'vue';
import LabMapLevel from '@/components/labs/LabMapLevel.vue';
import type { LabMapLevel as LabMapLevelData, LabMapRow } from '@/types/lab';

const props = defineProps<{
    row: LabMapRow;
    selectedLevelId: number | null;
}>();

const emit = defineEmits<{
    select: [level: LabMapLevelData];
}>();

const levelCount = computed(() =>
    props.row.shelves.reduce(
        (count, shelf) => Math.max(count, shelf.levels.length),
        0,
    ),
);

const gridStyle = computed(() => ({
    gridTemplateColumns: `2.5rem repeat(${props.row.shelves.length}, minmax(1.5rem, 1fr))`,
}));

const intrinsicSize = computed(() => ({
    containIntrinsicSize: `auto ${32 + levelCount.value * 24}px`,
}));

const levelNumber = (index: number): number =>
    props.row.shelves[0]?.levels[index]?.number ?? index + 1;
</script>

<template>
    <section
        class="surface-card [content-visibility:auto]"
        :style="intrinsicSize"
        :aria-label="`Row ${row.number}`"
    >
        <div class="grid min-w-0" :style="gridStyle">
            <div
                class="sticky left-0 z-20 flex h-8 items-center rounded-tl-[7px] border-r border-b border-border/60 bg-muted px-2 [@media(pointer:coarse)]:h-9"
            >
                <span
                    class="font-mono text-[0.65rem] font-semibold tracking-[0.12em] text-foreground uppercase"
                >
                    R{{ row.number }}
                </span>
            </div>

            <div
                v-for="(shelf, index) in row.shelves"
                :key="shelf.id"
                :class="[
                    'flex h-8 items-center justify-center border-b border-l border-border/40 bg-muted font-mono text-[0.65rem] text-muted-foreground [@media(pointer:coarse)]:h-9',
                    index === row.shelves.length - 1 && 'rounded-tr-[7px]',
                ]"
            >
                S{{ shelf.number }}
            </div>

            <template v-for="(_, index) in levelCount" :key="`level-${index}`">
                <div
                    class="sticky left-0 z-10 flex h-6 items-center justify-end border-r border-b border-border/40 bg-card pr-2 font-mono text-[0.65rem] text-muted-foreground [@media(pointer:coarse)]:h-8"
                >
                    L{{ levelNumber(index) }}
                </div>

                <LabMapLevel
                    v-for="shelf in row.shelves"
                    :key="`${shelf.id}-${index}`"
                    :level="shelf.levels[index] ?? null"
                    :context="`Row ${row.number}, shelf ${shelf.number}`"
                    :selected="
                        selectedLevelId !== null &&
                        shelf.levels[index]?.id === selectedLevelId
                    "
                    @select="emit('select', $event)"
                />
            </template>
        </div>
    </section>
</template>
