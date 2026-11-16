<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Trash2 } from '@lucide/vue';
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
import { create, destroy, edit, index } from '@/routes/organizations';
import type { Organization } from '@/types/organization';

defineProps<{
    organizations: Organization[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Manage Organizations',
                href: index(),
            },
        ],
    },
});

const page = usePage();
const deleteForm = useForm({});
</script>

<template>
    <Head title="Manage Organizations" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 p-4 md:p-6">
        <header
            class="animate-rise flex flex-col gap-4 border-b border-border pb-6 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="eyebrow">Workspace / Tenants</p>
                <h1 class="mt-3 text-[2.25rem] leading-none font-bold">
                    Manage Organizations
                </h1>
                <p
                    class="mt-3 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                >
                    Organizations group labs and carry the timezone used to
                    calculate their ISO weeks. The selected organization drives
                    the Labs menu.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-6">
                <div>
                    <p class="tech uppercase">Memberships</p>
                    <p class="mt-1 font-mono text-2xl font-medium">
                        {{ organizations.length }}
                    </p>
                </div>
                <Link :href="create()" :class="buttonVariants({ size: 'lg' })">
                    <Plus class="size-4" />
                    New organization
                </Link>
            </div>
        </header>

        <div
            v-if="organizations.length > 0"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="(organization, position) in organizations"
                :key="organization.id"
                class="surface-card animate-rise flex flex-col gap-4 p-5"
                :style="{ animationDelay: `${position * 100}ms` }"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="flex size-11 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground"
                    >
                        <Building2 class="size-5" />
                    </span>
                    <div
                        class="flex flex-wrap items-center justify-end gap-1.5"
                    >
                        <Badge
                            v-if="
                                page.props.organizationSwitch.selected?.id ===
                                organization.id
                            "
                            variant="secondary"
                        >
                            Current
                        </Badge>
                        <Badge variant="outline" class="capitalize">
                            {{ organization.role }}
                        </Badge>
                    </div>
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        {{ organization.name }}
                    </h2>
                    <p
                        class="mt-1 line-clamp-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ organization.address }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between gap-3 border-t border-border pt-3"
                >
                    <p class="tech">
                        {{ organization.labs_count }} labs ·
                        {{ organization.timezone }}
                    </p>

                    <div
                        v-if="organization.role === 'owner'"
                        class="flex items-center gap-1.5"
                    >
                        <Link
                            :href="edit(organization.id)"
                            :class="
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'sm',
                                })
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
                                        Delete {{ organization.name }}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        This permanently deletes the
                                        organization, its
                                        {{ organization.labs_count }} labs, and
                                        all samples and observations inside
                                        them. This cannot be undone.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        :disabled="deleteForm.processing"
                                        @click="
                                            deleteForm.delete(
                                                destroy.url(organization.id),
                                            )
                                        "
                                    >
                                        Delete organization
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </article>
        </div>

        <section
            v-else
            class="surface-card flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground"
            >
                <Building2 class="size-6" />
            </span>
            <h2 class="text-xl font-semibold tracking-tight">
                No organizations yet
            </h2>
            <p
                class="max-w-[42ch] text-sm leading-relaxed text-muted-foreground"
            >
                Create your first organization to start defining labs and their
                storage structure.
            </p>
            <Link :href="create()" :class="buttonVariants({ size: 'lg' })">
                Create organization
            </Link>
        </section>
    </div>
</template>
