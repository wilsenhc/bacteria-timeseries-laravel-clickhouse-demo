<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 42"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="20"
            cy="21"
            r="15.5"
            stroke="currentColor"
            stroke-width="2.5"
        />
        <circle
            cx="20"
            cy="21"
            r="11"
            stroke="currentColor"
            stroke-width="1"
            opacity="0.35"
        />
        <circle cx="14.5" cy="16.5" r="3" fill="currentColor" />
        <circle cx="25" cy="15" r="2" fill="currentColor" />
        <circle cx="23" cy="26" r="3.5" fill="currentColor" />
        <circle cx="14" cy="26.5" r="1.75" fill="currentColor" />
    </svg>
</template>
