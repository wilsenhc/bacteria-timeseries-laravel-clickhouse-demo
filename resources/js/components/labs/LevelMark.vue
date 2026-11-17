<script setup lang="ts">
import type { LevelState } from '@/lib/lab-map-state';

defineProps<{
    state: LevelState;
}>();
</script>

<template>
    <span
        aria-hidden="true"
        :class="[
            'inline-block size-2.5 shrink-0',
            state === 'high' && 'rounded-[2px] bg-red-500',
            state === 'medium' &&
                'bg-amber-500 [clip-path:polygon(50%_0,100%_100%,0_100%)]',
            state === 'low' && 'rounded-full bg-emerald-500',
            state === 'count' && 'rotate-45 rounded-[2px] bg-sky-500',
            state === 'empty' &&
                'rounded-full border border-muted-foreground/50',
        ]"
    />
</template>
