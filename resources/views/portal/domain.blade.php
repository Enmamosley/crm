<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dominio — {{ $client->legal_name }}</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Mi Dominio</h1>
                <p class="text-sm text-gray-500">{{ $client->legal_name }} · <span class="font-mono font-semibold text-blue-700">{{ $client->domain }}</span></p>
            </div>
            <a href="{{ route('portal.dashboard', $client->portal_token) }}"
               class="text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-1"></i> Volver al portal
            </a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8 space-y-6" x-data="domainPortal()">

        {{-- Error al cargar info --}}
        @if($error)
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ $error }}
            </div>
        @endif

        {{-- Información del dominio --}}
        @if($domainInfo)
            @php $d = $domainInfo['domain'] ?? []; $ns = $domainInfo['nameservers'] ?? []; @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="px-6 py-4 border-b">
                    <h2 class="font-semibold text-gray-800"><i class="fas fa-info-circle text-blue-500 mr-1.5"></i> Información</h2>
                </div>
                <div class="p-6 grid grid-cols-2 sm:grid-cols-3 gap-5">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Estado</p>
                        @if($d['locked'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800"><i class="fas fa-lock mr-1"></i> Bloqueado</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-800"><i class="fas fa-lock-open mr-1"></i> Desbloqueado</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Privacidad WHOIS</p>
                        @if($d['whois_privacy'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800"><i class="fas fa-shield-alt mr-1"></i> Protegido</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600"><i class="fas fa-eye mr-1"></i> Público</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Auto-renovación</p>
                        @if($d['auto_billing'] ?? false)
                            <span class="text-sm text-green-700"><i class="fas fa-check mr-1"></i> Activa</span>
                        @else
                            <span class="text-sm text-gray-500"><i class="fas fa-times mr-1"></i> Desactivada</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Creado</p>
                        <p class="text-sm text-gray-700">{{ $d['created'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Expira</p>
                        <p class="text-sm font-medium {{ isset($d['expiration_date']) && \Carbon\Carbon::parse($d['expiration_date'])->diffInDays(now()) < 30 ? 'text-red-600' : 'text-gray-700' }}">
                            {{ $d['expiration_date'] ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Nameservers --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800"><i class="fas fa-server text-purple-500 mr-1.5"></i> Nameservers</h2>
                    <button @click="editingNs = !editingNs" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas" :class="editingNs ? 'fa-times' : 'fa-edit'"></i>
                        <span x-text="editingNs ? 'Cancelar' : 'Editar'"></span>
                    </button>
                </div>
                <div class="p-6">
                    {{-- Vista --}}
                    <div x-show="!editingNs" class="space-y-2">
                        @forelse($ns as $nameserver)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="w-6 h-6 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xs font-bold">{{ $loop->iteration }}</span>
                                <code class="font-mono text-gray-700">{{ $nameserver }}</code>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Sin nameservers configurados.</p>
                        @endforelse
                    </div>

                    {{-- Edición --}}
                    <div x-show="editingNs" class="space-y-3">
                        <template x-for="(ns, i) in nameservers" :key="i">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xs font-bold flex-shrink-0" x-text="i + 1"></span>
                                <input type="text" x-model="nameservers[i]" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" placeholder="ns1.ejemplo.com">
                                <button x-show="nameservers.length > 1" @click="nameservers.splice(i, 1)" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                            </div>
                        </template>
                        <div class="flex gap-3 items-center">
                            <button x-show="nameservers.length < 4" @click="nameservers.push('')" class="text-xs text-blue-600 hover:text-blue-800">
                                <i class="fas fa-plus mr-1"></i> Agregar
                            </button>
                            <button @click="saveNameservers()" :disabled="savingNs" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50">
                                <i class="fas mr-1" :class="savingNs ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                                <span x-text="savingNs ? 'Guardando...' : 'Guardar'"></span>
                            </button>
                        </div>
                        <p x-show="nsMessage" :class="nsSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm" x-text="nsMessage"></p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Registros DNS --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h2 class="font-semibold text-gray-800"><i class="fas fa-network-wired text-teal-500 mr-1.5"></i> Registros DNS</h2>
                <button @click="loadDns()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-sync-alt mr-1" :class="dnsLoading && 'fa-spin'"></i> Cargar DNS
                </button>
            </div>
            <div class="p-6">
                {{-- Loading --}}
                <div x-show="dnsLoading" class="text-center text-gray-500 py-4">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Cargando registros DNS...
                </div>

                {{-- Error --}}
                <div x-show="dnsError" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <span x-text="dnsError"></span>
                </div>

                {{-- DNS content --}}
                <div x-show="!dnsLoading && !dnsError && dnsLoaded">
                    <template x-for="type in ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'PTR']" :key="type">
                        <div x-show="(dnsRecords[type] && dnsRecords[type].length > 0) || editingDns" class="mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-gray-200 text-gray-700" x-text="type"></span>
                                <button x-show="editingDns" @click="addDnsRecord(type)" class="text-xs text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="space-y-1.5">
                                <template x-for="(rec, idx) in (dnsRecords[type] || [])" :key="type + '-' + idx">
                                    <div class="flex items-center gap-2 text-sm">
                                        <div x-show="!editingDns" class="flex items-center gap-3 w-full bg-gray-50 rounded px-3 py-2">
                                            <span class="font-mono text-gray-600 w-28 truncate" x-text="rec.host || '@'"></span>
                                            <span class="text-gray-400">→</span>
                                            <span class="font-mono text-gray-800 flex-1 truncate" x-text="rec.pointto || rec.content || ''"></span>
                                            <span class="text-gray-400 text-xs" x-text="'TTL: ' + (rec.ttl || 300)"></span>
                                        </div>
                                        <div x-show="editingDns" class="flex items-center gap-2 w-full">
                                            <input x-model="rec.host" placeholder="host" class="border rounded px-2 py-1.5 text-xs font-mono w-24">
                                            <input x-model="rec.pointto" x-show="type !== 'TXT'" placeholder="apunta a" class="border rounded px-2 py-1.5 text-xs font-mono flex-1">
                                            <input x-model="rec.content" x-show="type === 'TXT'" placeholder="contenido" class="border rounded px-2 py-1.5 text-xs font-mono flex-1">
                                            <input x-model.number="rec.ttl" placeholder="TTL" class="border rounded px-2 py-1.5 text-xs font-mono w-16">
                                            <button @click="dnsRecords[type].splice(idx, 1)" class="text-red-400 hover:text-red-600"><i class="fas fa-trash text-xs"></i></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex gap-3 pt-4 border-t mt-4">
                        <button @click="editingDns = !editingDns" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas" :class="editingDns ? 'fa-times' : 'fa-edit'"></i>
                            <span x-text="editingDns ? 'Cancelar' : 'Editar DNS'"></span>
                        </button>
                        <button x-show="editingDns" @click="saveDns()" :disabled="dnsSaving" class="bg-teal-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-teal-700 disabled:opacity-50">
                            <i class="fas mr-1" :class="dnsSaving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                            <span x-text="dnsSaving ? 'Guardando...' : 'Guardar DNS'"></span>
                        </button>
                    </div>
                    <p x-show="dnsMessage" :class="dnsSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm mt-2" x-text="dnsMessage"></p>
                </div>

                {{-- Not loaded --}}
                <div x-show="!dnsLoading && !dnsError && !dnsLoaded" class="text-center text-gray-400 py-4">
                    <p class="text-sm">Haz clic en "Cargar DNS" para ver los registros de tu dominio.</p>
                </div>
            </div>
        </div>

        {{-- Info box --}}
        <div class="bg-gray-50 border rounded-xl p-5 text-sm text-gray-500">
            <h4 class="font-semibold text-gray-600 mb-2"><i class="fas fa-info-circle text-blue-400 mr-1"></i> ¿Qué puedo hacer aquí?</h4>
            <ul class="space-y-1 text-xs">
                <li><i class="fas fa-check text-gray-400 mr-1.5"></i> Ver la información y estado de tu dominio.</li>
                <li><i class="fas fa-check text-gray-400 mr-1.5"></i> Editar los nameservers de tu dominio (ej: apuntar a otro hosting).</li>
                <li><i class="fas fa-check text-gray-400 mr-1.5"></i> Gestionar los registros DNS (A, CNAME, MX, TXT, etc.).</li>
                <li><i class="fas fa-check text-gray-400 mr-1.5"></i> Configurar tus registros MX para recibir correo en otro proveedor.</li>
            </ul>
        </div>

    </main>

<script>
function domainPortal() {
    return {
        // Nameservers
        editingNs: false,
        savingNs: false,
        nsMessage: '',
        nsSuccess: false,
        nameservers: @json($domainInfo['nameservers'] ?? []),

        async saveNameservers() {
            const ns = this.nameservers.filter(n => n.trim() !== '');
            if (ns.length === 0) { this.nsMessage = 'Agrega al menos un nameserver.'; this.nsSuccess = false; return; }
            this.savingNs = true; this.nsMessage = '';
            try {
                const resp = await fetch('{{ route("portal.domain.nameservers.save", $client->portal_token) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ nameservers: ns }),
                });
                const data = await resp.json();
                if (data.success) { this.nsMessage = 'Nameservers actualizados.'; this.nsSuccess = true; this.editingNs = false; }
                else { this.nsMessage = data.error ?? 'Error al guardar.'; this.nsSuccess = false; }
            } catch (e) { this.nsMessage = 'Error: ' + e.message; this.nsSuccess = false; }
            finally { this.savingNs = false; }
        },

        // DNS
        dnsRecords: { A: [], AAAA: [], CNAME: [], MX: [], TXT: [], PTR: [] },
        dnsLoading: false,
        dnsError: null,
        dnsLoaded: false,
        editingDns: false,
        dnsSaving: false,
        dnsMessage: '',
        dnsSuccess: false,

        async loadDns() {
            this.dnsLoading = true; this.dnsError = null;
            try {
                const resp = await fetch('{{ route("portal.domain.dns", $client->portal_token) }}', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await resp.json();
                if (!resp.ok) { this.dnsError = data.error ?? 'Error'; return; }
                const raw = data.records ?? {};
                const normalized = {};
                ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'PTR'].forEach(t => {
                    normalized[t] = (raw[t] || []).map(r => ({
                        host:     r.host     ?? r.Host     ?? '@',
                        pointto:  r.pointto  ?? r.pointsTo ?? r.value   ?? '',
                        content:  r.content  ?? r.data     ?? r.pointto ?? r.pointsTo ?? '',
                        ttl:      r.ttl      ?? r.TTL      ?? 300,
                        priority: r.priority ?? r.Priority ?? (t === 'MX' ? 10 : 0),
                    }));
                });
                this.dnsRecords = normalized;
                this.dnsLoaded = true;
            } catch (e) { this.dnsError = 'Error: ' + e.message; }
            finally { this.dnsLoading = false; }
        },

        addDnsRecord(type) {
            if (!this.dnsRecords[type]) this.dnsRecords[type] = [];
            const rec = { ttl: 300, priority: type === 'MX' ? 10 : 0, host: '' };
            if (type === 'TXT') rec.content = ''; else rec.pointto = '';
            this.dnsRecords[type].push(rec);
        },

        async saveDns() {
            this.dnsSaving = true; this.dnsMessage = '';
            try {
                const resp = await fetch('{{ route("portal.domain.dns.save", $client->portal_token) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ records: this.dnsRecords }),
                });
                const data = await resp.json();
                if (data.success) { this.dnsMessage = 'DNS actualizado correctamente.'; this.dnsSuccess = true; this.editingDns = false; }
                else { this.dnsMessage = data.error ?? 'Error al guardar.'; this.dnsSuccess = false; }
            } catch (e) { this.dnsMessage = 'Error: ' + e.message; this.dnsSuccess = false; }
            finally { this.dnsSaving = false; }
        }
    };
}
</script>
</body>
</html>
