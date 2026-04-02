<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS — {{ $client->domain }}</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gray-50 min-h-screen">

    <header class="bg-white shadow-sm border-b">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Registros DNS</h1>
                <p class="text-sm text-gray-500">{{ $client->legal_name }} · <span class="font-mono font-semibold text-blue-700">{{ $client->domain }}</span></p>
            </div>
            <a href="{{ route('portal.dashboard', $client->portal_token) }}"
               class="text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-1"></i> Volver al portal
            </a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-8 space-y-6" x-data="{ editId: null, editType: '', editHost: '', editValue: '', editTtl: 3600, editPriority: 10 }">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if(session('error') || $error)
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') ?: $error }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Tabla de registros --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h2 class="font-semibold text-gray-800">Registros actuales</h2>
                    </div>

                    @if(!empty($records))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-left">Tipo</th>
                                    <th class="px-4 py-3 text-left">Host</th>
                                    <th class="px-4 py-3 text-left">Valor</th>
                                    <th class="px-4 py-3 text-center">TTL</th>
                                    <th class="px-4 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($records as $record)
                                @php
                                    $typeColors = ['A'=>'blue','AAAA'=>'indigo','CNAME'=>'green','MX'=>'orange','TXT'=>'gray','NS'=>'purple','SRV'=>'pink'];
                                    $type  = strtoupper($record['type'] ?? '?');
                                    $color = $typeColors[$type] ?? 'gray';
                                    $host  = $record['host'] ?? $record['name'] ?? '@';
                                    $value = $record['ip'] ?? $record['ipv6'] ?? $record['target'] ?? $record['txt'] ?? $record['content'] ?? '-';
                                    $ttl   = $record['ttl'] ?? 3600;
                                    $pri   = $record['pri'] ?? $record['priority'] ?? 10;
                                    $id    = $record['id'] ?? null;
                                @endphp
                                {{-- Fila normal --}}
                                <tr class="hover:bg-gray-50" x-show="editId !== '{{ $id }}'">
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-{{ $color }}-100 text-{{ $color }}-700">{{ $type }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-700 text-xs">{{ $host }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-600 text-xs max-w-[200px] truncate" title="{{ $value }}">{{ $value }}</td>
                                    <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $ttl }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($id)
                                            <button type="button" title="Editar"
                                                @click="editId='{{ $id }}'; editType='{{ $type }}'; editHost='{{ $host }}'; editValue='{{ addslashes($value) }}'; editTtl={{ (int)$ttl }}; editPriority={{ (int)$pri }}"
                                                class="text-blue-400 hover:text-blue-600 text-xs">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="{{ route('portal.dns.destroy', [$client->portal_token, $id]) }}" method="POST"
                                                  onsubmit="return confirm('¿Eliminar este registro?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-400 hover:text-red-600 text-xs" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                {{-- Fila de edición inline --}}
                                @if($id)
                                <tr x-show="editId === '{{ $id }}'" x-cloak>
                                    <td colspan="5" class="px-4 py-3 bg-blue-50">
                                        <form action="{{ route('portal.dns.update', [$client->portal_token, $id]) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="flex flex-wrap items-end gap-2">
                                                <div>
                                                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                                                    <select name="type" x-model="editType" class="border rounded px-2 py-1.5 text-xs">
                                                        @foreach(['A','AAAA','CNAME','MX','TXT'] as $t)
                                                            <option value="{{ $t }}">{{ $t }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-500 mb-1">Host</label>
                                                    <input type="text" name="host" x-model="editHost" class="border rounded px-2 py-1.5 text-xs font-mono w-28">
                                                </div>
                                                <div class="flex-1 min-w-32">
                                                    <label class="block text-xs text-gray-500 mb-1" x-text="['MX','CNAME'].includes(editType) ? 'Destino' : editType === 'TXT' ? 'Valor' : 'IP'"></label>
                                                    <input type="text" name="value" x-model="editValue" class="border rounded px-2 py-1.5 text-xs font-mono w-full">
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-500 mb-1">TTL</label>
                                                    <input type="number" name="ttl" x-model="editTtl" min="60" max="86400" class="border rounded px-2 py-1.5 text-xs w-20">
                                                </div>
                                                <div x-show="editType === 'MX'">
                                                    <label class="block text-xs text-gray-500 mb-1">Prioridad</label>
                                                    <input type="number" name="priority" x-model="editPriority" min="0" class="border rounded px-2 py-1.5 text-xs w-16">
                                                </div>
                                                <div class="flex gap-2">
                                                    <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs hover:bg-blue-700">
                                                        <i class="fas fa-save mr-1"></i> Guardar
                                                    </button>
                                                    <button type="button" @click="editId=null" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs hover:bg-gray-300">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                        <div class="px-6 py-10 text-center text-gray-400 text-sm">
                            <i class="fas fa-network-wired text-3xl mb-2 opacity-30"></i>
                            <p>No se encontraron registros DNS.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Formulario añadir --}}
            <div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="font-semibold text-gray-800 mb-4">Añadir registro</h3>

                    <form action="{{ route('portal.dns.store', $client->portal_token) }}" method="POST" class="space-y-4" x-data="{ type: '{{ old('type', 'A') }}' }">
                        @csrf

                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Tipo</label>
                            <select name="type" x-model="type"
                                class="w-full border rounded-lg px-3 py-2 text-sm">
                                @foreach(['A','AAAA','CNAME','MX','TXT'] as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Host</label>
                            <input type="text" name="host" value="{{ old('host', '@') }}"
                                placeholder="@ o subdominio"
                                class="w-full border rounded-lg px-3 py-2 text-sm font-mono">
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600 mb-1"
                                   x-text="['MX','CNAME'].includes(type) ? 'Destino' : type === 'TXT' ? 'Valor' : 'IP'"></label>
                            <input type="text" name="value" value="{{ old('value') }}"
                                class="w-full border rounded-lg px-3 py-2 text-sm font-mono">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">TTL</label>
                                <input type="number" name="ttl" value="{{ old('ttl', 3600) }}" min="60" max="86400"
                                    class="w-full border rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div x-show="type === 'MX'">
                                <label class="block text-sm text-gray-600 mb-1">Prioridad</label>
                                <input type="number" name="priority" value="{{ old('priority', 10) }}" min="0" max="65535"
                                    class="w-full border rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                            <i class="fas fa-plus mr-1"></i> Añadir
                        </button>
                    </form>
                </div>

                <div class="mt-4 bg-blue-50 border border-blue-100 rounded-lg p-4 text-xs text-blue-700 space-y-1">
                    <p class="font-semibold">Ejemplos:</p>
                    <p><span class="font-mono font-bold">A</span> — <code>@</code> → IP del servidor</p>
                    <p><span class="font-mono font-bold">CNAME</span> — <code>www</code> → <code>dominio.com.</code></p>
                    <p><span class="font-mono font-bold">MX</span> — <code>@</code> → servidor de correo</p>
                    <p><span class="font-mono font-bold">TXT</span> — <code>@</code> → SPF, DKIM, verificación</p>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
