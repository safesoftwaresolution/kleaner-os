<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCountUp } from '@/composables/useCountUp';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bike, Car, Clock, MessageCircle } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
    whatsapp_url: string | null;
    cash_received: number | null;
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

const ordersToday = useCountUp(() => props.summary.orders_today);
const collectedToday = useCountUp(() => props.summary.collected_today, 900);

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

const columns: { status: Status; title: string; dot: string; border: string }[] = [
    { status: 'queued', title: 'En cola', dot: 'bg-status-queued', border: 'border-t-status-queued' },
    { status: 'washing', title: 'Lavando', dot: 'bg-status-washing', border: 'border-t-status-washing' },
    { status: 'ready', title: 'Listo', dot: 'bg-status-ready', border: 'border-t-status-ready' },
];

const byStatus = (status: Status) => props.orders.filter((o) => o.status === status);
const delivered = computed(() => props.orders.filter((o) => o.status === 'delivered'));

const advance = (order: OrderCard, status: Status, payment_method?: string) => {
    router.patch(route('orders.status', order.id), { status, payment_method }, { preserveScroll: true });
};

// Cash payment: ask how much the customer handed over and show the change to give back.
const cashOrder = ref<OrderCard | null>(null);
const cashDigits = ref('');
const cashError = ref<string | null>(null);
const cashProcessing = ref(false);
const cashInput = ref<{ $el?: HTMLInputElement } | null>(null);

const cashOpen = computed({
    get: () => cashOrder.value !== null,
    set: (open: boolean) => {
        if (!open) cashOrder.value = null;
    },
});
const received = computed(() => Number(cashDigits.value) || 0);
const cashTotal = computed(() => cashOrder.value?.total ?? 0);
const change = computed(() => received.value - cashTotal.value);
const receivedLabel = computed(() => (cashDigits.value === '' ? '' : new Intl.NumberFormat('es-CO').format(received.value)));

// Suggested amounts: exact, then the total rounded up to common Colombian bills.
const quickAmounts = computed(() => {
    const total = cashTotal.value;
    const rounded = [5000, 10000, 20000, 50000, 100000].map((bill) => Math.ceil(total / bill) * bill);
    return [...new Set([total, ...rounded])].filter((amount) => amount >= total).sort((a, b) => a - b).slice(0, 4);
});

const openCash = (order: OrderCard) => {
    cashOrder.value = order;
    cashDigits.value = '';
    cashError.value = null;
};
const setReceived = (value: string | number) => {
    cashDigits.value = String(value).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    cashError.value = null;
};
const focusCashInput = (event: Event) => {
    event.preventDefault();
    nextTick(() => cashInput.value?.$el?.focus());
};
const confirmCash = () => {
    const order = cashOrder.value;
    if (!order || change.value < 0 || cashProcessing.value) return;

    router.patch(
        route('orders.status', order.id),
        { status: 'delivered', payment_method: 'cash', cash_received: received.value },
        {
            preserveScroll: true,
            onStart: () => (cashProcessing.value = true),
            onFinish: () => (cashProcessing.value = false),
            onSuccess: () => (cashOrder.value = null),
            onError: (errors) => (cashError.value = errors.cash_received ?? Object.values(errors)[0] ?? 'No se pudo registrar el cobro.'),
        },
    );
};

const isMoto = (name: string) => name.toLowerCase().includes('moto');

// Elapsed time since the vehicle came in. Turns amber after 30 min and red after 60.
const now = ref(Date.now());
const minutes = (order: OrderCard) => Math.max(0, Math.floor((now.value - new Date(order.created_at).getTime()) / 60000));
const elapsed = (order: OrderCard) => {
    const m = minutes(order);
    if (m < 1) return 'Recién llegó';
    if (m < 60) return `Lleva ${m} min`;
    return `Lleva ${Math.floor(m / 60)} h ${m % 60} min`;
};
const elapsedClass = (order: OrderCard) => {
    if (order.status === 'ready') return 'text-muted-foreground';
    const m = minutes(order);
    return m >= 60 ? 'font-semibold text-destructive' : m >= 30 ? 'font-semibold text-status-queued-text' : 'text-muted-foreground';
};

// Live board: several people look at it at once, so refresh in the background.
let clock: ReturnType<typeof setInterval> | undefined;
let poll: ReturnType<typeof setInterval> | undefined;
const refresh = () => {
    now.value = Date.now();
    if (document.visibilityState === 'visible' && !form.processing) {
        router.reload({ only: ['orders', 'summary'], preserveScroll: true });
    }
};
onMounted(() => {
    clock = setInterval(() => (now.value = Date.now()), 30000);
    poll = setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', refresh);
});
onBeforeUnmount(() => {
    clearInterval(clock);
    clearInterval(poll);
    document.removeEventListener('visibilitychange', refresh);
});
</script>

