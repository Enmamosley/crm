@extends('layouts.admin')
@section('title', $client->name ?? $client->legal_name)
@section('header', $client->name ?? $client->legal_name)

@section('actions')
<a href="{{ $client->portalUrl() }}" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm">
    <i class="fas fa-external-link-alt mr-1"></i> Ver Portal Cliente
</a>
@if($client->lead_id)
<a href="{{ route('admin.quotes.create', ['lead_id' => $client->lead_id]) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
    <i class="fas fa-file-alt mr-1"></i> Nueva Cotización
</a>
@endif
<a href="{{ route('admin.orders.create', ['client_id' => $client->id]) }}" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
    <i class="fas fa-file-invoice mr-1"></i> Nueva Factura
</a>
@if($client->twentyi_package_id)
<a href="{{ route('admin.clients.mailboxes.index', $client) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
    <i class="fas fa-envelope mr-1"></i> Correos
</a>
@endif
@if($client->twentyi_package_id && $client->domain)
<a href="{{ route('admin.clients.dns.index', $client) }}" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm">
    <i class="fas fa-network-wired mr-1"></i> DNS
</a>
@endif
<a href="{{ route('admin.clients.edit', $client) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-edit mr-1"></i> Editar
</a>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Columna izquierda: datos y facturas --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Información del cliente --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Información del Cliente</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="col-span-2"><span class="text-gray-500">Nombre:</span><p class="font-medium text-base">{{ $client->name ?? $client->legal_name }}</p></div>
                <div><span class="text-gray-500">Email:</span><p class="font-medium">{{ $client->email ?? '-' }}</p></div>
                <div><span class="text-gray-500">Teléfono:</span><p class="font-medium">{{ $client->phone ?? '-' }}</p></div>
                @if($client->notes)
                <div class="col-span-2"><span class="text-gray-500">Notas:</span><p class="font-medium text-gray-700">{{ $client->notes }}</p></div>
                @endif
            </div>
        </div>

        {{-- Datos fiscales --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Datos Fiscales</h3>
                @if($client->billing_type === 'publico_general')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">Público en General</span>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">Razón Social:</span><p class="font-medium">{{ $client->legal_name }}</p></div>
                <div><span class="text-gray-500">RFC:</span><p class="font-medium font-mono">{{ $client->tax_id }}</p></div>
                <div><span class="text-gray-500">Régimen Fiscal:</span><p class="font-medium">{{ $client->tax_system }}</p></div>
                <div><span class="text-gray-500">Uso CFDI:</span><p class="font-medium">{{ $client->cfdi_use }}</p></div>
                <div class="col-span-2">
                    <span class="text-gray-500">Dirección Fiscal:</span>
                    <p class="font-medium">
                        {{ implode(', ', array_filter([
                            $client->address_street, $client->address_exterior,
                            $client->address_neighborhood, $client->address_city,
                            $client->address_state, 'CP ' . $client->address_zip
                        ])) ?: '-' }}
                    </p>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t flex items-center gap-3">
                <span class="text-sm text-gray-500">FacturAPI:</span>
                @if($client->facturapi_customer_id)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                        <i class="fas fa-check mr-1"></i> Sincronizado ({{ $client->facturapi_customer_id }})
                    </span>
                @else
                    <span class="text-xs text-yellow-600">No sincronizado</span>
                    @if(App\Models\Setting::get('facturapi_api_key'))
                        <form method="POST" action="{{ route('admin.clients.sync-facturapi', $client) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-blue-600 hover:underline ml-1">
                                <i class="fas fa-sync-alt mr-1"></i>Sincronizar ahora
                            </button>
                        </form>
                    @endif
                @endif
                <span class="text-sm text-gray-400 ml-auto">
                    Portal:
                    <a href="{{ $client->portalUrl() }}" target="_blank" class="text-blue-600 hover:underline text-xs truncate max-w-xs inline-block align-bottom">
                        {{ $client->portalUrl() }}
                    </a>
                </span>
            </div>
        </div>

        {{-- Facturas --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold">Facturas</h3>
                <a href="{{ route('admin.orders.create', ['client_id' => $client->id]) }}" class="text-sm text-blue-600 hover:underline">
                    <i class="fas fa-plus mr-1"></i> Nueva
                </a>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Folio</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cotización</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($client->invoices as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-mono text-sm font-medium">{{ $order->folio() }}</td>
                            <td class="px-6 py-3 text-sm">{{ $order->quote?->quote_number ?? '-' }}</td>
                            <td class="px-6 py-3 text-right text-sm font-medium">${{ number_format($order->total, 2) }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $colors = ['draft'=>'gray','sent'=>'blue','pending'=>'yellow','valid'=>'green','cancelled'=>'red'];
                                    $labels = ['draft'=>'Borrador','sent'=>'Pagada','pending'=>'Procesando','valid'=>'Timbrada','cancelled'=>'Cancelada'];
                                    $c = $colors[$order->status] ?? 'gray';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                                    {{ $labels[$order->status] ?? $order->status }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-xs text-gray-500">{{ $order->created_at->format('d/m/Y') }}</td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-blue-600 hover:text-blue-800 text-sm">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Sin facturas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Columna derecha: lead + documentos --}}
    <div class="space-y-6">

        {{-- Lead --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Lead</h3>
            @if($client->lead)
                <div class="space-y-2 text-sm">
                    <p><span class="text-gray-500">Nombre: </span><span class="font-medium">{{ $client->lead->name }}</span></p>
                    <p><span class="text-gray-500">Estado: </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ ucfirst($client->lead->status) }}</span>
                    </p>
                    <p><span class="text-gray-500">Negocio: </span>{{ $client->lead->business ?? '-' }}</p>
                </div>
                <a href="{{ route('admin.leads.show', $client->lead) }}" class="mt-3 inline-block text-sm text-blue-600 hover:underline">Ver lead →</a>
            @else
                <p class="text-sm text-gray-400">Sin lead vinculado.</p>
            @endif
        </div>

        {{-- Dominio --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-3">
                <i class="fas fa-globe text-indigo-500 mr-1"></i> Dominio
            </h3>
            @if($client->domain)
                <div class="flex items-center gap-2 mb-2">
                    <span class="font-mono font-medium text-gray-800">{{ $client->domain }}</span>
                    @if($client->domain_type === 'cosmotown')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                            Cosmotown
                        </span>
                    @elseif($client->domain_type === 'own')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                            Propio
                        </span>
                    @endif
                </div>

                {{-- Botones de acción individuales --}}
                <div class="mt-3 space-y-2">
                    @if($client->twentyi_package_id)
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                <i class="fas fa-check mr-1"></i> Hosting 20i: #{{ $client->twentyi_package_id }}
                            </span>
                            <a href="{{ route('admin.clients.dns.index', $client) }}"
                               class="text-xs text-purple-600 hover:underline">
                                <i class="fas fa-network-wired"></i> DNS
                            </a>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Sin hosting 20i
                            </span>
                            <button onclick="rebuildHosting()" id="btn-rebuild-hosting"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-blue-600 text-white hover:bg-blue-700 transition">
                                <i class="fas fa-server"></i> Crear hosting
                            </button>
                        </div>
                    @endif

                    @if($client->domain_type === 'cosmotown')
                        <div class="flex items-center gap-2">
                            <button onclick="rebuildDomain()" id="btn-rebuild-domain"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition">
                                <i class="fas fa-globe"></i> Registrar dominio
                            </button>
                            <span class="text-xs text-gray-400">Cosmotown</span>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-400">Sin dominio configurado.</p>
                <a href="{{ route('admin.clients.edit', $client) }}" class="mt-1 inline-block text-xs text-blue-600 hover:underline">
                    Asignar dominio →
                </a>
            @endif
        </div>

        {{-- Documentos --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Documentos</h3>

            <form action="{{ route('admin.clients.documents.store', $client) }}" method="POST" enctype="multipart/form-data" class="mb-4 space-y-2">
                @csrf
                <input type="text" name="name" placeholder="Nombre del documento" required
                    class="w-full border rounded-lg px-3 py-2 text-sm">
                <input type="file" name="document" required
                    class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                <button type="submit" class="w-full bg-gray-800 text-white py-2 rounded-lg hover:bg-gray-900 text-sm">
                    <i class="fas fa-upload mr-1"></i> Subir
                </button>
            </form>

            <div class="space-y-2">
                @forelse($client->documents as $doc)
                    <div class="flex items-center justify-between border rounded-lg p-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ $doc->name }}</p>
                            <p class="text-xs text-gray-400">{{ $doc->sizeForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2 ml-2">
                            <a href="{{ route('admin.clients.documents.download', [$client, $doc]) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs">
                                <i class="fas fa-download"></i>
                            </a>
                            <form action="{{ route('admin.clients.documents.destroy', [$client, $doc]) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar documento?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-4">Sin documentos.</p>
                @endforelse
            </div>
        </div>

        {{-- Notas --}}
        @if($client->notes)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Notas internas</h3>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $client->notes }}</p>
        </div>
        @endif

        {{-- Tareas --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Tareas</h3>
                <a href="{{ route('admin.tasks.create', ['client_id' => $client->id]) }}"
                    class="text-xs text-blue-600 hover:underline">
                    <i class="fas fa-plus mr-1"></i> Nueva tarea
                </a>
            </div>
            @forelse($client->tasks->sortBy(fn($t) => [$t->status === 'done' ? 1 : 0, $t->due_at]) as $task)
                @php
                    $priorityClasses = ['low' => 'bg-gray-100 text-gray-600', 'medium' => 'bg-yellow-100 text-yellow-700', 'high' => 'bg-red-100 text-red-700'];
                @endphp
                <div class="flex items-start gap-3 py-3 border-b last:border-0 {{ $task->status === 'done' ? 'opacity-60' : '' }}">
                    <form method="POST" action="{{ route('admin.tasks.complete', $task) }}" class="mt-0.5">
                        @csrf @method('PATCH')
                        <button type="submit"
                            class="w-5 h-5 rounded border-2 flex items-center justify-center transition flex-shrink-0
                                {{ $task->status === 'done' ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400' }}">
                            @if($task->status === 'done') <i class="fas fa-check text-xs"></i> @endif
                        </button>
                    </form>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 {{ $task->status === 'done' ? 'line-through' : '' }}">{{ $task->title }}</p>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="px-1.5 py-0.5 text-xs rounded {{ $priorityClasses[$task->priority] }}">
                                {{ \App\Models\Task::PRIORITY_LABELS[$task->priority] }}
                            </span>
                            @if($task->due_at)
                                <span class="text-xs {{ $task->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                    <i class="fas fa-calendar mr-0.5"></i>{{ $task->due_at->format('d/m/Y') }}
                                </span>
                            @endif
                            @if($task->assignee)
                                <span class="text-xs text-gray-400"><i class="fas fa-user mr-0.5"></i>{{ $task->assignee->name }}</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.tasks.edit', $task) }}" class="text-gray-400 hover:text-blue-600 text-xs flex-shrink-0">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
            @empty
                <p class="text-sm text-gray-400 text-center py-4">Sin tareas asignadas.
                    <a href="{{ route('admin.tasks.create', ['client_id' => $client->id]) }}" class="text-blue-600 hover:underline">Crear una</a>
                </p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
function rebuildHosting() {
    const btn = document.getElementById('btn-rebuild-hosting');
    if (!confirm('¿Crear paquete de hosting 20i para {{ $client->domain }}?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    fetch('{{ route("admin.clients.create-hosting", $client) }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    })
    .then(r => r.json().then(d => ({ok: r.ok, data: d})))
    .then(({ok, data}) => {
        if (ok) { alert(data.message); location.reload(); }
        else { alert('Error: ' + (data.error || 'Falló la creación')); btn.disabled = false; btn.innerHTML = '<i class="fas fa-server"></i> Crear hosting'; }
    })
    .catch(() => { alert('Error de conexión'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-server"></i> Crear hosting'; });
}

function rebuildDomain() {
    const btn = document.getElementById('btn-rebuild-domain');
    if (!confirm('¿Registrar {{ $client->domain }} en Cosmotown? Se usará crédito de tu cuenta reseller.')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    fetch('{{ route("admin.clients.register-domain", $client) }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    })
    .then(r => r.json().then(d => ({ok: r.ok, data: d})))
    .then(({ok, data}) => {
        if (ok) { alert(data.message); location.reload(); }
        else { alert('Error: ' + (data.error || 'Falló el registro')); btn.disabled = false; btn.innerHTML = '<i class="fas fa-globe"></i> Registrar dominio'; }
    })
    .catch(() => { alert('Error de conexión'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-globe"></i> Registrar dominio'; });
}
</script>
@endpush
@endsection
