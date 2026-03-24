<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pagar factura {{ $invoice->folio() }} — {{ $client->legal_name }}</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <style>
        .mp-iframe { border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.625rem 0.75rem; height: 42px; width: 100%; }
        .mp-iframe:focus-within { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.2); }
        .tab-btn.active { border-color: #6366f1; color: #4338ca; background: #eef2ff; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <header class="bg-white shadow-sm border-b">
        <div class="max-w-xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Pagar factura</h1>
                <p class="text-sm text-gray-500">{{ $client->legal_name }}</p>
            </div>
            <a href="{{ route('portal.dashboard', $client->portal_token) }}" class="text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-1"></i> Volver
            </a>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-6 py-8 space-y-6">

        {{-- Alertas --}}
        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
        </div>
        @endif

        {{-- Resumen de factura --}}
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-sm text-gray-500">Factura</p>
                    <p class="text-lg font-bold font-mono">{{ $invoice->folio() ?: 'Borrador' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Total a pagar</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($invoice->total, 2) }} <span class="text-sm font-normal text-gray-400">MXN</span></p>
                </div>
            </div>
            @if($invoice->quote)
            <p class="text-xs text-gray-400">Cotización: {{ $invoice->quote->quote_number }}</p>
            @endif
        </div>

        {{-- Preferencia de facturación --}}
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-file-invoice text-indigo-500 mr-1"></i> ¿Necesitas factura?
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="billing-pref-group">
                <label class="billing-opt flex items-start gap-3 border-2 border-indigo-500 rounded-lg p-3 cursor-pointer bg-indigo-50">
                    <input type="radio" name="billing_preference_global" value="fiscal" class="mt-0.5 billing-radio" checked>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Sí, con mis datos fiscales</p>
                        <p class="text-xs text-gray-500 mt-0.5">RFC: {{ $client->tax_id }}</p>
                    </div>
                </label>
                <label class="billing-opt flex items-start gap-3 border-2 border-transparent rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="billing_preference_global" value="publico_general" class="mt-0.5 billing-radio">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Sí, como Público en General</p>
                        <p class="text-xs text-gray-500 mt-0.5">RFC: XAXX010101000</p>
                    </div>
                </label>
                <label class="billing-opt flex items-start gap-3 border-2 border-transparent rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="billing_preference_global" value="none" class="mt-0.5 billing-radio">
                    <div>
                        <p class="text-sm font-medium text-gray-800">No necesito factura</p>
                        <p class="text-xs text-gray-500 mt-0.5">No se emitirá CFDI</p>
                    </div>
                </label>
            </div>
        </div>

        @if(!$mpPublicKey && !$hasBankData)
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-4 py-3 text-sm">
            <i class="fas fa-exclamation-triangle mr-1"></i> El sistema de pagos no está configurado. Contacta al administrador.
        </div>
        @else

        {{-- Tabs de método de pago --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex border-b">
                @if($mpPublicKey)
                <button class="tab-btn active flex-1 py-3 px-4 text-sm font-medium border-b-2 border-transparent transition" data-tab="card">
                    <i class="fas fa-credit-card mr-1"></i> Tarjeta
                </button>
                <button class="tab-btn flex-1 py-3 px-4 text-sm font-medium border-b-2 border-transparent transition" data-tab="oxxo">
                    <i class="fas fa-store mr-1"></i> OXXO
                </button>
                <button class="tab-btn flex-1 py-3 px-4 text-sm font-medium border-b-2 border-transparent transition" data-tab="spei">
                    <i class="fas fa-building-columns mr-1"></i> SPEI (MP)
                </button>
                @endif
                @if($hasBankData)
                <button class="tab-btn {{ !$mpPublicKey ? 'active' : '' }} flex-1 py-3 px-4 text-sm font-medium border-b-2 border-transparent transition" data-tab="transfer">
                    <i class="fas fa-money-bill-transfer mr-1"></i> Transferencia
                </button>
                @endif
            </div>

            {{-- TAB: Tarjeta --}}
            <div id="panel-card" class="tab-panel active p-5">
                <form id="form-checkout" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Número de tarjeta</label>
                        <div id="mp-card-number" class="mp-iframe"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vencimiento</label>
                            <div id="mp-expiration-date" class="mp-iframe"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                            <div id="mp-security-code" class="mp-iframe"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titular de la tarjeta</label>
                        <input type="text" id="cardholder-name" placeholder="Como aparece en la tarjeta" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email para recibo</label>
                        <input type="email" id="cardholder-email" value="{{ $client->email }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div id="issuer-container">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Banco emisor</label>
                            <select id="issuer-select" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meses</label>
                            <select id="installments-select" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                                <option value="1">1 mes (sin intereses)</option>
                            </select>
                        </div>
                    </div>

                    <div id="card-error" class="hidden bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"></div>

                    <button type="submit" id="btn-card-pay"
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 font-medium transition flex items-center justify-center gap-2">
                        <i class="fas fa-lock"></i>
                        Pagar ${{ number_format($invoice->total, 2) }} MXN
                    </button>
                </form>
            </div>

            {{-- TAB: OXXO --}}
            <div id="panel-oxxo" class="tab-panel p-5">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-store text-yellow-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-600">Se generará un código de barras para pagar en cualquier tienda OXXO. Tienes <strong>3 días</strong> para completar el pago.</p>
                </div>
                <form method="POST" action="{{ route('portal.pay.oxxo', [$client->portal_token, $invoice]) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="billing_preference" class="billing-pref-input" value="fiscal">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email para recibo</label>
                        <input type="email" name="email" value="{{ $client->email }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-yellow-500 text-white py-3 rounded-lg hover:bg-yellow-600 font-medium transition">
                        <i class="fas fa-barcode mr-2"></i> Generar referencia OXXO
                    </button>
                </form>
            </div>

            {{-- TAB: SPEI (MercadoPago) --}}
            <div id="panel-spei" class="tab-panel p-5">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-building-columns text-blue-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-600">Recibirás una CLABE interbancaria para realizar la transferencia desde tu banca en línea. El pago se confirma en minutos.</p>
                </div>
                <form method="POST" action="{{ route('portal.pay.spei', [$client->portal_token, $invoice]) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="billing_preference" class="billing-pref-input" value="fiscal">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email para recibo</label>
                        <input type="email" name="email" value="{{ $client->email }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-medium transition">
                        <i class="fas fa-money-bill-transfer mr-2"></i> Generar referencia SPEI
                    </button>
                </form>
            </div>

            @if($hasBankData)
            {{-- TAB: Transferencia directa --}}
            <div id="panel-transfer" class="tab-panel {{ !$mpPublicKey ? 'active' : '' }} p-5">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5">
                    <p class="text-sm font-semibold text-blue-800 mb-3">
                        <i class="fas fa-university mr-1"></i> Datos para transferencia
                    </p>
                    <div class="grid grid-cols-1 gap-2 text-sm">
                        @if($bankData['name'])
                        <div class="flex justify-between">
                            <span class="text-blue-600 font-medium">Banco:</span>
                            <span class="font-semibold text-blue-900">{{ $bankData['name'] }}</span>
                        </div>
                        @endif
                        @if($bankData['beneficiary'])
                        <div class="flex justify-between">
                            <span class="text-blue-600 font-medium">Beneficiario:</span>
                            <span class="font-semibold text-blue-900">{{ $bankData['beneficiary'] }}</span>
                        </div>
                        @endif
                        @if($bankData['account'])
                        <div class="flex justify-between items-center">
                            <span class="text-blue-600 font-medium">No. de cuenta:</span>
                            <span class="font-mono font-bold text-blue-900 text-base">{{ $bankData['account'] }}</span>
                        </div>
                        @endif
                        @if($bankData['clabe'])
                        <div class="flex justify-between items-center">
                            <span class="text-blue-600 font-medium">CLABE:</span>
                            <span class="font-mono font-bold text-blue-900 text-base">{{ $bankData['clabe'] }}</span>
                        </div>
                        @endif
                        @if($bankData['reference'])
                        <div class="flex justify-between">
                            <span class="text-blue-600 font-medium">Referencia:</span>
                            <span class="text-blue-900">{{ $bankData['reference'] }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between border-t border-blue-200 pt-2 mt-1">
                            <span class="text-blue-600 font-medium">Monto exacto:</span>
                            <span class="font-bold text-blue-900">${{ number_format($invoice->total, 2) }} MXN</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('portal.pay.transfer', [$client->portal_token, $invoice]) }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="billing_preference" class="billing-pref-input" value="fiscal">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email de confirmación</label>
                        <input type="email" name="email" value="{{ $client->email }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Comprobante de transferencia <span class="font-normal text-gray-400">(PDF, JPG o PNG — recomendado)</span>
                        </label>
                        <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-400 mt-1">El administrador verificará tu pago y activará tus servicios.</p>
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 font-medium transition">
                        <i class="fas fa-paper-plane mr-2"></i> Enviar comprobante
                    </button>
                </form>
            </div>
            @endif
        </div>

        <p class="text-center text-xs text-gray-400">
            <i class="fas fa-shield-halved mr-1"></i> Tus datos y pagos están protegidos.
        </p>

        @endif
    </main>

    <footer class="text-center py-6 text-xs text-gray-400">
        Portal privado · {{ $client->legal_name }} · {{ now()->year }}
    </footer>

    @if($mpPublicKey || $hasBankData)
    <script>
    // ── Tabs ───────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
        });
    });

    // ── Preferencia de facturación → sincronizar hidden inputs ─────
    function syncBillingPref(val) {
        document.querySelectorAll('.billing-pref-input').forEach(inp => inp.value = val);
        document.querySelectorAll('.billing-opt').forEach(lbl => {
            const radio = lbl.querySelector('.billing-radio');
            if (radio.value === val) {
                lbl.classList.add('border-indigo-500', 'bg-indigo-50');
                lbl.classList.remove('border-transparent');
            } else {
                lbl.classList.remove('border-indigo-500', 'bg-indigo-50');
                lbl.classList.add('border-transparent');
            }
        });
    }
    document.querySelectorAll('.billing-radio').forEach(radio => {
        radio.addEventListener('change', () => syncBillingPref(radio.value));
    });
    // Valor inicial
    const initialPref = document.querySelector('.billing-radio:checked')?.value || 'fiscal';
    syncBillingPref(initialPref);

    @if($mpPublicKey)
    // ── MercadoPago Secure Fields ──────────────────────
    const mp = new MercadoPago('{{ $mpPublicKey }}', { locale: 'es-MX' });

    const cardNumberElement = mp.fields.create('cardNumber', {
        placeholder: '4509 9535 6623 3704',
    }).mount('mp-card-number');

    const expirationDateElement = mp.fields.create('expirationDate', {
        placeholder: 'MM/YY',
    }).mount('mp-expiration-date');

    const securityCodeElement = mp.fields.create('securityCode', {
        placeholder: '123',
    }).mount('mp-security-code');

    // Track current BIN for issuer/installments lookup
    let currentBin = '';
    let currentPaymentMethodId = '';

    cardNumberElement.on('binChange', async (data) => {
        const bin = data.bin;
        if (!bin || bin.length < 6) {
            currentBin = '';
            return;
        }
        if (bin === currentBin) return;
        currentBin = bin;

        try {
            // Get payment methods for this BIN
            const paymentMethods = await mp.getPaymentMethods({ bin });
            if (paymentMethods.results && paymentMethods.results.length > 0) {
                currentPaymentMethodId = paymentMethods.results[0].id;

                // Get issuers
                const issuers = await mp.getIssuers({
                    paymentMethodId: currentPaymentMethodId,
                    bin,
                });
                const issuerSelect = document.getElementById('issuer-select');
                issuerSelect.innerHTML = '';
                issuers.forEach(issuer => {
                    const opt = document.createElement('option');
                    opt.value = issuer.id;
                    opt.textContent = issuer.name;
                    issuerSelect.appendChild(opt);
                });

                // Get installments
                const installments = await mp.getInstallments({
                    amount: '{{ $invoice->total }}',
                    bin,
                });
                const installSelect = document.getElementById('installments-select');
                installSelect.innerHTML = '';
                if (installments.length > 0) {
                    installments[0].payer_costs.forEach(cost => {
                        const opt = document.createElement('option');
                        opt.value = cost.installments;
                        opt.textContent = cost.recommended_message || `${cost.installments} mes(es)`;
                        installSelect.appendChild(opt);
                    });
                }
            }
        } catch (e) {
            console.warn('BIN lookup error:', e);
        }
    });

    // ── Form submit → createCardToken ──────────────────
    const form = document.getElementById('form-checkout');
    const btn = document.getElementById('btn-card-pay');
    const errorDiv = document.getElementById('card-error');
    const PAY_LABEL = '<i class="fas fa-lock"></i> Pagar ${{ number_format($invoice->total, 2) }} MXN';

    const rejectionReasons = {
        'cc_rejected_other_reason': 'La tarjeta fue rechazada. Intenta con otra tarjeta o método de pago.',
        'cc_rejected_insufficient_amount': 'Fondos insuficientes en la tarjeta.',
        'cc_rejected_bad_filled_security_code': 'Código de seguridad (CVV) incorrecto.',
        'cc_rejected_bad_filled_date': 'Fecha de vencimiento incorrecta.',
        'cc_rejected_bad_filled_other': 'Revisa los datos de la tarjeta.',
        'cc_rejected_high_risk': 'Pago rechazado por seguridad. Intenta con otra tarjeta.',
        'cc_rejected_call_for_authorize': 'Debes llamar a tu banco para autorizar el pago.',
        'cc_rejected_card_disabled': 'Esta tarjeta está deshabilitada. Contacta a tu banco.',
        'cc_rejected_max_attempts': 'Demasiados intentos. Espera unos minutos o usa otra tarjeta.',
        'cc_rejected_duplicated_payment': 'Ya se realizó un pago por este monto recientemente.',
        'cc_rejected_card_type_not_allowed': 'Este tipo de tarjeta no es aceptado.',
    };

    function showError(msg) {
        errorDiv.textContent = msg;
        errorDiv.classList.remove('hidden');
        btn.disabled = false;
        btn.innerHTML = PAY_LABEL;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorDiv.classList.add('hidden');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

        const cardholderName = document.getElementById('cardholder-name').value.trim();
        const email = document.getElementById('cardholder-email').value.trim();

        if (!cardholderName) return showError('Ingresa el nombre del titular.');
        if (!email) return showError('Ingresa un email válido.');

        try {
            const tokenData = await mp.fields.createCardToken({
                cardholderName: cardholderName,
            });

            if (!tokenData || !tokenData.id) {
                return showError('No se pudo tokenizar la tarjeta. Revisa los datos.');
            }

            const response = await fetch('{{ route("portal.pay.card", [$client->portal_token, $invoice]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    token: tokenData.id,
                    payment_method_id: currentPaymentMethodId || 'visa',
                    issuer_id: document.getElementById('issuer-select').value || null,
                    installments: Number(document.getElementById('installments-select').value) || 1,
                    email: email,
                    billing_preference: document.querySelector('.billing-radio:checked')?.value || 'fiscal',
                }),
            });

            const data = await response.json();

            if (data.success && data.redirect) {
                window.location = data.redirect;
            } else {
                const detail = data.status_detail || '';
                showError(rejectionReasons[detail] || data.error || 'Error al procesar el pago.');
            }
        } catch (err) {
            console.error('Payment error:', err);
            let msg = 'Revisa los datos de la tarjeta e intenta de nuevo.';
            if (err && err.message) {
                msg = err.message;
            }
            if (err && Array.isArray(err)) {
                const fieldNames = { cardNumber: 'número de tarjeta', expirationDate: 'fecha de vencimiento', securityCode: 'CVV' };
                const details = err.map(e => fieldNames[e.field] || e.message || e.cause).filter(Boolean);
                if (details.length) msg = 'Revisa: ' + details.join(', ') + '.';
            }
            showError(msg);
        }
    });
    @endif
    </script>
    @endif
</body>
</html>
