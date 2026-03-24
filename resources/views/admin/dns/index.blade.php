@extends('layouts.admin')
@section('title', 'DNS – ' . $client->legal_name)
@section('header', 'Registros DNS')

@section('actions')
<a href="{{ route('admin.clients.show', $client) }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
    <i class="fas fa-arrow-left mr-1"></i> Volver al cliente
</a>
@endsection

@section('content')

{{-- Encabezado informativo --}}
<div class="mb-6 bg-white rounded-lg shadow p-5 flex items-start gap-4">
    <div class="text-indigo-500 text-2xl mt-0.5">
        <i class="fas fa-globe"></i>
    </div>
    <div>
        <p class="font-semibold text-gray-800">{{ $client->legal_name }}</p>
        <p class="text-sm text-gray-500 font-mono">{{ $client->domain ?: '(sin dominio configurado)' }}</p>
        <p class="text-xs text-gray-400 mt-1">Package 20i: <code class="font-mono">{{ $client->twentyi_package_id }}</code></p>
    </div>
    @if($client->domain)
    <div class="ml-auto text-right">
        @if($client->domain_type === 'cosmotown')
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Cosmotown</span>
        @elseif($client->domain_type === 'own')
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Dominio propio</span>
        @endif
    </div>
    @endif
</div>

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    </div>
@endif

@if(session('error') || $error)
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') ?: $error }}
    </div>
@endif

@if(!$configured)
    <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-4 py-4 text-sm">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        No hay API key de 20i configurada.
        <a href="{{ route('admin.settings.index') }}" class="font-medium underline ml-1">Ir a configuración →</a>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Tabla de registros --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="font-semibold text-gray-800">Registros actuales</h3>
            </div>

            @if(!empty($records))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Tipo</th>
                            <th class="px-4 py-3 text-left">Host</th>
                            <th class="px-4 py-3 text-left">Valor / Destino</th>
                            <th class="px-4 py-3 text-center">TTL</th>
                            <th class="px-4 py-3 text-center">Pri.</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($records as $record)
                        @php
                            $typeColors = [
                                'A'     => 'blue',
                                'AAAA'  => 'indigo',
                                'CNAME' => 'green',
                                'MX'    => 'orange',
                                'TXT'   => 'gray',
                                'NS'    => 'purple',
                                'SRV'   => 'pink',
                            ];
                            $type  = strtoupper($record['type'] ?? '?');
                            $color = $typeColors[$type] ?? 'gray';
                            $host  = $record['host'] ?? $record['name'] ?? '@';
                            $value = $record['ip'] ?? $record['content'] ?? $record['value'] ?? '-';
                            $ttl   = $record['ttl'] ?? '-';
                            $prio  = $record['priority'] ?? $record['pref'] ?? '-';
                            $id    = $record['id'] ?? null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-{{ $color }}-100 text-{{ $color }}-700">{{ $type }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ $host }}</td>
                            <td class="px-4 py-3 font-mono text-gray-600 max-w-xs truncate" title="{{ $value }}">{{ $value }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $ttl }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $prio !== '-' ? $prio : '' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($id)
                                <form action="{{ route('admin.clients.dns.destroy', [$client, $id]) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar este registro DNS?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @elseif(!$error && $configured)
                <div class="px-6 py-10 text-center text-gray-400 text-sm">
                    <i class="fas fa-network-wired text-3xl mb-2 opacity-30"></i>
                    <p>No hay registros DNS.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Formulario de añadir registro --}}
    <div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Añadir registro</h3>

            <form action="{{ route('admin.clients.dns.store', $client) }}" method="POST" class="space-y-4" x-data="{ type: '{{ old('type', 'A') }}' }">
                @csrf

                <div>
                    <label class="block text-sm text-gray-600 mb-1">Tipo <span class="text-red-500">*</span></label>
                    <select name="type" x-model="type"
                        class="w-full border rounded-lg px-3 py-2 text-sm @error('type') border-red-400 @enderror">
                        @foreach(['A','AAAA','CNAME','MX','TXT','NS','SRV'] as $t)
                            <option value="{{ $t }}" {{ old('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 mb-1">Host <span class="text-red-500">*</span></label>
                    <input type="text" name="host" value="{{ old('host', '@') }}"
                        placeholder="@ o subdomain"
                        class="w-full border rounded-lg px-3 py-2 text-sm font-mono @error('host') border-red-400 @enderror">
                    @error('host')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 mb-1" x-text="['MX','CNAME','NS'].includes(type) ? 'Destino *' : type === 'TXT' ? 'Valor *' : 'IP / Valor *'"></label>
                    <input type="text" name="value" value="{{ old('value') }}"
                        class="w-full border rounded-lg px-3 py-2 text-sm font-mono @error('value') border-red-400 @enderror">
                    @error('value')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">TTL (seg.)</label>
                        <input type="number" name="ttl" value="{{ old('ttl', 3600) }}" min="60" max="86400"
                            class="w-full border rounded-lg px-3 py-2 text-sm @error('ttl') border-red-400 @enderror">
                        @error('ttl')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="['MX','SRV'].includes(type)">
                        <label class="block text-sm text-gray-600 mb-1">Prioridad</label>
                        <input type="number" name="priority" value="{{ old('priority', 10) }}" min="0" max="65535"
                            class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> Añadir registro
                </button>
            </form>
        </div>

        <div class="mt-4 bg-blue-50 border border-blue-100 rounded-lg p-4 text-xs text-blue-700 space-y-1">
            <p class="font-semibold">Ejemplos:</p>
            <p><span class="font-mono font-bold">A</span> – host <code>@</code>, IP del servidor</p>
            <p><span class="font-mono font-bold">CNAME</span> – host <code>www</code>, destino <code>dominio.com.</code></p>
            <p><span class="font-mono font-bold">MX</span> – host <code>@</code>, destino servidor de correo, prioridad 10</p>
            <p><span class="font-mono font-bold">TXT</span> – host <code>@</code>, valor SPF/DKIM/verificación</p>
        </div>
    </div>

</div>
@endsection
