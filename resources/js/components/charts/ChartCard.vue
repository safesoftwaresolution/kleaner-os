<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table2, ChartColumn } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Container for one chart. Every chart ships its table twin (the accessible equivalent),
 * reachable from the toggle in the header.
 */
defineProps<{
    title: string;
    description?: string;
    table: { columns: string[]; rows: (string | number)[][] };
}>();

const showTable = ref(false);
</script>

<template>
    <Card class="min-w-0">
        <CardHeader class="flex-row items-start justify-between gap-3 space-y-0 pb-2">
            <div class="min-w-0">
                <CardTitle class="text-base">{{ title }}</CardTitle>
                <p v-if="description" class="mt-1 text-xs text-muted-foreground">{{ description }}</p>
            </div>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                class="shrink-0 text-muted-foreground"
                :aria-pressed="showTable"
                :aria-label="showTable ? `Ver gráfica: ${title}` : `Ver tabla: ${title}`"
                @click="showTable = !showTable"
            >
                <component :is="showTable ? ChartColumn : Table2" class="size-4" />
                <span class="hidden sm:inline">{{ showTable ? 'Gráfica' : 'Tabla' }}</span>
            </Button>
        </CardHeader>
        <CardContent>
            <div v-if="!showTable"><slot /></div>
            <div v-else class="max-h-64 overflow-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                        <tr>
                            <th v-for="column in table.columns" :key="column" scope="col" class="px-3 py-2 font-medium">{{ column }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in table.rows" :key="i" class="border-t">
                            <td v-for="(cell, j) in row" :key="j" class="px-3 py-1.5" :class="{ 'tabular-nums': j > 0 }">{{ cell }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
