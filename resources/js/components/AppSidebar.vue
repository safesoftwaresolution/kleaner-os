<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChartColumn, ClipboardList, LayoutGrid, Tags } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Tablero',
        href: '/orders',
        icon: ClipboardList,
    },
    // Owner-only entries; the server enforces this with the manage-catalog and view-reports gates.
    ...(page.props.auth.user?.role === 'owner'
        ? [
              { title: 'Reportes', href: '/reports', icon: ChartColumn },
              { title: 'Precios', href: '/catalog', icon: Tags },
          ]
        : []),
    {
        title: 'Inicio',
        href: '/dashboard',
        icon: LayoutGrid,
    },
]);

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
