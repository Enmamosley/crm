<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $quote->quote_number }}</title>
    <style>
        @page {
            margin: 0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #2d3748;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* ── Accent bar at the very top ── */
        .top-bar {
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, #1e3a5f 0%, #2c7be5 100%);
        }

        .page-wrapper {
            padding: 40px 50px 30px 50px;
        }

        /* ── Header ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 35px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 55%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            width: 45%;
            text-align: right;
        }

        .logo { max-height: 55px; max-width: 180px; margin-bottom: 12px; }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .company-info {
            font-size: 9.5px;
            color: #718096;
            line-height: 1.7;
        }

        .quote-badge {
            display: inline-block;
            background-color: #1e3a5f;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 3px;
            padding: 8px 22px;
            margin-bottom: 14px;
        }
        .quote-number {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 0.5px;
        }
        .quote-meta {
            margin-top: 12px;
            font-size: 9.5px;
            color: #718096;
        }
        .quote-meta strong {
            color: #4a5568;
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1.5px solid #e2e8f0;
            margin: 0 0 30px 0;
        }

        /* ── Client & Date ── */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .info-box {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .info-box-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            text-align: right;
        }

        .info-box-header {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #a0aec0;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #e2e8f0;
        }

        .client-name {
            font-size: 15px;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 4px;
        }
        .client-business {
            font-size: 11px;
            color: #4a5568;
            margin-bottom: 8px;
        }
        .client-detail {
            font-size: 10px;
            color: #718096;
            margin-bottom: 3px;
        }
        .client-detail i {
            display: inline-block;
            width: 14px;
            color: #a0aec0;
        }

        .date-row {
            margin-bottom: 10px;
        }
        .date-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a0aec0;
            margin-bottom: 2px;
        }
        .date-value {
            font-size: 12px;
            font-weight: bold;
            color: #2d3748;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .items-table thead th {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 11px 14px;
            text-align: left;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .items-table thead th:nth-child(1) {
            width: 8%;
            text-align: center;
        }
        .items-table thead th:nth-child(2) {
            width: 47%;
        }
        .items-table thead th:nth-child(3) {
            width: 12%;
            text-align: center;
        }
        .items-table thead th:nth-child(4),
        .items-table thead th:nth-child(5) {
            text-align: right;
        }

        .items-table tbody td {
            padding: 12px 14px;
            font-size: 10.5px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }
        .items-table tbody tr:nth-child(even) td {
            background-color: #f7fafc;
        }
        .items-table tbody td:nth-child(1) {
            text-align: center;
            color: #a0aec0;
            font-size: 10px;
        }
        .items-table tbody td:nth-child(3) {
            text-align: center;
        }
        .items-table tbody td:nth-child(4),
        .items-table tbody td:nth-child(5) {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .item-name {
            font-weight: 600;
            color: #2d3748;
        }
        .item-category {
            font-size: 9px;
            color: #a0aec0;
            margin-top: 2px;
        }

        /* ── Totals ── */
        .totals-wrapper {
            display: table;
            width: 100%;
            margin-top: 0;
        }
        .totals-left {
            display: table-cell;
            vertical-align: top;
            width: 55%;
        }
        .totals-right {
            display: table-cell;
            vertical-align: top;
            width: 45%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 9px 14px;
            font-size: 11px;
        }
        .totals-table .label-cell {
            text-align: right;
            color: #718096;
            font-weight: 500;
        }
        .totals-table .value-cell {
            text-align: right;
            font-weight: 600;
            color: #2d3748;
            width: 130px;
            font-variant-numeric: tabular-nums;
        }
        .totals-table .subtotal-row td {
            border-top: 1.5px solid #e2e8f0;
        }
        .totals-table .iva-row td {
            /* normal row */
        }
        .totals-table .grand-total-row td {
            border-top: 2px solid #1e3a5f;
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .totals-table .grand-total-row .label-cell {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a5f;
        }
        .totals-table .grand-total-row .value-cell {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a5f;
        }

        .currency-note {
            text-align: right;
            font-size: 8.5px;
            color: #a0aec0;
            margin-top: 4px;
            padding-right: 14px;
        }

        /* ── Notes ── */
        .notes-section {
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .notes-header {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #a0aec0;
            margin-bottom: 8px;
        }
        .notes-content {
            padding: 14px 16px;
            background-color: #f7fafc;
            border-left: 3px solid #2c7be5;
            font-size: 10px;
            color: #4a5568;
            line-height: 1.7;
        }

        /* ── Terms / Validity ── */
        .terms-section {
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .terms-header {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #a0aec0;
            margin-bottom: 8px;
        }
        .terms-list {
            padding-left: 18px;
            font-size: 9.5px;
            color: #718096;
            line-height: 1.9;
        }
        .terms-list li {
            margin-bottom: 2px;
        }

        /* ── Acceptance bar ── */
        .acceptance-bar {
            margin-top: 32px;
            background: #f0f7ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 14px 18px;
            display: table;
            width: 100%;
        }
        .acceptance-bar-left {
            display: table-cell;
            vertical-align: middle;
            width: 70%;
        }
        .acceptance-bar-right {
            display: table-cell;
            vertical-align: middle;
            width: 30%;
            text-align: right;
        }
        .acceptance-bar-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 3px;
        }
        .acceptance-bar-text {
            font-size: 9px;
            color: #3b82f6;
        }
        .acceptance-bar-contact {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a5f;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 30px;
            padding-top: 14px;
            border-top: 1.5px solid #e2e8f0;
            text-align: center;
        }
        .footer-company {
            font-size: 9px;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .footer-contact {
            font-size: 8.5px;
            color: #a0aec0;
        }
        .footer-generated {
            font-size: 7.5px;
            color: #cbd5e0;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <!-- Accent bar -->
    <div class="top-bar"></div>

    <div class="page-wrapper">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                @if($settings['company_logo'])
                    <img src="{{ public_path('storage/' . $settings['company_logo']) }}" alt="Logo" class="logo">
                @endif
                <div class="company-name">{{ $settings['company_name'] }}</div>
                <div class="company-info">
                    @if($settings['company_rfc'])RFC: {{ $settings['company_rfc'] }}<br>@endif
                    @if($settings['company_address']){{ $settings['company_address'] }}<br>@endif
                    @if($settings['company_phone'])Tel: {{ $settings['company_phone'] }}<br>@endif
                    @if($settings['company_email']){{ $settings['company_email'] }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="quote-badge">Cotización</div><br>
                <div class="quote-number">{{ $quote->quote_number }}</div>
                <div class="quote-meta">
                    <strong>Fecha:</strong> {{ $quote->created_at->format('d \d\e F \d\e Y') }}<br>
                    <strong>Vigencia:</strong> {{ $quote->valid_until->format('d \d\e F \d\e Y') }}
                </div>
            </div>
        </div>

        <hr class="divider">

        <!-- Client & Dates -->
        <div class="info-section">
            <div class="info-box">
                <div class="info-box-header">Datos del cliente</div>
                <div class="client-name">{{ $quote->lead->name }}</div>
                @if($quote->lead->business)
                    <div class="client-business">{{ $quote->lead->business }}</div>
                @endif
                @if($quote->lead->email)
                    <div class="client-detail">✉ {{ $quote->lead->email }}</div>
                @endif
                @if($quote->lead->phone)
                    <div class="client-detail">✆ {{ $quote->lead->phone }}</div>
                @endif
            </div>
            <div class="info-box-right">
                <div class="info-box-header">Fechas</div>
                <div class="date-row">
                    <div class="date-label">Emisión</div>
                    <div class="date-value">{{ $quote->created_at->format('d/m/Y') }}</div>
                </div>
                <div class="date-row">
                    <div class="date-label">Válida hasta</div>
                    <div class="date-value">{{ $quote->valid_until->format('d/m/Y') }}</div>
                </div>
                <div class="date-row">
                    <div class="date-label">Estado</div>
                    <div class="date-value" style="color: #2c7be5;">{{ ucfirst($quote->status) }}</div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descripción del servicio</th>
                    <th>Cant.</th>
                    <th>Precio unitario</th>
                    <th>Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $index => $item)
                    <tr>
                        <td>{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <div class="item-name">{{ $item->description }}</div>
                            @if($item->service && $item->service->category)
                                <div class="item-category">{{ $item->service->category->name }}</div>
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->unit_price, 2) }}</td>
                        <td>${{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-wrapper">
            <div class="totals-left"></div>
            <div class="totals-right">
                <table class="totals-table">
                    <tr class="subtotal-row">
                        <td class="label-cell">Subtotal</td>
                        <td class="value-cell">${{ number_format($quote->subtotal, 2) }}</td>
                    </tr>
                    <tr class="iva-row">
                        <td class="label-cell">IVA ({{ number_format($quote->iva_percentage, 0) }}%)</td>
                        <td class="value-cell">${{ number_format($quote->iva_amount, 2) }}</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td class="label-cell">Total</td>
                        <td class="value-cell">${{ number_format($quote->total, 2) }}</td>
                    </tr>
                </table>
                <div class="currency-note">* Todos los montos están expresados en pesos mexicanos (MXN)</div>
            </div>
        </div>

        <!-- Notes -->
        @if($quote->notes)
            <div class="notes-section">
                <div class="notes-header">Observaciones</div>
                <div class="notes-content">{{ $quote->notes }}</div>
            </div>
        @endif

        <!-- Terms -->
        <div class="terms-section">
            <div class="terms-header">Condiciones</div>
            <ol class="terms-list">
                <li>Esta cotización tiene una vigencia de <strong>30 días</strong> a partir de la fecha de emisión.</li>
                <li>Los precios no incluyen servicios adicionales no especificados en este documento.</li>
                <li>Los tiempos de entrega serán acordados una vez aceptada la cotización.</li>
                <li>Se requiere un anticipo del 50% para iniciar el proyecto y el 50% restante al finalizar.</li>
            </ol>
        </div>

        <!-- Acceptance bar -->
        <div class="acceptance-bar">
            <div class="acceptance-bar-left">
                <div class="acceptance-bar-title">¿Listo para comenzar?</div>
                <div class="acceptance-bar-text">Contáctenos para confirmar esta cotización o solicitar ajustes antes de su vencimiento.</div>
            </div>
            <div class="acceptance-bar-right">
                @if($settings['company_email'])
                    <div class="acceptance-bar-contact">{{ $settings['company_email'] }}</div>
                @endif
                @if($settings['company_phone'])
                    <div style="font-size:9px;color:#718096;">{{ $settings['company_phone'] }}</div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-company">{{ $settings['company_name'] }}</div>
            <div class="footer-contact">
                {{ $settings['company_email'] }}
                @if($settings['company_phone'])&nbsp;&middot;&nbsp;{{ $settings['company_phone'] }}@endif
                @if($settings['company_address'])&nbsp;&middot;&nbsp;{{ $settings['company_address'] }}@endif
            </div>
            <div class="footer-generated">Documento generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} hrs</div>
        </div>
    </div>
</body>
</html>
