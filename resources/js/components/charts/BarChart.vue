<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';

export interface Series {
    key: string;
    label: string;
    values: number[];
    /** CSS color, normally a validated token such as var(--viz-1). */
    color: string;
}

/**
 * Column chart, single series or stacked. Specs follow the dataviz skill:
 * bars are at most 24px thick, rounded 4px at the data end and square on the baseline,
 * stacked segments are separated by a 2px surface gap, grid and axes are hairlines,
 * and a legend appears from two series up. Hover (or arrow keys) shows one tooltip with
 * every series at that position; the table view in ChartCard is the accessible twin.
 */
const props = withDefaults(
    defineProps<{
        /** x tick text per column (may be thinned automatically) */
        labels: string[];
        /** tooltip heading per column */
        titles: string[];
        series: Series[];
        stacked?: boolean;
        /** full value text, used in tooltips */
        format: (value: number) => string;
        /** compact text for y ticks; falls back to format */
        tickFormat?: (value: number) => string;
        /** whole-number scale (counts) */
        integer?: boolean;
        height?: number;
        minLabelWidth?: number;
        ariaLabel: string;
        /** change it to replay the entrance animation when the data set changes */
        dataKey?: string | number;
    }>(),
    { stacked: false, integer: false, height: 220, minLabelWidth: 60, dataKey: 0 },
);

const wrap = ref<HTMLElement | null>(null);
const { width } = useElementSize(wrap);
const active = ref<number | null>(null);

const margin = { top: 8, right: 8, bottom: 26 };
const GAP = 2;
const MAX_BAR = 24;

const n = computed(() => props.labels.length);
const plotW = computed(() => Math.max(0, width.value - left.value - margin.right));
const plotH = computed(() => props.height - margin.top - margin.bottom);
const band = computed(() => (n.value ? plotW.value / n.value : 0));
const baseline = computed(() => margin.top + plotH.value);

const totals = computed(() =>
    props.labels.map((_, i) =>
        props.stacked ? props.series.reduce((sum, s) => sum + (s.values[i] ?? 0), 0) : Math.max(0, ...props.series.map((s) => s.values[i] ?? 0)),
    ),
);

/** A clean 1/2/5 scale with about four ticks. */
const scale = computed(() => {
    const max = Math.max(1, ...totals.value);
    const rough = max / 4;
    const pow = 10 ** Math.floor(Math.log10(rough));
    const frac = rough / pow;
    let step = (frac <= 1 ? 1 : frac <= 2 ? 2 : frac <= 5 ? 5 : 10) * pow;
    if (props.integer) step = Math.max(1, Math.ceil(step));
    const top = Math.ceil(max / step) * step;
    const ticks: number[] = [];
    for (let v = 0; v <= top + step / 1000; v += step) ticks.push(v);
    return { top, ticks };
});

/** Room for the widest tick label (about 6.6px per character at 12px), so long labels never clip. */
const left = computed(() => {
    const format = props.tickFormat ?? props.format;
    const longest = Math.max(...scale.value.ticks.map((tick) => format(tick).length));
    return Math.max(36, Math.round(12 + longest * 6.6));
});

const yOf = (value: number) => baseline.value - (value / scale.value.top) * plotH.value;

const barW = computed(() => Math.max(2, Math.min(MAX_BAR, band.value * 0.72)));

/** Rounded at the top only: the base sits square on the baseline. */
const barPath = (x: number, y: number, w: number, h: number, radius: number) => {
    const r = Math.min(radius, w / 2, h);
    return r <= 0
        ? `M${x},${y}h${w}v${h}h${-w}Z`
        : `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${y + h}Z`;
};

const columns = computed(() =>
    props.labels.map((_, i) => {
        const x = left.value + band.value * i + (band.value - barW.value) / 2;
        const topSeries = props.stacked ? props.series.reduce((last, s, k) => ((s.values[i] ?? 0) > 0 ? k : last), -1) : 0;
        let cumulative = 0;

        const segments = props.series.flatMap((s, k) => {
            const value = s.values[i] ?? 0;
            if (value <= 0) return [];
            const below = props.stacked ? cumulative : 0;
            if (props.stacked) cumulative += value;
            const yTop = yOf(below + value);
            const height = Math.max(2, baseline.value - yOf(value) - (below > 0 ? GAP : 0));
            return [{ key: s.key, color: s.color, d: barPath(x, yTop, barW.value, height, k === topSeries ? 4 : 0) }];
        });

        return { i, segments };
    }),
);

const labelStep = computed(() => Math.max(1, Math.ceil(props.minLabelWidth / Math.max(1, band.value))));

