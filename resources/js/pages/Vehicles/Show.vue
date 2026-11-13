<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bike, Car } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    vehicle: {
        id: number;
        plate: string;
        vehicle_type_id: number;
        type: string;
        customer_name: string | null;
        customer_phone: string | null;
    };
    vehicleTypes: { id: number; name: string }[];
    stats: { visits: number; delivered: number; total_spent: number; average: number; first_visit: string | null; last_visit: string | null };
    history: { id: number; created_at: string; status: string; total: number; payment_method: string | null; services: string[] }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clientes', href: '/vehicles' },
    { title: props.vehicle.plate, href: `/vehicles/${props.vehicle.id}` },
]);

const money = (value: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);

const dateTime = (iso: string) =>
    new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso));

const since = (iso: string | null) => {
    if (!iso) return 'Sin visitas';
    const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
    if (days <= 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 14) return `Hace ${days} días`;
    if (days < 60) return `Hace ${Math.floor(days / 7)} semanas`;
    return `Hace ${Math.floor(days / 30)} meses`;
};

const statusLabels: Record<string, { label: string; dot: string }> = {
    queued: { label: 'En cola', dot: 'bg-status-queued' },
    washing: { label: 'Lavando', dot: 'bg-status-washing' },
    ready: { label: 'Listo', dot: 'bg-status-ready' },
    delivered: { label: 'Entregado', dot: 'bg-status-delivered' },
};
const methodLabels: Record<string, string> = { cash: 'Efectivo', transfer: 'Transferencia', other: 'Otro' };

const isMoto = (name: string) => name.toLowerCase().includes('moto');

const form = useForm({
    plate: props.vehicle.plate,
    vehicle_type_id: props.vehicle.vehicle_type_id,
    customer_name: props.vehicle.customer_name ?? '',
    customer_phone: props.vehicle.customer_phone ?? '',
});

const save = () => form.put(route('vehicles.update', props.vehicle.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Vehículo ${vehicle.plate}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-3">
                <Button as-child variant="ghost" size="sm"><Link :href="route('vehicles.index')"><ArrowLeft class="size-4" /> Clientes</Link></Button>
                <span class="plate !text-2xl" data-testid="plate">{{ vehicle.plate }}</span>
                <span class="flex items-center gap-1 text-muted-foreground"><component :is="isMoto(vehicle.type) ? Bike : Car" class="size-5" /> {{ vehicle.type }}</span>
                <span v-if="stats.visits >= 2" class="rounded-full bg-status-ready/15 px-3 py-1 text-xs font-semibold">Cliente recurrente</span>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card class="animate-rise border-t-4 border-t-primary">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Visitas</CardTitle></CardHeader>
                    <CardContent class="font-display text-3xl font-extrabold tabular-nums" data-testid="visits">{{ stats.visits }}</CardContent>
                </Card>
                <Card class="animate-rise border-t-4 border-t-status-ready [animation-delay:80ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Total gastado</CardTitle></CardHeader>
                    <CardContent class="font-display text-3xl font-extrabold tabular-nums" data-testid="spent">{{ money(stats.total_spent) }}</CardContent>
                </Card>
                <Card class="animate-rise border-t-4 border-t-brand-cyan [animation-delay:160ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Promedio por visita</CardTitle></CardHeader>
                    <CardContent class="font-display text-3xl font-extrabold tabular-nums">{{ money(stats.average) }}</CardContent>
                </Card>
                <Card class="animate-rise border-t-4 border-t-status-queued [animation-delay:240ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Última visita</CardTitle></CardHeader>
                    <CardContent>
                        <p class="font-display text-2xl font-extrabold" data-testid="last-visit">{{ since(stats.last_visit) }}</p>
                        <p v-if="stats.first_visit" class="text-xs text-muted-foreground">Primera: {{ dateTime(stats.first_visit) }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <Card class="h-fit animate-rise">
                    <CardHeader><CardTitle>Datos</CardTitle></CardHeader>
                    <CardContent>
                        <form class="flex flex-col gap-4" @submit.prevent="save">
                            <div class="flex flex-col gap-1">
                                <label for="plate" class="text-sm font-medium">Placa</label>
                                <Input id="plate" v-model="form.plate" class="font-display font-bold uppercase tracking-widest" maxlength="10" autocomplete="off" />
                                <p v-if="form.errors.plate" class="text-xs text-destructive" role="alert">{{ form.errors.plate }}</p>
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium">Tipo de vehículo</p>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="type in vehicleTypes"
                                        :key="type.id"
                                        type="button"
                                        :variant="form.vehicle_type_id === type.id ? 'default' : 'outline'"
                                        @click="form.vehicle_type_id = type.id"
                                    >
                                        <component :is="isMoto(type.name) ? Bike : Car" class="size-4" /> {{ type.name }}
                                    </Button>
                                </div>
                                <p v-if="form.errors.vehicle_type_id" class="mt-1 text-xs text-destructive" role="alert">{{ form.errors.vehicle_type_id }}</p>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="customer_name" class="text-sm font-medium">Nombre del cliente</label>
                                <Input id="customer_name" v-model="form.customer_name" maxlength="100" autocomplete="off" />
                                <p v-if="form.errors.customer_name" class="text-xs text-destructive" role="alert">{{ form.errors.customer_name }}</p>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="customer_phone" class="text-sm font-medium">Celular</label>
                                <Input id="customer_phone" v-model="form.customer_phone" inputmode="tel" maxlength="20" placeholder="300 123 4567" autocomplete="off" />
                                <p v-if="form.errors.customer_phone" class="text-xs text-destructive" role="alert">{{ form.errors.customer_phone }}</p>
                                <p class="text-xs text-muted-foreground">Si el celular ya es de otro cliente, este vehículo se vincula a él. Dejar nombre y celular vacíos quita el contacto.</p>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <p v-if="form.recentlySuccessful" class="animate-pop text-sm font-medium text-status-ready" role="status">Guardado.</p>
                                <span v-else />
                                <Button type="submit" :disabled="form.processing || !form.isDirty">Guardar cambios</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card class="animate-rise [animation-delay:80ms]">
                    <CardHeader><CardTitle>Historial</CardTitle></CardHeader>
                    <CardContent>
                        <p v-if="!history.length" class="py-6 text-center text-muted-foreground">Este vehículo aún no tiene visitas.</p>
                        <ol v-else class="flex flex-col divide-y">
                            <li v-for="visit in history" :key="visit.id" class="flex flex-wrap items-center justify-between gap-2 py-3" :data-testid="`visit-${visit.id}`">
                                <div>
                                    <p class="font-medium">{{ visit.services.join(' + ') }}</p>
                                    <p class="flex items-center gap-2 text-xs text-muted-foreground">
                                        {{ dateTime(visit.created_at) }}
                                        <span class="flex items-center gap-1">
                                            <span class="size-2 rounded-full" :class="statusLabels[visit.status]?.dot" /> {{ statusLabels[visit.status]?.label ?? visit.status }}
                                        </span>
                                        <span v-if="visit.payment_method">· {{ methodLabels[visit.payment_method] ?? visit.payment_method }}</span>
                                    </p>
                                </div>
                                <p class="font-display text-lg font-bold tabular-nums">{{ money(visit.total) }}</p>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
