{{--
    Sección de selección de dominio en el checkout público.
    Uso: @include('buy._domain-step')
    Expone los IDs: #domain-hidden, #domain-type-hidden, #domain-available-hidden
    para que el JS del checkout pueda leer los valores.
--}}
<div
    id="domain-section"
    x-data="{
        domainChoice: 'new',
        domainValue: '',
        domainChecked: false,
        domainAvailable: null,
        domainChecking: false,
        domainPrice: '',
        domainError: '',
        async checkDomain() {
            const d = this.domainValue.trim();
            if (!d) return;
            this.domainChecking = true;
            this.domainChecked = false;
            this.domainAvailable = null;
            this.domainError = '';
            this.domainPrice = '';
            try {
                const res = await fetch('{{ route('buy.domain.check') }}?domain=' + encodeURIComponent(d), {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (json.error && !json.available) {
                    this.domainError = json.error;
                } else if (json.available === null) {
                    this.domainError = json.message || '';
                    this.domainChecked = true;
                } else {
                    this.domainAvailable = json.available;
                    this.domainChecked = true;
                    if (json.price) this.domainPrice = json.currency + ' ' + json.price;
                }
            } catch (e) {
                this.domainError = 'Error al verificar. Intenta de nuevo.';
            } finally {
                this.domainChecking = false;
            }
        }
    }"
    class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="font-bold text-gray-900 mb-1">
        <i class="fas fa-globe text-indigo-500 mr-1.5"></i> Tu dominio
    </h3>
    <p class="text-sm text-gray-400 mb-5">Elige si quieres registrar un dominio nuevo o usar uno que ya tienes.</p>

    {{-- Tabs: nuevo / propio --}}
    <div class="flex gap-3 mb-5">
        <button type="button"
            @click="domainChoice = 'new'; domainChecked = false; domainAvailable = null; domainError = ''"
            :class="domainChoice === 'new'
                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                : 'border-gray-200 text-gray-500 hover:border-gray-300'"
            class="flex-1 py-2.5 px-4 rounded-xl border-2 text-sm transition-all duration-150 flex items-center justify-center gap-2">
            <i class="fas fa-plus-circle"></i> Registrar dominio nuevo
        </button>
        <button type="button"
            @click="domainChoice = 'own'; domainChecked = false; domainAvailable = null; domainError = ''"
            :class="domainChoice === 'own'
                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                : 'border-gray-200 text-gray-500 hover:border-gray-300'"
            class="flex-1 py-2.5 px-4 rounded-xl border-2 text-sm transition-all duration-150 flex items-center justify-center gap-2">
            <i class="fas fa-server"></i> Ya tengo dominio
        </button>
    </div>

    {{-- Opción: Registrar nuevo --}}
    <div x-show="domainChoice === 'new'" x-cloak>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">¿Qué dominio quieres? <span class="text-gray-400 font-normal">(ej: miempresa.com)</span></label>
        <div class="flex gap-2">
            <input type="text" x-model="domainValue"
                @keydown.enter.prevent="checkDomain()"
                placeholder="miempresa.com"
                class="flex-1 input-field font-mono text-sm"
                autocomplete="off" autocorrect="off" spellcheck="false">
            <button type="button" @click="checkDomain()"
                :disabled="domainChecking || !domainValue.trim()"
                class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1.5 whitespace-nowrap">
                <svg x-show="domainChecking" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span x-text="domainChecking ? 'Verificando...' : 'Verificar'"></span>
            </button>
        </div>

        {{-- Resultado disponible --}}
        <div x-show="domainChecked && domainAvailable === true" x-cloak
            class="mt-3 bg-green-50 border border-green-200 rounded-xl p-3 flex items-center gap-3 text-sm">
            <i class="fas fa-check-circle text-green-500 text-base"></i>
            <div>
                <span class="font-medium text-green-700" x-text="domainValue"></span>
                <span class="text-green-600"> está disponible</span>
                <span x-show="domainPrice" class="text-green-500 ml-2" x-text="domainPrice ? '— ' + domainPrice : ''"></span>
                <p class="text-xs text-green-600 mt-0.5">Se registrará automáticamente al completar tu compra.</p>
            </div>
        </div>

        {{-- Resultado no disponible --}}
        <div x-show="domainChecked && domainAvailable === false" x-cloak
            class="mt-3 bg-red-50 border border-red-200 rounded-xl p-3 flex items-start gap-3 text-sm">
            <i class="fas fa-times-circle text-red-400 text-base mt-0.5"></i>
            <div>
                <span class="font-medium text-red-700" x-text="domainValue"></span>
                <span class="text-red-600"> no está disponible.</span>
                <p class="text-xs text-red-500 mt-0.5">Intenta con otro nombre o una extensión diferente (.net, .mx, .org).</p>
            </div>
        </div>

        {{-- Error verificación --}}
        <div x-show="domainError" x-cloak
            class="mt-3 bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-sm text-yellow-700">
            <i class="fas fa-exclamation-triangle mr-1.5"></i>
            <span x-text="domainError"></span>
        </div>

        <p class="text-xs text-gray-400 mt-2">El dominio se registrará en tu nombre y quedará activo en 24–48 h.</p>
    </div>

    {{-- Opción: Dominio propio --}}
    <div x-show="domainChoice === 'own'" x-cloak>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tu dominio actual <span class="text-gray-400 font-normal">(ej: miempresa.com)</span></label>
        <input type="text" x-model="domainValue"
            placeholder="tudominio.com"
            class="w-full input-field font-mono text-sm"
            autocomplete="off" autocorrect="off" spellcheck="false">
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            Necesitarás apuntar tus DNS a nuestros servidores. Te enviaremos las instrucciones por correo.
        </p>
    </div>

    {{-- Campos espejo — dentro del scope Alpine para que x-bind funcione --}}
    <input type="hidden" id="domain-hidden"           x-bind:value="domainValue.trim()">
    <input type="hidden" id="domain-type-hidden"      x-bind:value="domainChoice === 'new' ? 'cosmotown' : 'own'">
    <input type="hidden" id="domain-available-hidden" x-bind:value="String(domainAvailable)">
</div>
