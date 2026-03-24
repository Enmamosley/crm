@extends('layouts.admin')
@section('title', 'Log de Actividad')
@section('header', 'Log de Actividad')

@section('content')
<div class="mb-6">
    <form method="GET" class="bg-white rounded-lg shadow p-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Acción</label>
            <select name="action" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>{{ $act }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Descripción..." class="border rounded-lg px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
            <i class="fas fa-filter mr-1"></i> Filtrar
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3">Fecha</th>
                    <th class="text-left px-4 py-3">Usuario</th>
                    <th class="text-left px-4 py-3">Acción</th>
                    <th class="text-left px-4 py-3">Descripción</th>
                    <th class="text-left px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($logs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3">{{ $log->user->name ?? 'Sistema' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ $log->action }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $log->description }}</td>
                    <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $log->ip_address }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin registros de actividad.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t">
        {{ $logs->links() }}
    </div>
</div>
@endsection
