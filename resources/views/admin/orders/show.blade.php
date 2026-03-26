@extends('layouts.admin')
@section('title', 'Factura ' . $order->folio())
@section('header', 'Factura ' . ($order->folio() ?: 'Sin folio'))

@section('actions')
@if(!$order->isPaid() && in_array($order->status, ['draft','pending','sent']))
    <button onclick="document.getElementById('manualPayModal').classList.remove('hidden')"
        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
        <i class="fas fa-money-bill mr-1"></i> Registrar Pago
    </button>
@endif

@if($order->client && $order->client->portal_token && !$order->isPaid())
    <form action="{{ route('admin.orders.send-link', $order) }}" method="POST" class="inline">
        @csrf
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
            <i class="fas fa-link mr-1"></i> Enviar Link de Cobro
        </button>
    </form>
@endif

@if(($order->isPaid() || $order->status === 'sent') && in_array($order->status, ['draft', 'sent']))
    <form action="{{ route('admin.orders.stamp', $order) }}" method="POST" class="inline"
          onsubmit="return confirm('¿Timbrar esta factura ante el SAT?')">
        @csrf @method('PATCH')
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium">
            <i class="fas fa-stamp mr-1"></i> Timbrar ante SAT
        </button>
    </form>
@elseif($order->status === 'draft' && !$order->isPaid())
    <form action="{{ route('admin.orders.stamp', $order) }}" method="POST" class="inline"
          onsubmit="return confirm('La orden aún no está pagada. ¿Timbrar de todos modos?')">
        @csrf @method('PATCH')
        <button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm">
            <i class="fas fa-stamp mr-1"></i> Timbrar (sin pago)
        </button>
    </form>
@endif

@if($order->isStamped())
    <a href="{{ route('admin.orders.pdf', $order) }}" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
        <i class="fas fa-file-pdf mr-1"></i> PDF
    </a>
    <a href="{{ route('admin.orders.xml', $order) }}" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm">
        <i class="fas fa-file-code mr-1"></i> XML
    </a>
@endif

@if($order->isCancellable())
    <button onclick="document.getElementById('cancelModal').classList.remove('hidden')"
        class="bg-gray-200 text-red-600 border border-red-200 px-4 py-2 rounded-lg hover:bg-red-50 text-sm">
        <i class="fas fa-ban mr-1"></i> Cancelar ante SAT
    </button>
@endif

@if($order->isVoidable())
    <button onclick="document.getElementById('voidModal').classList.remove('hidden')"
        class="bg-gray-100 text-red-500 border border-red-100 px-4 py-2 rounded-lg hover:bg-red-50 text-sm">
        <i class="fas fa-times-circle mr-1"></i> Anular Orden
    </button>
@endif
@endsection

@section('content')
{{-- Barra de flujo --}}
@php
    $flowStep = 1; // orden creada
    if ($order->client && $order->client->portal_token) $flowStep = 2; // link disponible
    if ($order->isPaid() || $order->status === 'sent') $flowStep = 3;
    if ($order->isStamped()) $flowStep = 4;
    if ($order->status === 'cancelled') $flowStep = 0;
    $invSteps = [
        ['label' => 'Orden',         'icon' => 'fa-clipboard-list'],
        ['label' => 'Cobro enviado', 'icon' => 'fa-link'],
        ['label' => 'Pagada',        'icon' => 'fa-credit-card'],
        ['label' => 'Facturada',     'icon' => 'fa-stamp'],
    ];
