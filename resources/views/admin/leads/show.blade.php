@extends('layouts.admin')
@section('title', $lead->name)
@section('header', 'Lead: ' . $lead->name)

@section('actions')
<a href="{{ route('admin.leads.edit', $lead) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-edit mr-1"></i> Editar
</a>
<a href="{{ route('admin.quotes.create', ['lead_id' => $lead->id]) }}" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
    <i class="fas fa-file-invoice-dollar mr-1"></i> Crear Cotización
</a>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Info principal -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Datos del lead -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Información del Lead</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Nombre:</span>
                    <p class="font-medium">{{ $lead->name }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Email:</span>
                    <p class="font-medium">{{ $lead->email ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Teléfono:</span>
                    <p class="font-medium">{{ $lead->phone ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Negocio:</span>
                    <p class="font-medium">{{ $lead->business ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Origen:</span>
                    <p class="font-medium">{{ ucfirst($lead->source ?? '-') }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Creado:</span>
                    <p class="font-medium">{{ $lead->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            @if($lead->project_description)
                <div class="mt-4 pt-4 border-t">
                    <span class="text-gray-500 text-sm">Descripción del Proyecto:</span>
                    <p class="mt-1 text-sm text-gray-700">{{ $lead->project_description }}</p>
                </div>
            @endif
        </div>

        <!-- Notas -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Notas</h3>

            <form action="{{ route('admin.leads.add-note', $lead) }}" method="POST" class="mb-4">
                @csrf
                <div class="flex gap-2">
                    <textarea name="content" rows="2" placeholder="Agregar una nota..." required
                        class="flex-1 border rounded-lg px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 self-end">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </form>

            <div class="space-y-3">
                @forelse($lead->notes->sortByDesc('created_at') as $note)
                    <div class="border rounded-lg p-3">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-medium text-blue-600">{{ $note->author }}</span>
                            <span class="text-xs text-gray-400">{{ $note->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700">{{ $note->content }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">Sin notas aún.</p>
                @endforelse
            </div>
        </div>

        <!-- Cotizaciones -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Cotizaciones</h3>
            @forelse($lead->quotes as $quote)
                <a href="{{ route('admin.quotes.show', $quote) }}" class="flex justify-between items-center p-3 border rounded-lg mb-2 hover:bg-gray-50">
                    <div>
                        <span class="font-medium">{{ $quote->quote_number }}</span>
                        <span class="text-sm text-gray-500 ml-2">{{ $quote->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="text-right">
                        <span class="font-semibold">${{ number_format($quote->total, 2) }}</span>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full
                            {{ $quote->status === 'borrador' ? 'bg-gray-100 text-gray-600' : '' }}
                            {{ $quote->status === 'enviada' ? 'bg-blue-100 text-blue-600' : '' }}
                            {{ $quote->status === 'aceptada' ? 'bg-green-100 text-green-600' : '' }}
                            {{ $quote->status === 'rechazada' ? 'bg-red-100 text-red-600' : '' }}
                            {{ $quote->status === 'vencida' ? 'bg-yellow-100 text-yellow-600' : '' }}
                        ">{{ ucfirst($quote->status) }}</span>
                    </div>
                </a>
            @empty
                <p class="text-sm text-gray-500 text-center py-4">Sin cotizaciones.</p>
            @endforelse
        </div>

        <!-- Tareas -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Tareas</h3>
                <a href="{{ route('admin.tasks.create', ['lead_id' => $lead->id]) }}"
                    class="text-xs text-blue-600 hover:underline">
                    <i class="fas fa-plus mr-1"></i> Nueva tarea
                </a>
            </div>
            @forelse($lead->tasks->sortBy(fn($t) => [$t->status === 'done' ? 1 : 0, $t->due_at]) as $task)
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
                <p class="text-sm text-gray-500 text-center py-4">Sin tareas.
                    <a href="{{ route('admin.tasks.create', ['lead_id' => $lead->id]) }}" class="text-blue-600 hover:underline">Crear una</a>
                </p>
            @endforelse
        </div>
    </div>

    <!-- Sidebar derecho -->
    <div class="space-y-6">
        <!-- Estado -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Estado</h3>
            <div class="mb-4">
                <span class="inline-flex px-3 py-1 text-sm rounded-full font-medium
                    {{ $lead->status === 'nuevo' ? 'bg-blue-100 text-blue-700' : '' }}
                    {{ $lead->status === 'contactado' ? 'bg-yellow-100 text-yellow-700' : '' }}
                    {{ $lead->status === 'cotizado' ? 'bg-purple-100 text-purple-700' : '' }}
                    {{ $lead->status === 'cerrado' ? 'bg-green-100 text-green-700' : '' }}
                    {{ $lead->status === 'perdido' ? 'bg-red-100 text-red-700' : '' }}
                ">{{ ucfirst($lead->status) }}</span>
            </div>

            <form action="{{ route('admin.leads.update-status', $lead) }}" method="POST">
                @csrf @method('PATCH')
                <label class="block text-sm text-gray-600 mb-1">Cambiar estado:</label>
                <select name="status" class="w-full border rounded-lg px-3 py-2 mb-2 text-sm">
                    @foreach(\App\Models\Lead::STATUSES as $status)
                        <option value="{{ $status }}" {{ $lead->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="w-full bg-gray-800 text-white py-2 rounded-lg hover:bg-gray-900 text-sm">
                    Actualizar Estado
                </button>
            </form>
        </div>

        <!-- Historial de estados -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Historial</h3>
            <div class="space-y-3">
                @forelse($lead->statusHistory as $history)
                    <div class="flex items-start text-sm">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 mr-3 flex-shrink-0"></div>
                        <div>
                            <p class="text-gray-700">
                                {{ $history->old_status ? ucfirst($history->old_status) . ' →' : '' }}
                                <strong>{{ ucfirst($history->new_status) }}</strong>
                            </p>
                            <p class="text-xs text-gray-400">{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->changed_by }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Sin historial.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
