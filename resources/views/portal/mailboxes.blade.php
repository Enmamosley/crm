<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correo electrónico — {{ $client->legal_name }}</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Correo electrónico</h1>
                <p class="text-sm text-gray-500">{{ $client->legal_name }}@if($domain) · <span class="font-mono">{{ $domain }}</span>@endif</p>
            </div>
            <a href="{{ route('portal.dashboard', $client->portal_token) }}"
               class="text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-1"></i> Volver al portal
            </a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8 space-y-6">

        {{-- Alertas --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 flex items-center gap-2 text-sm">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 flex items-center gap-2 text-sm">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif
        @if($error ?? null)
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                <i class="fas fa-exclamation-triangle mr-1"></i> Error al conectar con el servicio: {{ $error }}
            </div>
        @endif

        {{-- Lista de buzones --}}
        <div class="bg-white rounded-lg shadow">
            <div class="p-5 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold">
                    <i class="fas fa-envelope text-indigo-500 mr-2"></i>Buzones
                    <span class="text-sm font-normal text-gray-400 ml-1">({{ count($mailboxes) }})</span>
                </h2>
            </div>

            @if(count($mailboxes))
            <div class="divide-y">
                @foreach($mailboxes as $box)
                <div class="px-5 py-4 flex items-center justify-between hover:bg-gray-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 bg-indigo-50 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-at text-indigo-500 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-sm font-mono truncate">{{ $box['local'] }}@if($domain)<span class="text-gray-400">{{ '@' . $domain }}</span>@endif</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                @if(isset($box['usageMB']) && isset($box['quotaMB']))
                                    {{ number_format($box['usageMB'], 1) }} / {{ number_format($box['quotaMB']) }} MB
                                @endif
                                @if(!($box['enabled'] ?? true))
                                    · <span class="text-red-500">Desactivado</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Webmail --}}
                        <form method="POST" action="{{ route('portal.mailbox.webmail', [$client->portal_token, $box['id']]) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium border border-indigo-200 rounded px-2.5 py-1.5 hover:bg-indigo-50 transition" title="Abrir Webmail">
                                <i class="fas fa-arrow-up-right-from-square mr-1"></i>Webmail
                            </button>
                        </form>

                        {{-- Cambiar contraseña --}}
                        <button onclick="openPasswordModal('{{ $box['id'] }}', '{{ $box['local'] }}')"
                                class="text-yellow-600 hover:text-yellow-800 text-xs font-medium border border-yellow-200 rounded px-2.5 py-1.5 hover:bg-yellow-50 transition" title="Cambiar contraseña">
                            <i class="fas fa-key mr-1"></i>Contraseña
                        </button>

                        {{-- Eliminar --}}
                        <form method="POST"
                              action="{{ route('portal.mailboxes.destroy', [$client->portal_token, $box['id']]) }}"
                              class="inline"
                              onsubmit="return confirm('¿Eliminar el buzón {{ $box['local'] . '@' . ($domain ?? '') }}? Esta acción no se puede deshacer.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium border border-red-200 rounded px-2.5 py-1.5 hover:bg-red-50 transition" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-10 text-center text-gray-400">
                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                No hay buzones creados aún.
            </div>
            @endif
        </div>

        {{-- Formulario crear buzón --}}
        @if($client->twentyi_package_id && \App\Models\Setting::get('twentyi_api_key'))
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-base font-semibold mb-4"><i class="fas fa-plus-circle text-green-500 mr-2"></i>Nuevo buzón</h3>
            <form method="POST" action="{{ route('portal.mailboxes.store', $client->portal_token) }}">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección de correo</label>
                        <div class="flex">
                            <input type="text" name="local" value="{{ old('local') }}"
                                   placeholder="usuario"
                                   class="flex-1 border-gray-300 rounded-l-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
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
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                               required minlength="8" autocomplete="new-password"
                               placeholder="Mínimo 8 caracteres">
                        @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium transition">
                        <i class="fas fa-plus mr-1"></i> Crear buzón
                    </button>
                </div>
            </form>
        </div>
        @endif
    </main>

    <footer class="text-center py-8 text-xs text-gray-400">
        Portal privado · {{ $client->legal_name }} · {{ now()->year }}
    </footer>

    {{-- Modal cambiar contraseña --}}
    <div id="passwordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 mx-4">
            <h4 class="text-lg font-semibold mb-1">Cambiar contraseña</h4>
            <p id="modalMailboxName" class="text-sm text-gray-500 mb-4 font-mono"></p>
            <form id="passwordForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                    <input type="password" name="password" id="modalPassword"
                           required minlength="8" autocomplete="new-password"
                           placeholder="Mínimo 8 caracteres"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closePasswordModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</button>
                    <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 text-sm font-medium transition">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openPasswordModal(mailboxId, local) {
        const base = '{{ route('portal.mailboxes.password', [$client->portal_token, '__ID__']) }}';
        document.getElementById('passwordForm').action = base.replace('__ID__', mailboxId);
        document.getElementById('modalMailboxName').textContent = local + '{{ '@' . ($domain ?? '') }}';
        document.getElementById('modalPassword').value = '';
        document.getElementById('passwordModal').classList.remove('hidden');
    }
    function closePasswordModal() {
        document.getElementById('passwordModal').classList.add('hidden');
    }
    document.getElementById('passwordModal').addEventListener('click', function(e) {
        if (e.target === this) closePasswordModal();
    });
    </script>
</body>
</html>
