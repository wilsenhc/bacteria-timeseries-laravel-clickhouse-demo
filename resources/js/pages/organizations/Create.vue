<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { index, store } from '@/routes/organizations';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Organizations',
                href: index(),
            },
            {
                title: 'New organization',
                href: '#',
            },
        ],
    },
});

const timezones = (
    Intl as unknown as { supportedValuesOf: (key: string) => string[] }
).supportedValuesOf('timeZone');
const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const form = useForm({
    name: '',
    address: '',
    timezone: timezones.includes(browserTimezone) ? browserTimezone : 'UTC',
});

const submit = () => form.post(store.url());
</script>

<template>
    <Head title="New organization" />

    <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6 p-4 md:p-6">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Organizations
        </Link>

        <header class="animate-rise">
            <p class="eyebrow">Workspace / New tenant</p>
            <h1 class="mt-3 text-[2.25rem] leading-none font-bold">
                New organization
            </h1>
            <p
                class="mt-3 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
            >
                The timezone is used to calculate the ISO week for every lab in
                this organization.
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
                    autocomplete="organization"
                    placeholder="Instituto de Análises Clínicas"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Textarea
                    id="address"
                    v-model="form.address"
                    required
                    rows="3"
                    placeholder="Av. Paulista, 1000 — São Paulo, SP"
                />
                <InputError :message="form.errors.address" />
            </div>

            <div class="grid gap-2">
                <Label for="timezone">Timezone</Label>
                <Select v-model="form.timezone">
                    <SelectTrigger id="timezone" class="w-full">
                        <SelectValue placeholder="Select a timezone" />
                    </SelectTrigger>
                    <SelectContent class="max-h-72">
                        <SelectItem
                            v-for="timezone in timezones"
                            :key="timezone"
                            :value="timezone"
                        >
                            {{ timezone }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.timezone" />
            </div>

            <div
                class="flex flex-wrap items-center gap-3 border-t border-border pt-4"
            >
                <Button type="submit" :disabled="form.processing">
                    Create organization
                </Button>
                <Link
                    :href="index()"
                    :class="buttonVariants({ variant: 'ghost' })"
                >
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
