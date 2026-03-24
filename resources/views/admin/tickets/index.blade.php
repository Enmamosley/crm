@extends('layouts.admin')
@section('title', 'Tickets de Soporte')
@section('header', 'Tickets de Soporte')

@section('content')
<!-- Filtros -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="{{ route('admin.tickets.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm text-gray-600 mb-1">Estado</label>
            <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach(\App\Models\SupportTicket::STATUSES as $st)
                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Prioridad</label>
            <select name="priority" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach(\App\Models\SupportTicket::PRIORITIES as $pr)
                    <option value="{{ $pr }}" {{ request('priority') === $pr ? 'selected' : '' }}>{{ ucfirst($pr) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Asignado a</label>
            <select name="assigned_to" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm">
                <i class="fas fa-search mr-1"></i> Filtrar
            </button>
            <a href="{{ route('admin.tickets.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Limpiar</a>
        </div>
    </form>
</div>

<!-- Tabla de tickets -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">#</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Asunto</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cliente</th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Prioridad</th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Asignado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($tickets as $ticket)
                @php
                    $prColors = ['low' => 'gray', 'medium' => 'blue', 'high' => 'orange', 'urgent' => 'red'];
                    $stColors = ['open' => 'blue', 'in_progress' => 'yellow', 'waiting' => 'purple', 'resolved' => 'green', 'closed' => 'gray'];
                    $prColor = $prColors[$ticket->priority] ?? 'gray';
                    $stColor = $stColors[$ticket->status] ?? 'gray';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium">#{{ $ticket->id }}</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-medium text-blue-600 hover:underline text-sm">
                            {{ $ticket->subject }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->client->legal_name ?? '—' }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-{{ $prColor }}-100 text-{{ $prColor }}-700">{{ ucfirst($ticket->priority) }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-{{ $stColor }}-100 text-{{ $stColor }}-700">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->assignee->name ?? 'Sin asignar' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-gray-500 hover:text-blue-600"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">No hay tickets de soporte.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="p-4 border-t">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
