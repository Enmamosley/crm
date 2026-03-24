@extends('layouts.admin')
@section('title', 'Leads')
@section('header', 'Gestión de Leads')

@section('actions')
<a href="{{ route('admin.leads.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nuevo Lead
</a>
@endsection

@section('content')
<!-- Filtros -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="{{ route('admin.leads.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm text-gray-600 mb-1">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre, email, negocio..."
                class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Estado</label>
            <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach(\App\Models\Lead::STATUSES as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Asignado a</label>
            <select name="assigned_to" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach($salesUsers as $user)
                    <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm">
                <i class="fas fa-search mr-1"></i> Filtrar
            </button>
            <a href="{{ route('admin.leads.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Limpiar</a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Nombre</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Contacto</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Negocio</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Asignado</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Valor Est.</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($leads as $lead)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.leads.show', $lead) }}" class="font-medium text-blue-600 hover:underline">
                            {{ $lead->name }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        @if($lead->email)<div>{{ $lead->email }}</div>@endif
                        @if($lead->phone)<div>{{ $lead->phone }}</div>@endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $lead->business ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full
                            {{ $lead->status === 'nuevo' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $lead->status === 'contactado' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $lead->status === 'cotizado' ? 'bg-purple-100 text-purple-700' : '' }}
                            {{ $lead->status === 'cerrado' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $lead->status === 'perdido' ? 'bg-red-100 text-red-700' : '' }}
                        ">{{ ucfirst($lead->status) }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $lead->assignee->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm text-right text-gray-500">{{ $lead->estimated_value ? '$' . number_format($lead->estimated_value, 0) : '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $lead->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.leads.edit', $lead) }}" class="text-gray-500 hover:text-blue-600 mr-3">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.leads.destroy', $lead) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este lead?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">No se encontraron leads.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t">
        {{ $leads->links() }}
    </div>
</div>
@endsection
