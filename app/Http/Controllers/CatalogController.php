<?php

namespace App\Http\Controllers;

use App\Enums\ServiceKind;
use App\Models\Service;
use App\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Catalog/Index', [
            'vehicleTypes' => VehicleType::orderBy('id')->get(['id', 'name']),
            'services' => Service::with('prices')
                ->orderBy('id')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'kind' => $service->kind->value,
                    'active' => $service->active,
                    'prices' => $service->prices->pluck('price', 'vehicle_type_id'),
                ]),
        ]);
    }

    public function storeVehicleType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('vehicle_types', 'name')
                ->where('business_id', $request->user()->business_id)],
        ]);

        VehicleType::create($data);

        return back();
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('services', 'name')
                ->where('business_id', $request->user()->business_id)],
            'kind' => ['required', Rule::enum(ServiceKind::class)],
        ]);

        Service::create($data);

        return back();
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('services', 'name')
                ->where('business_id', $request->user()->business_id)
                ->ignore($service->id)],
            'active' => ['sometimes', 'boolean'],
        ]);

        $service->update($data);

        return back();
    }

    /**
     * Replaces the prices of one service. A null or missing price removes the
     * service from that vehicle type. Orders already registered keep the
     * price they were charged.
     */
    public function updatePrices(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'prices' => ['present', 'array'],
            'prices.*' => ['nullable', 'integer', 'min:1', 'max:10000000'],
        ], [
            'prices.*.integer' => 'Escribe los precios como números enteros, sin puntos ni comas.',
            'prices.*.min' => 'El precio debe ser mayor que cero. Déjalo vacío si no se ofrece.',
            'prices.*.max' => 'El precio es demasiado alto.',
        ]);

        $typeIds = VehicleType::pluck('id')->all();

        // Keys must be canonical ids ("1", not "01", " 1" or "1abc") of this business's types.
        foreach (array_keys($data['prices']) as $key) {
            $canonical = ctype_digit((string) $key) && (string) (int) $key === (string) $key;

            if (! $canonical || ! in_array((int) $key, $typeIds, true)) {
                throw ValidationException::withMessages(['prices' => 'Tipo de vehículo inválido.']);
            }
        }

        DB::transaction(function () use ($service, $data) {
            foreach ($data['prices'] as $typeId => $price) {
                if ($price === null || $price === '') {
                    $service->prices()->where('vehicle_type_id', $typeId)->delete();

                    continue;
                }

                $service->prices()->updateOrCreate(
                    ['vehicle_type_id' => $typeId],
                    ['price' => (int) $price, 'business_id' => $service->business_id],
                );
            }
        });

        return back();
    }
}
