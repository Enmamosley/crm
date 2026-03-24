@extends('layouts.admin')
@section('title', 'Ticket #' . $ticket->id)
@section('header', 'Ticket #' . $ticket->id . ': ' . $ticket->subject)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Info + Conversación -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Descripción -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Descripción</h3>
            <p class="text-gray-800 whitespace-pre-line">{{ $ticket->description }}</p>
            <p class="text-xs text-gray-400 mt-3">Creado {{ $ticket->created_at->format('d/m/Y H:i') }} por {{ $ticket->client->legal_name ?? 'Cliente' }}</p>
        </div>

        <!-- Respuestas -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Conversación</h3>
            </div>
            <div class="divide-y max-h-96 overflow-y-auto">
                @forelse($ticket->replies as $reply)
                    <div class="p-4 {{ $reply->is_internal ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium {{ $reply->user_id ? 'text-blue-700' : 'text-gray-700' }}">
                                {{ $reply->authorName() }}
                                @if($reply->is_internal)
                                    <span class="text-xs font-normal text-yellow-600 ml-1">(Nota interna)</span>
                                @endif
                            </span>
                            <span class="text-xs text-gray-400">{{ $reply->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $reply->body }}</p>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-500 text-sm">Sin respuestas aún.</div>
                @endforelse
            </div>
        </div>

        <!-- Formulario de respuesta -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Responder</h3>
            <form action="{{ route('admin.tickets.reply', $ticket) }}" method="POST">
                @csrf
                <textarea name="body" rows="3" required class="w-full border rounded-lg px-3 py-2 text-sm mb-3" placeholder="Escribe tu respuesta..."></textarea>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="is_internal" value="1" class="rounded">
                        Nota interna (no visible para el cliente)
                    </label>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="space-y-6">
        <!-- Propiedades -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Propiedades</h3>
            <form action="{{ route('admin.tickets.update', $ticket) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(\App\Models\SupportTicket::STATUSES as $st)
                            <option value="{{ $st }}" {{ $ticket->status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                    <select name="priority" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(\App\Models\SupportTicket::PRIORITIES as $pr)
                            <option value="{{ $pr }}" {{ $ticket->priority === $pr ? 'selected' : '' }}>{{ ucfirst($pr) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a</label>
                    <select name="assigned_to" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">Sin asignar</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $ticket->assigned_to == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm">
                    <i class="fas fa-save mr-1"></i> Actualizar
                </button>
            </form>
        </div>

        <!-- Info del cliente -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Cliente</h3>
            @if($ticket->client)
                <p class="font-medium text-gray-800">{{ $ticket->client->legal_name }}</p>
                <p class="text-sm text-gray-500">{{ $ticket->client->email }}</p>
                <p class="text-sm text-gray-500">{{ $ticket->client->phone ?? '' }}</p>
                <a href="{{ route('admin.clients.show', $ticket->client) }}" class="text-blue-600 hover:underline text-sm mt-2 block">
                    Ver perfil del cliente →
                </a>
            @else
                <p class="text-sm text-gray-500">Cliente no disponible</p>
            @endif
        </div>
    </div>
</div>
@endsection
