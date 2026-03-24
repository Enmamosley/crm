@extends('layouts.admin')
@section('title', 'Editar Cupón')
@section('header', 'Editar Cupón: ' . $discountCode->code)

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.discount-codes.update', $discountCode) }}" method="POST">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
                    <input type="text" name="code" value="{{ old('code', $discountCode->code) }}" required
                        class="w-full border rounded-lg px-3 py-2 uppercase">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <input type="text" name="description" value="{{ old('description', $discountCode->description) }}"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                    <select name="type" class="w-full border rounded-lg px-3 py-2">
                        <option value="percentage" {{ old('type', $discountCode->type) === 'percentage' ? 'selected' : '' }}>Porcentaje (%)</option>
                        <option value="fixed" {{ old('type', $discountCode->type) === 'fixed' ? 'selected' : '' }}>Monto fijo ($)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor *</label>
                    <input type="number" name="value" value="{{ old('value', $discountCode->value) }}" required step="0.01" min="0.01"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto mínimo de compra</label>
                    <input type="number" name="min_amount" value="{{ old('min_amount', $discountCode->min_amount) }}" step="0.01" min="0"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Usos máximos</label>
                    <input type="number" name="max_uses" value="{{ old('max_uses', $discountCode->max_uses) }}" min="1"
                        class="w-full border rounded-lg px-3 py-2" placeholder="Ilimitado">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Válido desde</label>
                    <input type="date" name="valid_from" value="{{ old('valid_from', $discountCode->valid_from?->format('Y-m-d')) }}"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Válido hasta</label>
                    <input type="date" name="valid_until" value="{{ old('valid_until', $discountCode->valid_until?->format('Y-m-d')) }}"
                        class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="mb-4 bg-gray-50 rounded-lg p-3">
                <p class="text-sm text-gray-600"><strong>Usos actuales:</strong> {{ $discountCode->times_used }}</p>
            </div>

            <div class="mb-6">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="active" value="1" {{ old('active', $discountCode->active) ? 'checked' : '' }} class="rounded">
                    <span class="text-sm text-gray-700">Activo</span>
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save mr-1"></i> Actualizar Cupón
                </button>
                <a href="{{ route('admin.discount-codes.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
