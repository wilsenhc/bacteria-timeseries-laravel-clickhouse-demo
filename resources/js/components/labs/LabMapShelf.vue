<script setup lang="ts">
import SampleCell from '@/components/labs/SampleCell.vue';
import type { LabMapSample, LabMapShelf } from '@/types/lab';

defineProps<{
    shelf: LabMapShelf;
}>();

const emit = defineEmits<{
    select: [sample: LabMapSample];
}>();
</script>

<template>
    <section class="surface-card flex flex-col gap-2 p-3">
        <header class="flex items-center justify-between gap-2">
            <p class="tech uppercase">Shelf {{ shelf.number }}</p>
            <p class="tech">{{ shelf.levels.length }} levels</p>
        </header>

        <div class="grid gap-1.5">
            <div
                v-for="level in shelf.levels"
                :key="level.id"
                class="flex flex-col gap-1"
            >
                <p class="tech text-[0.65rem] uppercase">
                    Level {{ level.number }}
                </p>
                <div class="grid grid-cols-3 gap-1">
                    <SampleCell
                        v-for="sample in level.samples"
                        :key="sample.id"
                        :sample="sample"
                        @select="emit('select', $event)"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
