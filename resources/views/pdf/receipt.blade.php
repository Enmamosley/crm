<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de pago {{ $ref }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #2d3748; line-height: 1.6; }
        .top-bar { width: 100%; height: 8px; background: linear-gradient(90deg, #15803d 0%, #22c55e 100%); }
        .page-wrapper { padding: 28px 50px 20px 50px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header-left { display: table-cell; vertical-align: top; width: 55%; }
        .header-right { display: table-cell; vertical-align: top; width: 45%; text-align: right; }
        .logo { max-height: 45px; max-width: 160px; margin-bottom: 8px; }
        .company-name { font-size: 20px; font-weight: bold; color: #14532d; letter-spacing: 0.5px; margin-bottom: 6px; }
        .company-info { font-size: 9.5px; color: #718096; line-height: 1.7; }
        .badge { display: inline-block; background-color: #15803d; color: #fff; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; padding: 5px 18px; margin-bottom: 8px; }
        .doc-number { font-size: 20px; font-weight: bold; color: #14532d; }
        .doc-meta { margin-top: 6px; font-size: 9.5px; color: #718096; }
        .doc-meta strong { color: #4a5568; }
        .divider { border: none; border-top: 1.5px solid #e2e8f0; margin: 0 0 18px 0; }
        .info-section { display: table; width: 100%; margin-bottom: 18px; }
        .info-box { display: table-cell; vertical-align: top; width: 50%; }
        .info-box-right { display: table-cell; vertical-align: top; width: 50%; text-align: right; }
        .info-box-header { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #a0aec0; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1.5px solid #e2e8f0; }
        .client-name { font-size: 15px; font-weight: bold; color: #14532d; margin-bottom: 4px; }
        .client-detail { font-size: 10px; color: #718096; margin-bottom: 3px; }
        .date-row { margin-bottom: 6px; }
        .date-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #a0aec0; margin-bottom: 2px; }
        .date-value { font-size: 12px; font-weight: bold; color: #2d3748; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .items-table thead th { background-color: #14532d; color: #fff; padding: 7px 14px; text-align: left; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .items-table thead th:last-child { text-align: right; }
        .items-table tbody td { padding: 9px 14px; font-size: 10.5px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
        .items-table tbody td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
        .item-name { font-weight: 600; color: #2d3748; }
        .totals-wrapper { display: table; width: 100%; }
        .totals-right { display: table-cell; vertical-align: top; width: 45%; }
        .totals-left { display: table-cell; width: 55%; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 5px 14px; font-size: 11px; }
        .totals-table .label-cell { text-align: right; color: #718096; font-weight: 500; }
        .totals-table .value-cell { text-align: right; font-weight: 600; color: #2d3748; width: 130px; font-variant-numeric: tabular-nums; }
        .totals-table .subtotal-row td { border-top: 1.5px solid #e2e8f0; }
        .totals-table .grand-total-row td { border-top: 2px solid #15803d; padding-top: 8px; padding-bottom: 8px; }
        .totals-table .grand-total-row .label-cell { font-size: 13px; font-weight: bold; color: #14532d; }
        .totals-table .grand-total-row .value-cell { font-size: 16px; font-weight: bold; color: #14532d; }
        .pay-box { margin-top: 18px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 14px 18px; }
        .pay-box-header { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #15803d; margin-bottom: 8px; }
        .pay-row { font-size: 10.5px; color: #4a5568; margin-bottom: 3px; }
        .pay-row strong { color: #14532d; }
        .note { margin-top: 14px; font-size: 9px; color: #a0aec0; }
        .footer { margin-top: 18px; padding-top: 10px; border-top: 1.5px solid #e2e8f0; text-align: center; }
        .footer-company { font-size: 9px; font-weight: bold; color: #14532d; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 3px; }
        .footer-contact { font-size: 8.5px; color: #a0aec0; }
        .footer-generated { font-size: 7.5px; color: #cbd5e0; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="page-wrapper">
        <div class="header">
            <div class="header-left">
                @if(!empty($settings['company_logo']))
                    <img src="{{ public_path('storage/' . $settings['company_logo']) }}" alt="Logo" class="logo">
                @endif
                <div class="company-name">{{ $settings['company_name'] }}</div>
                <div class="company-info">
                    @if(!empty($settings['company_rfc']))RFC: {{ $settings['company_rfc'] }}<br>@endif
                    @if(!empty($settings['company_address'])){{ $settings['company_address'] }}<br>@endif
                    @if(!empty($settings['company_phone']))Tel: {{ $settings['company_phone'] }}<br>@endif
                    @if(!empty($settings['company_email'])){{ $settings['company_email'] }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="badge">Recibo de pago</div><br>
                <div class="doc-number">{{ $ref }}</div>
                <div class="doc-meta">
                    <strong>Fecha de pago:</strong> {{ optional($order->paid_at)->format('d/m/Y H:i') ?? '—' }}
                </div>
            </div>
        </div>

        <hr class="divider">

        <div class="info-section">
            <div class="info-box">
                <div class="info-box-header">Cliente</div>
                <div class="client-name">{{ $client->name ?: $client->legal_name }}</div>
                @if($client->email)<div class="client-detail">Email: {{ $client->email }}</div>@endif
                @if($client->phone)<div class="client-detail">Tel: {{ $client->phone }}</div>@endif
                @if($client->tax_id)<div class="client-detail">RFC: {{ $client->tax_id }}</div>@endif
            </div>
            <div class="info-box-right">
                <div class="info-box-header">Comprobante</div>
                <div class="date-row">
                    <div class="date-label">Folio / Orden</div>
                    <div class="date-value">{{ $ref }}</div>
                </div>
                <div class="date-row">
                    <div class="date-label">Estado</div>
                    <div class="date-value" style="color:#15803d;">Pagado</div>
                </div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr><th>Concepto</th><th>Importe</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><div class="item-name">{{ \Illuminate\Support\Str::of($order->notes ?? 'Servicio')->replace(['Compra directa: ', 'Carrito: '], '') }}</div></td>
                    <td>${{ number_format($order->subtotal, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals-wrapper">
            <div class="totals-left"></div>
            <div class="totals-right">
                <table class="totals-table">
                    <tr class="subtotal-row">
                        <td class="label-cell">Subtotal</td>
                        <td class="value-cell">${{ number_format($order->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">IVA</td>
                        <td class="value-cell">${{ number_format($order->iva_amount, 2) }}</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td class="label-cell">Total pagado</td>
                        <td class="value-cell">${{ number_format($order->total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($payment)
        <div class="pay-box">
            <div class="pay-box-header">Detalle del pago</div>
            <div class="pay-row"><strong>Método:</strong> {{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'N/A')) }}</div>
            <div class="pay-row"><strong>Monto:</strong> ${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</div>
            <div class="pay-row"><strong>Fecha:</strong> {{ optional($payment->paid_at)->format('d/m/Y H:i') ?? '—' }}</div>
            @if($payment->mp_payment_id)<div class="pay-row"><strong>Ref. MercadoPago:</strong> {{ $payment->mp_payment_id }}</div>@endif
            @if($payment->paypal_order_id)<div class="pay-row"><strong>Ref. PayPal:</strong> {{ $payment->paypal_order_id }}</div>@endif
        </div>
        @endif

        <div class="note">Este documento es un comprobante de pago (no fiscal). Si requiere factura (CFDI), solicítela con sus datos fiscales.</div>

        <div class="footer">
            <div class="footer-company">{{ $settings['company_name'] }}</div>
            <div class="footer-contact">
                {{ $settings['company_email'] }}
                @if(!empty($settings['company_phone']))&nbsp;&middot;&nbsp;{{ $settings['company_phone'] }}@endif
            </div>
            <div class="footer-generated">Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} hrs</div>
        </div>
    </div>
</body>
</html>
