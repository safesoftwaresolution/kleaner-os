<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Status = 'queued' | 'washing' | 'ready' | 'delivered';

interface OrderCard {
    id: number;
    plate: string;
    vehicle_type: string;
    phone: string | null;
    status: Status;
    total: number;
    services: string[];
    payment_method: string | null;
    created_at: string;
}

interface ServiceOption {
    id: number;
    name: string;
    kind: 'main' | 'extra';
    prices: Record<string, number>;
}

const props = defineProps<{
    orders: OrderCard[];
    vehicleTypes: { id: number; name: string }[];
    services: ServiceOption[];
    summary: { orders_today: number; collected_today: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tablero', href: '/orders' }];

const money = (value: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);

const form = useForm({
    plate: '',
    vehicle_type_id: null as number | null,
    customer_name: '',
    customer_phone: '',
    service_id: null as number | null,
    extra_ids: [] as number[],
});

const knownVehicle = ref(false);

const priceFor = (service: ServiceOption) => (form.vehicle_type_id ? (service.prices[String(form.vehicle_type_id)] ?? null) : null);

const mainServices = computed(() => props.services.filter((s) => s.kind === 'main' && priceFor(s) !== null));
const extraServices = computed(() => props.services.filter((s) => s.kind === 'extra' && priceFor(s) !== null));

const total = computed(() => {
    const ids = [form.service_id, ...form.extra_ids].filter((id): id is number => id !== null);
    return ids.reduce((sum, id) => {
        const service = props.services.find((s) => s.id === id);
        return sum + (service ? (priceFor(service) ?? 0) : 0);
    }, 0);
});

// Drop selections that have no price for the newly chosen vehicle type.
watch(
    () => form.vehicle_type_id,
    () => {
        if (form.service_id && !mainServices.value.some((s) => s.id === form.service_id)) {
            form.service_id = null;
        }
        form.extra_ids = form.extra_ids.filter((id) => extraServices.value.some((s) => s.id === id));
    },
);

// Autocomplete type and contact data when the plate is already known.
let lookupTimer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => form.plate,
    (value) => {
        clearTimeout(lookupTimer);
        knownVehicle.value = false;
        const plate = value.replace(/\s+/g, '').toUpperCase();
        if (plate.length < 5) return;

        lookupTimer = setTimeout(async () => {
            const response = await fetch(route('vehicles.lookup', { plate }), { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.found) return;
            knownVehicle.value = true;
            form.vehicle_type_id = data.vehicle_type_id;
            form.customer_name = data.name ?? '';
            form.customer_phone = data.phone ?? '';
        }, 300);
    },
);

const toggleExtra = (id: number) => {
    form.extra_ids = form.extra_ids.includes(id) ? form.extra_ids.filter((e) => e !== id) : [...form.extra_ids, id];
};

const submit = () => {
    form.post(route('orders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            knownVehicle.value = false;
        },
    });
};

const columns: { status: Status; title: string }[] = [
    { status: 'queued', title: 'En cola' },
    { status: 'washing', title: 'Lavando' },
    { status: 'ready', title: 'Listo' },
];

const byStatus = (status: Status) => props.orders.filter((o) => o.status === status);
const delivered = computed(() => props.orders.filter((o) => o.status === 'delivered'));

const advance = (order: OrderCard, status: Status, payment_method?: string) => {
    router.patch(route('orders.status', order.id), { status, payment_method }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Tablero" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="grid grid-cols-2 gap-4">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Vehículos hoy</CardTitle></CardHeader>
                    <CardContent class="text-2xl font-bold" data-testid="orders-today">{{ summary.orders_today }}</CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Cobrado hoy</CardTitle></CardHeader>
                    <CardContent class="text-2xl font-bold" data-testid="collected-today">{{ money(summary.collected_today) }}</CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader><CardTitle>Registrar vehículo</CardTitle></CardHeader>
                <CardContent>
                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1">
                                <label for="plate" class="text-sm font-medium">Placa</label>
                                <Input id="plate" v-model="form.plate" class="text-lg font-semibold uppercase" maxlength="10" autocomplete="off" placeholder="ABC123" />
                                <p v-if="knownVehicle" class="text-xs text-green-600">Cliente conocido: datos completados.</p>
                                <p v-if="form.errors.plate" class="text-xs text-destructive">{{ form.errors.plate }}</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="customer_phone" class="text-sm font-medium">Teléfono</label>
                                <Input id="customer_phone" v-model="form.customer_phone" inputmode="tel" placeholder="300 123 4567" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="customer_name" class="text-sm font-medium">Nombre (opcional)</label>
                                <Input id="customer_name" v-model="form.customer_name" />
                            </div>
                        </div>

                        <div>
                            <p class="mb-1 text-sm font-medium">Tipo de vehículo</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="type in vehicleTypes"
                                    :key="type.id"
                                    type="button"
                                    :variant="form.vehicle_type_id === type.id ? 'default' : 'outline'"
                                    @click="form.vehicle_type_id = type.id"
                                >
                                    {{ type.name }}
                                </Button>
                            </div>
                            <p v-if="form.errors.vehicle_type_id" class="mt-1 text-xs text-destructive">{{ form.errors.vehicle_type_id }}</p>
                        </div>

                        <div v-if="form.vehicle_type_id">
                            <p class="mb-1 text-sm font-medium">Servicio</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="service in mainServices"
                                    :key="service.id"
                                    type="button"
                                    :variant="form.service_id === service.id ? 'default' : 'outline'"
                                    @click="form.service_id = service.id"
                                >
                                    {{ service.name }} · {{ money(priceFor(service)!) }}
                                </Button>
                            </div>
                            <p v-if="form.errors.service_id" class="mt-1 text-xs text-destructive">{{ form.errors.service_id }}</p>
                        </div>

                        <div v-if="form.vehicle_type_id && extraServices.length">
                            <p class="mb-1 text-sm font-medium">Extras</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="service in extraServices"
                                    :key="service.id"
                                    type="button"
                                    :variant="form.extra_ids.includes(service.id) ? 'default' : 'outline'"
                                    @click="toggleExtra(service.id)"
                                >
                                    {{ service.name }} · {{ money(priceFor(service)!) }}
                                </Button>
                            </div>
                            <p v-if="form.errors.extra_ids" class="mt-1 text-xs text-destructive">{{ form.errors.extra_ids }}</p>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <p class="text-lg font-semibold">Total: {{ money(total) }}</p>
                            <Button type="submit" size="lg" :disabled="form.processing || !form.service_id">Registrar</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="column in columns" :key="column.status">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center justify-between text-base">
                            {{ column.title }}
                            <span class="rounded-full bg-muted px-2 text-sm">{{ byStatus(column.status).length }}</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <p v-if="!byStatus(column.status).length" class="text-sm text-muted-foreground">Sin vehículos.</p>
                        <div v-for="order in byStatus(column.status)" :key="order.id" class="rounded-lg border p-3" :data-testid="`order-${order.id}`">
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-bold">{{ order.plate }}</span>
                                <span class="text-sm text-muted-foreground">{{ order.vehicle_type }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ order.services.join(' + ') }}</p>
                            <p class="text-sm font-medium">{{ money(order.total) }}</p>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button v-if="order.status === 'queued'" size="sm" @click="advance(order, 'washing')">Empezar</Button>
                                <Button v-if="order.status === 'washing'" size="sm" @click="advance(order, 'ready')">Listo</Button>
                                <template v-if="order.status === 'ready'">
                                    <Button size="sm" @click="advance(order, 'delivered', 'cash')">Entregar · efectivo</Button>
                                    <Button size="sm" variant="outline" @click="advance(order, 'delivered', 'transfer')">Entregar · transferencia</Button>
                                </template>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="delivered.length">
                <CardHeader class="pb-2"><CardTitle class="text-base">Entregados hoy</CardTitle></CardHeader>
                <CardContent class="flex flex-col gap-1 text-sm">
                    <div v-for="order in delivered" :key="order.id" class="flex justify-between">
                        <span>{{ order.plate }} · {{ order.services.join(' + ') }}</span>
                        <span>{{ money(order.total) }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
