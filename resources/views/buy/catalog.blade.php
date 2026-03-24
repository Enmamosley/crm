<!DOCTYPE html>
<html lang="es">
<head>
    <title>Servicios — {{ $companyName }}</title>
    @include('buy._head')
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-brand-50/30 min-h-screen">

    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $companyName }}" class="h-10">
                @endif
                <div class="text-center">
                    <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $companyName }}</h1>
                    <p class="text-sm text-gray-500">Elige el servicio que necesitas</p>
                </div>
            </div>
            <a href="{{ route('buy.cart') }}" class="relative text-gray-600 hover:text-brand-600 transition">
                <i class="fas fa-shopping-cart text-xl"></i>
                @if($cartCount > 0)
                    <span class="absolute -top-2 -right-2 bg-brand-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-10">
        @forelse($services as $category => $items)
            <div class="mb-12 animate-fade-in">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 bg-brand-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-layer-group text-brand-600 text-sm"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $category }}</h2>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($items as $index => $service)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between card-hover animate-slide-up"
                             style="animation-delay: {{ $index * 100 }}ms">
                            <div>
                                <div class="w-10 h-10 bg-brand-50 rounded-xl flex items-center justify-center mb-4">
                                    <i class="fas fa-cube text-brand-500"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-1">{{ $service->name }}</h3>
                                @if($service->description)
                                    <p class="text-sm text-gray-500 leading-relaxed mb-4">{{ $service->description }}</p>
                                @else
                                    <div class="mb-4"></div>
                                @endif
                            </div>
                            <div>
                                <div class="border-t pt-4 mb-4">
                                    <div class="flex items-end gap-1">
                                        <span class="text-3xl font-extrabold text-gray-900">${{ number_format($service->price, 0) }}</span>
                                        <span class="text-sm text-gray-400 mb-1">.{{ substr(number_format($service->price, 2), -2) }} + IVA</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Total: ${{ number_format($service->priceWithIva(), 2) }} MXN</p>
                                </div>
                                <a href="{{ $service->slug ? route('buy.show', $service->slug) : '#' }}"
                                   class="btn-primary block w-full text-center px-4 py-3 rounded-xl text-sm">
                                    Comprar ahora <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                                <form action="{{ route('buy.cart.add') }}" method="POST" class="mt-2">
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

    <footer class="text-center py-8 text-xs text-gray-400">
        <p>&copy; {{ date('Y') }} {{ $companyName }} · Todos los precios en MXN</p>
    </footer>
</body>
</html>
