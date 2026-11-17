<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Trash2,
} from '@lucide/vue';
import LabEngineSwitch from '@/components/labs/LabEngineSwitch.vue';
import LabMapRow from '@/components/labs/LabMapRow.vue';
import LevelDetailsModal from '@/components/labs/LevelDetailsModal.vue';
import MapLegend from '@/components/labs/MapLegend.vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, edit, index as labsIndex, show } from '@/routes/labs';
import type {
    LabDetail,
    LabMapEngine,
    LabMapLevel,
    LabMapRow as LabMapRowData,
    LabMapEngineOption,
    LabMapSample,
    LabMapTiming,
    SampleDetails,
    WeekOption,
} from '@/types/lab';
import type { OrganizationOption } from '@/types/organization';

const props = defineProps<{
    organization: OrganizationOption;
    lab: LabDetail;
    weeks: WeekOption[];
    selectedWeek: WeekOption;
    rows: LabMapRowData[];
    canManage: boolean;
    engine: LabMapEngine;
    engines: LabMapEngineOption[];
    timings: Record<string, LabMapTiming>;
    engineError: string | null;
}>();

defineOptions({
    layout: (pageProps: { lab: LabDetail }) => ({
        breadcrumbs: [
            {
                title: 'Labs',
                href: labsIndex(),
            },
            {
                title: pageProps.lab.name,
                href: '#',
            },
        ],
    }),
});

const selected = ref(props.selectedWeek.value);

watch(
    () => props.selectedWeek.value,
    (value) => {
        selected.value = value;
    },
);

const selectedIndex = computed(() =>
    props.weeks.findIndex((week) => week.value === selected.value),
);

const previousWeek = computed(
    () => props.weeks[selectedIndex.value + 1] ?? null,
);

const nextWeek = computed(() => props.weeks[selectedIndex.value - 1] ?? null);

const loadWeek = (week: string) => {
    selected.value = week;

    router.get(
        show.url(props.lab.id),
        { week },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedWeek', 'rows', 'timings', 'engineError'],
        },
    );
};

const onWeekChange = (value: unknown) => {
    if (typeof value !== 'string') {
        return;
    }

    loadWeek(value);
};

const goToWeek = (week: WeekOption | null) => {
    if (week === null) {
        return;
    }

    loadWeek(week.value);
};

const selectedLevel = ref<LabMapLevel | null>(null);
const modalOpen = ref(false);
const sampleDetails = ref<SampleDetails | null>(null);
const sampleDetailsLoading = ref(false);
const sampleDetailsError = ref<string | null>(null);

const openLevel = (level: LabMapLevel) => {
    selectedLevel.value = level;
    modalOpen.value = true;
};

watch(
    () => props.rows,
    (rows) => {
        if (selectedLevel.value === null) {
            return;
        }

        const id = selectedLevel.value.id;
        selectedLevel.value =
            rows
                .flatMap((row) => row.shelves)
                .flatMap((shelf) => shelf.levels)
                .find((level) => level.id === id) ?? null;

        if (selectedLevel.value === null) {
            modalOpen.value = false;
        }
    },
);

const requestSampleDetails = (sample: LabMapSample) => {
    if (sampleDetailsLoading.value) {
        return;
    }

    if (
        sampleDetails.value?.sample.id === sample.id &&
        sampleDetailsError.value === null
    ) {
        return;
    }

    sampleDetailsError.value = null;
    sampleDetailsLoading.value = true;

    router.post(
        `${show.url(props.lab.id)}?week=${encodeURIComponent(props.selectedWeek.value)}`,
        { sample: sample.id },
        {
            only: ['sampleDetails'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                sampleDetails.value =
                    (page.props.sampleDetails as SampleDetails | undefined) ??
                    null;
            },
            onError: () => {
                sampleDetailsError.value = 'Unable to load sample details.';
            },
            onFinish: () => {
                sampleDetailsLoading.value = false;
            },
        },
    );
};

const shelfCount = computed(() =>
    props.rows.reduce((count, row) => Math.max(count, row.shelves.length), 0),
);

const mapMinWidth = computed(() => `${2.5 + shelfCount.value * 1.5}rem`);

const hasObservations = computed(() =>
    props.rows.some((row) =>
        row.shelves.some((shelf) =>
            shelf.levels.some((level) =>
                level.samples.some(
                    (sample) => sample.summary.present_count > 0,
                ),
            ),
        ),
    ),
);

const deleteForm = useForm({});

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : null;
</script>

