<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::with('category')->paginate(15);
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        $categories = ServiceCategory::active()->get();
        return view('admin.services.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:services,slug',
            'description' => 'nullable|string',
            'info_url'    => 'nullable|url|max:500',
            'price' => 'required|numeric|min:0',
            'active'          => 'boolean',
            'public'          => 'boolean',
            'requires_domain' => 'boolean',
            'twentyi_package_bundle_id' => 'nullable|string|max:50',
            // Campos fiscales SAT
            'sat_product_key' => 'nullable|string|max:10',
            'sat_unit_key'    => 'nullable|string|max:10',
            'sat_unit_name'   => 'nullable|string|max:50',
            'tax_object'      => 'nullable|string|in:01,02,03',
            'iva_exempt'      => 'boolean',
        ]);

        $validated['active']          = $request->boolean('active', true);
        $validated['public']          = $request->boolean('public', false);
        $validated['requires_domain'] = $request->boolean('requires_domain', false);
        $validated['iva_exempt']      = $request->boolean('iva_exempt', false);

        Service::create($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'Servicio creado exitosamente.');
    }

    public function edit(Service $service)
    {
        $categories = ServiceCategory::active()->get();
        return view('admin.services.edit', compact('service', 'categories'));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'service_category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:services,slug,' . $service->id,
            'description' => 'nullable|string',
            'info_url'    => 'nullable|url|max:500',
            'price' => 'required|numeric|min:0',
            'active'          => 'boolean',
            'public'          => 'boolean',
            'requires_domain' => 'boolean',
            'twentyi_package_bundle_id' => 'nullable|string|max:50',
            // Campos fiscales SAT
            'sat_product_key' => 'nullable|string|max:10',
            'sat_unit_key'    => 'nullable|string|max:10',
            'sat_unit_name'   => 'nullable|string|max:50',
            'tax_object'      => 'nullable|string|in:01,02,03',
            'iva_exempt'      => 'boolean',
        ]);

        $validated['active']          = $request->boolean('active', true);
        $validated['public']          = $request->boolean('public', false);
        $validated['requires_domain'] = $request->boolean('requires_domain', false);
        $validated['iva_exempt']      = $request->boolean('iva_exempt', false);

        $service->update($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'Servicio actualizado.');
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return redirect()->route('admin.services.index')
            ->with('success', 'Servicio eliminado.');
    }
}
