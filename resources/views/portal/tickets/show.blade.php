<!DOCTYPE html>
<html lang="es">
<head>
    <title>Ticket #{{ $ticket->id }} — {{ $client->legal_name }}</title>
    @include('buy._head')
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="" class="h-8">
                @endif
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Ticket #{{ $ticket->id }}</h1>
                    <p class="text-xs text-gray-400">{{ $client->legal_name }}</p>
                </div>
            </div>
            <a href="{{ route('portal.tickets.index', $client->portal_token) }}"
               class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Mis Tickets
            </a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-8 space-y-6">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
        @endif

        @php
            $stColors = ['open' => 'blue', 'in_progress' => 'yellow', 'waiting' => 'purple', 'resolved' => 'green', 'closed' => 'gray'];
            $stLabels = ['open' => 'Abierto', 'in_progress' => 'En progreso', 'waiting' => 'Esperando', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
            $prColors = ['low' => 'gray', 'medium' => 'blue', 'high' => 'orange', 'urgent' => 'red'];
            $sc = $stColors[$ticket->status] ?? 'gray';
            $pc = $prColors[$ticket->priority] ?? 'gray';
        @endphp

        <!-- Info del ticket -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 animate-fade-in">
            <div class="flex items-start justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900">{{ $ticket->subject }}</h2>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ ucfirst($ticket->priority) }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ $stLabels[$ticket->status] ?? $ticket->status }}</span>
                </div>
            </div>
            <p class="text-gray-700 whitespace-pre-line text-sm">{{ $ticket->description }}</p>
            <p class="text-xs text-gray-400 mt-3">Creado {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
        </div>

        <!-- Conversación -->
        <div class="space-y-3">
            @foreach($ticket->replies as $reply)
                <div class="animate-slide-up {{ $reply->client_id ? 'ml-0' : 'ml-8' }}">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 {{ $reply->user_id ? 'border-l-4 border-brand-400' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium {{ $reply->user_id ? 'text-brand-700' : 'text-gray-700' }}">
                                {{ $reply->user_id ? ($reply->user->name ?? 'Soporte') : 'Tú' }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $reply->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $reply->body }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Formulario de respuesta -->
        @if(!in_array($ticket->status, ['closed']))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                <form action="{{ route('portal.tickets.reply', [$client->portal_token, $ticket]) }}" method="POST">
                    @csrf
                    <textarea name="body" rows="3" required class="input-field mb-3"
                        placeholder="Escribe tu respuesta..."></textarea>
                    <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Enviar
                    </button>
                </form>
            </div>
        @else
            <div class="text-center py-4 text-sm text-gray-500">
                <i class="fas fa-lock mr-1"></i> Este ticket está cerrado.
            </div>
        @endif
    </main>
</body>
</html>
