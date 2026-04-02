@extends('layouts.admin')
@section('title', $domain . ' — Detalle de Dominio')
@section('header', $domain)

@section('actions')
<a href="{{ route('admin.domains.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-arrow-left mr-1"></i> Volver
</a>
@endsection

@section('content')
<div x-data="domainDetail()" class="space-y-6">

    @if($isSandbox)
        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
            <i class="fas fa-flask"></i>
            <span>Entorno <strong>Sandbox</strong> — los cambios no afectan producción.</span>
        </div>
    @endif

    {{-- Información general --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-info-circle text-blue-500 mr-1.5"></i> Información del dominio</h3>
        </div>
        <div class="p-6">
            @php $d = $domainInfo['domain'] ?? []; $ns = $domainInfo['nameservers'] ?? []; $contact = $domainInfo['contact'] ?? []; @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Dominio</p>
                    <p class="font-mono font-semibold text-blue-700">{{ $d['domain'] ?? $domain }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Auto-renovación</p>
                    <p>
                        @if($d['auto_billing'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800"><i class="fas fa-check mr-1"></i> Activa</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600"><i class="fas fa-times mr-1"></i> Desactivada</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Privacidad WHOIS</p>
                    <p>
                        @if($d['whois_privacy'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800"><i class="fas fa-shield-alt mr-1"></i> Protegido</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-800"><i class="fas fa-eye mr-1"></i> Público</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Bloqueado</p>
                    <p>
                        @if($d['locked'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800"><i class="fas fa-lock mr-1"></i> Bloqueado</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-800"><i class="fas fa-lock-open mr-1"></i> Desbloqueado</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Fecha de creación</p>
                    <p class="text-sm text-gray-700">{{ $d['created'] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Fecha de expiración</p>
                    <p class="text-sm font-medium {{ isset($d['expiration_date']) && \Carbon\Carbon::parse($d['expiration_date'])->diffInDays(now()) < 30 ? 'text-red-600' : 'text-gray-700' }}">
                        {{ $d['expiration_date'] ?? '—' }}
                    </p>
                </div>
            </div>

            {{-- Contacto registrante --}}
            @if(isset($contact['registrant']))
                <div class="mt-6 pt-4 border-t">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Registrante</p>
                    <div class="text-sm text-gray-600 space-y-0.5">
                        <p>{{ $contact['registrant']['FirstName'] ?? '' }} {{ $contact['registrant']['LastName'] ?? '' }}</p>
                        @if(!empty($contact['registrant']['Company']))<p class="text-gray-500">{{ $contact['registrant']['Company'] }}</p>@endif
                        <p class="text-gray-400">{{ $contact['registrant']['Email'] ?? '' }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Nameservers --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-server text-purple-500 mr-1.5"></i> Nameservers</h3>
            <button @click="editingNs = !editingNs" class="text-sm text-blue-600 hover:text-blue-800">
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
                    <p class="text-sm text-gray-400">No se encontraron nameservers.</p>
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
                <div class="flex gap-3">
                    <button x-show="nameservers.length < 4" @click="nameservers.push('')" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i> Agregar NS
                    </button>
                    <button @click="saveNameservers()" :disabled="savingNs" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                        <i class="fas fa-save mr-1" :class="savingNs && 'fa-spin fa-spinner'"></i>
                        <span x-text="savingNs ? 'Guardando...' : 'Guardar Nameservers'"></span>
                    </button>
                </div>
                <div x-show="nsMessage" :class="nsSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm" x-text="nsMessage"></div>
            </div>
        </div>
    </div>

    {{-- DNS Records --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-network-wired text-teal-500 mr-1.5"></i> Registros DNS</h3>
            <button @click="loadDns()" class="text-sm text-blue-600 hover:text-blue-800">
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
                {{-- Existing records --}}
                <template x-for="type in ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'PTR']" :key="type">
                    <div x-show="(dnsRecords[type] && dnsRecords[type].length > 0) || editingDns" class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-gray-200 text-gray-700" x-text="type"></span>
                            <button x-show="editingDns" @click="addDnsRecord(type)" class="text-xs text-blue-600 hover:text-blue-800">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                        <div class="space-y-1.5">
                            <template x-for="(rec, idx) in (dnsRecords[type] || [])" :key="type + '-' + idx">
                                <div class="flex items-center gap-2 text-sm">
                                    <div x-show="!editingDns" class="flex items-center gap-3 w-full bg-gray-50 rounded px-3 py-2">
                                        <span class="font-mono text-gray-600 w-32 truncate" x-text="rec.host || '@'"></span>
                                        <span class="text-gray-400">→</span>
                                        <span class="font-mono text-gray-800 flex-1 truncate" x-text="rec.pointto || rec.content || ''"></span>
                                        <span class="text-gray-400 text-xs" x-text="'TTL: ' + (rec.ttl || 300)"></span>
                                        <span x-show="type === 'MX'" class="text-gray-400 text-xs" x-text="'Pri: ' + (rec.priority || 10)"></span>
                                    </div>
                                    <div x-show="editingDns" class="flex items-center gap-2 w-full">
                                        <input x-model="rec.host" placeholder="host" class="border rounded px-2 py-1.5 text-xs font-mono w-28">
                                        <input x-model="rec.pointto" x-show="type !== 'TXT'" placeholder="apunta a" class="border rounded px-2 py-1.5 text-xs font-mono flex-1">
                                        <input x-model="rec.content" x-show="type === 'TXT'" placeholder="contenido" class="border rounded px-2 py-1.5 text-xs font-mono flex-1">
                                        <input x-model.number="rec.ttl" placeholder="TTL" class="border rounded px-2 py-1.5 text-xs font-mono w-16">
                                        <input x-show="type === 'MX'" x-model.number="rec.priority" placeholder="Pri" class="border rounded px-2 py-1.5 text-xs font-mono w-14">
                                        <button @click="dnsRecords[type].splice(idx, 1)" class="text-red-400 hover:text-red-600"><i class="fas fa-trash text-xs"></i></button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Actions --}}
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
                <div x-show="dnsMessage" :class="dnsSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm mt-2" x-text="dnsMessage"></div>
            </div>

            {{-- Not loaded yet --}}
            <div x-show="!dnsLoading && !dnsError && !dnsLoaded" class="text-center text-gray-400 py-4">
                <p class="text-sm">Haz clic en "Cargar DNS" para ver los registros.</p>
            </div>
        </div>
    </div>

    {{-- Asignar a cliente --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-user-tag text-indigo-500 mr-1.5"></i> Cliente asignado</h3>
        </div>
        <div class="p-6" x-data="assignClient()">
            {{-- Estado actual --}}
            <div x-show="assigned" class="flex items-center gap-3 mb-4 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                <i class="fas fa-user-check text-indigo-500"></i>
                <span class="text-sm font-medium text-indigo-800" x-text="assigned?.name"></span>
                <a :href="`/panel/clients/${assigned?.id}`" class="text-xs text-indigo-600 hover:underline" target="_blank">Ver cliente →</a>
                <button @click="unassign()" :disabled="saving" class="ml-auto text-xs text-red-500 hover:text-red-700">
                    <i class="fas fa-times mr-1"></i> Desasignar
                </button>
            </div>
            <div x-show="!assigned" class="mb-4 text-sm text-gray-400 italic">
                Este dominio no está asignado a ningún cliente.
            </div>

            {{-- Selector --}}
            <div class="flex gap-3 items-end flex-wrap">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-500 mb-1">Asignar a cliente</label>
                    <select x-model="selectedClientId" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">— Selecciona un cliente —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}@if($c->email) — {{ $c->email }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <button @click="assign()" :disabled="saving || !selectedClientId"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
                    <i class="fas mr-1" :class="saving ? 'fa-spinner fa-spin' : 'fa-link'"></i>
                    <span x-text="saving ? 'Guardando...' : 'Asignar'"></span>
                </button>
            </div>

            <div x-show="message" class="mt-3 text-sm" :class="success ? 'text-green-600' : 'text-red-600'" x-text="message"></div>
        </div>
    </div>

    {{-- Renovar --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-redo text-orange-500 mr-1.5"></i> Renovar dominio</h3>
        </div>
        <div class="p-6">
            <div class="flex items-end gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Años a renovar</label>
                    <select x-model.number="renewYears" class="border rounded-lg px-3 py-2 text-sm">
                        <option value="1">1 año</option>
                        <option value="2">2 años</option>
                        <option value="3">3 años</option>
                        <option value="5">5 años</option>
                        <option value="10">10 años</option>
                    </select>
                </div>
                <button @click="renewDomain()" :disabled="renewing" class="bg-orange-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-700 disabled:opacity-50">
                    <i class="fas mr-1" :class="renewing ? 'fa-spinner fa-spin' : 'fa-redo'"></i>
                    <span x-text="renewing ? 'Renovando...' : 'Renovar'"></span>
                </button>
            </div>
            <div x-show="renewMessage" :class="renewSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm mt-3" x-text="renewMessage"></div>
        </div>
    </div>
</div>

<script>
function assignClient() {
    return {
        assigned: @json($assignedClient ? ['id' => $assignedClient->id, 'name' => $assignedClient->name] : null),
        selectedClientId: '{{ $assignedClient?->id ?? '' }}',
        saving: false,
        message: '',
        success: false,

        async assign() {
            if (!this.selectedClientId) return;
            this.saving = true;
            this.message = '';
            try {
                const resp = await fetch('{{ route("admin.domains.assign-client", $domain) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ client_id: this.selectedClientId }),
                });
                const data = await resp.json();
                if (data.success) {
                    this.assigned = data.client;
                    this.message = data.message;
                    this.success = true;
                } else {
                    this.message = data.error ?? data.message ?? 'Error al asignar.';
                    this.success = false;
                }
            } catch (e) { this.message = 'Error: ' + e.message; this.success = false; }
            finally { this.saving = false; }
        },

        async unassign() {
            if (!confirm('¿Desasignar este dominio del cliente?')) return;
            this.saving = true;
            this.message = '';
            try {
                const resp = await fetch('{{ route("admin.domains.unassign-client", $domain) }}', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                });
                const data = await resp.json();
                if (data.success) {
                    this.assigned = null;
                    this.selectedClientId = '';
                    this.message = data.message;
                    this.success = true;
                } else {
                    this.message = data.error ?? 'Error al desasignar.';
                    this.success = false;
                }
            } catch (e) { this.message = 'Error: ' + e.message; this.success = false; }
            finally { this.saving = false; }
        },
    };
}

function domainDetail() {
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
            this.savingNs = true;
            this.nsMessage = '';
            try {
                const resp = await fetch('{{ route("admin.domains.nameservers.save", $domain) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ nameservers: ns }),
                });
                const data = await resp.json();
                if (data.success) { this.nsMessage = 'Nameservers guardados.'; this.nsSuccess = true; this.editingNs = false; }
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
            this.dnsLoading = true;
            this.dnsError = null;
            try {
                const resp = await fetch('{{ route("admin.domains.dns", $domain) }}', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
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
            this.dnsSaving = true;
            this.dnsMessage = '';
            try {
                const resp = await fetch('{{ route("admin.domains.dns.save", $domain) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ records: this.dnsRecords }),
                });
                const data = await resp.json();
                if (data.success) { this.dnsMessage = 'DNS guardados correctamente.'; this.dnsSuccess = true; this.editingDns = false; }
                else { this.dnsMessage = data.error ?? 'Error al guardar.'; this.dnsSuccess = false; }
            } catch (e) { this.dnsMessage = 'Error: ' + e.message; this.dnsSuccess = false; }
            finally { this.dnsSaving = false; }
        },

        // Renew
        renewYears: 1,
        renewing: false,
        renewMessage: '',
        renewSuccess: false,

        async renewDomain() {
            if (!confirm(`¿Renovar "{{ $domain }}" por ${this.renewYears} año(s)?\n\nSe descontará el costo de tu cuenta Cosmotown.`)) return;
            this.renewing = true;
            this.renewMessage = '';
            try {
                const resp = await fetch('{{ route("admin.domains.renew", $domain) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ years: this.renewYears }),
                });
                const data = await resp.json();
                if (data.success) { this.renewMessage = 'Solicitud de renovación enviada. El proceso es asíncrono.'; this.renewSuccess = true; }
                else { this.renewMessage = data.error ?? 'Error al renovar.'; this.renewSuccess = false; }
            } catch (e) { this.renewMessage = 'Error: ' + e.message; this.renewSuccess = false; }
            finally { this.renewing = false; }
        }
    };
}
</script>

{{-- Raw API response: solo visible en modo debug para identificar campos --}}
@if(config('app.debug'))
<details class="mt-4">
    <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">Ver respuesta cruda de API (debug)</summary>
    <pre class="mt-2 text-xs bg-gray-900 text-green-400 p-4 rounded overflow-auto max-h-96">{{ json_encode($domainInfo['_raw'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</details>
@endif

@endsection
