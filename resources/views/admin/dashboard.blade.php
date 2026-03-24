@extends('layouts.admin')
@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
<!-- Métricas principales -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Leads</p>
                <p class="text-3xl font-bold text-gray-800">{{ $metrics['total_leads'] }}</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Leads Nuevos</p>
                <p class="text-3xl font-bold text-green-600">{{ $metrics['leads_nuevos'] }}</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <i class="fas fa-star text-green-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Cotizaciones</p>
                <p class="text-3xl font-bold text-purple-600">{{ $metrics['total_cotizaciones'] }}</p>
            </div>
            <div class="bg-purple-100 p-3 rounded-full">
                <i class="fas fa-file-invoice text-purple-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Monto Cotizado</p>
                <p class="text-3xl font-bold text-yellow-600">${{ number_format($metrics['monto_total_cotizado'], 2) }}</p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-full">
                <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Métricas financieras -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Ingresos del Mes</p>
                <p class="text-3xl font-bold text-emerald-600">${{ number_format($metrics['ingresos_mes'], 2) }}</p>
            </div>
            <div class="bg-emerald-100 p-3 rounded-full">
                <i class="fas fa-chart-line text-emerald-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Por Cobrar</p>
                <p class="text-3xl font-bold text-orange-600">${{ number_format($metrics['monto_por_cobrar'], 2) }}</p>
            </div>
            <div class="bg-orange-100 p-3 rounded-full">
                <i class="fas fa-clock text-orange-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Clientes</p>
                <p class="text-3xl font-bold text-indigo-600">{{ $metrics['total_clientes'] }}</p>
            </div>
            <div class="bg-indigo-100 p-3 rounded-full">
                <i class="fas fa-building text-indigo-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Tasa Conversión</p>
                <p class="text-3xl font-bold text-teal-600">{{ $metrics['tasa_conversion'] }}%</p>
            </div>
            <div class="bg-teal-100 p-3 rounded-full">
                <i class="fas fa-percentage text-teal-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Estado del pipeline + Agente -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Pipeline -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Pipeline de Leads</h3>
        <div class="grid grid-cols-5 gap-2">
            @php
                $pipelineColors = [
                    'leads_nuevos' => 'blue',
                    'leads_contactados' => 'yellow',
                    'leads_cotizados' => 'purple',
                    'leads_cerrados' => 'green',
                    'leads_perdidos' => 'red',
                ];
                $pipelineLabels = [
                    'leads_nuevos' => 'Nuevos',
                    'leads_contactados' => 'Contactados',
                    'leads_cotizados' => 'Cotizados',
                    'leads_cerrados' => 'Cerrados',
                    'leads_perdidos' => 'Perdidos',
                ];
                $pipelineValues = [
                    'leads_nuevos' => 'valor_nuevos',
                    'leads_contactados' => 'valor_contactados',
                    'leads_cotizados' => 'valor_cotizados',
                    'leads_cerrados' => 'valor_cerrados',
                    'leads_perdidos' => 'valor_perdidos',
                ];
            @endphp
            @foreach($pipelineColors as $key => $color)
                <div class="text-center p-4 bg-{{ $color }}-50 rounded-lg">
                    <p class="text-2xl font-bold text-{{ $color }}-600">{{ $metrics[$key] }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $pipelineLabels[$key] }}</p>
                    @if($metrics[$pipelineValues[$key]] > 0)
                        <p class="text-xs font-semibold text-{{ $color }}-500 mt-1">${{ number_format($metrics[$pipelineValues[$key]], 0) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Estado Agente -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Estado del Agente</h3>
        <div class="text-center">
            @if($metrics['agent_paused'])
                <div class="inline-flex items-center px-4 py-2 bg-red-100 text-red-700 rounded-full text-lg font-semibold">
                    <i class="fas fa-pause-circle mr-2"></i> Pausado
                </div>
            @else
                <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-full text-lg font-semibold">
                    <i class="fas fa-play-circle mr-2"></i> Activo
                </div>
            @endif
            <div class="mt-4">
                <a href="{{ route('admin.agent.index') }}" class="text-blue-600 hover:underline text-sm">
                    Gestionar agente →
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Leads recientes y Cotizaciones recientes -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Leads recientes -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">Leads Recientes</h3>
            <a href="{{ route('admin.leads.index') }}" class="text-blue-600 hover:underline text-sm">Ver todos</a>
        </div>
        <div class="divide-y">
            @forelse($recent_leads as $lead)
                <a href="{{ route('admin.leads.show', $lead) }}" class="flex items-center justify-between p-4 hover:bg-gray-50">
                    <div>
                        <p class="font-medium text-gray-800">{{ $lead->name }}</p>
                        <p class="text-sm text-gray-500">{{ $lead->business ?? $lead->email }}</p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full
                        {{ $lead->status === 'nuevo' ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $lead->status === 'contactado' ? 'bg-yellow-100 text-yellow-700' : '' }}
                        {{ $lead->status === 'cotizado' ? 'bg-purple-100 text-purple-700' : '' }}
                        {{ $lead->status === 'cerrado' ? 'bg-green-100 text-green-700' : '' }}
                        {{ $lead->status === 'perdido' ? 'bg-red-100 text-red-700' : '' }}
                    ">{{ ucfirst($lead->status) }}</span>
                </a>
            @empty
                <p class="p-4 text-gray-500 text-center">No hay leads registrados.</p>
            @endforelse
        </div>
    </div>

    <!-- Cotizaciones recientes -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">Cotizaciones Recientes</h3>
            <a href="{{ route('admin.quotes.index') }}" class="text-blue-600 hover:underline text-sm">Ver todas</a>
        </div>
        <div class="divide-y">
            @forelse($recent_quotes as $quote)
                <a href="{{ route('admin.quotes.show', $quote) }}" class="flex items-center justify-between p-4 hover:bg-gray-50">
                    <div>
                        <p class="font-medium text-gray-800">{{ $quote->quote_number }}</p>
                        <p class="text-sm text-gray-500">{{ $quote->lead->name ?? 'N/A' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-800">${{ number_format($quote->total, 2) }}</p>
                        <span class="text-xs text-gray-500">{{ ucfirst($quote->status) }}</span>
                    </div>
                </a>
            @empty
                <p class="p-4 text-gray-500 text-center">No hay cotizaciones.</p>
            @endforelse
        </div>
    </div>
</div>

<!-- Facturas pendientes de pago -->
@if($unpaid_invoices->isNotEmpty())
<div class="bg-white rounded-lg shadow mt-6">
    <div class="p-6 border-b flex justify-between items-center">
        <h3 class="text-lg font-semibold"><i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i>Facturas Pendientes de Pago</h3>
        <a href="{{ route('admin.invoices.index') }}" class="text-blue-600 hover:underline text-sm">Ver todas</a>
    </div>
    <div class="divide-y">
        @foreach($unpaid_invoices as $invoice)
            <a href="{{ route('admin.invoices.show', $invoice) }}" class="flex items-center justify-between p-4 hover:bg-gray-50">
                <div>
                    <p class="font-medium text-gray-800">{{ $invoice->folio() ?: 'Sin folio' }} — {{ $invoice->client->legal_name ?? 'N/A' }}</p>
                    <p class="text-sm text-gray-500">{{ $invoice->created_at->format('d/m/Y') }} · {{ $invoice->status === 'valid' ? 'Timbrada' : 'Borrador' }}</p>
                </div>
                <p class="font-bold text-orange-600">${{ number_format($invoice->total, 2) }}</p>
            </a>
        @endforeach
    </div>
</div>
@endif
@endsection
