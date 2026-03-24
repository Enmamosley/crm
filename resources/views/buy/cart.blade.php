<!DOCTYPE html>
<html lang="es">
<head>
    <title>Carrito — {{ $companyName }}</title>
    @include('buy._head')
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-brand-50/30 min-h-screen">

    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-4xl mx-auto px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $companyName }}" class="h-10">
                @endif
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900">Carrito</h1>
                    <p class="text-sm text-gray-500">{{ $items->count() }} producto(s)</p>
                </div>
            </div>
            <a href="{{ route('buy.catalog') }}" class="text-brand-600 hover:text-brand-800 text-sm font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Seguir comprando
            </a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-10">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">{{ session('error') }}</div>
        @endif

        @if($items->isEmpty())
            <div class="text-center py-24 animate-fade-in">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shopping-cart text-3xl text-gray-300"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">Tu carrito está vacío</h3>
                <p class="text-gray-400 mb-6">Explora nuestros servicios y agrega lo que necesites.</p>
                <a href="{{ route('buy.catalog') }}" class="btn-primary px-6 py-3 rounded-xl inline-block">
                    Ver catálogo <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Productos -->
                <div class="lg:col-span-2 space-y-3">
                    @foreach($items as $item)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center justify-between card-hover animate-slide-up">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-cube text-brand-500 text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900">{{ $item->service->name }}</h3>
                                    <p class="text-sm text-gray-500">{{ $item->service->category->name ?? 'General' }}</p>
                                    <div class="flex items-center gap-1 mt-1">
                                        <form action="{{ route('buy.cart.update-quantity', $item) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ max(1, $item->quantity - 1) }}">
                                            <button type="submit" class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs flex items-center justify-center transition" {{ $item->quantity <= 1 ? 'disabled' : '' }}>
                                                <i class="fas fa-minus text-[10px]"></i>
                                            </button>
                                        </form>
                                        <span class="w-7 text-center text-sm font-medium text-gray-700">{{ $item->quantity }}</span>
                                        <form action="{{ route('buy.cart.update-quantity', $item) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ min(10, $item->quantity + 1) }}">
                                            <button type="submit" class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs flex items-center justify-center transition" {{ $item->quantity >= 10 ? 'disabled' : '' }}>
                                                <i class="fas fa-plus text-[10px]"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="font-bold text-gray-900">${{ number_format($item->service->price * $item->quantity, 2) }}</p>
                                <form action="{{ route('buy.cart.remove', $item) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-gray-400 hover:text-red-500 transition">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Resumen -->
                <div class="space-y-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                        <h3 class="font-bold text-gray-900 mb-4">Resumen</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-medium">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($discount > 0)
                                <div class="flex justify-between text-green-600">
                                    <span>Descuento ({{ $discountCode }})</span>
                                    <span>-${{ number_format($discount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-gray-500">IVA</span>
                                <span class="font-medium">${{ number_format($iva, 2) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t text-lg font-bold">
                                <span>Total</span>
                                <span class="text-brand-600">${{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Cupón -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        @if($discountCode)
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium text-green-700"><i class="fas fa-check-circle mr-1"></i> {{ $discountCode }}</span>
                                </div>
                                <form action="{{ route('buy.cart.discount.remove') }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-sm">Quitar</button>
                                </form>
                            </div>
                        @else
                            <form action="{{ route('buy.cart.discount') }}" method="POST" class="flex gap-2">
                                @csrf
                                <input type="text" name="code" placeholder="Código de descuento" required
                                    class="input-field flex-1 text-sm uppercase">
                                <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 text-sm">Aplicar</button>
                            </form>
                        @endif
                    </div>

                    <!-- Botón de checkout -->
                    <a href="{{ route('buy.cart.checkout') }}" class="btn-primary block w-full text-center px-4 py-3.5 rounded-xl text-sm font-semibold">
                        <i class="fas fa-lock mr-1"></i> Proceder al pago
                    </a>
                </div>
            </div>
        @endif
    </main>

    <footer class="text-center py-8 text-xs text-gray-400">
        <p>&copy; {{ date('Y') }} {{ $companyName }} · Todos los precios en MXN</p>
    </footer>
</body>
</html>