const onPointer = (event: PointerEvent) => {
    const rect = (event.currentTarget as SVGElement).getBoundingClientRect();
    const x = event.clientX - rect.left - left.value;
    active.value = x < 0 || x > plotW.value || !n.value ? null : Math.min(n.value - 1, Math.max(0, Math.floor(x / band.value)));
};

const onKey = (event: KeyboardEvent) => {
    const last = n.value - 1;
    const current = active.value ?? last;
    const moves: Record<string, number> = { ArrowRight: Math.min(last, current + 1), ArrowLeft: Math.max(0, current - 1), Home: 0, End: last };

    if (event.key in moves) {
        event.preventDefault();
        active.value = moves[event.key];
    } else if (event.key === 'Escape') {
        active.value = null;
    }
};

const TIP_W = 176;
const tipLeft = computed(() => {
    if (active.value === null) return 0;
    const center = left.value + band.value * (active.value + 0.5);
    const x = center > width.value / 2 ? center - TIP_W - 14 : center + 14;
    return Math.max(0, Math.min(width.value - TIP_W, x));
});
</script>

<template>
    <div>
        <ul v-if="series.length > 1" class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground" aria-label="Leyenda">
            <li v-for="s in series" :key="s.key" class="flex items-center gap-1.5">
                <span class="inline-block size-2.5 rounded-[3px]" :style="{ backgroundColor: s.color }" />
                {{ s.label }}
            </li>
        </ul>

        <div ref="wrap" class="relative w-full" :style="{ height: `${height}px` }">
            <svg
                v-if="width > 0"
                :width="width"
                :height="height"
                role="group"
                aria-roledescription="gráfica de barras"
                :aria-label="`${ariaLabel}. Usa las flechas del teclado para recorrer los valores.`"
                tabindex="0"
                class="block touch-pan-y rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @pointerdown="onPointer"
                @pointermove="onPointer"
                @pointerleave="active = null"
                @keydown="onKey"
                @focus="active = active ?? n - 1"
                @blur="active = null"
            >
                <g aria-hidden="true">
                    <template v-for="tick in scale.ticks" :key="tick">
                        <line :x1="left" :x2="width - margin.right" :y1="yOf(tick)" :y2="yOf(tick)" style="stroke: hsl(var(--border))" stroke-width="1" />
                        <text :x="left - 8" :y="yOf(tick) + 4" text-anchor="end" font-size="12" style="fill: hsl(var(--muted-foreground))">
                            {{ (tickFormat ?? format)(tick) }}
                        </text>
                    </template>

                    <rect
                        v-if="active !== null"
                        :x="left + band * active"
                        :y="margin.top"
                        :width="band"
                        :height="plotH"
                        rx="4"
                        style="fill: hsl(var(--muted))"
                    />

                    <g :key="dataKey">
                        <g v-for="column in columns" :key="column.i" :opacity="active === null || active === column.i ? 1 : 0.55">
                            <path
                                v-for="segment in column.segments"
                                :key="segment.key"
                                class="bar"
                                :d="segment.d"
                                :style="{ fill: segment.color, animationDelay: `${Math.min(column.i, 40) * 10}ms` }"
                            />
                        </g>
                    </g>

                    <template v-for="(label, i) in labels" :key="`x-${i}`">
                        <text
                            v-if="i % labelStep === 0"
                            :x="left + band * (i + 0.5)"
                            :y="height - 6"
                            text-anchor="middle"
                            font-size="12"
                            style="fill: hsl(var(--muted-foreground))"
                        >
                            {{ label }}
                        </text>
                    </template>
                </g>
            </svg>

            <div
                v-if="active !== null"
                role="status"
                class="pointer-events-none absolute top-0 z-10 rounded-lg border bg-popover p-2.5 text-popover-foreground shadow-md"
                :style="{ left: `${tipLeft}px`, width: `${TIP_W}px` }"
            >
                <p class="mb-1 text-xs font-semibold">{{ titles[active] }}</p>
                <p v-for="s in series" :key="s.key" class="flex items-center gap-2 text-xs">
                    <span class="inline-block h-0.5 w-3 shrink-0 rounded" :style="{ backgroundColor: s.color }" />
                    <strong class="font-display text-sm">{{ format(s.values[active] ?? 0) }}</strong>
                    <span class="truncate text-muted-foreground">{{ s.label }}</span>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bar {
    transform-box: fill-box;
    transform-origin: 50% 100%;
    animation: bar-grow 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

@keyframes bar-grow {
    from {
        transform: scaleY(0);
    }
    to {
        transform: scaleY(1);
    }
}
</style>