@endphp
<div class="bg-white rounded-lg shadow p-4 mb-6">
    @if($order->status === 'cancelled')
        <p class="text-center text-sm text-red-500"><i class="fas fa-ban mr-1"></i>Orden cancelada</p>
    @else
    <div class="flex items-center justify-between">
        @foreach($invSteps as $i => $step)
            @php $n = $i + 1; $done = $flowStep >= $n; @endphp
            <div class="flex items-center {{ $i < count($invSteps)-1 ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm
                        {{ $done ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400' }}">
                        @if($done && $flowStep > $n)
                            <i class="fas fa-check text-xs"></i>
                        @else
                            <i class="fas {{ $step['icon'] }} text-xs"></i>
                        @endif
                    </div>
                    <span class="text-xs mt-1 {{ $done ? 'text-green-600 font-medium' : 'text-gray-400' }}">{{ $step['label'] }}</span>
                </div>
                @if($i < count($invSteps)-1)
                    <div class="flex-1 h-0.5 mx-2 mb-4 {{ $flowStep > $n ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                @endif
            </div>
        @endforeach
    </div>
    @if($order->quote)
        <p class="text-center text-xs text-gray-400 mt-2">
            Cotización: <a href="{{ route('admin.quotes.show', $order->quote) }}" class="text-blue-500 hover:underline">{{ $order->quote->quote_number }}</a>
        </p>
    @endif
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        {{-- Estado y datos generales --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Información General</h3>
                @php
                    $colors = ['draft'=>'gray','sent'=>'blue','pending'=>'yellow','paid'=>'green','cancelled'=>'red'];
                    $labels = ['draft'=>'Borrador','sent'=>'Enviada','pending'=>'Procesando','paid'=>'Pagada','cancelled'=>'Cancelada'];
                    $c = $colors[$order->status] ?? 'gray';
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                    @if($order->isStamped()) <i class="fas fa-stamp mr-1"></i> @endif
                    {{ $labels[$order->status] ?? $order->status }}
                    @if($order->isStamped()) &bull; CFDI timbrado @endif
                </span>
                @if(in_array($order->status, ['draft', 'sent', 'pending']))
                <form action="{{ route('admin.orders.update-status', $order) }}" method="POST" class="inline-flex items-center gap-1 ml-2">
                    @csrf @method('PATCH')
                    <select name="status" class="text-xs border rounded px-2 py-1 bg-white">
                        <option value="draft" {{ $order->status === 'draft' ? 'selected' : '' }}>Borrador</option>
                        <option value="sent" {{ $order->status === 'sent' ? 'selected' : '' }}>Enviada</option>
                        <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Procesando</option>
                        <option value="paid" {{ $order->status === 'paid' ? 'selected' : '' }}>Pagada</option>
                    </select>
                    <button type="submit" class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">
                        <i class="fas fa-save"></i>
                    </button>
                </form>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">Folio:</span><p class="font-medium font-mono">{{ $order->folio_number ? $order->folio() : $order->series . ' — sin número asignado' }}</p></div>
                <div><span class="text-gray-500">Forma de pago:</span><p class="font-medium">{{ $order->payment_form }}</p></div>
                <div><span class="text-gray-500">Método de pago:</span><p class="font-medium">{{ $order->payment_method }}</p></div>
                <div><span class="text-gray-500">Uso CFDI:</span><p class="font-medium">{{ $order->use_cfdi }}</p></div>
                @php
                    $bpLabels = ['fiscal'=>'Con datos fiscales del cliente','publico_general'=>'Público en General (XAXX010101000)','none'=>'Sin factura (cliente no requiere)'];
                    $bpColors = ['fiscal'=>'green','publico_general'=>'yellow','none'=>'gray'];
                    $bp = $order->billing_preference ?? 'fiscal';
                @endphp
                <div class="col-span-2">
                    <span class="text-gray-500">Preferencia de facturación:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $bpColors[$bp] ?? 'gray' }}-100 text-{{ $bpColors[$bp] ?? 'gray' }}-700 ml-1">
                        {{ $bpLabels[$bp] ?? $bp }}
                    </span>
                    @if($bp === 'none')
                    <p class="text-xs text-orange-600 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>No timbrar — el cliente no quiere factura.</p>
                    @endif
                </div>
                <div><span class="text-gray-500">Cotización:</span>
                    @if($order->quote)
                        <a href="{{ route('admin.quotes.show', $order->quote) }}" class="font-medium text-blue-600 hover:underline">{{ $order->quote->quote_number }}</a>
                    @else
                        <p class="font-medium text-gray-400">-</p>
                    @endif
                </div>
                @if($order->fiscalDocument?->stamped_at)
                <div><span class="text-gray-500">CFDI timbrado:</span><p class="font-medium">{{ $order->fiscalDocument->stamped_at->format('d/m/Y H:i') }}</p></div>
                @endif
                @if($order->fiscalDocument?->cancelled_at)
                <div><span class="text-gray-500">CFDI cancelado:</span><p class="font-medium text-red-600">{{ $order->fiscalDocument->cancelled_at->format('d/m/Y H:i') }}</p></div>
                @endif
            </div>
            @if($order->notes)
            <div class="mt-4 pt-4 border-t">
                <span class="text-gray-500 text-sm">Notas:</span>
                <p class="text-sm mt-1">{{ $order->notes }}</p>
            </div>
            @endif
            @if($order->fiscalDocument?->facturapi_invoice_id)
            <div class="mt-4 pt-4 border-t text-xs text-gray-400">
                CFDI (FacturAPI): <span class="font-mono">{{ $order->fiscalDocument->facturapi_invoice_id }}</span>
                <span class="ml-2 px-1.5 py-0.5 rounded text-xs {{ $order->fiscalDocument->status === 'valid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $order->fiscalDocument->status }}</span>
            </div>
            @endif
        </div>

        {{-- Items de la cotización o ítems manuales --}}
        @if($order->quote && $order->quote->items->count())
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b"><h3 class="text-lg font-semibold">Conceptos <span class="text-xs font-normal text-gray-400 ml-1">(de cotización)</span></h3></div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Descripción</th>
                        <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cant.</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Precio</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($order->quote->items as $item)
                    <tr>
                        <td class="px-6 py-3 text-sm">{{ $item->description }}</td>
                        <td class="px-6 py-3 text-center text-sm">{{ $item->quantity }}</td>
                        <td class="px-6 py-3 text-right text-sm">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-6 py-3 text-right text-sm font-medium">${{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr><td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">Subtotal</td>
                        <td class="px-6 py-2 text-right text-sm">${{ number_format($order->subtotal, 2) }}</td></tr>
                    <tr><td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">IVA</td>
                        <td class="px-6 py-2 text-right text-sm">${{ number_format($order->iva_amount, 2) }}</td></tr>
                    <tr><td colspan="3" class="px-6 py-3 text-right font-bold">Total</td>
                        <td class="px-6 py-3 text-right font-bold text-lg">${{ number_format($order->total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        @elseif($order->items->count())
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b"><h3 class="text-lg font-semibold">Conceptos <span class="text-xs font-normal text-gray-400 ml-1">(ingresados manualmente)</span></h3></div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Descripción</th>
                        <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cant.</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Precio</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($order->items as $item)
                    <tr>
                        <td class="px-6 py-3 text-sm">{{ $item->description }}</td>
                        <td class="px-6 py-3 text-center text-sm">{{ $item->quantity }}</td>
                        <td class="px-6 py-3 text-right text-sm">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-6 py-3 text-right text-sm font-medium">${{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr><td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">Subtotal</td>
                        <td class="px-6 py-2 text-right text-sm">${{ number_format($order->subtotal, 2) }}</td></tr>
                    <tr><td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">IVA</td>
                        <td class="px-6 py-2 text-right text-sm">${{ number_format($order->iva_amount, 2) }}</td></tr>
                    <tr><td colspan="3" class="px-6 py-3 text-right font-bold">Total</td>
                        <td class="px-6 py-3 text-right font-bold text-lg">${{ number_format($order->total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        @endif

        {{-- Pagos --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Pagos</h3>
                @if($order->isPaid())
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 font-medium">
                        <i class="fas fa-check-circle"></i> Pagada
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700">
                        <i class="fas fa-clock"></i> Pendiente
                    </span>
                @endif
            </div>
            @forelse($order->payments->sortByDesc('created_at') as $pay)
            @php
                $payColors = ['approved'=>'green','pending'=>'yellow','in_process'=>'blue','rejected'=>'red','cancelled'=>'gray','refunded'=>'purple'];
                $payLabels = ['approved'=>'Aprobado','pending'=>'Pendiente','in_process'=>'En proceso','rejected'=>'Rechazado','cancelled'=>'Cancelado','refunded'=>'Devuelto','manual'=>'Manual'];
                $pc = $payColors[$pay->status] ?? 'gray';
            @endphp
            <div class="flex items-center justify-between py-3 border-b last:border-0">
                <div>
                    <p class="text-sm font-medium">${{ number_format($pay->amount, 2) }} MXN</p>
                    <p class="text-xs text-gray-400">
                        @if($pay->payment_type === 'manual') Pago manual
                        @elseif($pay->payment_type === 'transfer') Transferencia bancaria (portal)
                        @else {{ ucfirst(str_replace('_', ' ', $pay->payment_type ?? 'MP')) }}
                        @endif
                        @if($pay->payment_method_id && !in_array($pay->payment_method_id, ['bank_transfer'])) · {{ $pay->payment_method_id }} @endif
                        · {{ ($pay->paid_at ?? $pay->created_at)->format('d/m/Y H:i') }}
                    </p>
                    @if($pay->payment_type === 'transfer' && $pay->status === 'pending')
                    <form method="POST" action="{{ route('admin.payments.approve', $pay) }}" class="inline mt-1">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs text-green-700 bg-green-100 hover:bg-green-200 px-2 py-0.5 rounded">
                            <i class="fas fa-check mr-1"></i> Confirmar transferencia
                        </button>
                    </form>
                    @endif
                    @if($pay->mp_payment_id)
                        <p class="text-xs text-gray-300 font-mono">MP #{{ $pay->mp_payment_id }}</p>
                    @endif
                    @if($pay->payment_notes)
                        <p class="text-xs text-gray-500 mt-0.5"><i class="fas fa-comment mr-1"></i>{{ $pay->payment_notes }}</p>
                    @endif
                    @if($pay->proof_path)
                        <a href="{{ Storage::url($pay->proof_path) }}" target="_blank" class="text-xs text-blue-600 hover:underline mt-0.5 inline-block">
                            <i class="fas fa-paperclip mr-1"></i>Ver comprobante
                        </a>
                    @endif
                </div>
                <span class="text-xs px-2 py-1 rounded-full bg-{{ $pc }}-100 text-{{ $pc }}-700">
                    {{ $payLabels[$pay->status] ?? $pay->status }}
                </span>
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-6">
                Sin pagos registrados.
                @if($order->client && $order->client->portal_token)
                <br><a href="{{ route('portal.checkout', [$order->client->portal_token, $order]) }}"
                    target="_blank" class="text-blue-600 hover:underline text-xs mt-1 inline-block">
                    Ver checkout del cliente →
                </a>
                @endif
            </p>
            @endforelse
        </div>
    </div>

    {{-- Columna derecha: cliente --}}
    <div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Cliente</h3>
            @if($order->client)
            <div class="space-y-2 text-sm">
                <p class="font-medium text-base">{{ $order->client->name ?? $order->client->legal_name }}</p>
                <p class="font-mono text-gray-600">{{ $order->client->tax_id }}</p>
                <p class="text-gray-500">{{ $order->client->tax_system }}</p>
                @if($order->client->email)<p>{{ $order->client->email }}</p>@endif
                @if($order->client->phone)<p>{{ $order->client->phone }}</p>@endif
                <p class="text-gray-500 text-xs mt-2">CP {{ $order->client->address_zip }}, {{ $order->client->address_city }}</p>
            </div>
            <a href="{{ route('admin.clients.show', $order->client) }}" class="mt-3 inline-block text-sm text-blue-600 hover:underline">Ver cliente →</a>
            @endif
        </div>

        {{-- Portal del cliente --}}
        @if($order->client && $order->client->portal_token)
        <div class="bg-white rounded-lg shadow p-6 mt-4">
            <h3 class="text-sm font-semibold mb-3 text-gray-700">Portal del cliente</h3>
            <div class="space-y-2">
                @if(!$order->isPaid())
                <a href="{{ route('portal.checkout', [$order->client->portal_token, $order]) }}"
                    target="_blank"
                    class="flex items-center gap-2 text-sm text-blue-600 hover:underline">
                    <i class="fas fa-external-link-alt text-xs"></i>
                    Ver checkout del cliente
                </a>
                @endif
                <a href="{{ route('portal.dashboard', $order->client->portal_token) }}"
                    target="_blank"
                    class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-user text-xs"></i>
                    Portal del cliente
                </a>
            </div>
        </div>
        @elseif($order->client)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4 text-sm text-yellow-700">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            El cliente no tiene portal activo.
            <a href="{{ route('admin.clients.show', $order->client) }}" class="underline">Activarlo →</a>
        </div>
        @endif

        @if($order->status === 'draft' && !$order->isPaid())
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4 text-sm text-blue-700">
            <p class="font-medium mb-1"><i class="fas fa-info-circle mr-1"></i> Siguiente paso</p>
            <p>Registra el pago o envía el link de cobro al cliente para habilitar el timbrado.</p>
        </div>
        @endif
    </div>
</div>

{{-- Modal pago manual --}}
<div id="manualPayModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Registrar Pago Manual</h3>
        <form action="{{ route('admin.orders.pay-manual', $order) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto recibido</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                        value="{{ $order->total }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago (SAT)</label>
                    <select name="payment_form" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                        <option value="03">03 - Transferencia electrónica</option>
                        <option value="01">01 - Efectivo</option>
                        <option value="04">04 - Tarjeta de crédito</option>
                        <option value="28">28 - Tarjeta de débito</option>
                        <option value="02">02 - Cheque</option>
                        <option value="99">99 - Por definir</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                    <input type="text" name="notes" placeholder="Ej: Ref. transferencia #12345"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Comprobante de pago <span class="font-normal text-gray-400">(opcional — PDF, JPG o PNG, máx. 5 MB)</span>
                    </label>
                    <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit"
                    class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
                    <i class="fas fa-check mr-1"></i> Confirmar Pago
                </button>
                <button type="button"
                    onclick="document.getElementById('manualPayModal').classList.add('hidden')"
                    class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 text-sm">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal cancelar (SAT) --}}
<div id="cancelModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Cancelar Factura ante el SAT</h3>
        <p class="text-sm text-gray-600 mb-4">Selecciona el motivo de cancelación (requerido por el SAT):</p>
        <form action="{{ route('admin.orders.fiscal-document.cancel', $order) }}" method="POST">
            @csrf @method('DELETE')
            <select name="motive" required class="w-full border rounded-lg px-3 py-2 text-sm mb-4">
                <option value="02">02 - Comprobante emitido con errores con relación</option>
                <option value="03">03 - No se llevó a cabo la operación</option>
                <option value="04">04 - Operación nominativa relacionada en factura global</option>
                <option value="01">01 - Comprobante emitido con errores sin relación</option>
            </select>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 text-sm">
                    Confirmar Cancelación
                </button>
                <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden')"
                    class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 text-sm">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal anular orden (sin SAT) --}}
<div id="voidModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-2">Anular Orden</h3>
        <p class="text-sm text-gray-600 mb-6">Esta orden aún no ha sido timbrada, por lo que puede anularse sin trámite ante el SAT. Esta acción no se puede deshacer.</p>
        <form action="{{ route('admin.orders.void', $order) }}" method="POST">
            @csrf @method('PATCH')
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 text-sm">
                    Anular Orden
                </button>
                <button type="button" onclick="document.getElementById('voidModal').classList.add('hidden')"
                    class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 text-sm">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
