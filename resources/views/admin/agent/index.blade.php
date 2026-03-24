@extends('layouts.admin')
@section('title', 'Control del Agente')
@section('header', 'Control del Agente (OpenClaw)')

@section('content')
<!-- Filtro de canal -->
<div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center gap-4">
    <label class="text-sm font-medium text-gray-700">Ver canal:</label>
    <form method="GET" action="{{ route('admin.agent.index') }}" class="flex gap-2">
        <select name="channel" class="border rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
            @foreach(['general', 'whatsapp', 'instagram', 'web'] as $ch)
                <option value="{{ $ch }}" {{ $channel === $ch ? 'selected' : '' }}>{{ $ch }}</option>
            @endforeach
        </select>
    </form>
    <span class="text-sm text-gray-500">Estado del canal: <strong>{{ $channel }}</strong></span>
</div>

<!-- Estado actual -->
<div class="bg-white rounded-lg shadow p-8 mb-6 text-center">
    <h3 class="text-lg font-semibold mb-4">Estado Actual del Agente</h3>

    @if($isPaused)
        <div class="inline-flex items-center px-6 py-3 bg-red-100 text-red-700 rounded-full text-2xl font-bold mb-6">
            <i class="fas fa-pause-circle mr-3"></i> PAUSADO
        </div>

        <form action="{{ route('admin.agent.reactivate') }}" method="POST" class="max-w-md mx-auto">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Canal</label>
                <select name="channel" class="w-full border rounded-lg px-3 py-2">
                    @foreach(['general', 'whatsapp', 'instagram', 'web'] as $ch)
                        <option value="{{ $ch }}" {{ $channel === $ch ? 'selected' : '' }}>{{ $ch }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Razón (opcional)</label>
                <input type="text" name="reason" class="w-full border rounded-lg px-3 py-2" placeholder="Motivo de reactivación...">
            </div>
            <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 font-semibold text-lg">
                <i class="fas fa-play mr-2"></i> Reactivar Agente
            </button>
        </form>
    @else
        <div class="inline-flex items-center px-6 py-3 bg-green-100 text-green-700 rounded-full text-2xl font-bold mb-6">
            <i class="fas fa-play-circle mr-3"></i> ACTIVO
        </div>

        <form action="{{ route('admin.agent.pause') }}" method="POST" class="max-w-md mx-auto">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Canal</label>
                <select name="channel" class="w-full border rounded-lg px-3 py-2">
                    @foreach(['general', 'whatsapp', 'instagram', 'web'] as $ch)
                        <option value="{{ $ch }}" {{ $channel === $ch ? 'selected' : '' }}>{{ $ch }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Razón (opcional)</label>
                <input type="text" name="reason" class="w-full border rounded-lg px-3 py-2" placeholder="Motivo de pausa...">
            </div>
            <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-lg hover:bg-red-700 font-semibold text-lg">
                <i class="fas fa-pause mr-2"></i> Tomar Control (Pausar Agente)
            </button>
        </form>
    @endif
</div>

<!-- Historial -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold">Historial de Control</h3>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acción</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Canal</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Razón</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Usuario</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($history as $record)
                <tr>
                    <td class="px-6 py-4 text-sm">{{ $record->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full {{ $record->action === 'paused' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                            {{ $record->action === 'paused' ? 'Pausado' : 'Reactivado' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">{{ $record->channel }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $record->reason ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm">{{ $record->user->name ?? 'Sistema' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Sin historial.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t">{{ $history->links() }}</div>
</div>
@endsection
