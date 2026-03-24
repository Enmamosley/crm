<!DOCTYPE html>
<html lang="es">
<head>
    <title>Compra completada — {{ $companyName }}</title>
    @include('buy._head')
    <style>
        .confetti-container { position: fixed; inset: 0; pointer-events: none; z-index: 50; overflow: hidden; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">

    @if($payment->status === 'approved')
        <div class="confetti-container" id="confetti-box"></div>
    @endif

    <div class="max-w-md w-full mx-4 py-12">

        @if($payment->status === 'approved')
            {{-- Aprobado --}}
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center animate-bounce-in">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5 animate-bounce-in" style="animation-delay:.2s">
                    <i class="fas fa-check text-4xl text-green-500"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900 mb-2">¡Pago exitoso!</h1>
                <p class="text-gray-400 mb-6">Tu compra de <strong class="text-gray-700">{{ $service->name }}</strong> se procesó correctamente.</p>

                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-left space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Monto</span>
                        <span class="font-bold text-gray-900">${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Referencia</span>
                        <span class="font-mono text-xs text-gray-600">{{ $payment->mp_payment_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Fecha</span>
                        <span class="text-gray-600">{{ ($payment->paid_at ?? now())->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                <div class="bg-brand-50 rounded-xl p-5 mb-5 text-left animate-fade-in" style="animation-delay:.5s">
                    <div class="flex gap-3">
                        <div class="w-10 h-10 bg-brand-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-circle text-brand-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-brand-900 text-sm">Tu portal de cliente</h3>
                            <p class="text-xs text-brand-700 mt-1 mb-3">Se creó tu cuenta. Ve tus facturas, documentos y más.</p>
                            <a href="{{ route('portal.dashboard', $client->portal_token) }}"
                               class="btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded-lg">
                                <i class="fas fa-external-link-alt text-xs"></i> Ir a mi portal
                            </a>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-300"><i class="fas fa-bookmark mr-1"></i> Guarda el enlace del portal como favorito</p>
            </div>

        @elseif($payment->status === 'pending' || $payment->status === 'in_process')
            {{-- Pendiente --}}
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center animate-slide-up">
                <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-hourglass-half text-4xl text-yellow-500 animate-pulse"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Pago pendiente</h1>
                <p class="text-gray-400 mb-6"><strong class="text-gray-700">{{ $service->name }}</strong> está en espera de pago.</p>

                @php $mpData = $payment->mp_data ?? []; @endphp

                @if($payment->payment_method_id === 'oxxo' && isset($mpData['point_of_interaction']['transaction_data']))
                    @php $txData = $mpData['point_of_interaction']['transaction_data']; @endphp
                    <div class="bg-yellow-50 rounded-xl p-5 mb-5 text-left">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-store text-yellow-500"></i>
                            <h3 class="font-bold text-sm text-yellow-800">Ficha de pago OXXO</h3>
                        </div>
                        @if(isset($txData['ticket_url']))
                            <a href="{{ $txData['ticket_url'] }}" target="_blank"
                               class="inline-flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition text-sm font-medium">
                                <i class="fas fa-download"></i> Descargar ficha
                            </a>
                        @endif
                        @if(isset($txData['qr_code']))
                            <p class="text-sm mt-3 text-yellow-800"><strong>Referencia:</strong> <span class="font-mono bg-yellow-100 px-2 py-0.5 rounded">{{ $txData['qr_code'] }}</span></p>
                        @endif
                        <p class="text-xs text-yellow-700 mt-3"><i class="fas fa-clock mr-1"></i> Tienes 3 días para pagar en cualquier OXXO.</p>
                    </div>
                @elseif(isset($mpData['point_of_interaction']['transaction_data']['bank_info']))
                    @php $bankInfo = $mpData['point_of_interaction']['transaction_data']['bank_info']; @endphp
                    <div class="bg-blue-50 rounded-xl p-5 mb-5 text-left">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-building-columns text-blue-500"></i>
                            <h3 class="font-bold text-sm text-blue-800">Datos para transferencia SPEI</h3>
                        </div>
                        @if(isset($bankInfo['collector']['account_id']))
                            <p class="text-sm text-blue-800"><strong>CLABE:</strong> <span class="font-mono bg-blue-100 px-2 py-0.5 rounded">{{ $bankInfo['collector']['account_id'] }}</span></p>
                        @endif
                        <p class="text-sm mt-2 text-blue-800"><strong>Monto:</strong> ${{ number_format($payment->amount, 2) }} MXN</p>
                        <p class="text-xs text-blue-600 mt-3"><i class="fas fa-bolt mr-1"></i> Tu pago se acredita en minutos.</p>
                    </div>
                @endif

                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-left space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Monto</span>
                        <span class="font-bold">${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Estado</span>
                        <span class="text-yellow-600 font-medium"><i class="fas fa-circle text-[8px] animate-pulse mr-1"></i> Pendiente</span>
                    </div>
                </div>

                <div class="bg-brand-50 rounded-xl p-5 mb-5 text-left">
                    <div class="flex gap-3">
                        <div class="w-10 h-10 bg-brand-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-circle text-brand-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-brand-900 text-sm">Tu portal de cliente</h3>
                            <p class="text-xs text-brand-700 mt-1 mb-3">Ya tienes cuenta. Ve el estatus de pago y más.</p>
                            <a href="{{ route('portal.dashboard', $client->portal_token) }}"
                               class="btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded-lg">
                                <i class="fas fa-external-link-alt text-xs"></i> Ir a mi portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        @else
            {{-- Rechazado --}}
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center animate-slide-up">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-times text-4xl text-red-500"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Pago no procesado</h1>
                <p class="text-gray-400 mb-6">No pudimos procesar tu pago. Puedes intentar de nuevo.</p>
                <a href="{{ route('buy.show', $service->slug) }}"
                   class="btn-primary inline-flex items-center gap-2 px-6 py-3 text-sm rounded-xl">
                    <i class="fas fa-redo"></i> Intentar de nuevo
                </a>
            </div>
        @endif

        <footer class="text-center mt-8 text-xs text-gray-300">
            &copy; {{ date('Y') }} {{ $companyName }}
        </footer>
    </div>

@if($payment->status === 'approved')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const box = document.getElementById('confetti-box');
    const colors = ['#6366f1','#22c55e','#f59e0b','#ef4444','#3b82f6','#ec4899'];
    for (let i = 0; i < 60; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + '%';
        piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        piece.style.animationDelay = Math.random() * 1.5 + 's';
        piece.style.animationDuration = (2 + Math.random() * 2) + 's';
        box.appendChild(piece);
    }
    setTimeout(() => box.style.display = 'none', 5000);
});
</script>
@endif

</body>
</html>
