<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    FlaskConical,
    Plus,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { index as organizations } from '@/routes/organizations';
import { create as createLab, index, show as showLab } from '@/routes/labs';
import type { LabSummary } from '@/types/lab';
import type { OrganizationSummary } from '@/types/organization';

defineProps<{
    organization: OrganizationSummary | null;
    labs: LabSummary[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Labs',
                href: index(),
            },
        ],
    },
});

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : null;
</script>

<template>
    <Head title="Labs" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 p-4 md:p-6">
        <header
            class="animate-rise flex flex-col gap-4 border-b border-border pb-6 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="eyebrow">Workspace / Labs</p>
                    <Badge
                        v-if="organization?.role"
                        variant="outline"
                        class="capitalize"
                    >
                        {{ organization.role }}
                    </Badge>
                </div>
                <h1 class="mt-3 text-[2.25rem] leading-none font-bold">Labs</h1>
                <p
                    v-if="organization"
                    class="mt-3 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                >
                    {{ organization.name }}
                    <span class="tech ml-2">{{ organization.timezone }}</span>
                </p>
                <p
                    v-else
                    class="mt-3 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                >
                    Choose an organization to see the labs it owns.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-6">
                <div v-if="organization">
                    <p class="tech uppercase">Labs</p>
                    <p class="mt-1 font-mono text-2xl font-medium">
                        {{ labs.length }}
                    </p>
                </div>
                <Link
                    v-if="canManage"
                    :href="createLab()"
                    :class="buttonVariants({ size: 'lg' })"
                >
                    <Plus class="size-4" />
                    New lab
                </Link>
            </div>
        </header>

        <div
            v-if="organization === null"
            class="surface-card flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground"
            >
                <Building2 class="size-6" />
            </span>
            <h2 class="text-xl font-semibold tracking-tight">
                No organization selected
            </h2>
            <p
                class="max-w-[42ch] text-sm leading-relaxed text-muted-foreground"
            >
                Labs belong to an organization. Create or join one before
                defining a lab structure.
            </p>
            <Link
                :href="organizations()"
                :class="buttonVariants({ size: 'lg' })"
            >
                Manage organizations
            </Link>
        </div>

        <div
            v-else-if="labs.length > 0"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <Link
                v-for="(lab, position) in labs"
                :key="lab.id"
                :href="showLab(lab.id)"
                class="surface-card hover-lift animate-rise flex flex-col gap-4 p-5"
                :style="{ animationDelay: `${position * 90}ms` }"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="flex size-11 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground"
                    >
                        <FlaskConical class="size-5" />
                    </span>
                    <span v-if="lab.code" class="tech">{{ lab.code }}</span>
                </div>

                <div>
                    <h2 class="text-base font-semibold">{{ lab.name }}</h2>
                    <p class="tech mt-1">
                        {{ lab.row_count }} rows ×
                        {{ lab.shelves_per_row }} shelves ×
                        {{ lab.levels_per_shelf }} levels
                    </p>
                </div>

                <div
                    class="mt-auto grid grid-cols-2 gap-2 border-t border-border pt-3"
                >
                    <div>
                        <p class="tech uppercase">Levels</p>
                        <p class="mt-1 font-mono text-lg font-medium">
                            {{ lab.levels_count }}
                        </p>
                    </div>
                    <div>
                        <p class="tech uppercase">Samples</p>
                        <p class="mt-1 font-mono text-lg font-medium">
                            {{ lab.samples_count }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <p
                        v-if="lab.created_at"
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <CalendarDays class="size-3.5" />
                        {{ formatDate(lab.created_at) }}
                    </p>
                    <ArrowRight class="ml-auto size-4 text-muted-foreground" />
                </div>
            </Link>
        </div>

        <div
            v-else
            class="surface-card flex flex-col items-center justify-center gap-4 px-6 py-14 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground"
            >
                <FlaskConical class="size-6" />
            </span>
            <h2 class="text-lg font-semibold">No labs yet</h2>
            <p
                class="max-w-[42ch] text-sm leading-relaxed text-muted-foreground"
            >
                A lab defines the rows, shelves, and levels where samples live.
            </p>
            <Link
                v-if="canManage"
                :href="createLab()"
                :class="buttonVariants()"
            >
                Create the first lab
            </Link>
        </div>
    </div>
</template>
