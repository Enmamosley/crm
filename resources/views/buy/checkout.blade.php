<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comprar {{ $service->name }} — {{ $companyName }}</title>
    @include('buy._head')
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-lg mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $companyName }}" class="h-8">
                @endif
                <span class="font-bold text-gray-900">{{ $companyName }}</span>
            </div>
            <a href="{{ route('buy.catalog') }}" class="text-sm text-gray-400 hover:text-gray-700 transition">
                <i class="fas fa-arrow-left mr-1"></i> Catálogo
            </a>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-6 py-8">

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

        {{-- Resumen compacto --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 animate-fade-in">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-brand-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cube text-brand-500"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900">{{ $service->name }}</h2>
                        @if($service->description)
                            <p class="text-xs text-gray-400">{{ Str::limit($service->description, 60) }}</p>
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xl font-extrabold text-gray-900">${{ number_format($service->priceWithIva(), 2) }}</p>
                    <p class="text-xs text-gray-400">IVA incluido</p>
                </div>
            </div>
        </div>

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2 animate-slide-up">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        {{-- PASO 1: Datos --}}
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

            @if($service->requires_domain)
                @include('buy._domain-step')
            @endif

            {{-- Facturación --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-1"><i class="fas fa-file-invoice text-brand-500 mr-1"></i> ¿Necesitas factura?</h3>
                <p class="text-sm text-gray-400 mb-4">Selecciona cómo deseas tu comprobante fiscal (CFDI).</p>
                <div class="space-y-2" id="billing-options">
                    <label class="billing-opt flex items-start gap-3 border-2 border-transparent rounded-xl p-3 cursor-pointer hover:bg-gray-50 transition"
                           onclick="selectBilling('none')">
                        <input type="radio" name="billing_pref" value="none" class="mt-0.5 billing-radio" checked>
                        <div>
                            <p class="text-sm font-medium text-gray-800">No necesito factura</p>
                            <p class="text-xs text-gray-400">No se emitirá CFDI</p>
                        </div>
                    </label>
                    <label class="billing-opt flex items-start gap-3 border-2 border-transparent rounded-xl p-3 cursor-pointer hover:bg-gray-50 transition"
                           onclick="selectBilling('fiscal')">
                        <input type="radio" name="billing_pref" value="fiscal" class="mt-0.5 billing-radio">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Sí, con mis datos fiscales</p>
                            <p class="text-xs text-gray-400">Ingresa tu RFC y razón social</p>
                        </div>
                    </label>
                    <label class="billing-opt flex items-start gap-3 border-2 border-transparent rounded-xl p-3 cursor-pointer hover:bg-gray-50 transition"
                           onclick="selectBilling('publico_general')">
                        <input type="radio" name="billing_pref" value="publico_general" class="mt-0.5 billing-radio">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Sí, como Público en General</p>
                            <p class="text-xs text-gray-400">RFC: XAXX010101000</p>
                        </div>
                    </label>
                </div>

                {{-- Campos fiscales (solo visibles con "fiscal") --}}
                <div id="fiscal-fields" class="hidden mt-4 space-y-3 border-t pt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">RFC</label>
                        <input type="text" id="buyer-rfc" class="input-field uppercase" placeholder="XAXX010101000" maxlength="13" autocomplete="off">
                        <p class="text-xs text-red-500 mt-1 hidden" id="err-rfc">Ingresa un RFC válido (12 o 13 caracteres)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Razón social</label>
                        <input type="text" id="buyer-legal-name" class="input-field" placeholder="Nombre o razón social como aparece en tu constancia">
                        <p class="text-xs text-red-500 mt-1 hidden" id="err-legal-name">Ingresa la razón social</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Código postal fiscal</label>
                            <input type="text" id="buyer-zip" class="input-field" placeholder="06600" maxlength="5">
                            <p class="text-xs text-red-500 mt-1 hidden" id="err-zip">Ingresa tu C.P. fiscal</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Régimen fiscal</label>
                            <select id="buyer-tax-system" class="input-field">
                                <option value="601">601 - General de Ley</option>
                                <option value="603">603 - Personas Morales Fines no Lucrativos</option>
                                <option value="605">605 - Sueldos y Salarios</option>
                                <option value="606">606 - Arrendamiento</option>
                                <option value="608">608 - Demás Ingresos</option>
                                <option value="610">610 - Residentes en el Extranjero</option>
                                <option value="612">612 - Personas Físicas Actividad Empresarial</option>
                                <option value="616" selected>616 - Sin Obligaciones Fiscales</option>
                                <option value="620">620 - Sociedades Cooperativas de Producción</option>
                                <option value="621">621 - Incorporación Fiscal</option>
                                <option value="622">622 - Actividades Agrícolas, Ganaderas</option>
                                <option value="623">623 - Opcional para Grupos de Sociedades</option>
                                <option value="624">624 - Coordinados</option>
                                <option value="625">625 - RESICO</option>
                                <option value="626">626 - RESICO Personas Morales</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Uso del CFDI</label>
                        <select id="buyer-cfdi-use" class="input-field">
                            <option value="G03" selected>G03 - Gastos en general</option>
                            <option value="G01">G01 - Adquisición de mercancías</option>
                            <option value="G02">G02 - Devoluciones, descuentos o bonificaciones</option>
                            <option value="I01">I01 - Construcciones</option>
                            <option value="I02">I02 - Mobiliario y equipo de oficina</option>
                            <option value="I04">I04 - Equipo de computo y accesorios</option>
                            <option value="I08">I08 - Otra maquinaria y equipo</option>
                            <option value="D01">D01 - Honorarios médicos</option>
                            <option value="D02">D02 - Gastos médicos por incapacidad</option>
                            <option value="D03">D03 - Gastos funerales</option>
                            <option value="D04">D04 - Donativos</option>
                            <option value="D05">D05 - Intereses de créditos hipotecarios</option>
                            <option value="D06">D06 - Aportaciones voluntarias al SAR</option>
                            <option value="D10">D10 - Pagos por servicios educativos</option>
                            <option value="S01">S01 - Sin efectos fiscales</option>
                            <option value="CP01">CP01 - Pagos</option>
                        </select>
                    </div>
                </div>
            </div>

            <button id="btn-next" class="btn-primary w-full py-3.5 text-sm rounded-xl">
                Continuar al pago <i class="fas fa-arrow-right ml-1"></i>
            </button>
            @if($service->requires_domain)
                <p class="text-xs text-red-500 mt-2 hidden text-center" id="domain-err"></p>
            @endif
        </div>

        {{-- PASO 2: Pago --}}
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
                    @if($hasBankData)
                    <button class="tab-btn flex-1 py-3.5 text-sm font-medium border-b-2 border-transparent text-gray-400 transition-all duration-200" data-tab="transfer">
                        <i class="fas fa-money-bill-transfer mr-1.5"></i> Transferencia
                    </button>
                    @endif
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
                            <i class="fas fa-lock mr-1"></i> Pagar ${{ number_format($service->priceWithIva(), 2) }} MXN
                        </button>
                    </form>
                </div>

                {{-- OXXO --}}
                <div id="panel-oxxo" class="tab-panel p-6">
                    <form action="{{ route('buy.pay.oxxo', $service->slug) }}" method="POST" id="oxxo-form">
                        @csrf
                        <input type="hidden" name="name" class="sync-name">
                        <input type="hidden" name="email" class="sync-email">
                        <input type="hidden" name="phone" class="sync-phone">
                        <input type="hidden" name="domain" class="sync-domain">
                        <input type="hidden" name="domain_type" class="sync-domain-type">
                        <input type="hidden" name="billing_preference" class="sync-billing-pref">
                        <input type="hidden" name="tax_id" class="sync-rfc">
                        <input type="hidden" name="fiscal_name" class="sync-legal-name">
                        <input type="hidden" name="address_zip" class="sync-zip">
                        <input type="hidden" name="tax_system" class="sync-tax-system">
                        <input type="hidden" name="cfdi_use" class="sync-cfdi-use">
                        <div class="bg-yellow-50 rounded-xl p-4 mb-5 flex gap-3">
                            <i class="fas fa-store text-yellow-500 mt-0.5"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-medium mb-1">Paga en efectivo en OXXO</p>
                                <p class="text-yellow-700 text-xs">Se genera una ficha de pago. Tienes <strong>3 días</strong> para pagar en cualquier tienda OXXO.</p>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-yellow-500 text-white py-3.5 rounded-xl font-medium hover:bg-yellow-600 transition-all active:scale-[.98] text-sm btn-submit-once">
                            <i class="fas fa-barcode mr-1"></i> Generar ficha de pago
                        </button>
                    </form>
                </div>

                {{-- SPEI --}}
                <div id="panel-spei" class="tab-panel p-6">
                    <form action="{{ route('buy.pay.spei', $service->slug) }}" method="POST" id="spei-form">
                        @csrf
                        <input type="hidden" name="name" class="sync-name">
                        <input type="hidden" name="email" class="sync-email">
                        <input type="hidden" name="phone" class="sync-phone">
                        <input type="hidden" name="domain" class="sync-domain">
                        <input type="hidden" name="domain_type" class="sync-domain-type">
                        <input type="hidden" name="billing_preference" class="sync-billing-pref">
                        <input type="hidden" name="tax_id" class="sync-rfc">
                        <input type="hidden" name="fiscal_name" class="sync-legal-name">
                        <input type="hidden" name="address_zip" class="sync-zip">
                        <input type="hidden" name="tax_system" class="sync-tax-system">
                        <input type="hidden" name="cfdi_use" class="sync-cfdi-use">
                        <div class="bg-blue-50 rounded-xl p-4 mb-5 flex gap-3">
                            <i class="fas fa-building-columns text-blue-500 mt-0.5"></i>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Transferencia bancaria SPEI</p>
                                <p class="text-blue-700 text-xs">Se genera una CLABE. Tu pago se acredita en <strong>minutos</strong>.</p>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-medium hover:bg-blue-700 transition-all active:scale-[.98] text-sm btn-submit-once">
                            <i class="fas fa-building-columns mr-1"></i> Generar datos SPEI
                        </button>
                    </form>
                </div>

                {{-- Transferencia manual --}}
                @if($hasBankData)
                <div id="panel-transfer" class="tab-panel p-6">
                    <form action="{{ route('buy.pay.transfer', $service->slug) }}" method="POST" enctype="multipart/form-data" id="transfer-form">
                        @csrf
                        <input type="hidden" name="name" class="sync-name">
                        <input type="hidden" name="email" class="sync-email">
                        <input type="hidden" name="phone" class="sync-phone">
                        <input type="hidden" name="domain" class="sync-domain">
                        <input type="hidden" name="domain_type" class="sync-domain-type">
                        <input type="hidden" name="billing_preference" class="sync-billing-pref">
                        <input type="hidden" name="tax_id" class="sync-rfc">
                        <input type="hidden" name="fiscal_name" class="sync-legal-name">
                        <input type="hidden" name="address_zip" class="sync-zip">
                        <input type="hidden" name="tax_system" class="sync-tax-system">
                        <input type="hidden" name="cfdi_use" class="sync-cfdi-use">
                        <div class="bg-green-50 rounded-xl p-4 mb-5 flex gap-3">
                            <i class="fas fa-money-bill-transfer text-green-600 mt-0.5 text-lg"></i>
                            <div class="text-sm text-green-800 space-y-0.5">
                                <p class="font-medium mb-1">Datos para transferencia bancaria</p>
                                @if($bankData['bank_name'])<p class="text-xs"><strong>Banco:</strong> {{ $bankData['bank_name'] }}</p>@endif
                                @if($bankData['bank_beneficiary'])<p class="text-xs"><strong>Beneficiario:</strong> {{ $bankData['bank_beneficiary'] }}</p>@endif
                                @if($bankData['bank_clabe'])<p class="text-xs"><strong>CLABE:</strong> <span class="font-mono bg-green-100 px-1 rounded">{{ $bankData['bank_clabe'] }}</span></p>@endif
                                @if($bankData['bank_account'])<p class="text-xs"><strong>Cuenta:</strong> <span class="font-mono bg-green-100 px-1 rounded">{{ $bankData['bank_account'] }}</span></p>@endif
                                @if($bankData['bank_reference'])<p class="text-xs mt-1"><strong>Referencia:</strong> {{ $bankData['bank_reference'] }}</p>@endif
                                <p class="text-xs text-green-700 mt-2 border-t border-green-200 pt-2">
                                    Transfiere exactamente <strong>${{ number_format($service->priceWithIva(), 2) }} MXN</strong> y adjunta tu comprobante.
                                </p>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Comprobante de pago <span class="text-gray-400 text-xs">(opcional)</span></label>
                            <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-3.5 rounded-xl font-medium hover:bg-green-700 transition-all active:scale-[.98] text-sm btn-submit-once">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar solicitud de pago
                        </button>
                    </form>
                </div>
                @endif
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
    // ── Billing preference ──
    let billingPref = 'none';
    window.selectBilling = function(val) {
        billingPref = val;
        document.querySelectorAll('.billing-opt').forEach(lbl => {
            const radio = lbl.querySelector('.billing-radio');
            if (radio.value === val) {
                radio.checked = true;
                lbl.classList.add('border-brand-500', 'bg-brand-50');
                lbl.classList.remove('border-transparent');
            } else {
                radio.checked = false;
                lbl.classList.remove('border-brand-500', 'bg-brand-50');
                lbl.classList.add('border-transparent');
            }
        });
        document.getElementById('fiscal-fields').classList.toggle('hidden', val !== 'fiscal');
    };
    // Init
    selectBilling('none');

    // ── Stepper logic ──
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');

    function validateFiscalFields() {
        if (billingPref !== 'fiscal') return true;
        let valid = true;
        const rfc = (document.getElementById('buyer-rfc').value || '').trim().toUpperCase();
        const legalName = (document.getElementById('buyer-legal-name').value || '').trim();
        const zip = (document.getElementById('buyer-zip').value || '').trim();

        if (!rfc || rfc.length < 12 || rfc.length > 13) {
            document.getElementById('err-rfc').classList.remove('hidden');
            valid = false;
        } else {
            document.getElementById('err-rfc').classList.add('hidden');
        }
        if (!legalName) {
            document.getElementById('err-legal-name').classList.remove('hidden');
            valid = false;
        } else {
            document.getElementById('err-legal-name').classList.add('hidden');
        }
        if (!zip || zip.length !== 5) {
            document.getElementById('err-zip').classList.remove('hidden');
            valid = false;
        } else {
            document.getElementById('err-zip').classList.add('hidden');
        }
        return valid;
    }

    function syncBillingData() {
        document.querySelectorAll('.sync-billing-pref').forEach(el => el.value = billingPref);
        if (billingPref === 'fiscal') {
            const rfc = (document.getElementById('buyer-rfc').value || '').trim().toUpperCase();
            const legalName = (document.getElementById('buyer-legal-name').value || '').trim();
            const zip = (document.getElementById('buyer-zip').value || '').trim();
            const taxSys = document.getElementById('buyer-tax-system').value;
            const cfdiUse = document.getElementById('buyer-cfdi-use').value;
            document.querySelectorAll('.sync-rfc').forEach(el => el.value = rfc);
            document.querySelectorAll('.sync-legal-name').forEach(el => el.value = legalName);
            document.querySelectorAll('.sync-zip').forEach(el => el.value = zip);
            document.querySelectorAll('.sync-tax-system').forEach(el => el.value = taxSys);
            document.querySelectorAll('.sync-cfdi-use').forEach(el => el.value = cfdiUse);
        } else {
            document.querySelectorAll('.sync-rfc, .sync-legal-name, .sync-zip, .sync-tax-system, .sync-cfdi-use')
                .forEach(el => el.value = '');
        }
    }

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

        @if($service->requires_domain)
        const domainVal = document.getElementById('domain-hidden')?.value?.trim() || '';
        const domainType = document.getElementById('domain-type-hidden')?.value || 'own';
        const domainAvail = document.getElementById('domain-available-hidden')?.value;
        const domainErr = document.getElementById('domain-err');
        if (!domainVal) {
            if (domainErr) { domainErr.textContent = 'Ingresa el dominio que deseas usar.'; domainErr.classList.remove('hidden'); }
            valid = false;
        } else if (domainType === 'cosmotown' && domainAvail !== 'true') {
            if (domainErr) { domainErr.textContent = 'Por favor verifica la disponibilidad del dominio antes de continuar.'; domainErr.classList.remove('hidden'); }
            valid = false;
        } else {
            if (domainErr) domainErr.classList.add('hidden');
        }
        @endif

        if (!validateFiscalFields()) valid = false;
        if (!valid) return;

        // Sync data
        document.querySelectorAll('.sync-name').forEach(el => el.value = name);
        document.querySelectorAll('.sync-email').forEach(el => el.value = email);
        document.querySelectorAll('.sync-phone').forEach(el => el.value = document.getElementById('buyer-phone').value);
        document.querySelectorAll('.sync-domain').forEach(el => el.value = document.getElementById('domain-hidden')?.value || '');
        document.querySelectorAll('.sync-domain-type').forEach(el => el.value = document.getElementById('domain-type-hidden')?.value || 'own');
        syncBillingData();

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

    // ── Tabs ──
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
        });
    });

    // ── Mercado Pago Secure Fields ──
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

                const installments = await mp.getInstallments({ amount: '{{ $service->priceWithIva() }}', bin });
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
            const response = await fetch('{{ route("buy.pay.card", $service->slug) }}', {
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
                    billing_preference: billingPref,
                    tax_id: billingPref === 'fiscal' ? (document.getElementById('buyer-rfc').value || '').trim().toUpperCase() : '',
                    fiscal_name: billingPref === 'fiscal' ? (document.getElementById('buyer-legal-name').value || '').trim() : '',
                    address_zip: billingPref === 'fiscal' ? (document.getElementById('buyer-zip').value || '').trim() : '',
                    tax_system: billingPref === 'fiscal' ? document.getElementById('buyer-tax-system').value : '',
                    cfdi_use: billingPref === 'fiscal' ? document.getElementById('buyer-cfdi-use').value : '',
                }),
            });
            const data = await response.json();
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                const reason = rejectionReasons[data.status_detail] || data.error || 'No se pudo procesar el pago. Intenta de nuevo.';
                showError(reason);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pagar ${{ number_format($service->priceWithIva(), 2) }} MXN';
            }
        } catch (err) {
            showError('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pagar ${{ number_format($service->priceWithIva(), 2) }} MXN';
        }
    });
});

// Anti doble-submit para OXXO, SPEI y Transferencia
document.querySelectorAll('.btn-submit-once').forEach(btn => {
    btn.closest('form').addEventListener('submit', function() {
        if (btn.dataset.submitted) { event.preventDefault(); return; }
        btn.dataset.submitted = '1';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Procesando...';
    });
});
</script>
</body>
</html>
