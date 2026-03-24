<!DOCTYPE html>
<html lang="es">
<head>
    <title>Mis Tickets — {{ $client->legal_name }}</title>
    @include('buy._head')
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="" class="h-8">
                @endif
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Mis Tickets</h1>
                    <p class="text-xs text-gray-400">{{ $client->legal_name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('portal.tickets.create', $client->portal_token) }}"
                   class="btn-primary text-xs px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-1"></i> Nuevo Ticket
                </a>
                <a href="{{ route('portal.dashboard', $client->portal_token) }}"
                   class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium">
                    <i class="fas fa-arrow-left mr-1"></i> Portal
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
        @endif

        @if($tickets->isEmpty())
            <div class="text-center py-16 animate-fade-in">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-headset text-2xl text-gray-300"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">Sin tickets de soporte</h3>
                <p class="text-gray-400 mb-4">¿Necesitas ayuda? Crea un ticket y te responderemos pronto.</p>
                <a href="{{ route('portal.tickets.create', $client->portal_token) }}" class="btn-primary inline-block px-5 py-2.5 rounded-xl text-sm">
                    <i class="fas fa-plus mr-1"></i> Crear Ticket
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach($tickets as $ticket)
                    @php
                        $stColors = ['open' => 'blue', 'in_progress' => 'yellow', 'waiting' => 'purple', 'resolved' => 'green', 'closed' => 'gray'];
                        $stLabels = ['open' => 'Abierto', 'in_progress' => 'En progreso', 'waiting' => 'Esperando', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
                        $prColors = ['low' => 'gray', 'medium' => 'blue', 'high' => 'orange', 'urgent' => 'red'];
                        $sc = $stColors[$ticket->status] ?? 'gray';
                        $pc = $prColors[$ticket->priority] ?? 'gray';
                    @endphp
                    <a href="{{ route('portal.tickets.show', [$client->portal_token, $ticket]) }}"
                       class="block bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow animate-slide-up">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">{{ $ticket->subject }}</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    #{{ $ticket->id }} · {{ $ticket->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ ucfirst($ticket->priority) }}</span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ $stLabels[$ticket->status] ?? $ticket->status }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $tickets->links() }}
            </div>
        @endif
    </main>
</body>
</html>
