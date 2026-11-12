<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- Kleaner OS mark: a water drop with a spark. Keep in sync with public/favicon.svg. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" :class="className" role="img" aria-label="Kleaner OS" v-bind="$attrs">
        <defs>
            <linearGradient id="kleaner-drop" x1="32" y1="8" x2="32" y2="58" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#67E8F9" />
                <stop offset="1" stop-color="#0284C7" />
            </linearGradient>
        </defs>
        <path d="M32 9C32 9 15 28 15 40a17 17 0 0 0 34 0C49 28 32 9 32 9Z" fill="url(#kleaner-drop)" />
        <path d="M32 29l3 7.5 7.5 3-7.5 3-3 7.5-3-7.5-7.5-3 7.5-3z" fill="#FACC15" />
    </svg>
</template>
