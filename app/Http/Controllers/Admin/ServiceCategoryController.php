<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::withCount('services')->paginate(15);
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['active'] = $request->boolean('active', true);

        ServiceCategory::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Categoría creada exitosamente.');
    }

    public function show(ServiceCategory $category)
    {
        $category->load('services');
        return view('admin.categories.show', compact('category'));
    }

    public function edit(ServiceCategory $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, ServiceCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['active'] = $request->boolean('active', true);

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Categoría actualizada.');
    }

    public function destroy(ServiceCategory $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')
            ->with('success', 'Categoría eliminada.');
    }
}
