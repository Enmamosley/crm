@extends('layouts.admin')
@section('title', 'Permisos de ' . $user->name)
@section('header', 'Permisos: ' . $user->name)

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-lg shadow p-6">
        @if($user->role === 'admin')
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-700"><i class="fas fa-info-circle mr-1"></i> Los administradores tienen acceso total. Los permisos individuales no aplican.</p>
            </div>
        @endif

        <form action="{{ route('admin.users.permissions.update', $user) }}" method="POST">
            @csrf @method('PUT')

            @php
                $groups = [
                    'Leads' => ['leads.view_all', 'leads.view_own', 'leads.manage'],
                    'Clientes' => ['clients.view', 'clients.manage'],
                    'Cotizaciones' => ['quotes.view', 'quotes.manage'],
                    'Facturas' => ['invoices.view', 'invoices.manage'],
                    'Reportes' => ['reports.view'],
                    'Tickets' => ['tickets.view_all', 'tickets.view_own', 'tickets.manage'],
                    'Configuración' => ['settings.manage'],
                ];
            @endphp

            @foreach($groups as $group => $perms)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">{{ $group }}</h3>
                    <div class="space-y-2">
                        @foreach($perms as $perm)
                            <label class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                    {{ in_array($perm, $userPermissions) ? 'checked' : '' }}
                                    {{ $user->role === 'admin' ? 'disabled' : '' }}
                                    class="rounded text-blue-600">
                                <div>
                                    <span class="text-sm text-gray-800">{{ $available[$perm] ?? $perm }}</span>
                                    <span class="text-xs text-gray-400 ml-1 font-mono">({{ $perm }})</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex gap-3 pt-4 border-t">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700" {{ $user->role === 'admin' ? 'disabled' : '' }}>
                    <i class="fas fa-save mr-1"></i> Guardar Permisos
                </button>
                <a href="{{ route('admin.users.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Volver</a>
            </div>
        </form>
    </div>
</div>
@endsection
