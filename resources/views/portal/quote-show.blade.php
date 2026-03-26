<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización {{ $quote->quote_number }} — Portal</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <a href="{{ route('portal.dashboard', $client->portal_token) }}" class="text-sm text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i> Volver al portal</a>
                <h1 class="text-xl font-bold text-gray-900 mt-1">Cotización {{ $quote->quote_number }}</h1>
            </div>
            @php
                $colors = ['borrador'=>'gray','enviada'=>'blue','aceptada'=>'green','rechazada'=>'red','vencida'=>'yellow'];
                $c = $colors[$quote->status] ?? 'gray';
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-{{ $c }}-100 text-{{ $c }}-700">
                {{ ucfirst($quote->status) }}
            </span>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <div class="p-6 border-b">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Válida hasta:</span> <strong>{{ $quote->valid_until?->format('d/m/Y') }}</strong></div>
                    <div><span class="text-gray-500">Fecha:</span> <strong>{{ $quote->created_at->format('d/m/Y') }}</strong></div>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Servicio</th>
                        <th class="text-center px-4 py-3 text-gray-600 font-medium">Cant.</th>
                        <th class="text-right px-4 py-3 text-gray-600 font-medium">Precio Unit.</th>
                        <th class="text-right px-6 py-3 text-gray-600 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($quote->items as $item)
                    <tr>
                        <td class="px-6 py-3">
                            <p class="font-medium">{{ $item->description }}</p>
                            @if($item->service?->description)
                                <p class="text-xs text-gray-400 mt-0.5">{{ Str::limit($item->service->description, 80) }}</p>
                            @endif
                            @if($item->service?->info_url)
                                <a href="{{ $item->service->info_url }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 mt-1 font-medium">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>
                                    Más información
                                </a>
                            @endif
                        </td>
                        <td class="text-center px-4 py-3">{{ $item->quantity }}</td>
                        <td class="text-right px-4 py-3">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right px-6 py-3 font-medium">${{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="bg-gray-50 px-6 py-4">
                <div class="flex justify-end space-y-1 text-sm">
                    <div class="w-48">
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal:</span> <span>${{ number_format($quote->subtotal, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">IVA ({{ $quote->iva_percentage }}%):</span> <span>${{ number_format($quote->iva_amount, 2) }}</span></div>
                        <div class="flex justify-between text-lg font-bold mt-2 pt-2 border-t"><span>Total:</span> <span>${{ number_format($quote->total, 2) }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        @if($quote->notes)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-600 mb-2">Notas</h3>
            <p class="text-sm text-gray-700">{{ $quote->notes }}</p>
        </div>
        @endif

        @if($quote->status === 'enviada')
        <div class="flex gap-4 justify-center">
            <form method="POST" action="{{ route('portal.quote.accept', [$client->portal_token, $quote]) }}">
                @csrf
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-8 py-3 rounded-lg transition" onclick="return confirm('¿Está seguro de aceptar esta cotización?')">
                    <i class="fas fa-check mr-2"></i> Aceptar Cotización
                </button>
            </form>
            <form method="POST" action="{{ route('portal.quote.reject', [$client->portal_token, $quote]) }}">
                @csrf
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-8 py-3 rounded-lg transition" onclick="return confirm('¿Está seguro de rechazar esta cotización?')">
                    <i class="fas fa-times mr-2"></i> Rechazar Cotización
                </button>
            </form>
        </div>
        @endif
    </main>
</body>
</html>
