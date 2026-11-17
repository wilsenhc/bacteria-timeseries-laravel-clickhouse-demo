<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, FlaskConical, Microscope } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import GitHubIcon from '@/components/GitHubIcon.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as bacteria } from '@/routes/bacteria';
import { index as labs } from '@/routes/labs';
import { index as organizations } from '@/routes/organizations';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Labs',
        href: labs(),
        icon: FlaskConical,
    },
    {
        title: 'Organizations',
        href: organizations(),
        icon: Building2,
    },
    {
        title: 'Bacteria',
        href: bacteria(),
        icon: Microscope,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'GitHub',
        href: 'https://github.com/wilsenhc/bacteria-timeseries-laravel-clickhouse-demo',
        icon: GitHubIcon,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="labs()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <OrganizationSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
