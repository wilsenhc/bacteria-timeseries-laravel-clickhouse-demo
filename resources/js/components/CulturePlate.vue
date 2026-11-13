<script setup lang="ts">
import { computed } from 'vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';

type Culture = {
    icon: string;
    color: string;
};

const props = withDefaults(
    defineProps<{
        items: Culture[];
        label?: string;
        code?: string;
        temperature?: string;
    }>(),
    {
        label: 'Culture plate',
        code: 'PLATE 01',
        temperature: '37 °C',
    },
);

const slots = [
    { top: '17%', left: '28%', rotate: '-14deg', size: 'size-10', delay: 0 },
    { top: '24%', left: '63%', rotate: '18deg', size: 'size-7', delay: 120 },
    { top: '42%', left: '15%', rotate: '6deg', size: 'size-6', delay: 240 },
    { top: '52%', left: '71%', rotate: '-24deg', size: 'size-9', delay: 360 },
    { top: '66%', left: '35%', rotate: '10deg', size: 'size-8', delay: 480 },
    { top: '74%', left: '61%', rotate: '-6deg', size: 'size-6', delay: 600 },
    { top: '37%', left: '46%', rotate: '28deg', size: 'size-5', delay: 720 },
];

const cultures = computed(() =>
    props.items.length === 0
        ? []
        : slots.map((slot, index) => ({
              ...slot,
              ...props.items[index % props.items.length],
          })),
);
</script>

<template>
    <div class="relative aspect-square w-full">
        <div
            class="absolute inset-0 rounded-full border border-border bg-card shadow-card"
        />
        <div class="absolute inset-[6%] rounded-full border border-border/70" />
        <div
            class="absolute inset-[14%] rounded-full border border-dashed border-border/60"
        />

        <div
            class="absolute top-1/2 left-1/2 h-[90%] w-px -translate-x-1/2 -translate-y-1/2 bg-border/50"
        />
        <div
            class="absolute top-1/2 left-1/2 h-px w-[90%] -translate-x-1/2 -translate-y-1/2 bg-border/50"
        />

        <span
            v-for="(culture, index) in cultures"
            :key="index"
            class="animate-rise absolute"
            :style="{
                top: culture.top,
                left: culture.left,
                animationDelay: `${culture.delay}ms`,
            }"
        >
            <BacteriaIcon
                :icon="culture.icon"
                :class="`${culture.size} bacteria-color`"
                :style="{
                    '--bacteria-color': culture.color,
                    transform: `rotate(${culture.rotate})`,
                }"
            />
        </span>

        <div
            class="absolute top-1/2 left-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background/90 font-mono text-[10px] tracking-[0.14em] text-muted-foreground"
        >
            {{ temperature }}
        </div>

        <span
            class="absolute -top-3 left-6 border border-border bg-background px-2 py-1 font-mono text-[10px] tracking-[0.18em] text-muted-foreground uppercase"
        >
            {{ label }}
        </span>
        <span class="tech absolute right-6 -bottom-3 bg-background px-2">
            {{ code }}
        </span>
    </div>
</template>
