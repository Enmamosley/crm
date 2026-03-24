@extends('layouts.admin')
@section('title', 'Usuarios')
@section('header', 'Gestión de Usuarios')

@section('content')
<div class="mb-6 flex justify-end">
    <a href="{{ route('admin.users.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
        <i class="fas fa-plus mr-1"></i> Nuevo Usuario
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Email</th>
                <th class="text-center px-4 py-3">Rol</th>
                <th class="text-right px-4 py-3">Creado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                <td class="px-4 py-3">{{ $user->email }}</td>
                <td class="px-4 py-3 text-center">
                    @php
                        $roleColors = ['admin'=>'red','sales'=>'blue','accounting'=>'green'];
                        $roleLabels = ['admin'=>'Administrador','sales'=>'Ventas','accounting'=>'Contabilidad'];
                        $rc = $roleColors[$user->role] ?? 'gray';
                    @endphp
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-{{ $rc }}-100 text-{{ $rc }}-700">
                        {{ $roleLabels[$user->role] ?? $user->role }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right text-gray-500">{{ $user->created_at->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:text-blue-800 mr-2">
                        <i class="fas fa-edit"></i>
                    </a>
                    @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('¿Eliminar este usuario?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4 border-t">{{ $users->links() }}</div>
</div>
@endsection
