@extends('layouts.admin')
@section('title', 'Nuevo Servicio')
@section('header', 'Crear Servicio')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.services.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría *</label>
                <select name="service_category_id" required class="w-full border rounded-lg px-3 py-2">
                    <option value="">Seleccionar...</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('service_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug (URL)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="se-genera-automaticamente" class="w-full border rounded-lg px-3 py-2">
                <p class="text-xs text-gray-400 mt-1">Deja vacío para generar automáticamente. Se usa en la URL de compra directa: /buy/slug</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="3" class="w-full border rounded-lg px-3 py-2">{{ old('description') }}</textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio base *</label>
                <input type="number" name="price" value="{{ old('price') }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="active" value="1" checked class="mr-2">
                    <span class="text-sm text-gray-700">Activo</span>
                </label>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="public" value="1" {{ old('public') ? 'checked' : '' }} class="mr-2">
                    <span class="text-sm text-gray-700">Público (compra directa desde tu sitio web)</span>
                </label>
            </div>
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="requires_domain" value="1" {{ old('requires_domain') ? 'checked' : '' }} class="mr-2">
                    <span class="text-sm text-gray-700">Requiere dominio <span class="text-gray-400 font-normal">(el cliente elige o registra un dominio al comprar)</span></span>
                </label>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Package Bundle ID de 20i
                    <span class="text-gray-400 font-normal">(opcional &mdash; activa hosting autom&aacute;ticamente al comprar)</span>
                </label>
                <div class="flex gap-2">
                    <input type="text" name="twentyi_package_bundle_id" id="bundle-id-input"
                        value="{{ old('twentyi_package_bundle_id') }}"
                        placeholder="ej: 12345"
                        class="flex-1 border rounded-lg px-3 py-2 font-mono text-sm">
                    <button type="button" id="btn-load-bundles"
                        class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg text-gray-700 whitespace-nowrap transition">
                        <i class="fas fa-sync-alt mr-1"></i>Ver disponibles
                    </button>
                </div>
                <div id="bundle-list" class="mt-2 hidden">
                    <p class="text-xs text-gray-500 mb-1">Haz clic en un tipo para seleccionarlo:</p>
                    <div id="bundle-items" class="flex flex-wrap gap-2"></div>
                </div>
                <div id="bundle-error" class="mt-1 hidden text-xs text-red-500"></div>
                <p class="text-xs text-gray-400 mt-1">Si se asigna un bundle, cuando el cliente pague este servicio se crear&aacute; el paquete de hosting en 20i autom&aacute;ticamente.</p>
            </div>

            {{-- ─── DATOS FISCALES SAT ─── --}}
            <div class="mb-6 border-t pt-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-file-invoice text-indigo-500"></i>
                    Datos fiscales SAT (para timbrado en FacturAPI)
                </h3>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Clave SAT del producto/servicio
                            <a href="https://facturapi.io/catcfdi/c_ClaveProdServ" target="_blank" class="text-indigo-500 hover:underline ml-1">Buscar</a>
                        </label>
                        <input type="text" name="sat_product_key"
                            value="{{ old('sat_product_key', '80101501') }}"
                            placeholder="ej: 81161500" maxlength="10"
                            class="w-full border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">c_ClaveProdServ SAT. Default: 80101501 (Consultoría profesional)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Objeto de impuesto</label>
                        <select name="tax_object" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="02" {{ old('tax_object','02') === '02' ? 'selected' : '' }}>02 — Sí objeto de impuesto (normal)</option>
                            <option value="01" {{ old('tax_object') === '01' ? 'selected' : '' }}>01 — No objeto de impuesto</option>
                            <option value="03" {{ old('tax_object') === '03' ? 'selected' : '' }}>03 — Sí objeto, no obligado retención</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Clave unidad SAT
                            <a href="https://facturapi.io/catcfdi/c_ClaveUnidad" target="_blank" class="text-indigo-500 hover:underline ml-1">Buscar</a>
                        </label>
                        <input type="text" name="sat_unit_key"
                            value="{{ old('sat_unit_key', 'E48') }}"
                            placeholder="ej: E48" maxlength="10"
                            class="w-full border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">E48=Servicio · H87=Pieza · MTK=m²</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nombre de unidad (PDF factura)</label>
                        <input type="text" name="sat_unit_name"
                            value="{{ old('sat_unit_name', 'Servicio') }}"
                            placeholder="ej: Servicio" maxlength="50"
                            class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="iva_exempt" value="1" {{ old('iva_exempt') ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Exento de IVA <span class="text-gray-400 font-normal">(se timbra con factor Exento)</span></span>
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-1"></i> Guardar</button>
                <a href="{{ route('admin.services.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('btn-load-bundles');
    if (!btn) return;
    btn.addEventListener('click', function () {
        const list   = document.getElementById('bundle-list');
        const items  = document.getElementById('bundle-items');
        const errDiv = document.getElementById('bundle-error');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Cargando...';
        errDiv.classList.add('hidden');
        list.classList.add('hidden');
        fetch('{{ route("admin.settings.twentyi.bundle-types") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Error al consultar 20i');
            const data = res.data;
            items.innerHTML = '';
            const entries = Array.isArray(data)
                ? data.map(d => ({ id: d.id ?? d.Id, name: d.name ?? d.Name ?? d.id }))
                : Object.entries(data).map(([id, info]) => ({ id, name: info.name ?? info.Name ?? id }));
            if (!entries.length) {
                errDiv.innerHTML = 'La API de 20i no expone los bundle types directamente.<br>Obténlo desde tu panel de 20i: <strong>Reseller → Package Types</strong>.';
                errDiv.classList.remove('hidden');
            } else {
                entries.forEach(e => {
                    const badge = document.createElement('button');
                    badge.type = 'button';
                    badge.className = 'px-2 py-1 text-xs border rounded cursor-pointer bg-white hover:bg-blue-50 hover:border-blue-400 transition';
                    badge.innerHTML = `<span class="font-mono font-semibold">${e.id}</span><span class="text-gray-500 ml-1">${e.name}</span>`;
                    badge.addEventListener('click', () => {
                        document.getElementById('bundle-id-input').value = e.id;
                    });
                    items.appendChild(badge);
                });
                list.classList.remove('hidden');
            }
        })
        .catch(err => {
            errDiv.textContent = err.message;
            errDiv.classList.remove('hidden');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Ver disponibles';
        });
    });
})();
</script>
@endpush
@endsection
