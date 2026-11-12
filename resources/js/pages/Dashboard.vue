<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

watch(
    [selectedYear, selectedWeek],
    () => {
        const url = new URL(window.location.href);
        url.searchParams.set('yearweek', isoWeekLabel.value);
        window.history.replaceState(window.history.state, '', url);
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <Label for="week" class="text-xs text-muted-foreground">
                    Week
                </Label>
                <Select v-model="selectedWeek">
                    <SelectTrigger id="week" size="sm" class="w-20">
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

            <div class="flex items-center gap-2">
                <Label for="year" class="text-xs text-muted-foreground">
                    Year
                </Label>
                <Select v-model="selectedYear">
                    <SelectTrigger id="year" size="sm" class="w-24">
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
        </div>

        <p class="text-lg font-medium">
            Selected week:
            <span class="font-mono">{{ isoWeekLabel }}</span>
        </p>
    </div>
</template>
