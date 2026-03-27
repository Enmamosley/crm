@extends('layouts.admin')
@section('title', 'Paquetes de Servicios')
@section('header', 'Paquetes de Servicios')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
    @endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">Grupos de servicios que se agregan juntos a una cotización.</p>
        <a href="{{ route('admin.service-bundles.create') }}"
           class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
            <i class="fas fa-plus mr-2"></i> Nuevo Paquete
        </a>
    </div>

    @if($bundles->isEmpty())
    <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">
        <i class="fas fa-box-open text-4xl mb-3"></i>
        <p>Aún no hay paquetes. <a href="{{ route('admin.service-bundles.create') }}" class="text-blue-600 hover:underline">Crear el primero</a></p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($bundles as $bundle)
        <div class="bg-white rounded-lg shadow hover:shadow-md transition flex flex-col">
            <div class="p-5 flex-1">
                <div class="flex items-start justify-between mb-2">
                    <h3 class="font-semibold text-gray-900">{{ $bundle->name }}</h3>
                    @if($bundle->active)
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Activo</span>
                    @else
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Inactivo</span>
                    @endif
                </div>
                @if($bundle->description)
                <p class="text-sm text-gray-500 mb-3">{{ $bundle->description }}</p>
                @endif

                <ul class="space-y-1">
                    @foreach($bundle->services as $service)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-700">
                            <span class="text-gray-400 mr-1">×{{ $service->pivot->quantity }}</span>
                            {{ $service->name }}
                        </span>
                        <span class="text-gray-500">${{ number_format($service->price * $service->pivot->quantity, 2) }}</span>
                    </li>
                    @endforeach
                </ul>

                <div class="mt-3 pt-3 border-t flex justify-between text-sm font-semibold text-gray-800">
                    <span>Total estimado</span>
                    <span>${{ number_format($bundle->totalPrice(), 2) }}</span>
                </div>
            </div>

            <div class="px-5 py-3 bg-gray-50 rounded-b-lg flex justify-end gap-3">
                <a href="{{ route('admin.service-bundles.edit', $bundle) }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    <i class="fas fa-pencil mr-1"></i> Editar
                </a>
                <form method="POST" action="{{ route('admin.service-bundles.destroy', $bundle) }}"
                      onsubmit="return confirm('¿Eliminar el paquete «{{ $bundle->name }}»?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium">
                        <i class="fas fa-trash mr-1"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
