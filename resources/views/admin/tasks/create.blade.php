@extends('layouts.admin')
@section('title', 'Nueva Tarea')
@section('header', 'Nueva Tarea')

@section('actions')
<a href="{{ route('admin.tasks.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-arrow-left mr-1"></i> Volver
</a>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.tasks.store') }}" method="POST">
            @csrf

            @if($selectedClient)
                <input type="hidden" name="client_id" value="{{ $selectedClient->id }}">
            @endif
            @if($selectedLead)
                <input type="hidden" name="lead_id" value="{{ $selectedLead->id }}">
            @endif

            @if($selectedClient || $selectedLead)
                <div class="mb-6 p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                    <i class="fas fa-link mr-1"></i>
                    Tarea vinculada a:
                    @if($selectedClient) <strong>{{ $selectedClient->legal_name }}</strong> (cliente) @endif
                    @if($selectedLead) <strong>{{ $selectedLead->name }}</strong> (lead) @endif
                </div>
            @endif

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('title') border-red-400 @enderror">
                @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="3"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(\App\Models\Task::STATUS_LABELS as $val => $label)
                            <option value="{{ $val }}" {{ old('status', 'todo') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                    <select name="priority" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(\App\Models\Task::PRIORITY_LABELS as $val => $label)
                            <option value="{{ $val }}" {{ old('priority', 'medium') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha límite</label>
                    <input type="date" name="due_at" value="{{ old('due_at') }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asignar a</label>
                    <select name="assigned_to" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">Sin asignar</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if(!$selectedClient && !$selectedLead)
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vincular a cliente</label>
                        <select name="client_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="">Sin cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->legal_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vincular a lead</label>
                        <select name="lead_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="">Sin lead</option>
                            @foreach($leads as $lead)
                                <option value="{{ $lead->id }}" {{ old('lead_id') == $lead->id ? 'selected' : '' }}>{{ $lead->name }}@if($lead->business) — {{ $lead->business }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-save mr-1"></i> Crear Tarea
                </button>
                <a href="{{ route('admin.tasks.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition text-sm">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
