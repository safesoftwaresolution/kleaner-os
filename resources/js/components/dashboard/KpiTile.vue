<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Stat tile: label, value and the change versus the previous period of the same length.
 * Direction is carried by an icon and the sign, never by color alone.
 */
const props = defineProps<{
    label: string;
    value: string;
    /** rounded percentage, or null when there is no previous period to compare with */
    delta: number | null;
    periodLabel: string;
    hint?: string;
}>();

const direction = computed(() => (props.delta === null || props.delta === 0 ? 'flat' : props.delta > 0 ? 'up' : 'down'));
const icon = computed(() => ({ up: TrendingUp, down: TrendingDown, flat: Minus })[direction.value]);
const iconClass = computed(() => ({ up: 'text-status-ready', down: 'text-destructive', flat: 'text-muted-foreground' })[direction.value]);
const sign = computed(() => (props.delta !== null && props.delta > 0 ? '+' : props.delta !== null && props.delta < 0 ? '−' : ''));
</script>

<template>
    <Card class="animate-rise">
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">{{ label }}</CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Proportional figures on purpose: tabular digits look loose at display size. -->
            <p class="font-display text-3xl font-extrabold">{{ value }}</p>
            <p class="mt-1.5 flex items-center gap-1.5 text-xs">
                <component :is="icon" class="size-4 shrink-0" :class="iconClass" aria-hidden="true" />
                <span v-if="delta === null" class="text-muted-foreground">Sin periodo anterior para comparar</span>
                <span v-else>
                    <strong>{{ sign }}{{ Math.abs(delta) }}%</strong>
                    <span class="text-muted-foreground"> vs {{ periodLabel }}</span>
                </span>
            </p>
            <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
        </CardContent>
    </Card>
</template>
