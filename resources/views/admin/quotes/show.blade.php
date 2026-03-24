@extends('layouts.admin')
@section('title', $quote->quote_number)
@section('header', 'Cotización: ' . $quote->quote_number)

@section('actions')
@if($quote->status === 'borrador')
    <form action="{{ route('admin.quotes.send', $quote) }}" method="POST" class="inline">
        @csrf @method('PATCH')
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
            <i class="fas fa-paper-plane mr-1"></i> Marcar como Enviada
        </button>
    </form>
@endif

@php $existingInvoice = $quote->invoices->first(); @endphp
@if($existingInvoice)
    <a href="{{ route('admin.invoices.show', $existingInvoice) }}"
        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm">
        <i class="fas fa-file-invoice mr-1"></i> Ver Orden
    </a>
@elseif(!in_array($quote->status, ['rechazada','vencida']))
    <form action="{{ route('admin.quotes.convert', $quote) }}" method="POST" class="inline"
          onsubmit="return confirm('¿Convertir esta cotización en una orden de servicio?')">
        @csrf
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
            <i class="fas fa-arrow-right mr-1"></i> Convertir a Orden
        </button>
    </form>
@endif

<a href="{{ route('admin.quotes.pdf', $quote) }}" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
    <i class="fas fa-file-pdf mr-1"></i> PDF
</a>
<a href="{{ route('admin.quotes.edit', $quote) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-edit mr-1"></i> Editar
</a>
@endsection

@section('content')
{{-- Barra de flujo --}}
@php
    $flowStep = match($quote->status) {
        'borrador'  => 1,
        'enviada'   => 2,
        'aceptada'  => 3,
        'rechazada' => 0,
        'vencida'   => 0,
        default     => 1,
    };
    $existingInvoice = $existingInvoice ?? $quote->invoices->first();
    if ($existingInvoice) {
        if ($existingInvoice->isPaid())        $flowStep = 4;
        elseif ($existingInvoice->isStamped()) $flowStep = 5;
        else $flowStep = 3;
    }
    $steps = [
        ['label' => 'Cotización', 'icon' => 'fa-file-alt'],
        ['label' => 'Enviada',    'icon' => 'fa-paper-plane'],
        ['label' => 'Orden',      'icon' => 'fa-clipboard-list'],
        ['label' => 'Pago',       'icon' => 'fa-credit-card'],
        ['label' => 'Factura',    'icon' => 'fa-stamp'],
    ];
