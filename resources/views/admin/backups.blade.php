@extends('layouts.admin')
@section('title', 'Backups')
@section('header', 'Respaldos de Base de Datos')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <p class="text-gray-500 text-sm">Respaldos automáticos diarios a las 2:00 AM. Máximo 10 respaldos almacenados.</p>
    <form method="POST" action="{{ route('admin.backups.create') }}">
        @csrf
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
            <i class="fas fa-plus mr-1"></i> Crear backup ahora
        </button>
    </form>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    </div>
@endif

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Archivo</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tamaño</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($files as $file)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                        <i class="fas fa-database text-gray-400 mr-2"></i>{{ $file['name'] }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $file['size'] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $file['date'] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <a href="{{ route('admin.backups.download', $file['name']) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            <i class="fas fa-download mr-1"></i> Descargar
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                        <i class="fas fa-database text-3xl mb-2"></i>
                        <p>No hay backups aún. Crea el primero.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
