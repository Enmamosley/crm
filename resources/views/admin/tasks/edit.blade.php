@extends('layouts.admin')
@section('title', 'Editar Tarea')
@section('header', 'Editar Tarea')

@section('actions')
<a href="{{ route('admin.tasks.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-arrow-left mr-1"></i> Volver
</a>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.tasks.update', $task) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $task->title) }}" required
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('title') border-red-400 @enderror">
                @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="3"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('description', $task->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(\App\Models\Task::STATUS_LABELS as $val => $label)
                            <option value="{{ $val }}" {{ old('status', $task->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                    <select name="priority" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(\App\Models\Task::PRIORITY_LABELS as $val => $label)
                            <option value="{{ $val }}" {{ old('priority', $task->priority) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha límite</label>
                    <input type="date" name="due_at" value="{{ old('due_at', $task->due_at?->format('Y-m-d')) }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a</label>
                    <select name="assigned_to" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">Sin asignar</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to', $task->assigned_to) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="client_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">Sin cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $task->client_id) == $client->id ? 'selected' : '' }}>{{ $client->legal_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead</label>
                    <select name="lead_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">Sin lead</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}" {{ old('lead_id', $task->lead_id) == $lead->id ? 'selected' : '' }}>{{ $lead->name }}@if($lead->business) — {{ $lead->business }}@endif</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($task->completed_at)
                <p class="text-xs text-gray-400 mb-4">
                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                    Completada el {{ $task->completed_at->format('d/m/Y H:i') }}
                </p>
            @endif

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-save mr-1"></i> Guardar Cambios
                </button>
                <a href="{{ route('admin.tasks.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition text-sm">
                    Cancelar
                </a>
                <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="ml-auto"
                    onsubmit="return confirm('¿Eliminar esta tarea?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-red-50 text-red-600 px-4 py-2 rounded-lg hover:bg-red-100 transition text-sm">
                        <i class="fas fa-trash mr-1"></i> Eliminar
                    </button>
                </form>
            </div>
        </form>
    </div>
</div>
@endsection
