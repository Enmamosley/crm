@extends('layouts.admin')
@section('title', 'Servicios')
@section('header', 'Catálogo de Servicios')

@section('actions')
<a href="{{ route('admin.services.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nuevo Servicio
</a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Servicio</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Categoría</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Precio</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($services as $service)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-medium">{{ $service->name }}</p>
                        @if($service->description)
                            <p class="text-xs text-gray-500 mt-1">{{ Str::limit($service->description, 60) }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">{{ $service->category->name ?? '-' }}</td>
                    <td class="px-6 py-4 font-semibold">${{ number_format($service->price, 2) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full {{ $service->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $service->active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.services.edit', $service) }}" class="text-gray-500 hover:text-blue-600 mr-3"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.services.destroy', $service) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                            @csrf @method('DELETE')
                            <button class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">No hay servicios.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t">{{ $services->links() }}</div>
</div>
@endsection
