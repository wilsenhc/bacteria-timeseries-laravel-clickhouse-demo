<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        icon: string;
        class?: string;
    }>(),
    {
        class: 'size-6',
    },
);

const icons = import.meta.glob<string>('../../assets/icons/*.svg', {
    eager: true,
    query: '?raw',
    import: 'default',
});

const svg = computed(() => icons[`../../assets/icons/${props.icon}.svg`]);
</script>

<template>
    <span
        v-if="svg"
        aria-hidden="true"
        :class="cn('inline-flex [&>svg]:size-full', props.class)"
        v-html="svg"
    />
</template>
