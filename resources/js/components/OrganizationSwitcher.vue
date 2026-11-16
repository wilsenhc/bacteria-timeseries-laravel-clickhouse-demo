<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { select } from '@/routes/organizations';

const page = usePage();

const switchOrganization = (value: unknown) => {
    if (typeof value !== 'string' || value === '') {
        return;
    }

    router.post(
        select.url(),
        { organization_id: Number(value) },
        { preserveScroll: true },
    );
};
</script>

<template>
    <div
        v-if="page.props.organizationSwitch.options.length > 0"
        class="px-2 pb-2 group-data-[collapsible=icon]/sidebar-wrapper:hidden"
    >
        <p class="tech mb-1.5 px-1 uppercase">Organization</p>
        <Select
            :model-value="
                page.props.organizationSwitch.selected
                    ? String(page.props.organizationSwitch.selected.id)
                    : undefined
            "
            @update:model-value="switchOrganization"
        >
            <SelectTrigger
                class="w-full"
                size="sm"
                aria-label="Selected organization"
            >
                <Building2 class="size-4 shrink-0" />
                <SelectValue placeholder="Select organization" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="organization in page.props.organizationSwitch
                        .options"
                    :key="organization.id"
                    :value="String(organization.id)"
                >
                    {{ organization.name }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
