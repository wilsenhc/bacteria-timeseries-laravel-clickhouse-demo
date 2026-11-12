<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import Heading from '@/components/Heading.vue';
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

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Bacteria"
            description="Foodborne bacteria tracked in this project."
        />

        <p v-if="bacteria.length === 0" class="text-sm text-muted-foreground">
            No bacteria yet.
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="bacterium in bacteria"
                :key="bacterium.id"
                :href="show(bacterium.id)"
                class="group flex gap-4 rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:bg-muted/50 dark:border-sidebar-border"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg"
                    :style="{
                        color: bacterium.color,
                        backgroundColor: `${bacterium.color}1a`,
                    }"
                >
                    <BacteriaIcon :icon="bacterium.icon" class="size-6" />
                </span>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <h2 class="font-medium group-hover:underline">
                            {{ bacterium.name }}
                        </h2>
                        <span class="text-xs text-muted-foreground">
                            {{ bacterium.short_name }}
                        </span>
                    </div>
                    <p class="line-clamp-2 text-sm text-muted-foreground">
                        {{ bacterium.description }}
                    </p>
                </div>
            </Link>
        </div>
    </div>
</template>
