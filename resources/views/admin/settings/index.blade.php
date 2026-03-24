@extends('layouts.admin')
@section('title', 'Configuración')
@section('header', 'Configuración General')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')

            <h3 class="text-lg font-semibold mb-4">Datos de la Empresa</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Empresa</label>
                    <input type="text" name="company_name" value="{{ $settings['company_name'] }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
                    <input type="text" name="company_rfc" value="{{ $settings['company_rfc'] }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="company_email" value="{{ $settings['company_email'] }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="company_phone" value="{{ $settings['company_phone'] }}" class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                <textarea name="company_address" rows="2" class="w-full border rounded-lg px-3 py-2">{{ $settings['company_address'] }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                @if($settings['company_logo'])
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo" class="h-16">
                    </div>
                @endif
                <input type="file" name="company_logo" accept="image/*" class="w-full border rounded-lg px-3 py-2">
            </div>

            <div class="border-t pt-4 mt-4">
                <h3 class="text-lg font-semibold mb-4">Configuración Fiscal</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">% IVA</label>
                    <input type="number" name="iva_percentage" value="{{ $settings['iva_percentage'] }}" step="0.01" min="0" max="100" class="w-48 border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="border-t pt-4 mt-4">
                <h3 class="text-lg font-semibold mb-4">FacturAPI <span class="text-sm font-normal text-gray-400 ml-1">(Facturación electrónica SAT)</span></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <input type="password" name="facturapi_api_key" value="{{ $settings['facturapi_api_key'] ?? '' }}"
                            placeholder="sk_live_... o sk_test_..."
                            class="w-full border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">Obtén tu API Key en <a href="https://dashboard.facturapi.io" target="_blank" class="text-blue-500 hover:underline">dashboard.facturapi.io</a>. Usa <code>sk_test_</code> para pruebas.</p>
                    </div>
                </div>
            </div>

            <div class="border-t pt-4 mt-4">
                <h3 class="text-lg font-semibold mb-4">20i <span class="text-sm font-normal text-gray-400 ml-1">(Gestión de correos y hosting)</span></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key (Bearer Token)</label>
                        <input type="password" name="twentyi_api_key" value="{{ $settings['twentyi_api_key'] ?? '' }}"
                            placeholder="Bearer token de la API de 20i"
                            class="w-full border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">Encuéntrala en tu panel de 20i → API &amp; Integration.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Package Bundle ID
                            <span class="text-gray-400 font-normal">(para crear hosting automático)</span>
                        </label>
                        <div class="flex gap-2">
                            <input type="text" name="twentyi_package_bundle_id" id="bundle-id-input"
                                value="{{ $settings['twentyi_package_bundle_id'] ?? '' }}"
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
                        <p class="text-xs text-gray-400 mt-1">
                            ID del tipo de paquete de hosting a crear en 20i. Usa el botón para cargarlos desde tu cuenta.
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-t pt-4 mt-4">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-credit-card text-indigo-500 mr-1"></i>Mercado Pago
                    <span class="text-sm font-normal text-gray-400 ml-1">(Cobro de pagos online)</span>
                </h3>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Public Key</label>
                        <input type="text" name="mp_public_key" value="{{ $settings['mp_public_key'] ?? '' }}"
                            placeholder="APP_USR-... o TEST-..."
                            class="w-full md:w-2/3 border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">Clave pública para el SDK de JavaScript (checkout del cliente).</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                        <input type="password" name="mp_access_token" value="{{ $settings['mp_access_token'] ?? '' }}"
                            placeholder="APP_USR-... o TEST-..."
                            class="w-full md:w-2/3 border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">Token privado para procesar pagos desde el servidor.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret <span class="font-normal text-gray-400">(opcional)</span></label>
                        <input type="password" name="mp_webhook_secret" value="{{ $settings['mp_webhook_secret'] ?? '' }}"
                            placeholder="Secreto para validar firmas HMAC"
                            class="w-full md:w-2/3 border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">Configura el webhook en <a href="https://www.mercadopago.com.mx/developers/panel/app" target="_blank" class="text-blue-500 hover:underline">Tus integraciones</a> apuntando a: <code class="bg-gray-100 px-1 rounded">{{ url('api/webhooks/mercadopago') }}</code></p>
                    </div>
                </div>
            </div>

            <div class="border-t pt-4 mt-4">
                <h3 class="text-lg font-semibold mb-1">
                    <i class="fas fa-globe text-teal-500 mr-1"></i>Cosmotown
                    <span class="text-sm font-normal text-gray-400 ml-1">(Gestión de dominios)</span>
                </h3>
                <p class="text-xs text-gray-400 mb-4">Permite consultar disponibilidad de dominios y registrarlos. Obtén tu API key en <a href="https://sandbox.cosmotown.com" target="_blank" class="text-blue-500 hover:underline">sandbox.cosmotown.com</a> → My Account → Reseller API.</p>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <input type="password" name="cosmotown_api_key" value="{{ $settings['cosmotown_api_key'] ?? '' }}"
                            placeholder="API key de Cosmotown"
                            class="w-full md:w-2/3 border rounded-lg px-3 py-2 font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL base <span class="font-normal text-gray-400">(entorno)</span></label>
                        <input type="text" name="cosmotown_base_url" value="{{ $settings['cosmotown_base_url'] ?? 'https://irest-ote.cosmotown.com' }}"
                            placeholder="https://irest-ote.cosmotown.com"
                            class="w-full md:w-2/3 border rounded-lg px-3 py-2 font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">
                            OTE (sandbox): <code class="bg-gray-100 px-1 rounded text-xs">https://irest-ote.cosmotown.com</code>
                            &nbsp;·&nbsp; Producción: según documentación de Cosmotown.
                        </p>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('admin.domains.index') }}" class="text-sm text-teal-600 hover:underline">
                        <i class="fas fa-search mr-1"></i> Ir al buscador de dominios
                    </a>
                </div>
            </div>

            {{-- Datos bancarios para transferencias --}}
            <div class="border-t pt-4 mt-4">
                <h3 class="text-lg font-semibold mb-1">
                    <i class="fas fa-university text-blue-500 mr-1"></i>Datos Bancarios
                    <span class="text-sm font-normal text-gray-400 ml-1">(Transferencia / SPEI)</span>
                </h3>
                <p class="text-xs text-gray-400 mb-4">Estos datos se muestran al cliente cuando solicita pagar por transferencia bancaria.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Banco</label>
                        <input type="text" name="bank_name" value="{{ $settings['bank_name'] ?? '' }}"
                            placeholder="Ej: BBVA, Banamex, HSBC"
                            class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beneficiario</label>
                        <input type="text" name="bank_beneficiary" value="{{ $settings['bank_beneficiary'] ?? '' }}"
                            placeholder="Nombre o razón social"
                            class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. de cuenta</label>
                        <input type="text" name="bank_account" value="{{ $settings['bank_account'] ?? '' }}"
                            placeholder="18 dígitos"
                            class="w-full border rounded-lg px-3 py-2 font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CLABE interbancaria</label>
                        <input type="text" name="bank_clabe" value="{{ $settings['bank_clabe'] ?? '' }}"
                            placeholder="18 dígitos"
                            class="w-full border rounded-lg px-3 py-2 font-mono text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Concepto / Referencia sugerida <span class="font-normal text-gray-400">(opcional)</span></label>
                        <input type="text" name="bank_reference" value="{{ $settings['bank_reference'] ?? '' }}"
                            placeholder="Ej: Número de factura como referencia"
                            class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save mr-1"></i> Guardar Configuración
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('btn-load-bundles')?.addEventListener('click', function () {
    const btn = this;
    const list = document.getElementById('bundle-list');
    const items = document.getElementById('bundle-items');
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
            errDiv.innerHTML = 'La API de 20i no expone los bundle types directamente.<br>Obt\u00e9nlo desde tu panel de 20i: <strong>Reseller &rarr; Package Types</strong>.';
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
</script>
@endpush
@endsection
