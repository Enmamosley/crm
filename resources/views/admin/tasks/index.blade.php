@extends('layouts.admin')
@section('title', 'Tareas')
@section('header', 'Tareas internas')

@section('actions')
<a href="{{ route('admin.tasks.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
    <i class="fas fa-plus mr-1"></i> Nueva Tarea
</a>
@endsection

@section('content')
<!-- Filtros -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="{{ route('admin.tasks.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm text-gray-600 mb-1">Estado</label>
            <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach(\App\Models\Task::STATUS_LABELS as $val => $label)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Prioridad</label>
            <select name="priority" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach(\App\Models\Task::PRIORITY_LABELS as $val => $label)
                    <option value="{{ $val }}" {{ request('priority') === $val ? 'selected' : '' }}>{{ $label }}</option>
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
        <div>
            <label class="block text-sm text-gray-600 mb-1">Cliente</label>
            <select name="client_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->legal_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm w-full">
                <i class="fas fa-search mr-1"></i> Filtrar
            </button>
            <a href="{{ route('admin.tasks.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">✕</a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase w-8"></th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Tarea</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Prioridad</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Vence</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Asignado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Contexto</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($tasks as $task)
                @php
                    $priorityClasses = ['low' => 'bg-gray-100 text-gray-600', 'medium' => 'bg-yellow-100 text-yellow-700', 'high' => 'bg-red-100 text-red-700'];
                    $statusClasses   = ['todo' => 'bg-blue-100 text-blue-700', 'in_progress' => 'bg-purple-100 text-purple-700', 'done' => 'bg-green-100 text-green-700'];
                @endphp
                <tr class="hover:bg-gray-50 {{ $task->status === 'done' ? 'opacity-60' : '' }}">
                    <td class="px-6 py-4">
                        <form method="POST" action="{{ route('admin.tasks.complete', $task) }}">
                            @csrf @method('PATCH')
                            <button type="submit" title="{{ $task->status === 'done' ? 'Reabrir' : 'Marcar completada' }}"
                                class="w-5 h-5 rounded border-2 flex items-center justify-center transition
                                    {{ $task->status === 'done' ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400' }}">
                                @if($task->status === 'done')
                                    <i class="fas fa-check text-xs"></i>
                                @endif
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800 {{ $task->status === 'done' ? 'line-through' : '' }}">{{ $task->title }}</p>
                        @if($task->description)
                            <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $task->description }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium {{ $priorityClasses[$task->priority] }}">
                            {{ \App\Models\Task::PRIORITY_LABELS[$task->priority] }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium {{ $statusClasses[$task->status] }}">
                            {{ \App\Models\Task::STATUS_LABELS[$task->status] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if($task->due_at)
                            <span class="{{ $task->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                {{ $task->due_at->format('d/m/Y') }}
                                @if($task->isOverdue()) <i class="fas fa-exclamation-triangle ml-1"></i> @endif
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $task->assignee?->name ?? '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if($task->client)
                            <a href="{{ route('admin.clients.show', $task->client) }}" class="text-blue-600 hover:underline">
                                <i class="fas fa-user mr-1 text-xs"></i>{{ $task->client->legal_name }}
                            </a>
                        @elseif($task->lead)
                            <a href="{{ route('admin.leads.show', $task->lead) }}" class="text-indigo-600 hover:underline">
                                <i class="fas fa-funnel-dollar mr-1 text-xs"></i>{{ $task->lead->name }}
                            </a>
                        @else
                            <span class="text-gray-400">General</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.tasks.edit', $task) }}" class="text-gray-500 hover:text-blue-600 text-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}"
                                onsubmit="return confirm('¿Eliminar esta tarea?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-600 text-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-check-circle text-4xl mb-3 block"></i>
                        No hay tareas con los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($tasks->hasPages())
        <div class="px-6 py-4 border-t">
            {{ $tasks->links() }}
        </div>
    @endif
</div>
@endsection
