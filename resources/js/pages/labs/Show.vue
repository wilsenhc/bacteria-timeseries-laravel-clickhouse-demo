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
import LabMapShelf from '@/components/labs/LabMapShelf.vue';
import MapLegend from '@/components/labs/MapLegend.vue';
import SampleDetailsSheet from '@/components/labs/SampleDetailsSheet.vue';
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
    LabMapRow,
    LabMapSample,
    WeekOption,
} from '@/types/lab';
import type { OrganizationOption } from '@/types/organization';

const props = defineProps<{
    organization: OrganizationOption;
    lab: LabDetail;
    weeks: WeekOption[];
    selectedWeek: WeekOption;
    rows: LabMapRow[];
    canManage: boolean;
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
            only: ['selectedWeek', 'rows'],
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

const selectedSample = ref<LabMapSample | null>(null);
const sheetOpen = ref(false);

const openSample = (sample: LabMapSample) => {
    selectedSample.value = sample;
    sheetOpen.value = true;
};

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

            <div v-if="canManage" class="flex shrink-0 items-center gap-2">
                <Link
                    :href="edit(lab.id)"
                    :class="buttonVariants({ variant: 'outline', size: 'sm' })"
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
                            <DialogTitle>Delete {{ lab.name }}?</DialogTitle>
                            <DialogDescription>
                                This permanently deletes the lab, its
                                {{ lab.levels_count }} levels,
                                {{ lab.samples_count }} samples, and all
                                observations inside them. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="deleteForm.processing"
                                @click="deleteForm.delete(destroy.url(lab.id))"
                            >
                                Delete lab
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
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
                            <span v-if="week.is_current"> · current</span>
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
                    <span v-if="selectedWeek.is_current">(in progress)</span>
                </p>
                <MapLegend />
            </div>
        </section>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-1">
            <div
                v-if="!hasObservations"
                class="mb-3 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
            >
                <span class="font-medium text-foreground">
                    No present bacteria.
                </span>
                This week has no positive results for any sample.
            </div>

            <div class="flex flex-col gap-4 pb-2">
                <section
                    v-for="row in rows"
                    :key="row.id"
                    class="flex flex-col gap-2"
                >
                    <div class="flex items-center gap-3">
                        <p class="tech uppercase">Row {{ row.number }}</p>
                        <span class="h-px flex-1 bg-border" />
                    </div>

                    <div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                        <LabMapShelf
                            v-for="shelf in row.shelves"
                            :key="shelf.id"
                            :shelf="shelf"
                            @select="openSample"
                        />
                    </div>
                </section>
            </div>
        </div>

        <SampleDetailsSheet
            :open="sheetOpen"
            :sample="selectedSample"
            :lab-id="lab.id"
            :week="selectedWeek.value"
            :week-label="selectedWeek.label"
            @update:open="sheetOpen = $event"
        />
    </div>
</template>