<template>
    <Head :title="lab.name" />

    <div
        class="mx-auto flex h-[calc(100dvh-4rem)] w-full max-w-[1280px] flex-col gap-3 overflow-hidden p-4 group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(100dvh-3rem)] md:h-[calc(100dvh-5rem)] md:p-6 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(100dvh-4rem)]"
    >
        <header
            class="flex shrink-0 flex-col gap-3 border-b border-border pb-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="labsIndex()"
                        class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft class="size-3.5" />
                        {{ organization.name }}
                    </Link>
                    <span class="eyebrow">
                        Lab<span v-if="lab.code"> / {{ lab.code }}</span>
                    </span>
                    <Badge variant="outline" class="text-[0.65rem]">
                        {{ lab.levels_count }} levels
                    </Badge>
                </div>

                <h1
                    class="text-xl leading-tight font-semibold tracking-tight md:text-2xl"
                >
                    {{ lab.name }}
                </h1>

                <p class="tech flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>
                        {{ lab.row_count }} rows ×
                        {{ lab.shelves_per_row }} shelves ×
                        {{ lab.levels_per_shelf }} levels ·
                        {{ lab.samples_count }} samples
                    </span>
                    <span v-if="lab.created_at" class="flex items-center gap-1">
                        <CalendarDays class="size-3" />
                        Created {{ formatDate(lab.created_at) }}
                    </span>
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                <LabEngineSwitch
                    :engine="engine"
                    :lab-id="lab.id"
                    :week="selectedWeek.value"
                    :timings="timings"
                    :options="engines"
                />

                <template v-if="canManage">
                    <Link
                        :href="edit(lab.id)"
                        :class="
                            buttonVariants({ variant: 'outline', size: 'sm' })
                        "
                    >
                        <Pencil class="size-3.5" />
                        Edit
                    </Link>

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive" size="sm">
                                <Trash2 class="size-3.5" />
                                Delete
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Delete {{ lab.name }}?
                                </DialogTitle>
                                <DialogDescription>
                                    This permanently deletes the lab, its
                                    {{ lab.levels_count }} levels,
                                    {{ lab.samples_count }} samples, and all
                                    observations inside them. This cannot be
                                    undone.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    :disabled="deleteForm.processing"
                                    @click="
                                        deleteForm.delete(destroy.url(lab.id))
                                    "
                                >
                                    Delete lab
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </template>
            </div>
        </header>

        <section
            class="surface-card flex shrink-0 flex-wrap items-center gap-3 p-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <label for="week" class="eyebrow mr-1">Week</label>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Previous week"
                    :disabled="previousWeek === null"
                    @click="goToWeek(previousWeek)"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Select v-model="selected" @update:model-value="onWeekChange">
                    <SelectTrigger id="week" class="w-[240px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="max-h-72">
                        <SelectItem
                            v-for="week in weeks"
                            :key="week.value"
                            :value="week.value"
                        >
                            {{ week.label }}
                            <span v-if="week.isCurrent"> · current</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Next week"
                    :disabled="nextWeek === null"
                    @click="goToWeek(nextWeek)"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3">
                <p class="tech uppercase">
                    End of week · {{ selectedWeek.value }}
                    <span v-if="selectedWeek.isCurrent">(in progress)</span>
                </p>
                <MapLegend />
                <p class="hidden text-xs text-muted-foreground lg:block">
                    Click a level for its samples
                </p>
            </div>
        </section>

        <div class="min-h-0 flex-1 overflow-auto overscroll-contain pr-1">
            <div
                v-if="engineError"
                class="mb-3 rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive"
            >
                {{ engineError }}
            </div>

            <div
                v-if="!hasObservations"
                class="mb-3 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
            >
                <span class="font-medium text-foreground">
                    No present bacteria.
                </span>
                This week has no positive bacteria results for any sample.
            </div>

            <div
                class="flex flex-col gap-3 pb-2"
                :style="{ minWidth: mapMinWidth }"
            >
                <LabMapRow
                    v-for="row in rows"
                    :key="row.id"
                    :row="row"
                    :selected-level-id="
                        modalOpen ? (selectedLevel?.id ?? null) : null
                    "
                    @select="openLevel"
                />
            </div>
        </div>

        <LevelDetailsModal
            :open="modalOpen"
            :level="selectedLevel"
            :week-label="selectedWeek.label"
            :sample-details="sampleDetails"
            :sample-details-loading="sampleDetailsLoading"
            :sample-details-error="sampleDetailsError"
            @update:open="modalOpen = $event"
            @request-sample-details="requestSampleDetails"
        />
    </div>
</template>
