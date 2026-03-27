<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceBundle;
use Illuminate\Http\Request;

class ServiceBundleController extends Controller
{
    public function index()
    {
        $bundles = ServiceBundle::withCount('items')->with('services')->latest()->get();
        return view('admin.service-bundles.index', compact('bundles'));
    }

    public function create()
    {
        $services = Service::active()->with('category')->orderBy('name')->get();
        return view('admin.service-bundles.create', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'active'      => 'boolean',
            'services'    => 'required|array|min:2',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity'   => 'required|integer|min:1',
        ]);

        $bundle = ServiceBundle::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active'      => $request->boolean('active', true),
        ]);

        foreach ($validated['services'] as $item) {
            $bundle->items()->create([
                'service_id' => $item['service_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return redirect()->route('admin.service-bundles.index')
                         ->with('success', 'Paquete creado correctamente.');
    }

    public function edit(ServiceBundle $serviceBundle)
    {
        $services = Service::active()->with('category')->orderBy('name')->get();
        $serviceBundle->load('items.service');
        return view('admin.service-bundles.edit', compact('serviceBundle', 'services'));
    }

    public function update(Request $request, ServiceBundle $serviceBundle)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'active'      => 'boolean',
            'services'    => 'required|array|min:2',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity'   => 'required|integer|min:1',
        ]);

        $serviceBundle->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active'      => $request->boolean('active', true),
        ]);

        $serviceBundle->items()->delete();

        foreach ($validated['services'] as $item) {
            $serviceBundle->items()->create([
                'service_id' => $item['service_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return redirect()->route('admin.service-bundles.index')
                         ->with('success', 'Paquete actualizado correctamente.');
    }

    public function destroy(ServiceBundle $serviceBundle)
    {
        $serviceBundle->delete();
        return back()->with('success', 'Paquete eliminado.');
    }
}
