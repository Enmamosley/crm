<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pagar carrito — {{ $companyName }}</title>
    @include('buy._head')
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-2xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $companyName }}" class="h-8">
                @endif
                <span class="font-bold text-gray-900">{{ $companyName }}</span>
            </div>
            <a href="{{ route('buy.cart') }}" class="text-sm text-gray-400 hover:text-gray-700 transition">
                <i class="fas fa-arrow-left mr-1"></i> Carrito
            </a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-6 py-8">

        {{-- Stepper --}}
        <div class="flex items-center mb-8" id="stepper">
            <div class="flex items-center" id="step-1-indicator">
                <div class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center text-sm font-bold">1</div>
                <span class="ml-2 text-sm font-medium text-brand-700">Tus datos</span>
            </div>
            <div class="flex-1 mx-4 h-0.5 bg-gray-200 rounded" id="step-line">
                <div class="h-full bg-brand-500 rounded transition-all duration-500" id="step-progress" style="width:0%"></div>
            </div>
            <div class="flex items-center" id="step-2-indicator">
                <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-sm font-bold transition-all duration-300" id="step-2-circle">2</div>
                <span class="ml-2 text-sm text-gray-400 transition-all duration-300" id="step-2-label">Pago</span>
            </div>
        </div>

        {{-- Resumen del pedido --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 animate-fade-in">
            <h3 class="font-bold text-gray-900 mb-3">Resumen del pedido</h3>
            <div class="space-y-2">
                @foreach($items as $item)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-cube text-brand-500 text-xs"></i>
                            </div>
                            <span class="text-gray-700">{{ $item->service->name }} <span class="text-gray-400">&times;{{ $item->quantity }}</span></span>
                        </div>
                        <span class="font-medium text-gray-900">${{ number_format($item->service->price * $item->quantity, 2) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="border-t mt-3 pt-3 space-y-1 text-sm">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal</span>
                    <span>${{ number_format($subtotal, 2) }}</span>
                </div>
                @if($discount > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Descuento ({{ $discountCode }})</span>
                        <span>-${{ number_format($discount, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-gray-500">
                    <span>IVA</span>
                    <span>${{ number_format($iva, 2) }}</span>
                </div>
                <div class="flex justify-between font-bold text-lg pt-1 border-t">
                    <span>Total</span>
                    <span class="text-brand-600">${{ number_format($total, 2) }}</span>
                </div>
            </div>
        </div>

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2 animate-slide-up">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        {{-- PASO 1: Datos del comprador --}}
        <div id="step-1" class="animate-slide-up">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-1">¿Quién eres?</h3>
                <p class="text-sm text-gray-400 mb-5">Necesitamos tus datos para procesar la compra y crear tu cuenta.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre completo</label>
                        <input type="text" id="buyer-name" class="input-field" placeholder="Ej: María García López" autocomplete="name">
                        <p class="text-xs text-red-500 mt-1 hidden" id="err-name">Ingresa tu nombre</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo electrónico</label>
                        <input type="email" id="buyer-email" class="input-field" placeholder="tu@correo.com" autocomplete="email">
                        <p class="text-xs text-red-500 mt-1 hidden" id="err-email">Ingresa un email válido</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Teléfono <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input type="tel" id="buyer-phone" class="input-field" placeholder="55 1234 5678" autocomplete="tel">
                    </div>
                </div>
            </div>

            @if($requiresDomain)
                @include('buy._domain-step')
            @endif

            <button id="btn-next" class="btn-primary w-full py-3.5 text-sm rounded-xl">
                Continuar al pago <i class="fas fa-arrow-right ml-1"></i>
            </button>
            @if($requiresDomain)
                <p class="text-xs text-red-500 mt-2 hidden text-center" id="domain-err"></p>
            @endif
        </div>

        {{-- PASO 2: Método de pago --}}
        <div id="step-2" class="hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                {{-- Tabs --}}
                <div class="flex border-b bg-gray-50/50">
                    <button class="tab-btn active flex-1 py-3.5 text-sm font-medium border-b-2 transition-all duration-200" data-tab="card">
                        <i class="fas fa-credit-card mr-1.5"></i> Tarjeta
                    </button>
                    <button class="tab-btn flex-1 py-3.5 text-sm font-medium border-b-2 border-transparent text-gray-400 transition-all duration-200" data-tab="oxxo">
                        <i class="fas fa-store mr-1.5"></i> OXXO
                    </button>
                    <button class="tab-btn flex-1 py-3.5 text-sm font-medium border-b-2 border-transparent text-gray-400 transition-all duration-200" data-tab="spei">
                        <i class="fas fa-building-columns mr-1.5"></i> SPEI
                    </button>
                </div>

                {{-- Tarjeta --}}
                <div id="panel-card" class="tab-panel active p-6">
                    <form id="card-form">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Número de tarjeta</label>
                                <div id="mp-card-number" class="mp-iframe"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Vencimiento</label>
                                    <div id="mp-expiration-date" class="mp-iframe"></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">CVV</label>
                                    <div id="mp-security-code" class="mp-iframe"></div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre en la tarjeta</label>
                                <input type="text" id="cardholder-name" class="input-field" placeholder="Como aparece en la tarjeta">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Banco emisor</label>
                                    <select id="issuer-select" class="input-field"><option value="">Auto</option></select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Cuotas</label>
                                    <select id="installments-select" class="input-field"><option value="1">1 pago</option></select>
                                </div>
                            </div>
                        </div>
                        <div id="card-error" class="hidden mt-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl p-3 flex items-center gap-2">
                            <i class="fas fa-exclamation-circle"></i> <span id="card-error-text"></span>
                        </div>
                        <button type="submit" id="btn-pay-card" class="btn-primary mt-6 w-full py-3.5 text-sm rounded-xl">
                            <i class="fas fa-lock mr-1"></i> Pagar ${{ number_format($total, 2) }} MXN
                        </button>
                    </form>
                </div>

                {{-- OXXO --}}
                <div id="panel-oxxo" class="tab-panel p-6">
                    <form action="{{ route('buy.cart.pay.oxxo') }}" method="POST" id="oxxo-form">
                        @csrf
                        <input type="hidden" name="name" class="sync-name">
                        <input type="hidden" name="email" class="sync-email">
                        <input type="hidden" name="phone" class="sync-phone">
                        <input type="hidden" name="domain" class="sync-domain">
                        <input type="hidden" name="domain_type" class="sync-domain-type">
                        <div class="bg-yellow-50 rounded-xl p-4 mb-5 flex gap-3">
                            <i class="fas fa-store text-yellow-500 mt-0.5"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-medium mb-1">Paga en efectivo en OXXO</p>
                                <p class="text-yellow-700 text-xs">Se genera una ficha de pago. Tienes <strong>3 días</strong> para pagar en cualquier tienda OXXO.</p>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-yellow-500 text-white py-3.5 rounded-xl font-medium hover:bg-yellow-600 transition-all active:scale-[.98] text-sm">
                            <i class="fas fa-barcode mr-1"></i> Generar ficha de pago
                        </button>
                    </form>
                </div>

                {{-- SPEI --}}
                <div id="panel-spei" class="tab-panel p-6">
                    <form action="{{ route('buy.cart.pay.spei') }}" method="POST" id="spei-form">
                        @csrf
                        <input type="hidden" name="name" class="sync-name">
                        <input type="hidden" name="email" class="sync-email">
                        <input type="hidden" name="phone" class="sync-phone">
                        <input type="hidden" name="domain" class="sync-domain">
                        <input type="hidden" name="domain_type" class="sync-domain-type">
                        <div class="bg-blue-50 rounded-xl p-4 mb-5 flex gap-3">
                            <i class="fas fa-building-columns text-blue-500 mt-0.5"></i>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Transferencia bancaria SPEI</p>
                                <p class="text-blue-700 text-xs">Se genera una CLABE. Tu pago se acredita en <strong>minutos</strong>.</p>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-medium hover:bg-blue-700 transition-all active:scale-[.98] text-sm">
                            <i class="fas fa-building-columns mr-1"></i> Generar datos SPEI
                        </button>
                    </form>
                </div>
            </div>

            <button id="btn-back" class="mt-4 w-full text-sm text-gray-400 hover:text-gray-600 transition py-2">
                <i class="fas fa-arrow-left mr-1"></i> Cambiar datos
            </button>
        </div>

        <div class="mt-6 flex items-center justify-center gap-4 text-xs text-gray-300">
            <span><i class="fas fa-lock mr-1"></i> Pago seguro</span>
            <span>·</span>
            <span><i class="fas fa-shield-halved mr-1"></i> Mercado Pago</span>
        </div>
    </main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');

    function goToStep2() {
        const name = document.getElementById('buyer-name').value.trim();
        const email = document.getElementById('buyer-email').value.trim();
        let valid = true;

        document.getElementById('err-name').classList.toggle('hidden', !!name);
        document.getElementById('err-email').classList.toggle('hidden', !!email && email.includes('@'));
        if (!name) { document.getElementById('buyer-name').classList.add('error'); valid = false; }
        else { document.getElementById('buyer-name').classList.remove('error'); }
        if (!email || !email.includes('@')) { document.getElementById('buyer-email').classList.add('error'); valid = false; }
        else { document.getElementById('buyer-email').classList.remove('error'); }

        if (!valid) return;

        document.querySelectorAll('.sync-name').forEach(el => el.value = name);
        document.querySelectorAll('.sync-email').forEach(el => el.value = email);
        document.querySelectorAll('.sync-phone').forEach(el => el.value = document.getElementById('buyer-phone').value);
        document.querySelectorAll('.sync-domain').forEach(el => el.value = document.getElementById('domain-hidden')?.value || '');
        document.querySelectorAll('.sync-domain-type').forEach(el => el.value = document.getElementById('domain-type-hidden')?.value || 'own');

        @if($requiresDomain ?? false)
        const domainVal  = document.getElementById('domain-hidden')?.value?.trim() || '';
        const domainType = document.getElementById('domain-type-hidden')?.value || 'own';
        const domainAvail = document.getElementById('domain-available-hidden')?.value;
        const domainErr  = document.getElementById('domain-err');
        if (!domainVal) {
            if (domainErr) { domainErr.textContent = 'Ingresa el dominio que deseas usar.'; domainErr.classList.remove('hidden'); }
            return;
        } else if (domainType === 'cosmotown' && domainAvail !== 'true') {
            if (domainErr) { domainErr.textContent = 'Por favor verifica la disponibilidad del dominio antes de continuar.'; domainErr.classList.remove('hidden'); }
            return;
        } else {
            if (domainErr) domainErr.classList.add('hidden');
        }
        @endif

        step1.classList.add('hidden');
        step2.classList.remove('hidden');
        step2.classList.add('animate-slide-up');

        document.getElementById('step-progress').style.width = '100%';
        document.getElementById('step-2-circle').className = 'w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center text-sm font-bold transition-all duration-300';
        document.getElementById('step-2-label').className = 'ml-2 text-sm font-medium text-brand-700 transition-all duration-300';
    }

    function goToStep1() {
        step2.classList.add('hidden');
        step1.classList.remove('hidden');
        document.getElementById('step-progress').style.width = '0%';
        document.getElementById('step-2-circle').className = 'w-8 h-8 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-sm font-bold transition-all duration-300';
        document.getElementById('step-2-label').className = 'ml-2 text-sm text-gray-400 transition-all duration-300';
    }

    document.getElementById('btn-next').addEventListener('click', goToStep2);
    document.getElementById('btn-back').addEventListener('click', goToStep1);

    ['buyer-name', 'buyer-email', 'buyer-phone'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); goToStep2(); }
        });
    });

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
        });
    });

    // Mercado Pago Secure Fields
    const mpKey = @json($mpPublicKey);
    if (!mpKey) {
        document.getElementById('card-error-text').textContent = 'Pagos con tarjeta no disponibles.';
        document.getElementById('card-error').classList.remove('hidden');
        document.getElementById('btn-pay-card').disabled = true;
        return;
    }

    const mp = new MercadoPago(mpKey, { locale: 'es-MX' });
    const cardNumberField = mp.fields.create('cardNumber', { placeholder: '4509 9535 6623 3704' }).mount('mp-card-number');
    mp.fields.create('expirationDate', { placeholder: 'MM/AA' }).mount('mp-expiration-date');
    mp.fields.create('securityCode', { placeholder: '123' }).mount('mp-security-code');

    let currentPaymentMethodId = '';
    let currentBin = '';

    cardNumberField.on('binChange', async (data) => {
        const bin = data.bin;
        if (!bin || bin.length < 6 || bin === currentBin) return;
        currentBin = bin;
        try {
            const pmResult = await mp.getPaymentMethods({ bin });
            if (pmResult.results?.length > 0) {
                currentPaymentMethodId = pmResult.results[0].id;
                const issuers = await mp.getIssuers({ paymentMethodId: currentPaymentMethodId, bin });
                const issuerSel = document.getElementById('issuer-select');
                issuerSel.innerHTML = '';
                issuers.forEach(iss => { const o = document.createElement('option'); o.value = iss.id; o.textContent = iss.name; issuerSel.appendChild(o); });

                const installments = await mp.getInstallments({ amount: '{{ $total }}', bin });
                const installSel = document.getElementById('installments-select');
                installSel.innerHTML = '';
                if (installments.length > 0) {
                    installments[0].payer_costs.forEach(pc => {
                        const o = document.createElement('option');
                        o.value = pc.installments;
                        o.textContent = pc.installments === 1 ? '1 pago de $' + pc.total_amount : pc.installments + ' cuotas de $' + pc.installment_amount;
                        installSel.appendChild(o);
                    });
                }
            }
        } catch (err) { console.warn('binChange', err); }
    });

    const rejectionReasons = {
        cc_rejected_bad_filled_card_number: 'Revisa el número de tarjeta.',
        cc_rejected_bad_filled_date: 'La fecha de vencimiento es incorrecta.',
        cc_rejected_bad_filled_other: 'Revisa los datos de tu tarjeta.',
        cc_rejected_bad_filled_security_code: 'El código de seguridad es incorrecto.',
        cc_rejected_call_for_authorize: 'Llama a tu banco para autorizar este pago.',
        cc_rejected_card_disabled: 'Tu tarjeta está deshabilitada. Contacta a tu banco.',
        cc_rejected_insufficient_amount: 'Tu tarjeta no tiene fondos suficientes.',
        cc_rejected_max_attempts: 'Demasiados intentos. Prueba con otra tarjeta.',
        cc_rejected_other_reason: 'Tu tarjeta fue rechazada. Prueba con otra.',
    };

    function showError(msg) {
        document.getElementById('card-error-text').textContent = msg;
        document.getElementById('card-error').classList.remove('hidden');
    }

    document.getElementById('card-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const buyerName = document.getElementById('buyer-name').value.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();
        const cardholderName = document.getElementById('cardholder-name').value.trim() || buyerName;
        const btn = document.getElementById('btn-pay-card');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando tu pago...';
        document.getElementById('card-error').classList.add('hidden');

        try {
            const tokenResult = await mp.fields.createCardToken({ cardholderName });
            const response = await fetch('{{ route("buy.cart.pay.card") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({
                    token: tokenResult.id,
                    payment_method_id: currentPaymentMethodId,
                    email: buyerEmail,
                    name: buyerName,
                    phone: document.getElementById('buyer-phone').value.trim(),
                    installments: parseInt(document.getElementById('installments-select').value) || 1,
                    issuer_id: document.getElementById('issuer-select').value || null,
                    domain: document.getElementById('domain-hidden')?.value?.trim() || '',
                    domain_type: document.getElementById('domain-type-hidden')?.value || 'own',
                }),
            });
            const data = await response.json();
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                const reason = rejectionReasons[data.status_detail] || data.error || 'No se pudo procesar el pago. Intenta de nuevo.';
                showError(reason);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pagar ${{ number_format($total, 2) }} MXN';
            }
        } catch (err) {
            showError('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pagar ${{ number_format($total, 2) }} MXN';
        }
    });
});
</script>
</body>
</html>
