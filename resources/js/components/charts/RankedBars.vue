<script setup lang="ts">
import { computed } from 'vue';

/**
 * Part-to-whole as ranked horizontal bars, largest first. Nominal categories all take the
 * same series color: color must not re-encode the length the bar already shows.
 */
const props = defineProps<{
    rows: { name: string; count: number; total: number }[];
    /** which number sets the bar length */
    metric: 'total' | 'count';
    primary: (row: { name: string; count: number; total: number }) => string;
    secondary?: (row: { name: string; count: number; total: number }) => string;
}>();

const max = computed(() => Math.max(1, ...props.rows.map((row) => row[props.metric])));
const width = (row: { count: number; total: number }) => `${Math.max(2, (row[props.metric] / max.value) * 100)}%`;
</script>

<template>
    <ul class="flex flex-col gap-3.5">
        <li v-for="row in rows" :key="row.name">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-medium">{{ row.name }}</span>
                <span class="shrink-0">
                    <strong class="font-display">{{ primary(row) }}</strong>
                    <span v-if="secondary" class="ml-1.5 text-xs text-muted-foreground">{{ secondary(row) }}</span>
                </span>
            </div>
            <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-muted" role="presentation">
                <div class="h-full rounded-full transition-[width] duration-500" :style="{ width: width(row), backgroundColor: 'var(--viz-1)' }" />
            </div>
        </li>
    </ul>
</template>
