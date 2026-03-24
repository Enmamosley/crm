@extends('layouts.admin')
@section('title', 'Clientes')
@section('header', 'Clientes')

@section('actions')
<a href="{{ route('admin.clients.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nuevo Cliente
</a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="{{ route('admin.clients.index') }}" method="GET" class="flex gap-4">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, RFC, email..."
            class="flex-1 border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm">
            <i class="fas fa-search mr-1"></i> Filtrar
        </button>
        <a href="{{ route('admin.clients.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Limpiar</a>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Nombre fiscal</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">RFC</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Lead</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">FacturAPI</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($clients as $client)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium">{{ $client->legal_name }}</td>
                    <td class="px-6 py-4 font-mono text-sm">{{ $client->tax_id }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $client->lead?->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm">{{ $client->email ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @if($client->facturapi_customer_id)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                <i class="fas fa-check mr-1"></i> Sincronizado
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                                Pendiente
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('admin.clients.show', $client) }}" class="text-blue-600 hover:text-blue-800 text-sm">Ver</a>
                        <a href="{{ route('admin.clients.edit', $client) }}" class="text-gray-600 hover:text-gray-800 text-sm">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">No hay clientes aún.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($clients->hasPages())
        <div class="px-6 py-4 border-t">{{ $clients->links() }}</div>
    @endif
</div>
@endsection
