@extends('layouts.admin')
@section('title', 'Editar Paquete')
@section('header', 'Editar Paquete: ' . $serviceBundle->name)

@section('content')
<form action="{{ route('admin.service-bundles.update', $serviceBundle) }}" method="POST" id="bundleForm">
    @csrf @method('PUT')
    <div class="max-w-2xl space-y-6">

        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <h3 class="text-base font-semibold text-gray-800">Datos del Paquete</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name', $serviceBundle->name) }}" required
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="2"
                          class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400">{{ old('description', $serviceBundle->description) }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" id="active"
                       {{ $serviceBundle->active ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="active" class="text-sm text-gray-700">Paquete activo (visible en cotizaciones)</label>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-gray-800">Servicios incluidos <span class="text-xs text-gray-400 font-normal">(mínimo 2)</span></h3>
                <button type="button" onclick="addServiceRow()"
                        class="inline-flex items-center text-sm bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus mr-1"></i> Añadir servicio
                </button>
            </div>

            <div id="services-container" class="space-y-3">
                @foreach($serviceBundle->items as $i => $item)
                <div class="service-row flex items-center gap-3" data-index="{{ $i }}">
                    <div class="flex-1">
                        <select name="services[{{ $i }}][service_id]" required
                                class="w-full border rounded-lg px-2 py-2 text-sm">
                            <option value="">Seleccionar servicio...</option>
                            @foreach($services as $service)
                            <option value="{{ $service->id }}" {{ $service->id == $item->service_id ? 'selected' : '' }}>
                                [{{ $service->category->name ?? '—' }}] {{ $service->name }} — ${{ number_format($service->price, 2) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <input type="number" name="services[{{ $i }}][quantity]" value="{{ $item->quantity }}" min="1" required
                               class="w-full border rounded-lg px-2 py-2 text-sm text-center" title="Cantidad">
                    </div>
                    <button type="button" onclick="removeServiceRow(this)" class="text-red-400 hover:text-red-600 p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                @endforeach
            </div>

            @error('services') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium text-sm">
                <i class="fas fa-save mr-1"></i> Guardar Cambios
            </button>
            <a href="{{ route('admin.service-bundles.index') }}"
               class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-300 text-sm">Cancelar</a>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const servicesOptions = `@foreach($services as $service)<option value="{{ $service->id }}">[{{ addslashes($service->category->name ?? '—') }}] {{ addslashes($service->name) }} — ${{ number_format($service->price, 2) }}</option>@endforeach`;
    let rowIndex = {{ $serviceBundle->items->count() }};

    function addServiceRow() {
        const container = document.getElementById('services-container');
        const div = document.createElement('div');
        div.className = 'service-row flex items-center gap-3';
        div.dataset.index = rowIndex;
        div.innerHTML = `
            <div class="flex-1">
                <select name="services[${rowIndex}][service_id]" required class="w-full border rounded-lg px-2 py-2 text-sm">
                    <option value="">Seleccionar servicio...</option>
                    ${servicesOptions}
                </select>
            </div>
            <div class="w-24">
                <input type="number" name="services[${rowIndex}][quantity]" value="1" min="1" required
                       class="w-full border rounded-lg px-2 py-2 text-sm text-center" title="Cantidad">
            </div>
            <button type="button" onclick="removeServiceRow(this)" class="text-red-400 hover:text-red-600 p-1">
                <i class="fas fa-times"></i>
            </button>`;
        container.appendChild(div);
        rowIndex++;
    }

    function removeServiceRow(btn) {
        const container = document.getElementById('services-container');
        if (container.children.length > 2) {
            btn.closest('.service-row').remove();
        } else {
            alert('Un paquete debe tener al menos 2 servicios.');
        }
    }
</script>
@endpush
@endsection
