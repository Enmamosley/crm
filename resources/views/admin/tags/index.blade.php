@extends('layouts.admin')
@section('title', 'Etiquetas')
@section('header', 'Etiquetas de Clientes')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Formulario de nueva etiqueta -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Nueva Etiqueta</h3>
        <form action="{{ route('admin.tags.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full border rounded-lg px-3 py-2" placeholder="Ej: VIP">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Color *</label>
                <input type="color" name="color" value="{{ old('color', '#6366f1') }}" required
                    class="w-full h-10 rounded-lg border cursor-pointer">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 w-full">
                <i class="fas fa-plus mr-1"></i> Crear
            </button>
        </form>
    </div>

    <!-- Lista de etiquetas -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold">Etiquetas ({{ $tags->count() }})</h3>
        </div>
        <div class="divide-y">
            @forelse($tags as $tag)
                <div class="flex items-center justify-between p-4 hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full" style="background-color: {{ $tag->color }}"></span>
                        <div>
                            <p class="font-medium text-gray-800">{{ $tag->name }}</p>
                            <p class="text-xs text-gray-500">{{ $tag->clients_count }} cliente(s)</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <form action="{{ route('admin.tags.update', $tag) }}" method="POST" class="flex items-center gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $tag->name }}" class="border rounded px-2 py-1 text-sm w-24">
                            <input type="color" name="color" value="{{ $tag->color }}" class="w-8 h-8 rounded border cursor-pointer">
                            <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm"><i class="fas fa-save"></i></button>
                        </form>
                        <form action="{{ route('admin.tags.destroy', $tag) }}" method="POST" onsubmit="return confirm('¿Eliminar esta etiqueta?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-gray-500 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-tags text-gray-300 text-3xl mb-2"></i>
                    <p>No hay etiquetas creadas.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
