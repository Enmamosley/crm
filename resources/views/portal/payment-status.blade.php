<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del pago — {{ $client->legal_name }}</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <header class="bg-white shadow-sm border-b">
        <div class="max-w-xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Estado del pago</h1>
                <p class="text-sm text-gray-500">{{ $client->legal_name }}</p>
            </div>
            <a href="{{ route('portal.dashboard', $client->portal_token) }}" class="text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-1"></i> Volver al portal
            </a>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-6 py-8 space-y-6">

        {{-- Status card --}}
        @if($payment->isApproved())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-600 text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-green-700 mb-2">Pago aprobado</h2>
            <p class="text-gray-500 mb-4">Tu pago ha sido procesado exitosamente.</p>
            <div class="bg-green-50 rounded-lg p-4 inline-block">
                <p class="text-3xl font-bold text-green-800">${{ number_format($payment->amount, 2) }} <span class="text-sm font-normal">MXN</span></p>
            </div>
            <div class="mt-4 text-sm text-gray-400 space-y-1">
                <p>Factura: <span class="font-mono font-medium text-gray-600">{{ $invoice->folio() }}</span></p>
                <p>ID de pago MP: <span class="font-mono text-gray-600">{{ $payment->mp_payment_id }}</span></p>
                @if($payment->paid_at)
                <p>Fecha: {{ $payment->paid_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>

        @elseif($payment->isRejected())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-times text-red-600 text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-red-700 mb-2">Pago rechazado</h2>
            <p class="text-gray-500 mb-2">No se pudo procesar el pago.</p>
            @if($payment->status_detail)
            <p class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-2 inline-block mb-4">
                {{ match($payment->status_detail) {
                    'cc_rejected_insufficient_amount' => 'Fondos insuficientes.',
                    'cc_rejected_bad_filled_security_code' => 'Código de seguridad incorrecto.',
                    'cc_rejected_bad_filled_date' => 'Fecha de vencimiento incorrecta.',
                    'cc_rejected_bad_filled_other' => 'Datos de tarjeta incorrectos.',
                    'cc_rejected_high_risk' => 'Pago rechazado por seguridad.',
                    'cc_rejected_call_for_authorize' => 'Debes autorizar el pago con tu banco.',
                    'cc_rejected_card_disabled' => 'La tarjeta está deshabilitada.',
                    'cc_rejected_max_attempts' => 'Demasiados intentos. Intenta con otra tarjeta.',
                    'cc_rejected_other_reason' => 'La tarjeta fue rechazada. Intenta con otra tarjeta o método de pago.',
                    'cc_rejected_duplicated_payment' => 'Ya se realizó un pago por este monto. Si necesitas pagar de nuevo, espera unos minutos.',
                    'cc_rejected_card_type_not_allowed' => 'Este tipo de tarjeta no es aceptado. Intenta con otra.',
                    'cc_rejected_blacklist' => 'No se pudo procesar el pago con esta tarjeta. Usa otra.',
                    default => $payment->status_detail,
                } }}
            </p>
            @endif
            <div class="mt-4">
                <a href="{{ route('portal.checkout', [$client->portal_token, $invoice]) }}"
                   class="inline-flex items-center bg-indigo-600 text-white px-6 py-2.5 rounded-lg hover:bg-indigo-700 font-medium transition">
                    <i class="fas fa-redo mr-2"></i> Reintentar pago
                </a>
            </div>
        </div>

        @elseif($payment->isPending())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clock text-yellow-600 text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-yellow-700 mb-2">Pago pendiente</h2>
            <p class="text-gray-500 mb-4">Completa el pago siguiendo las instrucciones.</p>
            <div class="bg-yellow-50 rounded-lg p-4 inline-block mb-4">
                <p class="text-3xl font-bold text-yellow-800">${{ number_format($payment->amount, 2) }} <span class="text-sm font-normal">MXN</span></p>
            </div>
        </div>

        {{-- Detalles para pago offline --}}
        @if($payment->payment_type === 'transfer')
        <div class="bg-white rounded-lg shadow p-6 text-center space-y-3">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                <i class="fas fa-paper-plane text-green-600 text-2xl"></i>
            </div>
            <h3 class="font-semibold text-gray-800">Comprobante recibido</h3>
            <p class="text-sm text-gray-500">Hemos recibido tu comprobante de transferencia. El equipo lo verificará y activará tus servicios en breve.</p>
            @if($payment->proof_path)
            <p class="text-xs text-green-700 bg-green-50 rounded-lg px-4 py-2 inline-block">
                <i class="fas fa-check-circle mr-1"></i> Comprobante adjunto correctamente
            </p>
            @else
            <p class="text-xs text-yellow-700 bg-yellow-50 rounded-lg px-4 py-2 inline-block">
                <i class="fas fa-exclamation-circle mr-1"></i> Si deseas, envía tu comprobante al administrador para agilizar la verificación.
            </p>
            @endif
        </div>
        @elseif($payment->payment_type === 'ticket' || $payment->payment_type === 'bank_transfer')
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            @if($payment->payment_type === 'ticket')
                {{-- OXXO --}}
                <h3 class="font-semibold text-gray-800"><i class="fas fa-store text-yellow-500 mr-2"></i>Instrucciones para pago en OXXO</h3>
                <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                    <li>Acude a cualquier tienda OXXO.</li>
                    <li>Indica que realizarás un pago de servicios.</li>
                    <li>Proporciona el código de barras o la referencia.</li>
                    <li>Realiza el pago en efectivo por <strong>${{ number_format($payment->amount, 2) }} MXN</strong>.</li>
                    <li>Conserva tu comprobante.</li>
                </ol>
                @php $refId = $payment->mp_data['transaction_details']['payment_method_reference_id'] ?? null; @endphp
                @if($refId)
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Referencia de pago</p>
                    <p class="text-2xl font-mono font-bold tracking-wider text-gray-800">{{ $refId }}</p>
                </div>
                @endif
            @else
                {{-- SPEI --}}
                <h3 class="font-semibold text-gray-800"><i class="fas fa-building-columns text-blue-500 mr-2"></i>Instrucciones para pago por SPEI</h3>
                <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                    <li>Ingresa a tu banca en línea o app de tu banco.</li>
                    <li>Selecciona "Transferencia SPEI" o "A otros bancos".</li>
                    <li>Ingresa la CLABE y el monto exacto.</li>
                    <li>Confirma la transferencia.</li>
                </ol>
                @php
                    $clabe = $payment->mp_data['point_of_interaction']['transaction_data']['bank_info']['collector']['transfer_account_id'] ?? null;
                    $bankName = $payment->mp_data['point_of_interaction']['transaction_data']['bank_info']['collector']['long_name'] ?? null;
                @endphp
                @if($clabe)
                <div class="bg-gray-50 rounded-lg p-4 text-center space-y-2">
                    <p class="text-xs text-gray-500">CLABE interbancaria</p>
                    <p class="text-2xl font-mono font-bold tracking-wider text-gray-800">{{ $clabe }}</p>
                    @if($bankName)
                    <p class="text-xs text-gray-500">{{ $bankName }}</p>
                    @endif
                </div>
                @endif
            @endif

            @php $ticketUrl = $payment->ticketUrl(); @endphp
            @if($ticketUrl)
            <a href="{{ $ticketUrl }}" target="_blank" rel="noopener"
               class="block w-full text-center bg-gray-800 text-white py-3 rounded-lg hover:bg-gray-900 font-medium transition">
                <i class="fas fa-external-link-alt mr-2"></i> Ver comprobante completo
            </a>
            @endif

            <p class="text-xs text-gray-400 text-center">
                <i class="fas fa-info-circle mr-1"></i>
                El pago se confirmará automáticamente una vez procesado. Recibirás un correo de confirmación.
            </p>
        </div>
        @endif

        @else
        {{-- Cancelado u otro estado --}}
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-ban text-gray-400 text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-600 mb-2">Pago {{ $payment->status }}</h2>
            <p class="text-gray-500">Este pago no se completó.</p>
        </div>
        @endif

        {{-- Info factura --}}
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Detalle de la factura</h3>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Folio</dt>
                    <dd class="font-mono font-medium">{{ $invoice->folio() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Subtotal</dt>
                    <dd>${{ number_format($invoice->subtotal, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">IVA</dt>
                    <dd>${{ number_format($invoice->iva_amount, 2) }}</dd>
                </div>
                <div class="flex justify-between border-t pt-2 font-bold">
                    <dt>Total</dt>
                    <dd>${{ number_format($invoice->total, 2) }} MXN</dd>
                </div>
            </dl>
        </div>

        <div class="text-center">
            <a href="{{ route('portal.dashboard', $client->portal_token) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Volver al portal
            </a>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-gray-400">
        Portal privado · {{ $client->legal_name }} · {{ now()->year }}
    </footer>
</body>
</html>
