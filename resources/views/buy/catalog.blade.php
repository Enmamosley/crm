<!DOCTYPE html>
<html lang="es">
<head>
    <title>Servicios — {{ $companyName }}</title>
    @include('buy._head')
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-brand-50/30 min-h-screen" x-data="{ loginOpen: false, loginEmail: '', loginSent: false, loginLoading: false }">

    {{-- Modal magic link --}}
    <div x-show="loginOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
         @keydown.escape.window="loginOpen = false">
        <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-sm mx-4 relative" @click.stop>
            <div x-show="!loginSent">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Iniciar sesión</h2>
                <p class="text-sm text-gray-500 mb-5">Ingresa tu correo y te enviaremos un enlace de acceso.</p>
                <form @submit.prevent="
                    loginLoading = true;
                    fetch('{{ route('auth.magic.send') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ email: loginEmail })
                    })
                    .then(r => { loginSent = true; loginLoading = false; })
                    .catch(() => { loginLoading = false; })
                ">
                    <input type="email" x-model="loginEmail" required autofocus
                        placeholder="tucorreo@empresa.com"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent mb-4 outline-none">
                    <button type="submit"
                        :disabled="loginLoading || !loginEmail.trim()"
                        class="w-full bg-indigo-600 text-white py-3 rounded-xl font-semibold text-sm hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <svg x-show="loginLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <span x-text="loginLoading ? 'Enviando...' : 'Enviar enlace'"></span>
                    </button>
                </form>
            </div>
            <div x-show="loginSent" class="text-center py-4">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-envelope-open-text text-green-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-900 mb-1">¡Revisa tu correo!</h3>
                <p class="text-sm text-gray-500">Te enviamos un enlace de acceso a <strong x-text="loginEmail"></strong>. Es válido por 15 minutos.</p>
            </div>
            <button @click="loginOpen = false; loginSent = false; loginEmail = ''"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
    </div>

    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $companyName }}" class="h-10">
                @endif
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $companyName }}</h1>
                    <p class="text-sm text-gray-500">Elige el servicio que necesitas</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button @click="loginOpen = true"
                    class="text-sm text-gray-600 hover:text-indigo-600 font-medium transition flex items-center gap-1.5">
                    <i class="fas fa-lock text-xs"></i> Iniciar sesión
                </button>
                <a href="{{ route('buy.cart') }}" class="relative text-gray-600 hover:text-brand-600 transition">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    @if($cartCount > 0)
                        <span class="absolute -top-2 -right-2 bg-brand-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-6">
        @forelse($services as $category => $items)
            <div class="mb-7 animate-fade-in">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 bg-brand-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-layer-group text-brand-600 text-sm"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $category }}</h2>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($items as $index => $service)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex flex-col justify-between card-hover animate-slide-up"
                             style="animation-delay: {{ $index * 100 }}ms">
                            <div>
                                <div class="w-9 h-9 bg-brand-50 rounded-xl flex items-center justify-center mb-3">
                                    <i class="fas fa-cube text-brand-500"></i>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">{{ $service->name }}</h3>
                                @if($service->description)
                                    <p class="text-sm text-gray-500 leading-relaxed mb-3">{{ $service->description }}</p>
                                @else
                                    <div class="mb-3"></div>
                                @endif
                            </div>
                            <div>
                                <div class="border-t pt-3 mb-3">
                                    <div class="flex items-end gap-1">
                                        <span class="text-2xl font-extrabold text-gray-900">${{ number_format($service->price, 0) }}</span>
                                        <span class="text-sm text-gray-400 mb-0.5">.{{ substr(number_format($service->price, 2), -2) }} + IVA</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5">Total: ${{ number_format($service->priceWithIva(), 2) }} MXN</p>
                                </div>
                                <a href="{{ $service->slug ? route('buy.show', $service->slug) : '#' }}"
                                   class="btn-primary block w-full text-center px-4 py-2.5 rounded-xl text-sm">
                                    Comprar ahora <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                                <form action="{{ route('buy.cart.add') }}" method="POST" class="mt-1.5">
                                    @csrf
                                    <input type="hidden" name="service_id" value="{{ $service->id }}">
                                    <button type="submit" class="w-full text-center px-4 py-2.5 rounded-xl text-sm border border-brand-200 text-brand-600 hover:bg-brand-50 transition font-medium">
                                        <i class="fas fa-cart-plus mr-1"></i> Agregar al carrito
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-24 animate-fade-in">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-box-open text-3xl text-gray-300"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">Sin servicios disponibles</h3>
                <p class="text-gray-400">Vuelve pronto, estamos preparando algo para ti.</p>
            </div>
        @endforelse
    </main>

    <footer class="text-center py-4 text-xs text-gray-400">
        <p>&copy; {{ date('Y') }} {{ $companyName }} · Todos los precios en MXN</p>
    </footer>
</body>
</html>
