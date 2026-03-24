@extends('layouts.admin')
@section('title', 'Nuevo Lead')
@section('header', 'Crear Nuevo Lead')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.leads.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Negocio</label>
                    <input type="text" name="business" value="{{ old('business') }}"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Origen</label>
                <select name="source" class="w-full border rounded-lg px-3 py-2">
                    <option value="manual">Manual</option>
                    <option value="web">Web</option>
                    <option value="agente">Agente</option>
                    <option value="referido">Referido</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a vendedor</label>
                    <select name="assigned_to" class="w-full border rounded-lg px-3 py-2">
                        <option value="">Sin asignar</option>
                        @foreach($salesUsers as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor estimado ($)</label>
                    <input type="number" name="estimated_value" value="{{ old('estimated_value') }}" step="0.01" min="0"
                        class="w-full border rounded-lg px-3 py-2" placeholder="0.00">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción del Proyecto</label>
                <textarea name="project_description" rows="4" class="w-full border rounded-lg px-3 py-2">{{ old('project_description') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save mr-1"></i> Guardar Lead
                </button>
                <a href="{{ route('admin.leads.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
