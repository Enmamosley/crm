@extends('layouts.admin')
@section('title', $service->name)
@section('header', 'Servicio: ' . $service->name)

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-500">Nombre:</span><p class="font-medium">{{ $service->name }}</p></div>
            <div><span class="text-gray-500">Categoría:</span><p class="font-medium">{{ $service->category->name ?? '-' }}</p></div>
            <div><span class="text-gray-500">Precio:</span><p class="font-medium text-lg">${{ number_format($service->price, 2) }}</p></div>
            <div><span class="text-gray-500">Estado:</span><p class="font-medium">{{ $service->active ? 'Activo' : 'Inactivo' }}</p></div>
        </div>
        @if($service->description)
            <div class="mt-4 pt-4 border-t">
                <span class="text-gray-500 text-sm">Descripción:</span>
                <p class="mt-1">{{ $service->description }}</p>
            </div>
        @endif

        <div class="mt-6 flex gap-3">
            <a href="{{ route('admin.services.edit', $service) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><i class="fas fa-edit mr-1"></i> Editar</a>
            <a href="{{ route('admin.services.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Volver</a>
        </div>
    </div>
</div>
@endsection
