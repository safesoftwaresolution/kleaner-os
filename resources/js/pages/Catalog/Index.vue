<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

interface ServiceRow {
    id: number;
    name: string;
    kind: 'main' | 'extra';
    active: boolean;
    prices: Record<string, number>;
}

const props = defineProps<{
    vehicleTypes: { id: number; name: string }[];
    services: ServiceRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Precios', href: '/catalog' }];

const typeForm = useForm({ name: '' });
const serviceForm = useForm({ name: '', kind: 'main' as 'main' | 'extra' });

const addType = () => typeForm.post(route('catalog.vehicle-types.store'), { preserveScroll: true, onSuccess: () => typeForm.reset() });
const addService = () => serviceForm.post(route('catalog.services.store'), { preserveScroll: true, onSuccess: () => serviceForm.reset() });

// Draft price inputs per service. A service's draft is only reset when its
// saved prices change, so unsaved edits in other rows survive a save.
const drafts = reactive<Record<number, Record<number, string>>>({});
const snapshots: Record<number, string> = {};
const priceErrors = reactive<Record<number, string>>({});

const syncDrafts = () => {
    for (const service of props.services) {
        const snapshot = JSON.stringify([service.prices, props.vehicleTypes.map((t) => t.id)]);
        if (snapshots[service.id] === snapshot) continue;
        snapshots[service.id] = snapshot;
        drafts[service.id] = Object.fromEntries(props.vehicleTypes.map((t) => [t.id, service.prices[String(t.id)]?.toString() ?? '']));
    }
};
syncDrafts();
watch(() => [props.services, props.vehicleTypes], syncDrafts, { deep: true });

const savePrices = (service: ServiceRow) => {
    const prices = Object.fromEntries(
        props.vehicleTypes.map((t) => {
            const raw = (drafts[service.id]?.[t.id] ?? '').trim();
            return [t.id, raw === '' ? null : raw];
        }),
    );
    router.put(
        route('catalog.services.prices', service.id),
        { prices },
        {
            preserveScroll: true,
            onSuccess: () => delete priceErrors[service.id],
            onError: (errors) => (priceErrors[service.id] = Object.values(errors)[0] ?? 'No se pudieron guardar los precios.'),
        },
    );
};

const toggleActive = (service: ServiceRow) =>
    router.patch(route('catalog.services.update', service.id), { active: !service.active }, { preserveScroll: true });

</script>

<template>
    <Head title="Precios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Card>
                <CardHeader><CardTitle>Tipos de vehículo</CardTitle></CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <span v-for="type in vehicleTypes" :key="type.id" class="rounded-full border px-3 py-1 text-sm">{{ type.name }}</span>
                        <span v-if="!vehicleTypes.length" class="text-sm text-muted-foreground">Aún no hay tipos de vehículo.</span>
                    </div>
                    <form class="flex gap-2" @submit.prevent="addType">
                        <Input v-model="typeForm.name" placeholder="Nuevo tipo (ej. Camioneta)" maxlength="50" />
                        <Button type="submit" :disabled="typeForm.processing || !typeForm.name.trim()">Agregar</Button>
                    </form>
                    <p v-if="typeForm.errors.name" class="text-xs text-destructive">{{ typeForm.errors.name }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Servicios y precios</CardTitle></CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p class="text-sm text-muted-foreground">
                        Deja un precio vacío si el servicio no se ofrece para ese tipo de vehículo. Cambiar un precio no modifica las órdenes ya
                        registradas.
                    </p>

                    <form class="flex flex-col gap-2 md:flex-row" @submit.prevent="addService">
                        <Input v-model="serviceForm.name" placeholder="Nuevo servicio (ej. Cera)" maxlength="100" />
                        <div class="flex gap-2">
                            <Button type="button" :variant="serviceForm.kind === 'main' ? 'default' : 'outline'" @click="serviceForm.kind = 'main'">
                                Principal
                            </Button>
                            <Button type="button" :variant="serviceForm.kind === 'extra' ? 'default' : 'outline'" @click="serviceForm.kind = 'extra'">
                                Extra
                            </Button>
                            <Button type="submit" :disabled="serviceForm.processing || !serviceForm.name.trim()">Agregar</Button>
                        </div>
                    </form>
                    <p v-if="serviceForm.errors.name || serviceForm.errors.kind" class="text-xs text-destructive">
                        {{ serviceForm.errors.name || serviceForm.errors.kind }}
                    </p>

                    <p v-if="!services.length" class="text-sm text-muted-foreground">Aún no hay servicios.</p>

                    <div
                        v-for="service in services"
                        :key="service.id"
                        class="rounded-lg border p-3"
                        :class="{ 'opacity-60': !service.active }"
                        :data-testid="`service-${service.id}`"
                    >
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold">{{ service.name }}</span>
                                <span class="ml-2 rounded-full bg-muted px-2 text-xs">{{ service.kind === 'main' ? 'Principal' : 'Extra' }}</span>
                                <span v-if="!service.active" class="ml-2 text-xs text-muted-foreground">Desactivado</span>
                            </div>
                            <Button size="sm" variant="outline" @click="toggleActive(service)">
                                {{ service.active ? 'Desactivar' : 'Activar' }}
                            </Button>
                        </div>

                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div v-for="type in vehicleTypes" :key="type.id" class="flex flex-col gap-1">
                                <label :for="`price-${service.id}-${type.id}`" class="text-xs text-muted-foreground">{{ type.name }} (COP)</label>
                                <Input
                                    :id="`price-${service.id}-${type.id}`"
                                    v-model="drafts[service.id][type.id]"
                                    inputmode="numeric"
                                    placeholder="No se ofrece"
                                />
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <p class="text-xs text-destructive">{{ priceErrors[service.id] }}</p>
                            <Button size="sm" @click="savePrices(service)">Guardar precios</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
