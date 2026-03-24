@extends('layouts.admin')
@section('title', $category->name)
@section('header', 'Categoría: ' . $category->name)

@section('content')
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-500">Nombre:</span><p class="font-medium">{{ $category->name }}</p></div>
        <div><span class="text-gray-500">Slug:</span><p class="font-medium">{{ $category->slug }}</p></div>
        <div><span class="text-gray-500">Estado:</span><p class="font-medium">{{ $category->active ? 'Activa' : 'Inactiva' }}</p></div>
        <div><span class="text-gray-500">Descripción:</span><p class="font-medium">{{ $category->description ?? '-' }}</p></div>
    </div>
</div>

<h3 class="text-lg font-semibold mb-4">Servicios en esta categoría</h3>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Servicio</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Precio</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($category->services as $service)
                <tr>
                    <td class="px-6 py-4">{{ $service->name }}</td>
                    <td class="px-6 py-4">${{ number_format($service->price, 2) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full {{ $service->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $service->active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Sin servicios.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
