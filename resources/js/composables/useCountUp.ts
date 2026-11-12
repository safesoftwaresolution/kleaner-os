import { onBeforeUnmount, ref, watch, type Ref } from 'vue';

/**
 * Animates a number toward its latest value. Skips the animation entirely
 * when the user prefers reduced motion or the value starts at its target.
 */
export function useCountUp(target: Ref<number> | (() => number), duration = 700) {
    const read = typeof target === 'function' ? target : () => target.value;
    const display = ref(read());
    let frame = 0;

    const reduce = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    watch(
        read,
        (to) => {
            cancelAnimationFrame(frame);
            const from = display.value;
            if (reduce || from === to) {
                display.value = to;
                return;
            }
            const start = performance.now();
            const tick = (now: number) => {
                const t = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                display.value = Math.round(from + (to - from) * eased);
                if (t < 1) frame = requestAnimationFrame(tick);
            };
            frame = requestAnimationFrame(tick);
        },
        { immediate: false },
    );

    onBeforeUnmount(() => cancelAnimationFrame(frame));

    return display;
}
