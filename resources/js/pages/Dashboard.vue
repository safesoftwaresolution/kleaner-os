<script setup lang="ts">
import AnalyticsSection from '@/components/dashboard/AnalyticsSection.vue';
import { Button } from '@/components/ui/button';
import { useCountUp } from '@/composables/useCountUp';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type Analytics } from '@/types/analytics';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, ChartColumn, CircleCheck, Clock, Droplets, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    business: string | null;
    counts: { queued: number; washing: number; ready: number; delivered_today: number };
    collected_today: number;
    /** Only the owner receives it; null for other roles and accounts without a business. */
    analytics: Analytics | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const isOwner = computed(() => page.props.auth.user.role === 'owner');

const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const money = (value: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);

// Start at zero and count up on first paint so the numbers feel alive.
const target = ref({ queued: 0, washing: 0, ready: 0, delivered: 0, collected: 0 });
onMounted(() => {
    target.value = {
        queued: props.counts.queued,
        washing: props.counts.washing,
        ready: props.counts.ready,
        delivered: props.counts.delivered_today,
        collected: props.collected_today,
    };
});
const queued = useCountUp(() => target.value.queued);
const washing = useCountUp(() => target.value.washing);
const ready = useCountUp(() => target.value.ready);
const delivered = useCountUp(() => target.value.delivered);
const collected = useCountUp(() => target.value.collected, 900);

const tiles = computed(() => [
    { label: 'En cola', value: queued.value, icon: Clock, bar: 'bg-status-queued' },
    { label: 'Lavando', value: washing.value, icon: Droplets, bar: 'bg-status-washing' },
    { label: 'Listos', value: ready.value, icon: CircleCheck, bar: 'bg-status-ready' },
]);
</script>

<template>
    <Head title="Inicio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <!-- Account not attached to a business yet -->
            <div v-if="!business" class="animate-rise rounded-2xl border border-dashed bg-card p-8 text-center">
                <h1 class="text-2xl font-bold">¡Hola, {{ firstName }}!</h1>
                <p class="mx-auto mt-2 max-w-md text-muted-foreground">
                    Tu cuenta todavía no está asociada a un lavadero. Pídele a quien administra Kleaner OS que te agregue y vuelve a entrar.
                </p>
            </div>

            <template v-else>
                <section class="bg-brand-gradient relative animate-rise overflow-hidden rounded-3xl p-6 text-white md:p-8">
                    <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 size-56 rounded-full bg-brand-cyan/25 blur-3xl" />
                    <p class="relative text-sm font-medium text-white/70">{{ business }}</p>
                    <h1 class="relative mt-1 text-3xl font-extrabold md:text-4xl">{{ greeting }}, {{ firstName }}.</h1>
                    <p class="relative mt-2 text-white/80">Hoy llevas <span class="font-bold text-spark">{{ money(collected) }}</span> cobrados y {{ delivered }} vehículos entregados.</p>

                    <div class="relative mt-6 flex flex-wrap gap-3">
                        <Button as-child size="lg" class="bg-spark font-display font-bold text-black hover:bg-spark/90">
                            <Link :href="route('orders.index')"><Plus class="size-5" /> Registrar vehículo</Link>
                        </Button>
                        <Button v-if="isOwner" as-child size="lg" variant="outline" class="border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white">
                            <Link :href="route('reports.index')"><ChartColumn class="size-5" /> Ver reporte del día</Link>
                        </Button>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-3" aria-label="Estado del tablero">
                    <Link
                        v-for="(tile, i) in tiles"
                        :key="tile.label"
                        :href="route('orders.index')"
                        class="group relative animate-rise overflow-hidden rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :style="{ animationDelay: `${i * 80}ms` }"
                    >
                        <span class="absolute inset-x-0 top-0 h-1.5" :class="tile.bar" />
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-sm font-medium">{{ tile.label }}</span>
                            <component :is="tile.icon" class="size-5" />
                        </div>
                        <p class="mt-3 font-display text-5xl font-extrabold tabular-nums">{{ tile.value }}</p>
                        <p class="mt-2 flex items-center gap-1 text-xs text-muted-foreground opacity-0 transition group-hover:opacity-100">
                            Ver en el tablero <ArrowRight class="size-3" />
                        </p>
                    </Link>
                </section>

                <AnalyticsSection v-if="analytics" :analytics="analytics" />
            </template>
        </div>
    </AppLayout>
</template>
