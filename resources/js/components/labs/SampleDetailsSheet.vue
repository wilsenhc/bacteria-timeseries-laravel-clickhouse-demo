<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BacteriaIcon from '@/components/BacteriaIcon.vue';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { bacteria } from '@/routes/labs/samples';
import type { BacteriaObservation, LabMapSample } from '@/types/lab';

const props = defineProps<{
    open: boolean;
    sample: LabMapSample | null;
    labId: number;
    week: string;
    weekLabel: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const open = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const loading = ref(false);
const error = ref<string | null>(null);
const items = ref<BacteriaObservation[]>([]);

const requestKey = computed(() =>
    props.open && props.sample ? `${props.sample.id}:${props.week}` : null,
);

watch(
    requestKey,
    async () => {
        const sample = props.sample;

        if (!props.open || !sample) {
            return;
        }

        loading.value = true;
        error.value = null;
        items.value = [];

        try {
            const url = bacteria.url([props.labId, sample.id], {
                query: { week: props.week },
            });
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Unable to load sample details.');
            }

            const payload = (await response.json()) as {
                bacteria: BacteriaObservation[];
            };
            items.value = payload.bacteria;
        } catch (exception) {
            error.value =
                exception instanceof Error
                    ? exception.message
                    : 'Unable to load sample details.';
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

const formatCount = (value: number | null) =>
    value === null
        ? null
        : new Intl.NumberFormat(undefined, {
              notation: 'compact',
              maximumFractionDigits: 1,
          }).format(value);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ sample?.code ?? 'Sample' }}</SheetTitle>
                <SheetDescription>
                    {{ weekLabel }} · present bacteria
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 flex flex-col gap-2 overflow-y-auto pr-1">
                <p v-if="loading" class="text-sm text-muted-foreground">
                    Loading…
                </p>
                <p v-else-if="error" class="text-sm text-destructive">
                    {{ error }}
                </p>
                <p
                    v-else-if="items.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No present bacteria in this week.
                </p>

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
                            <span class="block truncate text-sm font-medium">
                                {{ item.name }}
                            </span>
                            <span class="tech">{{ item.short_name }}</span>
                        </span>
                    </span>

                    <Badge
                        v-if="item.observation_type === 'level'"
                        variant="outline"
                        class="uppercase"
                    >
                        {{ item.growth_level }}
                    </Badge>
                    <Badge v-else variant="secondary" class="font-mono">
                        {{ formatCount(item.count) }}
                    </Badge>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
