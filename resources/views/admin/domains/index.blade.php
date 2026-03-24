@extends('layouts.admin')
@section('title', 'Dominios')
@section('header', 'Gestión de Dominios')

@section('actions')
<a href="{{ route('admin.settings.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-cog mr-1"></i> Configuración
</a>
@endsection

@section('content')
<div class="space-y-6">

    @if(!$configured)
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-5 py-4">
            <p class="font-medium"><i class="fas fa-key mr-1"></i> Falta configurar la API Key de Cosmotown.</p>
            <p class="text-sm mt-1">Ve a <a href="{{ route('admin.settings.index') }}" class="underline">Ajustes → Cosmotown</a> y agrega tu API key.</p>
        </div>
    @endif

    @if($isSandbox)
        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
            <i class="fas fa-flask"></i>
            <span>Usando entorno <strong>Sandbox</strong> de Cosmotown. Los registros son de prueba y no son reales.</span>
        </div>
    @endif

    {{-- Tabs --}}
    <div x-data="{ tab: 'domains' }" class="space-y-4">
        <div class="flex gap-1 border-b">
            <button @click="tab = 'domains'" :class="tab === 'domains' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2.5 text-sm transition">
                <i class="fas fa-globe mr-1"></i> Mis Dominios
            </button>
            <button @click="tab = 'search'" :class="tab === 'search' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2.5 text-sm transition">
                <i class="fas fa-search mr-1"></i> Buscar / Registrar
            </button>
        </div>

        {{-- TAB: Mis Dominios --}}
        <div x-show="tab === 'domains'" x-data="domainList()" x-init="loadDomains()">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Dominios en tu cuenta Cosmotown</h3>
                    <button @click="loadDomains()" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-sync-alt mr-1" :class="loading && 'fa-spin'"></i> Actualizar
                    </button>
                </div>

                {{-- Loading --}}
                <div x-show="loading" class="p-8 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <p class="text-sm">Cargando dominios...</p>
                </div>

                {{-- Error --}}
                <div x-show="error" class="p-6">
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                        <i class="fas fa-exclamation-triangle mr-1"></i> <span x-text="error"></span>
                    </div>
                </div>

                {{-- Tabla --}}
                <div x-show="!loading && !error && domains.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3">Dominio</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3">Privacidad</th>
                                <th class="px-6 py-3">Bloqueado</th>
                                <th class="px-6 py-3">Creado</th>
                                <th class="px-6 py-3">Expira</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="d in domains" :key="d.domain">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3">
                                        <span class="font-medium font-mono text-blue-700" x-text="d.domain"></span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span x-show="d.auto_billing" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Auto-renueva</span>
                                        <span x-show="!d.auto_billing" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Manual</span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <i x-show="d.whois_privacy" class="fas fa-shield-alt text-green-500" title="WHOIS protegido"></i>
                                        <i x-show="!d.whois_privacy" class="fas fa-eye text-gray-400" title="WHOIS público"></i>
                                    </td>
                                    <td class="px-6 py-3">
                                        <i x-show="d.locked" class="fas fa-lock text-green-500" title="Bloqueado (seguro)"></i>
                                        <i x-show="!d.locked" class="fas fa-lock-open text-yellow-500" title="Desbloqueado"></i>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 text-xs" x-text="d.created ? d.created.split(' ')[0] : '—'"></td>
                                    <td class="px-6 py-3 text-xs" :class="isExpiringSoon(d.expiration_date) ? 'text-red-600 font-semibold' : 'text-gray-500'" x-text="d.expiration_date ? d.expiration_date.split(' ')[0] : '—'"></td>
                                    <td class="px-6 py-3 text-right space-x-2">
                                        <a :href="'{{ route('admin.domains.info', ':d') }}'.replace(':d', d.domain)" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            <i class="fas fa-info-circle mr-0.5"></i> Info
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Vacío --}}
                <div x-show="!loading && !error && domains.length === 0" class="p-8 text-center text-gray-400">
                    <i class="fas fa-globe text-3xl mb-2"></i>
                    <p class="text-sm">No tienes dominios registrados en Cosmotown.</p>
                </div>
            </div>
        </div>

        {{-- TAB: Buscar / Registrar --}}
        <div x-show="tab === 'search'" class="max-w-3xl space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-1">Verificar disponibilidad</h3>
        <p class="text-sm text-gray-500 mb-5">Escribe el dominio que quieres verificar (ej: <code class="bg-gray-100 px-1 rounded">miempresa.com</code>)</p>

        <div class="flex gap-3">
            <input type="text" id="domain-input"
                placeholder="miempresa.com"
                class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                {{ !$configured ? 'disabled' : '' }}>
            <button id="btn-check"
                class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 text-sm font-medium transition disabled:opacity-50"
                {{ !$configured ? 'disabled' : '' }}>
                <i class="fas fa-search mr-1"></i> Verificar
            </button>
        </div>

        {{-- Result panel --}}
        <div id="result-panel" class="hidden mt-5">

            {{-- Loading --}}
            <div id="result-loading" class="hidden flex items-center gap-3 text-gray-500 text-sm">
                <i class="fas fa-spinner fa-spin text-blue-500"></i> Consultando disponibilidad...
            </div>

            {{-- Available --}}
            <div id="result-available" class="hidden bg-green-50 border border-green-200 rounded-lg p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-green-600 text-xs"></i>
                            </div>
                            <span class="font-semibold text-green-800">¡Disponible!</span>
                        </div>
                        <p class="text-sm text-green-700">El dominio <strong id="result-domain-name" class="font-mono"></strong> está disponible para registro.</p>
                        <p id="result-price-line" class="text-sm text-green-600 mt-1 hidden">
                            Precio: <strong id="result-price"></strong>
                        </p>
                        <p id="result-extra" class="text-xs text-gray-500 mt-1 hidden"></p>
                    </div>
                    <div class="flex-shrink-0">
                        <button id="btn-register"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium transition">
                            <i class="fas fa-cart-plus mr-1"></i> Registrar dominio
                        </button>
                    </div>
                </div>
            </div>

            {{-- Not available --}}
            <div id="result-unavailable" class="hidden bg-red-50 border border-red-200 rounded-lg p-5">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-6 h-6 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-times text-red-600 text-xs"></i>
                    </div>
                    <span class="font-semibold text-red-800">No disponible</span>
                </div>
                <p class="text-sm text-red-700">El dominio <strong id="result-domain-name-2" class="font-mono"></strong> no está disponible.</p>
                <p id="result-extra-2" class="text-xs text-gray-500 mt-1 hidden"></p>
            </div>

            {{-- Error --}}
            <div id="result-error" class="hidden bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-sm text-red-700 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="result-error-text"></span>
                </p>
            </div>

        </div>
    </div>

    {{-- Register success --}}
    <div id="register-success" class="hidden bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-green-800">
        <p class="font-semibold"><i class="fas fa-check-circle mr-1"></i> Dominio registrado exitosamente</p>
        <p class="text-sm mt-1" id="register-success-msg"></p>
    </div>

    <div id="register-error" class="hidden bg-red-50 border border-red-200 rounded-lg px-5 py-4 text-red-800">
        <p class="font-semibold"><i class="fas fa-times-circle mr-1"></i> Error al registrar</p>
        <p class="text-sm mt-1" id="register-error-msg"></p>
    </div>

    {{-- Info box --}}
    <div class="bg-gray-50 border rounded-lg p-5 text-sm text-gray-600">
        <h4 class="font-semibold text-gray-700 mb-2"><i class="fas fa-info-circle mr-1 text-blue-400"></i> Sobre esta integración</h4>
        <ul class="space-y-1.5 text-xs text-gray-500">
            <li><i class="fas fa-check text-gray-400 mr-1.5 w-3"></i> Puedes verificar la disponibilidad de cualquier dominio usando la API de Cosmotown.</li>
            <li><i class="fas fa-check text-gray-400 mr-1.5 w-3"></i> Si el dominio está disponible, puedes registrarlo directamente desde aquí (requiere saldo en tu cuenta Cosmotown).</li>
            <li><i class="fas fa-check text-gray-400 mr-1.5 w-3"></i> Una vez registrado, asígna el Package ID de 20i al cliente para gestionar sus buzones de correo.</li>
            <li class="text-yellow-600"><i class="fas fa-flask mr-1.5 w-3"></i> En sandbox los registros no son reales ni tienen costo.</li>
        </ul>
    </div>

        </div>{{-- /tab search --}}
    </div>{{-- /tabs --}}
