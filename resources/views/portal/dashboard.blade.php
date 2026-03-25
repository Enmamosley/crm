<!DOCTYPE html>
<html lang="es">
<head>
    <title>Portal Cliente — {{ $client->legal_name }}</title>
    @include('buy._head')
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="" class="h-8">
                @endif
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Mi Portal</h1>
                    <p class="text-xs text-gray-400">{{ $client->legal_name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs text-gray-400">RFC: <span class="font-mono font-medium text-gray-600">{{ $client->tax_id }}</span></p>
                </div>
                <a href="{{ route('portal.fiscal.edit', $client->portal_token) }}"
                   class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium transition">
                    <i class="fas fa-edit mr-1"></i> Datos fiscales
                </a>
                @if($client->domain && $client->domain_type === 'cosmotown')
                <a href="{{ route('portal.domain', $client->portal_token) }}"
                   class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium transition">
                    <i class="fas fa-globe mr-1"></i> Dominio
                </a>
                @endif
                <a href="{{ route('portal.tickets.index', $client->portal_token) }}"
                   class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium transition">
                    <i class="fas fa-headset mr-1"></i> Soporte
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8 space-y-8">

        {{-- Quick Stats --}}
        @php
            $allQuotes = $client->lead?->quotes ?? collect();
            $pendingInvoices = $client->invoices->filter(fn($i) => !$i->paid_at && $i->status !== 'cancelled');
            $totalOwed = $pendingInvoices->sum('total');
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 animate-fade-in">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center"><i class="fas fa-file-invoice-dollar text-blue-500 text-sm"></i></div>
                    <div>
                        <p class="text-xs text-gray-400">Cotizaciones</p>
                        <p class="text-lg font-bold text-gray-900">{{ $allQuotes->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center"><i class="fas fa-receipt text-green-500 text-sm"></i></div>
                    <div>
                        <p class="text-xs text-gray-400">Facturas</p>
                        <p class="text-lg font-bold text-gray-900">{{ $client->invoices->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-yellow-50 rounded-lg flex items-center justify-center"><i class="fas fa-clock text-yellow-500 text-sm"></i></div>
                    <div>
                        <p class="text-xs text-gray-400">Por pagar</p>
                        <p class="text-lg font-bold text-gray-900">{{ $pendingInvoices->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-brand-50 rounded-lg flex items-center justify-center"><i class="fas fa-dollar-sign text-brand-500 text-sm"></i></div>
                    <div>
                        <p class="text-xs text-gray-400">Saldo</p>
                        <p class="text-lg font-bold text-gray-900">${{ number_format($totalOwed, 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cotizaciones --}}
        <section class="animate-slide-up">
            <h2 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 bg-blue-50 rounded-lg flex items-center justify-center"><i class="fas fa-file-invoice-dollar text-blue-500 text-xs"></i></span>
                Cotizaciones
            </h2>

            @if($allQuotes->isEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i class="fas fa-file-invoice text-gray-200 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-400">Sin cotizaciones.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($allQuotes as $quote)
                    @php
                        $qColors = ['borrador'=>'gray','enviada'=>'blue','aceptada'=>'green','rechazada'=>'red','vencida'=>'yellow'];
                        $qc = $qColors[$quote->status] ?? 'gray';
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center justify-between hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-{{ $qc }}-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-alt text-{{ $qc }}-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">{{ $quote->quote_number }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $quote->items_count ?? $quote->items->count() }} servicio(s) ·
                                    Válida hasta {{ $quote->valid_until?->format('d/m/Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="font-bold text-gray-900 text-sm">${{ number_format($quote->total, 2) }}</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $qc }}-100 text-{{ $qc }}-700">
                                {{ ucfirst($quote->status) }}
                            </span>
                            <a href="{{ route('portal.quote.show', [$client->portal_token, $quote]) }}"
                               class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 rounded-lg px-2.5 py-1 font-medium transition">
                                <i class="fas fa-eye mr-1"></i> Ver
                            </a>
                            <a href="{{ route('admin.quotes.pdf', $quote) }}" target="_blank"
                               class="text-xs text-red-500 hover:text-red-700 bg-red-50 rounded-lg px-2.5 py-1 font-medium transition">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Facturas --}}
        <section class="animate-slide-up" style="animation-delay:.1s">
            <h2 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 bg-green-50 rounded-lg flex items-center justify-center"><i class="fas fa-receipt text-green-500 text-xs"></i></span>
                Facturas
            </h2>

            @if($client->invoices->isEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i class="fas fa-receipt text-gray-200 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-400">Sin facturas aún.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($client->invoices as $invoice)
                    @php
                        $iColors = ['draft'=>'gray','sent'=>'blue','pending'=>'yellow','valid'=>'green','cancelled'=>'red'];
                        $iLabels = ['draft'=>'Borrador','sent'=>'Pagada','pending'=>'Procesando','valid'=>'Timbrada','cancelled'=>'Cancelada'];
                        $ic = $iColors[$invoice->status] ?? 'gray';
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center justify-between hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-{{ $ic }}-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-receipt text-{{ $ic }}-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">
                                    {{ $invoice->folio() ?: 'Folio pendiente' }}
                                    @if($invoice->quote)
                                        <span class="text-gray-400 font-normal text-xs ml-1">/ {{ $invoice->quote->quote_number }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ $invoice->created_at->format('d/m/Y') }}
                                    @if($invoice->paid_at)
                                        · <span class="text-green-500 font-medium"><i class="fas fa-check-circle text-[10px]"></i> Pagada {{ $invoice->paid_at->format('d/m/Y') }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-gray-900 text-sm">${{ number_format($invoice->total, 2) }}</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $ic }}-100 text-{{ $ic }}-700">
                                {{ $iLabels[$invoice->status] ?? $invoice->status }}
                            </span>
                            @if(!$invoice->paid_at && $invoice->status !== 'cancelled' && \App\Models\Setting::get('mp_public_key'))
                                <a href="{{ route('portal.checkout', [$client->portal_token, $invoice]) }}"
                                   class="btn-primary text-xs px-3 py-1.5 rounded-lg">
                                    <i class="fas fa-credit-card mr-1"></i> Pagar
                                </a>
                            @endif
                            @if($invoice->isStamped())
                                <a href="{{ route('portal.invoice.pdf', [$client->portal_token, $invoice]) }}"
                                   class="text-xs text-red-500 hover:text-red-700 bg-red-50 rounded-lg px-2.5 py-1 font-medium transition">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <a href="{{ route('portal.invoice.xml', [$client->portal_token, $invoice]) }}"
                                   class="text-xs text-orange-500 hover:text-orange-700 bg-orange-50 rounded-lg px-2.5 py-1 font-medium transition">
                                    <i class="fas fa-file-code"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Dominio --}}
        @if($client->domain && $client->domain_type === 'cosmotown')
        <section class="animate-slide-up" style="animation-delay:.15s">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-7 h-7 bg-blue-50 rounded-lg flex items-center justify-center"><i class="fas fa-globe text-blue-500 text-xs"></i></span>
                    Mi Dominio
                    <span class="text-xs font-normal font-mono text-blue-600 ml-1">{{ $client->domain }}</span>
                </h2>
                <a href="{{ route('portal.domain', $client->portal_token) }}" class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium transition">
                    Gestionar <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <p class="text-sm text-gray-600">Administra los nameservers y registros DNS de tu dominio.</p>
            </div>
        </section>
        @endif

        {{-- Correo electrónico --}}
        @if($hasEmailService)
        <section class="animate-slide-up" style="animation-delay:.2s">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-7 h-7 bg-brand-50 rounded-lg flex items-center justify-center"><i class="fas fa-envelope text-brand-500 text-xs"></i></span>
                    Correo electrónico
                    @if($emailDomain)<span class="text-xs font-normal text-gray-400 ml-1">{{ $emailDomain }}</span>@endif
                </h2>
                <a href="{{ route('portal.mailboxes', $client->portal_token) }}" class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium transition">
                    Gestionar <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            @if(!empty($mailboxes))
            <div class="space-y-2">
                @foreach($mailboxes as $box)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center justify-between hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-brand-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-at text-brand-500 text-sm"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm font-mono">{{ $box['local'] }}@if($emailDomain)<span class="text-gray-400">{{ '@' . $emailDomain }}</span>@endif</p>
                            <p class="text-xs text-gray-400">
                                @if(isset($box['usageMB']) && isset($box['quotaMB']))
                                    {{ number_format($box['usageMB'], 1) }} / {{ number_format($box['quotaMB']) }} MB
                                @endif
                                @if(!($box['enabled'] ?? true))
                                    · <span class="text-red-500">Desactivado</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('portal.mailbox.webmail', [$client->portal_token, $box['id']]) }}">
                        @csrf
                        <button type="submit" class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 border border-brand-200 rounded-lg px-3 py-1.5 hover:bg-brand-100 transition font-medium">
                            <i class="fas fa-arrow-up-right-from-square mr-1"></i> Webmail
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
            @else
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <i class="fas fa-envelope text-gray-200 text-3xl mb-2"></i>
                <p class="text-sm text-gray-400">Aún no tienes buzones creados.</p>
                <a href="{{ route('portal.mailboxes', $client->portal_token) }}" class="inline-block mt-3 text-xs text-brand-600 hover:text-brand-800 font-medium">Crear primer buzón <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            @endif
        </section>
        @endif

        {{-- Documentos --}}
        <section class="animate-slide-up" style="animation-delay:.3s">
            <h2 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 bg-purple-50 rounded-lg flex items-center justify-center"><i class="fas fa-folder-open text-purple-500 text-xs"></i></span>
                Documentos
            </h2>

            @if($client->documents->isEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i class="fas fa-folder-open text-gray-200 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-400">Sin documentos cargados.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($client->documents as $doc)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 hover:shadow-md transition-shadow">
                        <div class="w-9 h-9 bg-gray-50 rounded-lg flex items-center justify-center text-gray-500">
                            @php
                                $icon = 'fa-file';
                                if (str_contains($doc->file_type ?? '', 'pdf')) $icon = 'fa-file-pdf text-red-500';
                                elseif (str_contains($doc->file_type ?? '', 'image')) $icon = 'fa-file-image text-blue-500';
                                elseif (str_contains($doc->file_type ?? '', 'word') || str_contains($doc->file_type ?? '', 'document')) $icon = 'fa-file-word text-blue-700';
                                elseif (str_contains($doc->file_type ?? '', 'sheet') || str_contains($doc->file_type ?? '', 'excel')) $icon = 'fa-file-excel text-green-600';
                            @endphp
                            <i class="fas {{ $icon }} text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ $doc->name }}</p>
                            <p class="text-xs text-gray-400">{{ $doc->sizeForHumans() }}</p>
                        </div>
                        <a href="{{ route('portal.document.download', [$client->portal_token, $doc]) }}"
                           class="text-brand-600 hover:text-brand-800 text-sm bg-brand-50 w-8 h-8 rounded-lg flex items-center justify-center transition">
                            <i class="fas fa-download text-xs"></i>
                        </a>
                    </div>
                    @endforeach
                </div>
            @endif
        </section>
    </main>

    <footer class="text-center py-8 text-xs text-gray-300">
        Portal privado · {{ $client->legal_name }} · {{ now()->year }}
    </footer>
</body>
</html>