@endphp
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="flex items-center justify-between">
        @foreach($steps as $i => $step)
            @php $n = $i + 1; $done = $flowStep >= $n; $active = $flowStep === $n; @endphp
            <div class="flex items-center {{ $i < count($steps)-1 ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm
                        {{ $done ? 'bg-green-500 text-white' : ($active ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-400') }}">
                        @if($done && !$active)
                            <i class="fas fa-check text-xs"></i>
                        @else
                            <i class="fas {{ $step['icon'] }} text-xs"></i>
                        @endif
                    </div>
                    <span class="text-xs mt-1 {{ $done ? 'text-green-600 font-medium' : 'text-gray-400' }}">{{ $step['label'] }}</span>
                </div>
                @if($i < count($steps)-1)
                    <div class="flex-1 h-0.5 mx-2 mb-4 {{ $flowStep > $n ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                @endif
            </div>
        @endforeach
    </div>
    @if($quote->status === 'rechazada')
        <p class="text-center text-sm text-red-500 mt-2"><i class="fas fa-times-circle mr-1"></i>Cotización rechazada por el cliente</p>
    @elseif($quote->status === 'vencida')
        <p class="text-center text-sm text-yellow-600 mt-2"><i class="fas fa-clock mr-1"></i>Cotización vencida</p>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Info del lead -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Cliente</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">Nombre:</span><p class="font-medium">{{ $quote->lead->name }}</p></div>
                <div><span class="text-gray-500">Email:</span><p class="font-medium">{{ $quote->lead->email ?? '-' }}</p></div>
                <div><span class="text-gray-500">Teléfono:</span><p class="font-medium">{{ $quote->lead->phone ?? '-' }}</p></div>
                <div><span class="text-gray-500">Negocio:</span><p class="font-medium">{{ $quote->lead->business ?? '-' }}</p></div>
            </div>
        </div>

        <!-- Detalle de items -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Detalle de Servicios</h3>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Servicio</th>
                        <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($quote->items as $item)
                        <tr>
                            <td class="px-6 py-4">{{ $item->description }}</td>
                            <td class="px-6 py-4 text-center">{{ $item->quantity }}</td>
                            <td class="px-6 py-4 text-right">${{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-6 py-4 text-right font-medium">${{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm text-gray-500">Subtotal:</td>
                        <td class="px-6 py-3 text-right font-medium">${{ number_format($quote->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right text-sm text-gray-500">IVA ({{ $quote->iva_percentage }}%):</td>
                        <td class="px-6 py-3 text-right font-medium">${{ number_format($quote->iva_amount, 2) }}</td>
                    </tr>
                    <tr class="border-t-2">
                        <td colspan="3" class="px-6 py-3 text-right text-lg font-semibold">Total:</td>
                        <td class="px-6 py-3 text-right text-lg font-bold text-green-600">${{ number_format($quote->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($quote->notes)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-2">Notas</h3>
                <p class="text-sm text-gray-700">{{ $quote->notes }}</p>
            </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Información</h3>
            <div class="space-y-3 text-sm">
                <div>
                    <span class="text-gray-500">Número:</span>
                    <p class="font-medium">{{ $quote->quote_number }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Estado:</span>
                    <p>
                        <span class="px-2 py-1 text-xs rounded-full
                            {{ $quote->status === 'borrador' ? 'bg-gray-100 text-gray-600' : '' }}
                            {{ $quote->status === 'enviada' ? 'bg-blue-100 text-blue-600' : '' }}
                            {{ $quote->status === 'aceptada' ? 'bg-green-100 text-green-600' : '' }}
                            {{ $quote->status === 'rechazada' ? 'bg-red-100 text-red-600' : '' }}
                            {{ $quote->status === 'vencida' ? 'bg-yellow-100 text-yellow-600' : '' }}
                        ">{{ ucfirst($quote->status) }}</span>
                    </p>
                </div>
                <div>
                    <span class="text-gray-500">Creada:</span>
                    <p class="font-medium">{{ $quote->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Vigente hasta:</span>
                    <p class="font-medium {{ $quote->isExpired() ? 'text-red-500' : '' }}">
                        {{ $quote->valid_until->format('d/m/Y') }}
                        @if($quote->isExpired()) (vencida) @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Cliente vinculado --}}
        @if($client)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold mb-3 text-gray-700">Cliente</h3>
            <p class="text-sm font-medium">{{ $client->legal_name }}</p>
            <p class="text-xs text-gray-400 font-mono">{{ $client->tax_id }}</p>
            @if($client->email)<p class="text-xs text-gray-500 mt-1">{{ $client->email }}</p>@endif
            <a href="{{ route('admin.clients.show', $client) }}" class="text-xs text-blue-600 hover:underline mt-2 inline-block">Ver cliente →</a>
        </div>
        @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-700">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Sin cliente registrado.
            <a href="{{ route('admin.clients.create', ['lead_id' => $quote->lead_id]) }}" class="underline font-medium">Crear cliente</a>
            para poder generar la orden de servicio.
        </div>
        @endif

        {{-- Órdenes/Facturas vinculadas --}}
        @if($quote->invoices->count())
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold mb-3 text-gray-700">Órdenes de servicio</h3>
            @foreach($quote->invoices as $inv)
            @php
                $invColors = ['draft'=>'gray','pending'=>'yellow','valid'=>'green','cancelled'=>'red'];
                $invLabels = ['draft'=>'Borrador','pending'=>'Procesando','valid'=>'Timbrada','cancelled'=>'Cancelada'];
                $ic = $invColors[$inv->status] ?? 'gray';
            @endphp
            <a href="{{ route('admin.invoices.show', $inv) }}"
                class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 mb-1">
                <div>
                    <p class="text-sm font-medium">{{ $inv->folio() ?: 'Sin folio' }}</p>
                    <p class="text-xs text-gray-400">${{ number_format($inv->total, 2) }}</p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full bg-{{ $ic }}-100 text-{{ $ic }}-700">
                    {{ $invLabels[$inv->status] ?? $inv->status }}
                    @if($inv->isPaid()) · Pagada @endif
                </span>
            </a>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
