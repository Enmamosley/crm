@extends('layouts.admin')
@section('title', 'Correos — ' . $client->legal_name)
@section('header', 'Correos de ' . $client->legal_name)

@section('actions')
<a href="{{ route('admin.domains.index') }}" class="bg-teal-50 text-teal-700 px-4 py-2 rounded-lg hover:bg-teal-100 text-sm border border-teal-200">
    <i class="fas fa-globe mr-1"></i> Buscar dominio
</a>
<a href="{{ route('admin.clients.show', $client) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-arrow-left mr-1"></i> Volver al cliente
</a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Alertas --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif
    @if($error)
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3">
            <i class="fas fa-exclamation-triangle mr-1"></i> Error al conectar con 20i: {{ $error }}
        </div>
    @endif

    @if(!$client->twentyi_package_id)
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-4 py-4">
            <p class="font-medium"><i class="fas fa-info-circle mr-1"></i> Este cliente no tiene un Package ID de 20i configurado.</p>
            <p class="text-sm mt-1">Edita el cliente y añade el ID del paquete de 20i.</p>
            <a href="{{ route('admin.clients.edit', $client) }}" class="mt-2 inline-block text-sm underline">Editar cliente →</a>
        </div>
    @elseif(!config('services.twentyi.configured', \App\Models\Setting::get('twentyi_api_key')))
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-4 py-4">
            <p class="font-medium"><i class="fas fa-key mr-1"></i> Falta configurar la API Key de 20i.</p>
            <a href="{{ route('admin.settings') }}" class="mt-2 inline-block text-sm underline">Ir a Ajustes →</a>
        </div>
    @else

    {{-- Tabla de buzones --}}
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold">
                <i class="fas fa-envelope text-blue-500 mr-2"></i>
                Buzones de correo
                @if($domain)
                    <span class="ml-2 text-sm font-normal text-gray-500">{{ $domain }}</span>
                @endif
            </h3>
        </div>

        @if(count($mailboxes))
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Correo</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Cuota</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Uso</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($mailboxes as $box)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-mono">{{ $box['local'] ?? '-' }}@if($domain)<span class="text-gray-400">{{ '@' . $domain }}</span>@endif</td>
                        <td class="px-6 py-4 text-gray-600">{{ isset($box['quotaMB']) ? $box['quotaMB'] . ' MB' : '-' }}</td>
                        <td class="px-6 py-4 text-gray-600">
                            @if(isset($box['usageMB']) && isset($box['quotaMB']) && $box['quotaMB'] > 0)
                                {{ number_format($box['usageMB'], 1) }} MB
                                <div class="w-24 bg-gray-200 rounded-full h-1.5 mt-1">
                                    <div class="bg-blue-500 h-1.5 rounded-full"
                                         style="width: {{ min(100, round($box['usageMB'] / $box['quotaMB'] * 100)) }}%"></div>
                                </div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($box['enabled'] ?? true)
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Activo</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Desactivado</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">

                            {{-- Webmail --}}
                            <form method="POST" action="{{ route('admin.clients.mailboxes.webmail', [$client, $box['id']]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium" title="Abrir Webmail">
                                    <i class="fas fa-globe mr-1"></i>Webmail
                                </button>
                            </form>

                            {{-- Cambiar contraseña --}}
                            <button onclick="openPasswordModal('{{ $box['id'] }}')"
                                    class="text-yellow-600 hover:text-yellow-900 text-xs font-medium">
                                <i class="fas fa-key mr-1"></i>Contraseña
                            </button>

                            {{-- Eliminar --}}
                            <form method="POST"
                                  action="{{ route('admin.clients.mailboxes.destroy', [$client, $box['id']]) }}"
                                  class="inline"
                                  onsubmit="return confirm('¿Eliminar el buzón {{ $box['local'] ?? '' }}? Esta acción no se puede deshacer.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium">
                                    <i class="fas fa-trash mr-1"></i>Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-10 text-center text-gray-400">
            <i class="fas fa-inbox text-3xl mb-2 block"></i>
            No hay buzones creados aún.
        </div>
        @endif
    </div>

    {{-- Formulario crear buzón --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-plus-circle text-green-500 mr-2"></i>Nuevo buzón</h3>
        <form method="POST" action="{{ route('admin.clients.mailboxes.store', $client) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre local (antes del @)</label>
                <div class="flex">
                    <input type="text" name="local" value="{{ old('local') }}"
                           placeholder="usuario"
                           class="flex-1 border-gray-300 rounded-l-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                           required pattern="[a-zA-Z0-9._+\-]+"
                           title="Solo letras, números, puntos, guiones y +">
                    @if($domain)
                    <span class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">{{ '@' . $domain }}</span>
                    @endif
                </div>
                @error('local')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <input type="password" name="password"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                       required minlength="8" autocomplete="new-password">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cuota (MB)</label>
                <input type="number" name="quota_mb" value="{{ old('quota_mb', 2048) }}"
                       min="100" max="51200"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                @error('quota_mb')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-4">
                <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> Crear buzón
                </button>
            </div>
        </form>
    </div>

    @endif
</div>

{{-- Modal cambiar contraseña --}}
<div id="passwordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h4 class="text-lg font-semibold mb-4">Cambiar contraseña</h4>
        <form id="passwordForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                <input type="password" name="password" id="modalPassword"
                       required minlength="8" autocomplete="new-password"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closePasswordModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</button>
                <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 text-sm font-medium">
                    Actualizar
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openPasswordModal(mailboxId) {
    const base = '{{ route('admin.clients.mailboxes.password', [$client, '__ID__']) }}';
    document.getElementById('passwordForm').action = base.replace('__ID__', mailboxId);
    document.getElementById('modalPassword').value = '';
    document.getElementById('passwordModal').classList.remove('hidden');
}
function closePasswordModal() {
    document.getElementById('passwordModal').classList.add('hidden');
}
document.getElementById('passwordModal').addEventListener('click', function(e){
    if (e.target === this) closePasswordModal();
});
</script>
@endpush
@endsection
