<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Car, ChartColumn, LayoutDashboard, MessageCircle } from 'lucide-vue-next';

const features = [
    { icon: Car, title: 'Registro por placa', text: 'Escribes la placa y el sistema recuerda el tipo de vehículo y el celular del cliente.' },
    { icon: LayoutDashboard, title: 'Tablero en vivo', text: 'En cola, lavando, listo. Todo el equipo ve el mismo estado, desde el celular.' },
    { icon: MessageCircle, title: 'Aviso por WhatsApp', text: 'Un toque abre el chat con el mensaje listo para avisar que su vehículo está terminado.' },
    { icon: ChartColumn, title: 'Reportes del día', text: 'Cuánto cobraste, qué servicios se venden más y cuántos clientes vuelven.' },
];

const steps = [
    { n: '1', title: 'Registra', text: 'Placa, servicio y extras. El precio sale solo.' },
    { n: '2', title: 'Lava y avanza', text: 'Cada vehículo pasa de cola a lavando y a listo con un toque.' },
    { n: '3', title: 'Avisa y cobra', text: 'Entregas, eliges efectivo o transferencia y queda en tu reporte.' },
];

const mock = [
    { column: 'En cola', color: 'bg-status-queued', cards: [{ plate: 'KLN482', detail: 'Carro · Lavado + cera' }] },
    { column: 'Lavando', color: 'bg-status-washing', cards: [{ plate: 'ABC12D', detail: 'Moto · Lavado básico' }] },
    { column: 'Listo', color: 'bg-status-ready', cards: [{ plate: 'TGH907', detail: 'Carro · Lavado + aspirado' }] },
];
</script>

<template>
    <Head title="Kleaner OS · El sistema operativo de tu lavadero" />

    <div class="min-h-dvh bg-background text-foreground">
        <section class="bg-brand-gradient relative overflow-hidden text-white">
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-brand-cyan/20 blur-3xl" />

            <header class="relative z-10 mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <Link :href="route('home')" class="flex items-center gap-2">
                    <AppLogoIcon class="size-9" />
                    <span class="flex items-center gap-2 font-display text-xl font-bold tracking-tight">
                        Kleaner
                        <span class="rounded bg-spark px-1.5 py-0.5 text-[10px] font-extrabold leading-none tracking-wider text-black">OS</span>
                    </span>
                </Link>
                <Link
                    :href="$page.props.auth.user ? route('dashboard') : route('login')"
                    class="rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold ring-1 ring-white/25 transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-spark"
                >
                    {{ $page.props.auth.user ? 'Ir a mi tablero' : 'Entrar' }}
                </Link>
            </header>

            <div class="relative z-10 mx-auto grid max-w-6xl items-center gap-12 px-6 pb-20 pt-10 lg:grid-cols-2 lg:pb-28 lg:pt-16">
                <div class="animate-rise">
                    <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">
                        <span class="size-2 rounded-full bg-spark" /> Hecho para lavaderos de Colombia
                    </p>
                    <h1 class="text-4xl font-extrabold leading-[1.05] sm:text-5xl lg:text-6xl">
                        El sistema operativo de <span class="text-brand-cyan">tu lavadero</span>.
                    </h1>
                    <p class="mt-5 max-w-lg text-lg text-white/80">
                        Deja el cuaderno. Registra cada vehículo por placa, sigue el trabajo en un tablero y avisa al cliente por WhatsApp. Al final del día, sabes cuánto cobraste.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            :href="$page.props.auth.user ? route('dashboard') : route('login')"
                            class="group inline-flex items-center gap-2 rounded-xl bg-spark px-6 py-3 font-display text-base font-bold text-black shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        >
                            {{ $page.props.auth.user ? 'Ir a mi tablero' : 'Entrar' }}
                            <ArrowRight class="size-5 transition group-hover:translate-x-1" />
                        </Link>
                        <a href="#como-funciona" class="rounded-xl px-5 py-3 text-sm font-semibold text-white/90 ring-1 ring-white/25 transition hover:bg-white/10">
                            Ver cómo funciona
                        </a>
                    </div>
                </div>

                <div class="grid animate-rise grid-cols-1 gap-3 [animation-delay:150ms] sm:grid-cols-3" aria-label="Ejemplo de tablero">
                    <div
                        v-for="(col, i) in mock"
                        :key="col.column"
                        class="animate-float rounded-2xl bg-white/10 p-3 ring-1 ring-white/20 backdrop-blur"
                        :style="{ animationDelay: `${i * 0.8}s` }"
                    >
                        <p class="mb-3 flex items-center gap-2 text-sm font-semibold">
                            <span class="size-2.5 rounded-full" :class="col.color" /> {{ col.column }}
                        </p>
                        <div v-for="card in col.cards" :key="card.plate" class="rounded-xl bg-card p-3 text-card-foreground shadow">
                            <span class="plate">{{ card.plate }}</span>
                            <p class="mt-2 text-xs text-muted-foreground">{{ card.detail }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="max-w-xl text-3xl font-bold sm:text-4xl">Lo que necesita un lavadero, sin lo que sobra.</h2>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="(feature, i) in features"
                    :key="feature.title"
                    class="animate-rise rounded-2xl border bg-card p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                    :style="{ animationDelay: `${i * 80}ms` }"
                >
                    <span class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <component :is="feature.icon" class="size-6" />
                    </span>
                    <h3 class="mt-4 text-lg font-bold">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">{{ feature.text }}</p>
                </article>
            </div>
        </section>

        <section id="como-funciona" class="bg-muted/60 py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-3xl font-bold sm:text-4xl">Así de simple.</h2>
                <ol class="mt-10 grid gap-6 md:grid-cols-3">
                    <li v-for="step in steps" :key="step.n" class="flex gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary font-display text-xl font-bold text-primary-foreground">
                            {{ step.n }}
                        </span>
                        <div>
                            <h3 class="text-lg font-bold">{{ step.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ step.text }}</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <footer class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-10 text-sm text-muted-foreground">
            <span class="flex items-center gap-2 font-display font-bold text-foreground">
                <AppLogoIcon class="size-7" /> Kleaner OS
            </span>
            <span>Hecho para lavaderos de Colombia.</span>
        </footer>
    </div>
</template>
