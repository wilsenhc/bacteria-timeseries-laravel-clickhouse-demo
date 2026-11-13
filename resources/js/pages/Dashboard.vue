<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ChartLine } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { buttonVariants } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { isoWeek, isoWeeksInYear, parseIsoWeek } from '@/lib/iso-week';
import { dashboard } from '@/routes';
import { index as bacteriaIndex } from '@/routes/bacteria';

defineProps<{
    bacteriaCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const currentWeek = isoWeek(new Date());
const requestedWeek = parseIsoWeek(
    new URLSearchParams(window.location.search).get('yearweek'),
);

const initialWeek =
    requestedWeek &&
    requestedWeek.year >= currentWeek.year - 5 &&
    requestedWeek.year <= currentWeek.year + 1
        ? requestedWeek
        : currentWeek;

const selectedYear = ref(String(initialWeek.year));
const selectedWeek = ref(String(initialWeek.week));

const years = Array.from({ length: 7 }, (_, index) =>
    String(currentWeek.year - 5 + index),
);

const weeks = computed(() =>
    Array.from(
        { length: isoWeeksInYear(Number(selectedYear.value)) },
        (_, index) => String(index + 1),
    ),
);

watch(weeks, (options) => {
    if (!options.includes(selectedWeek.value)) {
        selectedWeek.value = options[options.length - 1];
    }
});

const isoWeekLabel = computed(
    () => `${selectedYear.value}-W${selectedWeek.value.padStart(2, '0')}`,
);

const isoWeekParam = computed(
    () => `${selectedYear.value}${selectedWeek.value.padStart(2, '0')}`,
);

watch(
    [selectedYear, selectedWeek],
    () => {
        const url = new URL(window.location.href);
        url.searchParams.set('yearweek', isoWeekParam.value);
        window.history.replaceState(window.history.state, '', url);
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 p-4 md:p-6">
        <header
            class="animate-rise flex flex-col gap-6 border-b border-border pb-6 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="eyebrow">Timeseries / Weekly</p>
                <h1 class="mt-3 text-[2.25rem] leading-none font-bold">
                    Observation window
                </h1>
                <p
                    class="mt-3 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                >
                    Choose an ISO week to scope the bacteria observations
                    collected for that window.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-8">
                <div>
                    <p class="tech uppercase">Catalogued</p>
                    <p class="mt-1 font-mono text-2xl font-medium">
                        {{ bacteriaCount }}
                    </p>
                </div>
                <div>
                    <p class="tech uppercase">Window</p>
                    <p class="mt-1 font-mono text-2xl font-medium">
                        {{ isoWeekLabel }}
                    </p>
                </div>
            </div>
        </header>

        <section class="surface-card animate-rise p-5 [animation-delay:120ms]">
            <div class="flex flex-wrap items-end gap-4">
                <div class="grid gap-2">
                    <Label for="week" class="eyebrow">Week</Label>
                    <Select v-model="selectedWeek">
                        <SelectTrigger id="week" size="sm" class="w-24">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="max-h-48">
                            <SelectItem
                                v-for="week in weeks"
                                :key="week"
                                :value="week"
                            >
                                {{ week }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="year" class="eyebrow">Year</Label>
                    <Select v-model="selectedYear">
                        <SelectTrigger id="year" size="sm" class="w-28">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="max-h-48">
                            <SelectItem
                                v-for="year in years"
                                :key="year"
                                :value="year"
                            >
                                {{ year }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <p class="tech ml-auto hidden pb-2 sm:block">
                    SELECTED / {{ isoWeekLabel }}
                </p>
            </div>
        </section>

        <section
            class="surface-card animate-rise flex flex-1 flex-col items-center justify-center gap-4 px-6 py-16 text-center [animation-delay:240ms]"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground"
            >
                <ChartLine class="size-6" />
            </span>
            <h2 class="text-xl font-semibold tracking-tight">
                No observations yet
            </h2>
            <p
                class="max-w-[42ch] text-sm leading-relaxed text-muted-foreground"
            >
                This week's window is empty. Start from the catalogued bacteria
                and add observations as data arrives.
            </p>
            <Link
                :href="bacteriaIndex()"
                :class="buttonVariants({ size: 'lg' })"
            >
                Open the atlas
                <ArrowRight class="size-4" />
            </Link>
        </section>
    </div>
</template>
