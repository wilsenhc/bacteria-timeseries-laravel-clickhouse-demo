<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as labsIndex, show, update } from '@/routes/labs';
import type { LabForm } from '@/types/lab';

const props = defineProps<{
    lab: LabForm;
}>();

defineOptions({
    layout: (pageProps: { lab: LabForm }) => ({
        breadcrumbs: [
            {
                title: 'Labs',
                href: labsIndex(),
            },
            {
                title: pageProps.lab.name,
                href: show(pageProps.lab.id),
            },
            {
                title: 'Edit',
                href: '#',
            },
        ],
    }),
});

const form = useForm({
    name: props.lab.name,
    code: props.lab.code ?? '',
    notes: props.lab.notes ?? '',
});

const totalLevels = computed(
    () =>
        props.lab.row_count *
        props.lab.shelves_per_row *
        props.lab.levels_per_shelf,
);

const submit = () => form.patch(update.url(props.lab.id));
</script>

<template>
    <Head :title="`Edit ${lab.name}`" />

    <div class="mx-auto flex w-full max-w-[760px] flex-col gap-6 p-4 md:p-6">
        <Link
            :href="show(lab.id)"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ lab.name }}
        </Link>

        <header class="animate-rise">
            <p class="eyebrow">Organization / Lab</p>
            <h1 class="mt-3 text-[2.25rem] leading-none font-bold">Edit lab</h1>
            <p
                class="mt-3 max-w-[56ch] text-sm leading-relaxed text-muted-foreground"
            >
                Update the lab metadata. The row, shelf, and level structure is
                fixed at creation.
            </p>
        </header>

        <form
            class="surface-card animate-rise flex flex-col gap-6 p-6 [animation-delay:120ms]"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code"
                    >Code
                    <span class="text-muted-foreground">(optional)</span></Label
                >
                <Input id="code" v-model="form.code" />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="notes"
                    >Notes
                    <span class="text-muted-foreground">(optional)</span></Label
                >
                <Textarea id="notes" v-model="form.notes" rows="3" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="rounded-md border border-border bg-muted/40 p-4">
                <p class="tech uppercase">Fixed structure</p>
                <p class="mt-2 font-mono text-lg font-medium">
                    {{ lab.row_count }} × {{ lab.shelves_per_row }} ×
                    {{ lab.levels_per_shelf }} = {{ totalLevels }} levels
                </p>
                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                    The structure cannot be changed after creation.
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-3 border-t border-border pt-4"
            >
                <Button type="submit" :disabled="form.processing">
                    Save changes
                </Button>
                <Link
                    :href="show(lab.id)"
                    :class="buttonVariants({ variant: 'ghost' })"
                >
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