</div>

<script>
function domainList() {
    return {
        domains: [],
        loading: false,
        error: null,
        async loadDomains() {
            this.loading = true;
            this.error = null;
            try {
                const resp = await fetch('{{ route("admin.domains.list") }}', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                });
                const data = await resp.json();
                if (!resp.ok) { this.error = data.error ?? 'Error desconocido'; return; }
                this.domains = data.domains ?? [];
            } catch (e) {
                this.error = 'Error de conexión: ' + e.message;
            } finally {
                this.loading = false;
            }
        },
        isExpiringSoon(date) {
            if (!date) return false;
            const exp = new Date(date);
            const now = new Date();
            const diff = (exp - now) / (1000 * 60 * 60 * 24);
            return diff < 30;
        }
    };
}

document.addEventListener('DOMContentLoaded', function () {
    const input       = document.getElementById('domain-input');
    const btnCheck    = document.getElementById('btn-check');
    const btnRegister = document.getElementById('btn-register');
    const panel       = document.getElementById('result-panel');

    const show = (id) => {
        ['result-loading','result-available','result-unavailable','result-error'].forEach(i => {
            document.getElementById(i).classList.add('hidden');
        });
        document.getElementById(id).classList.remove('hidden');
        panel.classList.remove('hidden');
    };

    let lastCheckedDomain = '';

    async function checkDomain() {
        const domain = input.value.trim().toLowerCase().replace(/^https?:\/\//, '');
        if (!domain) { input.focus(); return; }

        lastCheckedDomain = domain;
        btnCheck.disabled = true;
        btnCheck.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Verificando...';
        document.getElementById('register-success').classList.add('hidden');
        document.getElementById('register-error').classList.add('hidden');
        show('result-loading');

        try {
            const resp = await fetch('{{ route("admin.domains.check") }}?' + new URLSearchParams({ domain }), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            const data = await resp.json();

            if (!resp.ok) {
                document.getElementById('result-error-text').textContent = data.error ?? 'Error desconocido.';
                show('result-error');
                return;
            }

            if (data.available) {
                document.getElementById('result-domain-name').textContent = data.domain;
                const priceLine = document.getElementById('result-price-line');
                if (data.price) {
                    document.getElementById('result-price').textContent = '$' + parseFloat(data.price).toFixed(2) + ' USD';
                    priceLine.classList.remove('hidden');
                } else {
                    priceLine.classList.add('hidden');
                }
                const extra = document.getElementById('result-extra');
                if (data.message) { extra.textContent = data.message; extra.classList.remove('hidden'); }
                else { extra.classList.add('hidden'); }
                show('result-available');
            } else {
                document.getElementById('result-domain-name-2').textContent = data.domain;
                const extra2 = document.getElementById('result-extra-2');
                if (data.message) { extra2.textContent = data.message; extra2.classList.remove('hidden'); }
                else { extra2.classList.add('hidden'); }
                show('result-unavailable');
            }
        } catch (err) {
            document.getElementById('result-error-text').textContent = 'Error de conexión: ' + err.message;
            show('result-error');
        } finally {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fas fa-search mr-1"></i> Verificar';
        }
    }

    btnCheck.addEventListener('click', checkDomain);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') checkDomain(); });

    btnRegister.addEventListener('click', async function () {
        if (!lastCheckedDomain) return;
        if (!confirm(`¿Registrar el dominio "${lastCheckedDomain}"?\n\nEsta acción desconta el costo de tu cuenta Cosmotown.`)) return;

        btnRegister.disabled = true;
        btnRegister.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Registrando...';

        try {
            const resp = await fetch('{{ route("admin.domains.register") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ domain: lastCheckedDomain }),
            });
            const data = await resp.json();

            if (data.success) {
                document.getElementById('register-success-msg').textContent =
                    `"${lastCheckedDomain}" fue registrado correctamente. Ahora puedes asignarlo en la sección de correos del cliente.`;
                document.getElementById('register-success').classList.remove('hidden');
                document.getElementById('register-error').classList.add('hidden');
                show('result-unavailable'); // domain is now taken
                document.getElementById('result-domain-name-2').textContent = lastCheckedDomain;
            } else {
                document.getElementById('register-error-msg').textContent = data.error ?? 'No se pudo registrar el dominio.';
                document.getElementById('register-error').classList.remove('hidden');
            }
        } catch (err) {
            document.getElementById('register-error-msg').textContent = 'Error de conexión: ' + err.message;
            document.getElementById('register-error').classList.remove('hidden');
        } finally {
            btnRegister.disabled = false;
            btnRegister.innerHTML = '<i class="fas fa-cart-plus mr-1"></i> Registrar dominio';
        }
    });
});
</script>
@endsection
