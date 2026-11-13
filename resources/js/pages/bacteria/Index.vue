<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Microscope } from '@lucide/vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import { index, show } from '@/routes/bacteria';
import type { Bacteria } from '@/types/bacteria';

defineProps<{
    bacteria: Bacteria[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Bacteria',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Bacteria" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 p-4 md:p-6">
        <header
            class="animate-rise flex flex-col gap-4 border-b border-border pb-6 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="eyebrow">Pathogen atlas</p>
                <h1 class="mt-3 text-[2.25rem] leading-none font-bold">
                    Bacteria
                </h1>
                <p
                    class="mt-3 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                >
                    The foodborne organisms tracked in this project, each with a
                    fixed color and a general morphology icon.
                </p>
            </div>
            <p class="tech">{{ bacteria.length }} catalogued</p>
        </header>

        <div
            v-if="bacteria.length > 0"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <Link
                v-for="(bacterium, index) in bacteria"
                :key="bacterium.id"
                :href="show(bacterium.id)"
                class="surface-card hover-lift animate-rise relative overflow-hidden p-5"
                :style="{
                    animationDelay: `${index * 120}ms`,
                    '--bacteria-color': bacterium.color,
                }"
            >
                <span class="bacteria-bar absolute inset-x-0 top-0 h-0.5" />

                <div class="flex items-start gap-4">
                    <span
                        class="bacteria-surface flex size-12 shrink-0 items-center justify-center rounded-lg"
                    >
                        <BacteriaIcon
                            :icon="bacterium.icon"
                            class="bacteria-color size-6"
                        />
                    </span>

                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold">
                            {{ bacterium.name }}
                        </h2>
                        <p class="tech mt-0.5">
                            {{ bacterium.short_name }}
                        </p>
                        <p
                            class="mt-3 line-clamp-3 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ bacterium.description }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-5 flex items-center justify-between gap-4 border-t border-border pt-3"
                >
                    <span class="tech">
                        {{ bacterium.icon }} / {{ bacterium.color }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 text-xs font-medium text-foreground"
                    >
                        View
                        <ArrowRight class="size-3.5 text-primary" />
                    </span>
                </div>
            </Link>
        </div>

        <div
            v-else
            class="surface-card flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground"
            >
                <Microscope class="size-6" />
            </span>
            <h2 class="text-xl font-semibold tracking-tight">
                No bacteria catalogued
            </h2>
            <p
                class="max-w-[42ch] text-sm leading-relaxed text-muted-foreground"
            >
                The atlas is empty for now. Once bacteria are seeded they will
                appear here with their morphology and color.
            </p>
        </div>
    </div>
</template>
