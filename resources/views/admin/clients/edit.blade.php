@extends('layouts.admin')
@section('title', 'Editar Cliente')
@section('header', 'Editar: ' . ($client->name ?? $client->legal_name))

@section('content')
<form action="{{ route('admin.clients.update', $client) }}" method="POST" class="max-w-3xl mx-auto space-y-6"
      x-data="{
          billingType: '{{ old('billing_type', $client->billing_type ?? 'fiscal') }}',
          packageId: '{{ old('twentyi_package_id', $client->twentyi_package_id) }}',
          domain: '{{ old('domain', $client->domain) }}',
          loading: false, msg: '', msgType: '',
          async createHosting() {
              if (!this.domain) { this.msgType='error'; this.msg='Ingresa primero el dominio del cliente.'; return; }
              if (this.packageId) { this.msgType='error'; this.msg='Ya hay un Package ID asignado.'; return; }
              if (!confirm('¿Crear paquete de hosting en 20i para ' + this.domain + '?\n\nEsta acción es facturable en tu cuenta de 20i.')) return;
              this.loading = true; this.msg = '';
              try {
                  const res = await fetch('{{ route('admin.clients.create-hosting', $client) }}', {
                      method: 'POST',
                      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                  });
                  const json = await res.json();
                  if (json.success) { this.packageId = json.package_id; this.msgType = 'success'; this.msg = json.message; }
                  else { this.msgType = 'error'; this.msg = json.error || 'Error desconocido.'; }
              } catch (e) { this.msgType = 'error'; this.msg = 'Error de conexión.'; }
              finally { this.loading = false; }
          }
      }">
    @csrf @method('PUT')
    <input type="hidden" name="billing_type" :value="billingType">

    {{-- Información del cliente --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-1">Información del Cliente</h3>
        <p class="text-sm text-gray-400 mb-4">Nombre, contacto y notas generales. Independiente de la facturación.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Nombre <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $client->name) }}" required
                    placeholder="Nombre del cliente o empresa"
                    class="w-full border rounded-lg px-3 py-2 text-sm @error('name') border-red-500 @enderror">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $client->email) }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Teléfono</label>
                <input type="text" name="phone" value="{{ old('phone', $client->phone) }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Notas internas</label>
                <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm">{{ old('notes', $client->notes) }}</textarea>
            </div>
        </div>
    </div>

    {{-- Datos fiscales --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-1">Datos Fiscales</h3>
        <p class="text-sm text-gray-400 mb-4">Información para facturación. Se usa al emitir CFDIs.</p>

        <div class="flex gap-4 mb-5 p-3 bg-gray-50 rounded-lg">
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

        <p x-show="billingType === 'publico_general'" x-cloak class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
            Se usará RFC <strong>XAXX010101000</strong> y régimen <strong>616 – Sin obligaciones fiscales</strong>.
        </p>

        <div x-show="billingType === 'fiscal'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Razón Social <span class="text-red-500">*</span></label>
                <input type="text" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}"
                    :required="billingType === 'fiscal'"
                    placeholder="Nombre fiscal tal como aparece en el SAT"
                    class="w-full border rounded-lg px-3 py-2 text-sm @error('legal_name') border-red-500 @enderror">
                @error('legal_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">RFC <span class="text-red-500">*</span></label>
                <input type="text" name="tax_id" value="{{ old('tax_id', $client->tax_id) }}"
                    :required="billingType === 'fiscal'"
                    maxlength="13" style="text-transform:uppercase"
                    class="w-full border rounded-lg px-3 py-2 text-sm font-mono @error('tax_id') border-red-500 @enderror">
                @error('tax_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Régimen Fiscal <span class="text-red-500">*</span></label>
                <select name="tax_system" :required="billingType === 'fiscal'" class="w-full border rounded-lg px-3 py-2 text-sm">
                    @foreach($taxSystems as $code => $label)
                        <option value="{{ $code }}" {{ old('tax_system', $client->tax_system) === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Uso CFDI por defecto <span class="text-red-500">*</span></label>
                <select name="cfdi_use" :required="billingType === 'fiscal'" class="w-full border rounded-lg px-3 py-2 text-sm">
                    @foreach($cfdiUses as $code => $label)
                        <option value="{{ $code }}" {{ old('cfdi_use', $client->cfdi_use) === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Dirección fiscal --}}
        <div x-show="billingType === 'fiscal'" class="mt-5 pt-4 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Dirección Fiscal</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">CP <span class="text-red-500">*</span></label>
                    <input type="text" name="address_zip" value="{{ old('address_zip', $client->address_zip) }}"
                        :required="billingType === 'fiscal'" maxlength="10"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm text-gray-600 mb-1">Calle</label>
                    <input type="text" name="address_street" value="{{ old('address_street', $client->address_street) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Núm. Exterior</label>
                    <input type="text" name="address_exterior" value="{{ old('address_exterior', $client->address_exterior) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Núm. Interior</label>
                    <input type="text" name="address_interior" value="{{ old('address_interior', $client->address_interior) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Colonia</label>
                    <input type="text" name="address_neighborhood" value="{{ old('address_neighborhood', $client->address_neighborhood) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Ciudad</label>
                    <input type="text" name="address_city" value="{{ old('address_city', $client->address_city) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Municipio</label>
                    <input type="text" name="address_municipality" value="{{ old('address_municipality', $client->address_municipality) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Estado</label>
                    <input type="text" name="address_state" value="{{ old('address_state', $client->address_state) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </div>
    </div>

    {{-- Integración 20i --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-server text-blue-500 mr-1"></i> Integración 20i</h3>

        <div class="flex items-end gap-3">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Package ID de 20i <span class="text-gray-400">(opcional)</span></label>
                <input type="text" name="twentyi_package_id" x-model="packageId"
                    placeholder="ej: 12345"
                    class="w-full md:w-48 border rounded-lg px-3 py-2 text-sm font-mono">
            </div>
            <button type="button" @click="createHosting()"
                :disabled="loading || !!packageId"
                class="flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span x-text="loading ? 'Creando...' : 'Crear hosting en 20i'"></span>
            </button>
        </div>

        <div x-show="msg" x-cloak class="mt-2 text-xs px-3 py-2 rounded-lg"
            :class="msgType === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
            x-text="msg"></div>

        <div class="mt-5 pt-4 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-globe text-indigo-500 mr-1"></i> Dominio del cliente</h4>
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:gap-6">
                <div class="flex-1">
                    <label class="block text-sm text-gray-600 mb-1">Nombre de dominio <span class="text-gray-400">(opcional)</span></label>
                    <input type="text" name="domain" x-model="domain"
                        placeholder="ej: miempresa.com"
                        class="w-full border rounded-lg px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-2">Tipo de dominio</label>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="domain_type" value="cosmotown"
                                {{ old('domain_type', $client->domain_type) === 'cosmotown' ? 'checked' : '' }}
                                class="accent-indigo-600">
                            <span>Registrado vía Cosmotown</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="domain_type" value="own"
                                {{ old('domain_type', $client->domain_type) === 'own' ? 'checked' : '' }}
                                class="accent-indigo-600">
                            <span>Dominio propio del cliente</span>
                        </label>
                    </div>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                <a href="{{ route('admin.domains.index') }}" class="text-indigo-500 hover:underline">Buscar / registrar dominio →</a>
            </p>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Guardar Cambios
        </button>
        <a href="{{ route('admin.clients.show', $client) }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
    </div>
</form>
@endsection
