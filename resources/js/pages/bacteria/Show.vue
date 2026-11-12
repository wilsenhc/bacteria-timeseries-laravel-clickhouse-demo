<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import { index } from '@/routes/bacteria';
import type { Bacteria } from '@/types/bacteria';

defineProps<{
    bacteria: Bacteria;
}>();

defineOptions({
    layout: (pageProps: { bacteria: Bacteria }) => ({
        breadcrumbs: [
            {
                title: 'Bacteria',
                href: index(),
            },
            {
                title: pageProps.bacteria.short_name,
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head :title="bacteria.name" />

    <div class="flex flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            All bacteria
        </Link>

        <div
            class="flex flex-col gap-6 rounded-xl border border-sidebar-border/70 p-6 sm:flex-row dark:border-sidebar-border"
        >
            <span
                class="flex size-20 shrink-0 items-center justify-center rounded-2xl"
                :style="{
                    color: bacteria.color,
                    backgroundColor: `${bacteria.color}1a`,
                }"
            >
                <BacteriaIcon :icon="bacteria.icon" class="size-10" />
            </span>

            <div class="space-y-3">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ bacteria.name }}
                    </h1>
                    <span class="text-sm text-muted-foreground">
                        {{ bacteria.short_name }}
                    </span>
                </div>

                <p class="max-w-prose text-muted-foreground">
                    {{ bacteria.description }}
                </p>

                <p
                    class="flex items-center gap-2 font-mono text-xs text-muted-foreground"
                >
                    <span
                        class="size-3 rounded-full"
                        :style="{ backgroundColor: bacteria.color }"
                    />
                    {{ bacteria.color }}
                </p>
            </div>
        </div>
    </div>
</template>
