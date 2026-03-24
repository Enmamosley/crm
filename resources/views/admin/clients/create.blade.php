@extends('layouts.admin')
@section('title', 'Nuevo Cliente')
@section('header', 'Nuevo Cliente')

@section('content')
<form action="{{ route('admin.clients.store') }}" method="POST" class="max-w-3xl mx-auto space-y-6"
      x-data="{ billingType: '{{ old('billing_type', 'fiscal') }}' }">
    @csrf
    <input type="hidden" name="billing_type" :value="billingType">

    {{-- Lead vinculado --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Lead Vinculado</h3>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Lead <span class="text-red-500">*</span></label>
            <select name="lead_id" required class="w-full border rounded-lg px-3 py-2 text-sm @error('lead_id') border-red-500 @enderror">
                <option value="">Selecciona un lead...</option>
                @foreach($leads as $l)
                    <option value="{{ $l->id }}" {{ (old('lead_id', $lead?->id) == $l->id) ? 'selected' : '' }}>
                        {{ $l->name }} {{ $l->business ? "— {$l->business}" : '' }}
                    </option>
                @endforeach
            </select>
            @error('lead_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Tipo de facturación --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Tipo de Facturación</h3>
        <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="_billing_type_radio" value="fiscal"
                    :checked="billingType === 'fiscal'"
                    @change="billingType = 'fiscal'"
                    class="accent-blue-600">
                <span class="text-sm font-medium text-gray-700">Persona con RFC (fiscal)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="_billing_type_radio" value="publico_general"
                    :checked="billingType === 'publico_general'"
                    @change="billingType = 'publico_general'"
                    class="accent-blue-600">
                <span class="text-sm font-medium text-gray-700">Público en General</span>
            </label>
        </div>
        <p x-show="billingType === 'publico_general'" class="mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
            Se usará RFC <strong>XAXX010101000</strong> y régimen <strong>616 – Sin obligaciones fiscales</strong>. No se requieren datos fiscales detallados.
        </p>
    </div>

    {{-- Datos fiscales (solo para RFC) --}}
    <div class="bg-white rounded-lg shadow p-6" x-show="billingType === 'fiscal'">
        <h3 class="text-lg font-semibold mb-4">Datos Fiscales</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Nombre / Razón Social <span class="text-red-500">*</span></label>
                <input type="text" name="legal_name" value="{{ old('legal_name') }}"
                    :required="billingType === 'fiscal'"
                    class="w-full border rounded-lg px-3 py-2 text-sm @error('legal_name') border-red-500 @enderror">
                @error('legal_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">RFC <span class="text-red-500">*</span></label>
                <input type="text" name="tax_id" value="{{ old('tax_id') }}"
                    :required="billingType === 'fiscal'"
                    maxlength="13" style="text-transform:uppercase"
                    class="w-full border rounded-lg px-3 py-2 text-sm font-mono @error('tax_id') border-red-500 @enderror">
                @error('tax_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Régimen Fiscal <span class="text-red-500">*</span></label>
                <select name="tax_system" :required="billingType === 'fiscal'" class="w-full border rounded-lg px-3 py-2 text-sm">
                    @foreach($taxSystems as $code => $label)
                        <option value="{{ $code }}" {{ old('tax_system', '626') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Uso CFDI por defecto <span class="text-red-500">*</span></label>
                <select name="cfdi_use" :required="billingType === 'fiscal'" class="w-full border rounded-lg px-3 py-2 text-sm">
                    @foreach($cfdiUses as $code => $label)
                        <option value="{{ $code }}" {{ old('cfdi_use', 'G03') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $lead?->email) }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Teléfono</label>
                <input type="text" name="phone" value="{{ old('phone', $lead?->phone) }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    {{-- Email / Teléfono para público en general --}}
    <div class="bg-white rounded-lg shadow p-6" x-show="billingType === 'publico_general'">
        <h3 class="text-lg font-semibold mb-4">Contacto</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Email</label>
                <input type="email" name="email_publico" value="{{ old('email', $lead?->email) }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm"
                    x-on:input="document.querySelector('[name=email]').value = $event.target.value">
                <p class="text-xs text-gray-400 mt-1">Opcional, para envío de comprobantes.</p>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Teléfono</label>
                <input type="text" name="phone_publico" value="{{ old('phone', $lead?->phone) }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm"
                    x-on:input="document.querySelector('[name=phone]').value = $event.target.value">
            </div>
        </div>
    </div>

    {{-- Dirección fiscal --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Dirección Fiscal</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">CP <span class="text-red-500">*</span></label>
                <input type="text" name="address_zip" value="{{ old('address_zip') }}" required maxlength="10"
                    class="w-full border rounded-lg px-3 py-2 text-sm @error('address_zip') border-red-500 @enderror">
            </div>
            <div class="col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Calle</label>
                <input type="text" name="address_street" value="{{ old('address_street') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Núm. Exterior</label>
                <input type="text" name="address_exterior" value="{{ old('address_exterior') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Núm. Interior</label>
                <input type="text" name="address_interior" value="{{ old('address_interior') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Colonia</label>
                <input type="text" name="address_neighborhood" value="{{ old('address_neighborhood') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Ciudad</label>
                <input type="text" name="address_city" value="{{ old('address_city') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Municipio</label>
                <input type="text" name="address_municipality" value="{{ old('address_municipality') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Estado</label>
                <input type="text" name="address_state" value="{{ old('address_state') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <label class="block text-sm text-gray-600 mb-1">Notas internas</label>
        <textarea name="notes" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-server text-blue-500 mr-1"></i> Integración 20i</h3>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Package ID de 20i <span class="text-gray-400">(opcional)</span></label>
            <input type="text" name="twentyi_package_id" value="{{ old('twentyi_package_id') }}"
                placeholder="ej: 12345"
                class="w-full md:w-64 border rounded-lg px-3 py-2 text-sm font-mono">
            <p class="text-xs text-gray-400 mt-1">ID del paquete de hosting en 20i. Lo puedes ver en el panel de 20i.</p>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Crear Cliente
        </button>
        <a href="{{ route('admin.clients.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
    </div>
</form>
@endsection
