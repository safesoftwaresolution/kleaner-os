<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { messageTemplates, whatsappLink } from '@/lib/whatsapp';
import { Bike, Car, MessageCircle, Phone, Search, User } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

interface Row {
    id: number;
    plate: string;
    type: string;
    customer_name: string | null;
    customer_phone: string | null;
    whatsapp_number: string | null;
    visits: number;
    total_spent: number;
    last_visit: string | null;
}

interface Paginator {
    data: Row[];
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    business: string;
    vehicles: Paginator;
    filters: { q: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/vehicles' }];

const money = (value: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);

const since = (iso: string | null) => {
    if (!iso) return 'Sin visitas';
    const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
    if (days <= 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 14) return `Hace ${days} días`;
    if (days < 60) return `Hace ${Math.floor(days / 7)} semanas`;
    return `Hace ${Math.floor(days / 30)} meses`;
};

// Default greeting; the vehicle page lets staff pick another template and edit the text.
const greetingLink = (vehicle: Row) =>
    whatsappLink(vehicle.whatsapp_number, messageTemplates({ name: vehicle.customer_name, plate: vehicle.plate, business: props.business })[0].text);

// Search as you type: wait for a pause, then reload only the list.
const q = ref(props.filters.q);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('vehicles.index'), value.trim() ? { q: value.trim() } : {}, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Head title="Clientes y vehículos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Clientes y vehículos</h1>
                    <p class="text-sm text-muted-foreground">Se crean solos al registrar una orden. Aquí los encuentras y corriges sus datos.</p>
                </div>
                <p class="text-sm text-muted-foreground" data-testid="total">{{ vehicles.total }} {{ vehicles.total === 1 ? 'vehículo' : 'vehículos' }}</p>
            </div>

            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="q"
                    type="search"
                    class="h-12 pl-10 text-base"
                    placeholder="Busca por placa, nombre o celular"
                    aria-label="Buscar vehículos y clientes"
                    autocomplete="off"
                />
            </div>

            <Card v-if="!vehicles.data.length" class="animate-rise">
                <CardContent class="py-10 text-center text-muted-foreground">
                    <template v-if="filters.q">No encontramos nada para «{{ filters.q }}».</template>
                    <template v-else>Aún no hay vehículos. Aparecerán aquí cuando registres el primero en el tablero.</template>
                </CardContent>
            </Card>

            <ul v-else class="flex flex-col gap-3">
                <li v-for="(vehicle, i) in vehicles.data" :key="vehicle.id" class="flex animate-rise items-stretch gap-2" :style="{ animationDelay: `${Math.min(i, 8) * 40}ms` }">
                    <Link
                        :href="route('vehicles.show', vehicle.id)"
                        class="grid min-w-0 flex-1 gap-3 rounded-xl border bg-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:grid-cols-[auto_1fr_auto] md:items-center"
                        :data-testid="`vehicle-${vehicle.id}`"
                    >
                        <div class="flex items-center gap-3">
                            <span class="plate">{{ vehicle.plate }}</span>
                            <span class="flex items-center gap-1 text-sm text-muted-foreground">
                                <component :is="vehicle.type.toLowerCase().includes('moto') ? Bike : Car" class="size-4" /> {{ vehicle.type }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-0.5 text-sm">
                            <span v-if="vehicle.customer_name" class="flex items-center gap-1.5 font-medium"><User class="size-4 text-muted-foreground" /> {{ vehicle.customer_name }}</span>
                            <span v-if="vehicle.customer_phone" class="flex items-center gap-1.5 text-muted-foreground"><Phone class="size-4" /> {{ vehicle.customer_phone }}</span>
                            <span v-if="!vehicle.customer_name && !vehicle.customer_phone" class="text-muted-foreground">Sin datos de contacto</span>
                        </div>

                        <div class="flex items-center justify-between gap-6 text-sm md:justify-end">
                            <span class="text-muted-foreground">{{ vehicle.visits }} {{ vehicle.visits === 1 ? 'visita' : 'visitas' }} · {{ since(vehicle.last_visit) }}</span>
                            <span class="font-display text-lg font-bold tabular-nums">{{ money(vehicle.total_spent) }}</span>
                        </div>
                    </Link>
                    <a
                        v-if="greetingLink(vehicle)"
                        :href="greetingLink(vehicle)!"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex w-14 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border bg-card text-xs font-semibold shadow-sm transition hover:-translate-y-0.5 hover:bg-status-ready/10 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-24"
                        :aria-label="`Enviar WhatsApp a ${vehicle.customer_name ?? vehicle.plate}`"
                        :data-testid="`whatsapp-${vehicle.id}`"
                    >
                        <MessageCircle class="size-5 text-status-ready" />
                        <span class="hidden sm:inline">WhatsApp</span>
                    </a>
                    <span
                        v-else
                        class="flex w-14 shrink-0 flex-col items-center justify-center rounded-xl border border-dashed text-muted-foreground opacity-60 sm:w-24"
                        title="Sin celular válido para WhatsApp"
                        :data-testid="`no-whatsapp-${vehicle.id}`"
                    >
                        <MessageCircle class="size-5" />
                        <span class="sr-only">Sin celular válido para WhatsApp</span>
                    </span>
                </li>
            </ul>

            <div v-if="vehicles.prev_page_url || vehicles.next_page_url" class="flex items-center justify-between">
                <Button as-child variant="outline" :disabled="!vehicles.prev_page_url">
                    <Link v-if="vehicles.prev_page_url" :href="vehicles.prev_page_url" preserve-scroll>← Anterior</Link>
                    <span v-else>← Anterior</span>
                </Button>
                <span class="text-sm text-muted-foreground">{{ vehicles.from }}–{{ vehicles.to }} de {{ vehicles.total }}</span>
                <Button as-child variant="outline" :disabled="!vehicles.next_page_url">
                    <Link v-if="vehicles.next_page_url" :href="vehicles.next_page_url" preserve-scroll>Siguiente →</Link>
                    <span v-else>Siguiente →</span>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
