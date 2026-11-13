<script setup lang="ts">
import BarChart from '@/components/charts/BarChart.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import RankedBars from '@/components/charts/RankedBars.vue';
import KpiTile from '@/components/dashboard/KpiTile.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { compactNumber, count, longDay, money, shortDay, since } from '@/lib/format';
import { type Analytics } from '@/types/analytics';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ analytics: Analytics }>();

const ranges = [7, 30, 90];
const loading = ref(false);

// One filter row above everything it scopes. While the new window loads, the previous render stays (dimmed).
const setRange = (range: number) => {
    if (range === props.analytics.range || loading.value) return;
    router.get(
        route('dashboard'),
        { range },
        {
            only: ['analytics'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
};

const periodLabel = computed(() => `${props.analytics.range} días anteriores`);
const empty = computed(() => props.analytics.kpis.vehicles.value === 0);

const days = computed(() => props.analytics.daily);
const dayLabels = computed(() => days.value.map((d) => shortDay(d.date)));
const dayTitles = computed(() => days.value.map((d) => longDay(d.date)));

const collectedSeries = computed(() => [{ key: 'collected', label: 'Cobrado', values: days.value.map((d) => d.collected), color: 'var(--viz-1)' }]);
const visitSeries = computed(() => [
    { key: 'first', label: 'Primera visita', values: days.value.map((d) => d.first_visits), color: 'var(--viz-1)' },
    { key: 'repeat', label: 'Visita repetida', values: days.value.map((d) => d.repeat_visits), color: 'var(--viz-2)' },
]);

// Opening hours are usually 7-18; widen the window only if there is activity outside it.
const hourRange = computed(() => {
    const active = props.analytics.hourly.flatMap((n, hour) => (n > 0 ? [hour] : []));
    return { from: Math.min(7, ...active), to: Math.max(18, ...active) };
});
const hours = computed(() => Array.from({ length: hourRange.value.to - hourRange.value.from + 1 }, (_, i) => hourRange.value.from + i));
const hourSeries = computed(() => [{ key: 'hours', label: 'Vehículos', values: hours.value.map((h) => props.analytics.hourly[h]), color: 'var(--viz-1)' }]);

const plural = (n: number, one: string, many: string) => `${count(n)} ${n === 1 ? one : many}`;

const tables = computed(() => ({
    collected: { columns: ['Día', 'Cobrado', 'Vehículos'], rows: days.value.map((d) => [longDay(d.date), money(d.collected), d.vehicles]) },
    visits: { columns: ['Día', 'Primera visita', 'Visita repetida'], rows: days.value.map((d) => [longDay(d.date), d.first_visits, d.repeat_visits]) },
    hours: { columns: ['Hora', 'Vehículos'], rows: hours.value.map((h) => [`${h}:00 – ${h}:59`, props.analytics.hourly[h]]) },
    types: { columns: ['Tipo', 'Vehículos', 'Total'], rows: props.analytics.by_type.map((r) => [r.name, r.count, money(r.total)]) },
    services: { columns: ['Servicio', 'Ventas', 'Total'], rows: props.analytics.by_service.map((r) => [r.name, r.count, money(r.total)]) },
}));
</script>

<template>
    <section class="flex flex-col gap-4" aria-labelledby="analytics-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="analytics-title" class="text-xl font-bold">Análisis del negocio</h2>
                <p class="text-sm text-muted-foreground">Del {{ shortDay(analytics.from) }} al {{ shortDay(analytics.to) }}</p>
            </div>
            <div class="flex gap-1 rounded-lg border bg-card p-1" role="group" aria-label="Periodo">
                <Button
                    v-for="range in ranges"
                    :key="range"
                    type="button"
                    size="sm"
                    :variant="analytics.range === range ? 'default' : 'ghost'"
                    :aria-pressed="analytics.range === range"
                    :disabled="loading"
                    @click="setRange(range)"
                >
                    {{ range }} días
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-4 transition-opacity duration-200" :class="{ 'opacity-60': loading }" :aria-busy="loading">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <KpiTile
                    label="Vehículos atendidos"
                    :value="count(analytics.kpis.vehicles.value)"
                    :delta="analytics.kpis.vehicles.delta"
                    :period-label="periodLabel"
                />
                <KpiTile label="Cobrado" :value="money(analytics.kpis.collected.value)" :delta="analytics.kpis.collected.delta" :period-label="periodLabel" />
                <KpiTile
                    label="Vehículos nuevos"
                    :value="count(analytics.kpis.new_vehicles.value)"
                    :delta="analytics.kpis.new_vehicles.delta"
                    :period-label="periodLabel"
                    hint="Su primera visita fue en este periodo"
                />
                <KpiTile
                    label="Vehículos que repiten"
                    :value="count(analytics.kpis.returning_vehicles.value)"
                    :delta="analytics.kpis.returning_vehicles.delta"
                    :period-label="periodLabel"
                    hint="Con al menos una visita repetida"
                />
            </div>

            <Card v-if="empty" class="animate-rise">
                <CardContent class="py-10 text-center text-muted-foreground">
                    Aún no hay movimiento en los últimos {{ analytics.range }} días. Cuando registres vehículos, aquí verás cuánto cobras, quiénes son nuevos y quiénes vuelven.
                </CardContent>
            </Card>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <ChartCard class="lg:col-span-2" title="Cobrado por día" description="Pesos cobrados al entregar, por día de pago" :table="tables.collected">
                    <BarChart
                        :labels="dayLabels"
                        :titles="dayTitles"
                        :series="collectedSeries"
                        :format="money"
                        :tick-format="compactNumber"
                        :data-key="analytics.range"
                        :aria-label="`Cobrado por día durante los últimos ${analytics.range} días. Total ${money(analytics.kpis.collected.value)}`"
                    />
                </ChartCard>

                <ChartCard title="Primera visita y visitas repetidas" description="Cuántos vehículos llegan por primera vez y cuántos vuelven" :table="tables.visits">
                    <BarChart
                        :labels="dayLabels"
                        :titles="dayTitles"
                        :series="visitSeries"
                        stacked
                        integer
                        :format="count"
                        :data-key="analytics.range"
                        :aria-label="`Vehículos por día separados en primera visita y visitas repetidas, últimos ${analytics.range} días`"
                    />
                </ChartCard>

                <ChartCard title="Horas con más movimiento" description="Vehículos que llegan en cada hora del día" :table="tables.hours">
                    <BarChart
                        :labels="hours.map((h) => `${h}h`)"
                        :titles="hours.map((h) => `${h}:00 – ${h}:59`)"
                        :series="hourSeries"
                        integer
                        :format="count"
                        :min-label-width="30"
                        :data-key="analytics.range"
                        :aria-label="`Vehículos por hora de llegada, últimos ${analytics.range} días`"
                    />
                </ChartCard>

                <ChartCard title="Por tipo de vehículo" description="Vehículos atendidos y lo que dejaron" :table="tables.types">
                    <RankedBars
                        :rows="analytics.by_type"
                        metric="count"
                        :primary="(row) => plural(row.count, 'vehículo', 'vehículos')"
                        :secondary="(row) => money(row.total)"
                    />
                </ChartCard>

                <ChartCard title="Servicios más vendidos" description="Por valor vendido" :table="tables.services">
                    <RankedBars
                        :rows="analytics.by_service"
                        metric="total"
                        :primary="(row) => money(row.total)"
                        :secondary="(row) => plural(row.count, 'venta', 'ventas')"
                    />
                </ChartCard>

                <Card class="lg:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Clientes recurrentes</CardTitle>
                        <p class="text-xs text-muted-foreground">Vehículos que vinieron en este periodo y ya tienen dos o más visitas en total</p>
                    </CardHeader>
                    <CardContent>
                        <p v-if="!analytics.recurring.length" class="py-6 text-center text-sm text-muted-foreground">
                            Todavía no hay vehículos que hayan vuelto en este periodo.
                        </p>
                        <ol v-else class="flex flex-col divide-y" data-testid="recurring">
                            <li v-for="row in analytics.recurring" :key="row.vehicle_id">
                                <Link
                                    :href="route('vehicles.show', row.vehicle_id)"
                                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-md px-1 py-3 transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <span class="flex items-center gap-3">
                                        <span class="plate">{{ row.plate }}</span>
                                        <span class="text-sm">{{ row.customer_name ?? 'Sin nombre' }}</span>
                                    </span>
                                    <span class="flex items-center gap-4 text-sm text-muted-foreground">
                                        <span>{{ plural(row.visits, 'visita', 'visitas') }} <span class="text-xs">({{ row.total_visits }} en total)</span></span>
                                        <span class="hidden sm:inline">{{ since(row.last_visit) }}</span>
                                        <strong class="font-display text-base text-foreground">{{ money(row.spent) }}</strong>
                                    </span>
                                </Link>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>
</template>
