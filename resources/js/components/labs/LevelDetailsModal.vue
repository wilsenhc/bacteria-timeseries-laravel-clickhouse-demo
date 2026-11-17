<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import FilterBadge from '@/components/labs/FilterBadge.vue';
import LevelMark from '@/components/labs/LevelMark.vue';
import { levelStateRank, sampleState, stateLabels } from '@/lib/lab-map-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LabMapLevel, LabMapSample, SampleDetails } from '@/types/lab';

const props = defineProps<{
    open: boolean;
    level: LabMapLevel | null;
    weekLabel: string;
    sampleDetails: SampleDetails | null;
    sampleDetailsLoading: boolean;
    sampleDetailsError: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'request-sample-details': [sample: LabMapSample];
}>();

const open = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const samples = computed(() => props.level?.samples ?? []);

const levelCode = computed(
    () =>
        samples.value[0]?.code.replace(/-\d{2}$/, '') ??
        (props.level ? `Level ${props.level.number}` : 'Level'),
);

const selectedSample = ref<LabMapSample | null>(null);
const title = ref<{ $el?: HTMLElement } | null>(null);

const focusTitle = (event: Event) => {
    event.preventDefault();
    title.value?.$el?.focus();
};

watch(
    () => props.level,
    (level) => {
        if (!level) {
            selectedSample.value = null;

            return;
        }

        selectedSample.value =
            [...level.samples].sort(
                (a, b) =>
                    levelStateRank[sampleState(b.summary)] -
                    levelStateRank[sampleState(a.summary)],
            )[0] ?? null;
    },
    { immediate: true },
);

watch(
    [() => props.open, selectedSample],
    ([isOpen, sample]) => {
        if (isOpen && sample) {
            emit('request-sample-details', sample);
        }
    },
    { immediate: true },
);

const details = computed(() =>
    props.sampleDetails !== null &&
    props.sampleDetails.sample.id === selectedSample.value?.id
        ? props.sampleDetails
        : null,
);

const items = computed(() => details.value?.bacteria ?? []);
const filter = computed(() => details.value?.filter ?? null);

const formatCount = (value: number | null) =>
    value === null
        ? null
        : new Intl.NumberFormat(undefined, {
              notation: 'compact',
              maximumFractionDigits: 1,
          }).format(value);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85dvh] grid-rows-[auto_minmax(0,1fr)_auto] sm:max-w-2xl"
            @open-auto-focus="focusTitle"
        >
            <DialogHeader>
                <DialogTitle
                    ref="title"
                    tabindex="-1"
                    class="focus:outline-none"
                >
                    {{ levelCode }}
                </DialogTitle>
                <DialogDescription>
                    {{ weekLabel }} · {{ samples.length }} samples · pick a
                    sample for its details
                </DialogDescription>
            </DialogHeader>

            <div class="flex min-h-0 flex-col gap-5 overflow-y-auto pr-1">
                <p
                    v-if="samples.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No samples at this level.
                </p>

                <div v-else class="grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="sample in samples"
                        :key="sample.id"
                        type="button"
                        :aria-pressed="selectedSample?.id === sample.id"
                        :class="[
                            'flex flex-col gap-1.5 rounded-md border p-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            selectedSample?.id === sample.id
                                ? 'border-foreground/25 bg-muted'
                                : 'border-border hover:bg-muted/60',
                        ]"
                        @click="selectedSample = sample"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate font-mono text-[0.7rem]">
                                {{ sample.code }}
                            </span>
                            <FilterBadge
                                v-if="sample.summary.filter"
                                :filter="sample.summary.filter"
                            />
                        </span>
                        <span
                            class="flex items-center justify-between gap-2 text-[0.7rem] text-muted-foreground"
                        >
                            <span class="flex items-center gap-1.5">
                                <LevelMark
                                    :state="sampleState(sample.summary)"
                                />
                                {{ stateLabels[sampleState(sample.summary)] }}
                            </span>
                            {{ sample.summary.present_count }} present
                        </span>
                    </button>
                </div>

                <template v-if="selectedSample">
                    <div
                        class="flex items-center justify-between gap-3 border-t border-border pt-4"
                    >
                        <p class="tech uppercase">Present bacteria</p>
                        <p class="tech">{{ selectedSample.code }}</p>
                    </div>

                    <p
                        v-if="sampleDetailsLoading && details === null"
                        class="text-sm text-muted-foreground"
                    >
                        Loading…
                    </p>
                    <p
                        v-else-if="sampleDetailsError"
                        class="text-sm text-destructive"
                    >
                        {{ sampleDetailsError }}
                    </p>
                    <template v-else>
                        <p
                            v-if="items.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No present bacteria in this week.
                        </p>

                        <div v-else class="grid gap-2 pb-1 sm:grid-cols-2">
                            <div
                                v-for="item in items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 rounded-md border border-border p-3"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <BacteriaIcon
                                        :icon="item.icon"
                                        class="size-4 shrink-0"
                                        :style="{ color: item.color }"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-sm font-medium"
                                        >
                                            {{ item.name }}
                                        </span>
                                        <span class="tech">
                                            {{ item.shortName }}
                                        </span>
                                    </span>
                                </span>

                                <Badge
                                    v-if="item.observationType === 'level'"
                                    variant="outline"
                                    class="uppercase"
                                >
                                    {{ item.growthLevel }}
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                    class="font-mono"
                                >
                                    {{ formatCount(item.count) }}
                                </Badge>
                            </div>
                        </div>

                        <div v-if="filter" class="border-t border-border pt-4">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="tech uppercase">Filter</p>
                                <FilterBadge :filter="filter" />
                            </div>
                            <dl class="mt-2 grid gap-1 text-sm">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <dt class="text-muted-foreground">
                                        Placed
                                    </dt>
                                    <dd class="font-mono">
                                        {{ filter.placedWeek }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <dt class="text-muted-foreground">
                                        This week
                                    </dt>
                                    <dd
                                        v-if="filter.value !== null"
                                        class="font-mono"
                                    >
                                        {{ filter.value }}
                                    </dd>
                                    <dd v-else class="text-muted-foreground">
                                        No reading this week.
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </template>
                </template>
            </div>

            <DialogFooter class="items-center border-t border-border pt-4">
                <DialogClose as-child>
                    <Button variant="secondary">Close</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
