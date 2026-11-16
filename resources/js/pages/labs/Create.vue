<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as labsIndex, store } from '@/routes/labs';
import type { OrganizationOption } from '@/types/organization';

defineProps<{
    organization: OrganizationOption;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Labs',
                href: labsIndex(),
            },
            {
                title: 'New lab',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    name: '',
    code: '',
    notes: '',
    row_count: 5 as number | string,
    shelves_per_row: 6 as number | string,
    levels_per_shelf: 8 as number | string,
});

const totalLevels = computed(
    () =>
        Number(form.row_count) *
        Number(form.shelves_per_row) *
        Number(form.levels_per_shelf),
);

const submit = () => form.post(store.url());
</script>

<template>
    <Head :title="`New lab · ${organization.name}`" />

    <div class="mx-auto flex w-full max-w-[760px] flex-col gap-6 p-4 md:p-6">
        <Link
            :href="labsIndex()"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ organization.name }}
        </Link>

        <header class="animate-rise">
            <p class="eyebrow">Organization / New lab</p>
            <h1 class="mt-3 text-[2.25rem] leading-none font-bold">New lab</h1>
            <p
                class="mt-3 max-w-[56ch] text-sm leading-relaxed text-muted-foreground"
            >
                The lab is generated as a uniform grid of rows, shelves, and
                levels. Samples are placed on levels.
            </p>
        </header>

        <form
            class="surface-card animate-rise flex flex-col gap-6 p-6 [animation-delay:120ms]"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    placeholder="Lab A"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code"
                    >Code
                    <span class="text-muted-foreground">(optional)</span></Label
                >
                <Input id="code" v-model="form.code" placeholder="LAB-A" />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="notes"
                    >Notes
                    <span class="text-muted-foreground">(optional)</span></Label
                >
                <Textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    placeholder="Storage notes, room, or access details."
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="grid gap-4 border-t border-border pt-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="row_count">Rows</Label>
                    <Input
                        id="row_count"
                        v-model="form.row_count"
                        type="number"
                        min="1"
                        max="20"
                        required
                    />
                    <InputError :message="form.errors.row_count" />
                </div>
                <div class="grid gap-2">
                    <Label for="shelves_per_row">Shelves per row</Label>
                    <Input
                        id="shelves_per_row"
                        v-model="form.shelves_per_row"
                        type="number"
                        min="1"
                        max="20"
                        required
                    />
                    <InputError :message="form.errors.shelves_per_row" />
                </div>
                <div class="grid gap-2">
                    <Label for="levels_per_shelf">Levels per shelf</Label>
                    <Input
                        id="levels_per_shelf"
                        v-model="form.levels_per_shelf"
                        type="number"
                        min="1"
                        max="20"
                        required
                    />
                    <InputError :message="form.errors.levels_per_shelf" />
                </div>
            </div>

            <div class="rounded-md border border-border bg-muted/40 p-4">
                <p class="tech uppercase">Structure preview</p>
                <p class="mt-2 font-mono text-lg font-medium">
                    {{ form.row_count }} × {{ form.shelves_per_row }} ×
                    {{ form.levels_per_shelf }} = {{ totalLevels }} levels
                </p>
                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                    The structure is fixed after creation. You can edit the
                    lab's name, code, and notes later.
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-3 border-t border-border pt-4"
            >
                <Button type="submit" :disabled="form.processing">
                    Create lab
                </Button>
                <Link
                    :href="labsIndex()"
                    :class="buttonVariants({ variant: 'ghost' })"
                >
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