<template>
    <Head title="Tablero" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Tablero</h1>
                <span class="flex items-center gap-2 rounded-full bg-status-ready/15 px-3 py-1 text-xs font-semibold text-foreground">
                    <span class="size-2 animate-pulse rounded-full bg-status-ready" /> En vivo
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <Card class="animate-rise">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Vehículos hoy</CardTitle></CardHeader>
                    <CardContent class="font-display text-3xl font-extrabold tabular-nums" data-testid="orders-today">{{ ordersToday }}</CardContent>
                </Card>
                <Card class="animate-rise [animation-delay:80ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Cobrado hoy</CardTitle></CardHeader>
                    <CardContent class="font-display text-3xl font-extrabold tabular-nums text-primary" data-testid="collected-today">
                        {{ money(collectedToday) }}
                    </CardContent>
                </Card>
            </div>

            <Card class="animate-rise border-2 border-primary/20 [animation-delay:120ms]">
                <CardHeader><CardTitle>Registrar vehículo</CardTitle></CardHeader>
                <CardContent>
                    <form class="flex flex-col gap-5" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1">
                                <label for="plate" class="text-sm font-medium">Placa</label>
                                <Input
                                    id="plate"
                                    v-model="form.plate"
                                    class="h-14 border-2 border-black/80 bg-spark text-center font-display text-2xl font-bold uppercase tracking-[0.2em] text-black placeholder:text-black/40 focus-visible:ring-black"
                                    maxlength="10"
                                    autocomplete="off"
                                    placeholder="ABC123"
                                />
                                <p v-if="knownVehicle" class="animate-pop text-xs font-medium text-status-ready">Cliente conocido: datos completados.</p>
                                <p v-if="form.errors.plate" class="text-xs text-destructive">{{ form.errors.plate }}</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="customer_phone" class="text-sm font-medium">Celular</label>
                                <Input id="customer_phone" v-model="form.customer_phone" class="h-14" inputmode="tel" placeholder="300 123 4567" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="customer_name" class="text-sm font-medium">Nombre (opcional)</label>
                                <Input id="customer_name" v-model="form.customer_name" class="h-14" />
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium">Tipo de vehículo</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="type in vehicleTypes"
                                    :key="type.id"
                                    type="button"
                                    size="lg"
                                    class="min-h-11"
                                    :variant="form.vehicle_type_id === type.id ? 'default' : 'outline'"
                                    @click="form.vehicle_type_id = type.id"
                                >
                                    <component :is="isMoto(type.name) ? Bike : Car" class="size-5" />
                                    {{ type.name }}
                                </Button>
                            </div>
                            <p v-if="form.errors.vehicle_type_id" class="mt-1 text-xs text-destructive">{{ form.errors.vehicle_type_id }}</p>
                        </div>

                        <div v-if="form.vehicle_type_id" class="animate-rise">
                            <p class="mb-2 text-sm font-medium">Servicio</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="service in mainServices"
                                    :key="service.id"
                                    type="button"
                                    class="min-h-11"
                                    :variant="form.service_id === service.id ? 'default' : 'outline'"
                                    @click="form.service_id = service.id"
                                >
                                    {{ service.name }} · {{ money(priceFor(service)!) }}
                                </Button>
                            </div>
                            <p v-if="form.errors.service_id" class="mt-1 text-xs text-destructive">{{ form.errors.service_id }}</p>
                        </div>

                        <div v-if="form.vehicle_type_id && extraServices.length" class="animate-rise">
                            <p class="mb-2 text-sm font-medium">Extras</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="service in extraServices"
                                    :key="service.id"
                                    type="button"
                                    class="min-h-11"
                                    :variant="form.extra_ids.includes(service.id) ? 'default' : 'outline'"
                                    @click="toggleExtra(service.id)"
                                >
                                    {{ service.name }} · {{ money(priceFor(service)!) }}
                                </Button>
                            </div>
                            <p v-if="form.errors.extra_ids" class="mt-1 text-xs text-destructive">{{ form.errors.extra_ids }}</p>
                        </div>

                        <div class="flex items-center justify-between gap-4 border-t pt-4">
                            <p class="font-display text-2xl font-extrabold">
                                <span class="text-sm font-medium text-muted-foreground">Total</span> {{ money(total) }}
                            </p>
                            <Button
                                type="submit"
                                size="lg"
                                class="h-12 px-8 font-display text-base font-bold"
                                :class="{ 'animate-pulse-ring': form.service_id && !form.processing }"
                                :disabled="form.processing || !form.service_id"
                            >
                                Registrar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="column in columns" :key="column.status" class="border-t-4" :class="column.border">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center justify-between text-base">
                            <span class="flex items-center gap-2"><span class="size-2.5 rounded-full" :class="column.dot" /> {{ column.title }}</span>
                            <span class="rounded-full bg-muted px-2.5 py-0.5 font-display text-sm tabular-nums">{{ byStatus(column.status).length }}</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p v-if="!byStatus(column.status).length" class="py-4 text-center text-sm text-muted-foreground">Sin vehículos.</p>
                        <TransitionGroup name="card" tag="div" class="relative flex flex-col gap-3">
                            <div v-for="order in byStatus(column.status)" :key="order.id" class="rounded-xl border bg-card p-3 shadow-sm" :data-testid="`order-${order.id}`">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="plate">{{ order.plate }}</span>
                                    <span class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <component :is="isMoto(order.vehicle_type) ? Bike : Car" class="size-4" /> {{ order.vehicle_type }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-muted-foreground">{{ order.services.join(' + ') }}</p>
                                <div class="mt-1 flex items-center justify-between">
                                    <p class="font-display text-lg font-bold">{{ money(order.total) }}</p>
                                    <p class="flex items-center gap-1 text-xs" :class="elapsedClass(order)"><Clock class="size-3.5" /> {{ elapsed(order) }}</p>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Button v-if="order.status === 'queued'" class="min-h-10 flex-1" @click="advance(order, 'washing')">Empezar</Button>
                                    <Button v-if="order.status === 'washing'" class="min-h-10 flex-1" @click="advance(order, 'ready')">Listo</Button>
                                    <template v-if="order.status === 'ready'">
                                        <Button v-if="order.whatsapp_url" class="min-h-10 w-full" variant="secondary" as-child>
                                            <a :href="order.whatsapp_url" target="_blank" rel="noopener noreferrer">
                                                <MessageCircle class="size-4" /> Avisar por WhatsApp
                                            </a>
                                        </Button>
                                        <span v-else class="w-full text-xs text-muted-foreground">Sin celular válido para WhatsApp.</span>
                                        <Button class="min-h-10 flex-1" @click="openCash(order)">Entregar · efectivo</Button>
                                        <Button class="min-h-10 flex-1" variant="outline" @click="advance(order, 'delivered', 'transfer')">Entregar · transferencia</Button>
                                    </template>
                                </div>
                            </div>
                        </TransitionGroup>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="delivered.length">
                <CardHeader class="pb-2"><CardTitle class="text-base">Entregados hoy</CardTitle></CardHeader>
                <CardContent class="flex flex-col gap-2 text-sm">
                    <div v-for="order in delivered" :key="order.id" class="flex items-center justify-between gap-3">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="plate !text-sm">{{ order.plate }}</span> {{ order.services.join(' + ') }}
                            <span v-if="order.cash_received !== null" class="text-xs text-muted-foreground">
                                Recibió {{ money(order.cash_received) }} · devolvió {{ money(order.cash_received - order.total) }}
                            </span>
                        </span>
                        <span class="font-semibold">{{ money(order.total) }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="cashOpen">
            <DialogContent class="sm:max-w-md" @open-auto-focus="focusCashInput">
                <form class="flex flex-col gap-5" @submit.prevent="confirmCash">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Cobro en efectivo</DialogTitle>
                        <DialogDescription class="flex items-center gap-2">
                            <span class="plate">{{ cashOrder?.plate }}</span> {{ cashOrder?.services.join(' + ') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="rounded-xl bg-muted p-4 text-center">
                        <p class="text-sm text-muted-foreground">Total a cobrar</p>
                        <p class="font-display text-4xl font-extrabold tabular-nums" data-testid="cash-total">{{ money(cashTotal) }}</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="cash-received" class="text-sm font-medium">¿Cuánto entregó el cliente?</label>
                        <Input
                            id="cash-received"
                            ref="cashInput"
                            :model-value="receivedLabel"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="0"
                            class="h-14 text-center font-display text-2xl font-bold tabular-nums"
                            @update:model-value="setReceived"
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="(amount, index) in quickAmounts"
                                :key="amount"
                                type="button"
                                size="sm"
                                :variant="received === amount ? 'default' : 'outline'"
                                @click="setReceived(amount)"
                            >
                                {{ index === 0 ? 'Exacto' : money(amount) }}
                            </Button>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border-2 p-4 text-center transition-colors"
                        :class="
                            received === 0
                                ? 'border-dashed text-muted-foreground'
                                : change >= 0
                                  ? 'border-status-ready bg-status-ready/10'
                                  : 'border-destructive bg-destructive/10'
                        "
                        aria-live="polite"
                        data-testid="cash-change"
                    >
                        <template v-if="received === 0">Escribe lo que entregó para ver el cambio.</template>
                        <template v-else-if="change >= 0">
                            <p class="text-sm text-muted-foreground">Devolver</p>
                            <p class="font-display text-4xl font-extrabold tabular-nums">{{ money(change) }}</p>
                        </template>
                        <template v-else>
                            <p class="text-sm text-muted-foreground">Falta</p>
                            <p class="font-display text-4xl font-extrabold tabular-nums">{{ money(-change) }}</p>
                        </template>
                    </div>

                    <p v-if="cashError" class="text-sm text-destructive" role="alert">{{ cashError }}</p>

                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button type="button" variant="secondary" @click="cashOrder = null">Cancelar</Button>
                        <Button type="submit" class="font-display font-bold" :disabled="received === 0 || change < 0 || cashProcessing">
                            Confirmar y entregar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.card-move,
.card-enter-active,
.card-leave-active {
    transition:
        transform 0.35s ease,
        opacity 0.3s ease;
}
.card-enter-from {
    opacity: 0;
    transform: translateY(14px) scale(0.96);
}
.card-leave-to {
    opacity: 0;
    transform: scale(0.96);
}
.card-leave-active {
    position: absolute;
    width: 100%;
}
</style>
