<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <AppLogoIcon class="size-8 shrink-0" />
    <div class="ml-1 grid flex-1 text-left leading-none">
        <span class="flex items-center gap-1.5 font-display text-lg font-bold tracking-tight">
            Kleaner
            <span class="rounded bg-spark px-1 py-0.5 text-[10px] font-extrabold leading-none tracking-wider text-black">OS</span>
        </span>
    </div>
</template>
