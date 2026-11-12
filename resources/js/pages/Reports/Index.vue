<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Car } from 'lucide-vue-next';
import { computed } from 'vue';

interface Row {
    name?: string;
    method?: string;
    count: number;
    total: number;
}

const props = defineProps<{
    date: string;
    report: {
        vehicles: number;
        returning_vehicles: number;
        collected: number;
        pending_count: number;
        pending_total: number;
        collected_by_method: Row[];
        by_vehicle_type: Row[];
        by_service: Row[];
    };
    daily: { date: string; vehicles: number; collected: number }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reportes', href: '/reports' }];

const money = (value: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);

// Local-date helpers: parsing 'YYYY-MM-DD' with new Date(string) would shift the day in UTC-5.
const parse = (value: string) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const format = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const shift = (value: string, days: number) => {
    const date = parse(value);
    date.setDate(date.getDate() + days);
    return format(date);
};

const today = format(new Date());
const isToday = computed(() => props.date >= today);
const longDate = computed(() => {
    const text = new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }).format(parse(props.date));
    return text.charAt(0).toUpperCase() + text.slice(1);
});
const shortDay = (value: string) => new Intl.DateTimeFormat('es-CO', { weekday: 'short', day: 'numeric' }).format(parse(value));

const go = (date: string) => router.get(route('reports.index'), { date }, { preserveScroll: true, preserveState: true });

const methodLabels: Record<string, string> = { cash: 'Efectivo', transfer: 'Transferencia', other: 'Otro' };

const maxCollected = computed(() => Math.max(1, ...props.daily.map((d) => d.collected)));
const average = computed(() => (props.report.vehicles ? Math.round(props.report.collected / props.report.vehicles) : 0));
</script>

<template>
    <Head title="Reportes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center gap-2">
                <Button size="sm" variant="outline" aria-label="Día anterior" @click="go(shift(date, -1))">←</Button>
                <Input type="date" class="w-auto" :model-value="date" :max="today" @update:model-value="(v) => v && go(String(v))" />
                <Button size="sm" variant="outline" aria-label="Día siguiente" :disabled="isToday" @click="go(shift(date, 1))">→</Button>
                <Button size="sm" variant="ghost" :disabled="isToday" @click="go(today)">Hoy</Button>
                <h2 class="ml-2 text-lg font-semibold" data-testid="long-date">{{ longDate }}</h2>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card class="animate-rise border-t-4 border-t-primary ">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Vehículos</CardTitle></CardHeader>
                    <CardContent>
                        <p class="font-display text-3xl font-extrabold tabular-nums" data-testid="vehicles">{{ report.vehicles }}</p>
                        <p class="text-xs text-muted-foreground">{{ report.returning_vehicles }} ya habían venido</p>
                    </CardContent>
                </Card>
                <Card class="animate-rise border-t-4 border-t-status-ready [animation-delay:80ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Cobrado</CardTitle></CardHeader>
                    <CardContent>
                        <p class="font-display text-3xl font-extrabold tabular-nums" data-testid="collected">{{ money(report.collected) }}</p>
                        <p class="text-xs text-muted-foreground">Entregado y pagado este día</p>
                    </CardContent>
                </Card>
                <Card class="animate-rise border-t-4 border-t-status-queued [animation-delay:160ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Por cobrar</CardTitle></CardHeader>
                    <CardContent>
                        <p class="font-display text-3xl font-extrabold tabular-nums" data-testid="pending">{{ money(report.pending_total) }}</p>
                        <p class="text-xs text-muted-foreground">{{ report.pending_count }} sin entregar</p>
                    </CardContent>
                </Card>
                <Card class="animate-rise border-t-4 border-t-brand-cyan [animation-delay:240ms]">
                    <CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Promedio por vehículo</CardTitle></CardHeader>
                    <CardContent>
                        <p class="font-display text-3xl font-extrabold tabular-nums">{{ money(average) }}</p>
                        <p class="text-xs text-muted-foreground">Cobrado ÷ vehículos del día</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-base">Por tipo de vehículo</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-1 text-sm">
                        <p v-if="!report.by_vehicle_type.length" class="text-muted-foreground">Sin vehículos este día.</p>
                        <div v-for="row in report.by_vehicle_type" :key="row.name" class="flex justify-between">
                            <span>{{ row.name }} · {{ row.count }}</span>
                            <span>{{ money(row.total) }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-base">Servicios vendidos</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-1 text-sm">
                        <p v-if="!report.by_service.length" class="text-muted-foreground">Sin servicios este día.</p>
                        <div v-for="row in report.by_service" :key="row.name" class="flex justify-between">
                            <span>{{ row.name }} · {{ row.count }}</span>
                            <span>{{ money(row.total) }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-base">Cobrado por medio de pago</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-1 text-sm">
                        <p v-if="!report.collected_by_method.length" class="text-muted-foreground">Sin cobros este día.</p>
                        <div v-for="row in report.collected_by_method" :key="row.method" class="flex justify-between">
                            <span>{{ methodLabels[row.method ?? ''] ?? row.method }} · {{ row.count }}</span>
                            <span>{{ money(row.total) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-base">Últimos 7 días (cobrado)</CardTitle></CardHeader>
                <CardContent class="flex flex-col gap-2">
                    <button
                        v-for="day in daily"
                        :key="day.date"
                        type="button"
                        class="flex items-center gap-3 rounded-md p-1 text-left text-sm hover:bg-muted"
                        :class="{ 'font-semibold': day.date === date }"
                        :data-testid="`day-${day.date}`"
                        @click="go(day.date)"
                    >
                        <span class="w-16 shrink-0 capitalize">{{ shortDay(day.date) }}</span>
                        <span class="h-3 flex-1 rounded bg-muted">
                            <span class="block h-3 rounded bg-gradient-to-r from-brand-cyan to-primary transition-all duration-500" :style="{ width: `${(day.collected / maxCollected) * 100}%` }" />
                        </span>
                        <span class="w-24 shrink-0 text-right">{{ money(day.collected) }}</span>
                        <span class="flex w-12 shrink-0 items-center justify-end gap-1 text-muted-foreground">{{ day.vehicles }} <Car class="size-3.5" /></span>
                    </button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
