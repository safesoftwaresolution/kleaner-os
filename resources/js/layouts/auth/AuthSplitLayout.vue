<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
import { ChartColumn, Car, MessageCircle } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

// Decorative bubbles rising on the brand panel. Positions are fixed so the layout is deterministic.
const bubbles = [
    { left: '6%', size: 14, delay: 0, duration: 15 },
    { left: '18%', size: 26, delay: 4, duration: 18 },
    { left: '30%', size: 10, delay: 9, duration: 13 },
    { left: '44%', size: 34, delay: 2, duration: 20 },
    { left: '57%', size: 16, delay: 7, duration: 14 },
    { left: '68%', size: 24, delay: 11, duration: 17 },
    { left: '79%', size: 12, delay: 5, duration: 15 },
    { left: '90%', size: 30, delay: 1, duration: 19 },
];

const points = [
    { icon: Car, text: 'Registra cada vehículo por placa en segundos' },
    { icon: MessageCircle, text: 'Avisa al cliente por WhatsApp cuando esté listo' },
    { icon: ChartColumn, text: 'Sabe cuánto cobraste hoy, sin cuaderno' },
];
</script>

<template>
    <div class="relative grid min-h-dvh lg:grid-cols-2">
        <div class="bg-brand-gradient relative hidden flex-col overflow-hidden p-10 text-white lg:flex">
            <span
                v-for="(bubble, index) in bubbles"
                :key="index"
                aria-hidden="true"
                class="absolute -bottom-12 animate-bubble rounded-full border border-white/25 bg-white/10"
                :style="{ left: bubble.left, width: `${bubble.size}px`, height: `${bubble.size}px`, animationDelay: `${bubble.delay}s`, animationDuration: `${bubble.duration}s` }"
            />

            <Link :href="route('home')" class="relative z-10 flex items-center gap-2">
                <AppLogoIcon class="size-10" />
                <span class="flex items-center gap-2 font-display text-2xl font-bold tracking-tight">
                    Kleaner
                    <span class="rounded bg-spark px-1.5 py-0.5 text-xs font-extrabold leading-none tracking-wider text-black">OS</span>
                </span>
            </Link>

            <div class="relative z-10 mt-auto max-w-md">
                <h2 class="font-display text-4xl font-bold leading-tight">El sistema operativo de tu lavadero.</h2>
                <ul class="mt-6 space-y-3">
                    <li v-for="point in points" :key="point.text" class="flex items-center gap-3 text-white/90">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/20">
                            <component :is="point.icon" class="size-5 text-brand-cyan" />
                        </span>
                        {{ point.text }}
                    </li>
                </ul>
                <p class="mt-8 text-sm text-white/60">Hecho para lavaderos de Colombia.</p>
            </div>
        </div>

        <div class="flex flex-col justify-center px-6 py-10 sm:px-10 lg:p-12">
            <div class="mx-auto flex w-full max-w-sm flex-col gap-6">
                <Link :href="route('home')" class="flex items-center justify-center gap-2 lg:hidden">
                    <AppLogoIcon class="size-10" />
                    <span class="flex items-center gap-2 font-display text-2xl font-bold tracking-tight">
                        Kleaner
                        <span class="rounded bg-spark px-1.5 py-0.5 text-xs font-extrabold leading-none tracking-wider text-black">OS</span>
                    </span>
                </Link>

                <div class="flex animate-rise flex-col gap-2 text-center lg:text-left">
                    <h1 v-if="title" class="text-2xl font-bold">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <div class="animate-rise [animation-delay:80ms]">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
