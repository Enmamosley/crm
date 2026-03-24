@extends('layouts.admin')
@section('title', 'Nueva Categoría')
@section('header', 'Crear Categoría')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.categories.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="3" class="w-full border rounded-lg px-3 py-2">{{ old('description') }}</textarea>
            </div>
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="active" value="1" checked class="mr-2">
                    <span class="text-sm text-gray-700">Activa</span>
                </label>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-1"></i> Guardar</button>
                <a href="{{ route('admin.categories.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
