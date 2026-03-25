<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS — {{ $client->domain }}</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

    <main class="max-w-5xl mx-auto px-6 py-8 space-y-6">

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
                                    $ttl   = $record['ttl'] ?? '-';
                                    $id    = $record['id'] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-{{ $color }}-100 text-{{ $color }}-700">{{ $type }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-700 text-xs">{{ $host }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-600 text-xs max-w-[200px] truncate" title="{{ $value }}">{{ $value }}</td>
                                    <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $ttl }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($id)
                                        <form action="{{ route('portal.dns.destroy', [$client->portal_token, $id]) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar este registro?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs" title="Eliminar">
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
