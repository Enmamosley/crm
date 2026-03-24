@extends('layouts.admin')
@section('title', 'Categorías')
@section('header', 'Categorías de Servicios')

@section('actions')
<a href="{{ route('admin.categories.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nueva Categoría
</a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Nombre</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Servicios</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($categories as $cat)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium">{{ $cat->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $cat->slug }}</td>
                    <td class="px-6 py-4 text-sm">{{ $cat->services_count }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full {{ $cat->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $cat->active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.categories.edit', $cat) }}" class="text-gray-500 hover:text-blue-600 mr-3"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                            @csrf @method('DELETE')
                            <button class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">No hay categorías.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t">{{ $categories->links() }}</div>
</div>
@endsection
