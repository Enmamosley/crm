<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CFDI {{ $uuid }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9.5px; color: #2d3748; line-height: 1.5; }
        .top-bar { width: 100%; height: 8px; background: #1e3a5f; }
        .page { padding: 22px 40px; }
        .header { display: table; width: 100%; margin-bottom: 12px; }
        .h-left { display: table-cell; width: 60%; vertical-align: top; }
        .h-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company { font-size: 16px; font-weight: bold; color: #1e3a5f; }
        .badge { display: inline-block; background: #1e3a5f; color: #fff; font-size: 9px; font-weight: bold; letter-spacing: 2px; padding: 4px 12px; margin-bottom: 4px; }
        .folio { font-size: 15px; font-weight: bold; color: #1e3a5f; }
        .uuid { font-size: 8px; color: #718096; font-family: monospace; }
        .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #a0aec0; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin: 10px 0 6px; }
        .grid { display: table; width: 100%; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .kv { margin-bottom: 2px; }
        .kv b { color: #1e3a5f; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th { background: #1e3a5f; color: #fff; padding: 5px 8px; font-size: 8px; text-transform: uppercase; text-align: left; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 5px 8px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
        .totals { width: 240px; margin-left: auto; margin-top: 6px; }
        .totals td { padding: 3px 8px; font-size: 10px; }
        .totals .label { text-align: right; color: #718096; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .grand td { border-top: 2px solid #1e3a5f; font-size: 12px; color: #1e3a5f; }
        .stamps { margin-top: 14px; display: table; width: 100%; }
        .qr-cell { display: table-cell; width: 130px; vertical-align: top; }
        .seals-cell { display: table-cell; vertical-align: top; padding-left: 12px; }
        .seal { font-family: monospace; font-size: 6px; color: #718096; word-break: break-all; margin-bottom: 6px; }
        .seal-title { font-size: 7px; font-weight: bold; color: #4a5568; text-transform: uppercase; }
        .footer { margin-top: 12px; text-align: center; font-size: 7px; color: #a0aec0; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
<div class="top-bar"></div>
<div class="page">
    <div class="header">
        <div class="h-left">
            @if(!empty($settings['company_logo']))
                <img src="{{ public_path('storage/' . $settings['company_logo']) }}" style="max-height:40px;max-width:150px;margin-bottom:6px;" alt="">
            @endif
            <div class="company">{{ $emisor['Nombre'] }}</div>
            <div>RFC: {{ $emisor['Rfc'] }} &middot; Régimen: {{ $emisor['RegimenFiscal'] }}</div>
            <div>Lugar de expedición: {{ $comprobante['LugarExpedicion'] }}</div>
        </div>
        <div class="h-right">
            <div class="badge">CFDI &middot; INGRESO</div><br>
            <div class="folio">{{ $comprobante['Serie'] }}{{ $comprobante['Folio'] }}</div>
            <div>Fecha: {{ str_replace('T', ' ', $comprobante['Fecha']) }}</div>
            <div class="uuid">Folio fiscal: {{ $uuid }}</div>
        </div>
    </div>

    <div class="section-title">Receptor</div>
    <div class="grid">
        <div class="col">
            <div class="kv"><b>{{ $receptor['Nombre'] }}</b></div>
            <div class="kv">RFC: {{ $receptor['Rfc'] }} &middot; C.P.: {{ $receptor['DomicilioFiscalReceptor'] }}</div>
        </div>
        <div class="col">
            <div class="kv">Uso CFDI: {{ $receptor['UsoCFDI'] }} &middot; Régimen: {{ $receptor['RegimenFiscalReceptor'] }}</div>
            <div class="kv">Forma de pago: {{ $comprobante['FormaPago'] }} &middot; Método: {{ $comprobante['MetodoPago'] }} &middot; Moneda: {{ $comprobante['Moneda'] }}</div>
        </div>
    </div>

    <div class="section-title">Conceptos</div>
    <table class="items">
        <thead>
            <tr>
                <th>Clave</th><th>Cant.</th><th>Unidad</th><th>Descripción</th>
                <th class="r">V. Unitario</th><th class="r">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($conceptos as $c)
            <tr>
                <td>{{ $c['ClaveProdServ'] }}</td>
                <td>{{ $c['Cantidad'] }}</td>
                <td>{{ $c['ClaveUnidad'] }}</td>
                <td>{{ $c['Descripcion'] }}</td>
                <td class="r">${{ number_format((float) $c['ValorUnitario'], 2) }}</td>
                <td class="r">${{ number_format((float) $c['Importe'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Subtotal</td><td class="value">${{ number_format((float) $comprobante['SubTotal'], 2) }}</td></tr>
        @if((float) ($comprobante['Descuento'] ?: 0) > 0)
        <tr><td class="label">Descuento</td><td class="value">-${{ number_format((float) $comprobante['Descuento'], 2) }}</td></tr>
        @endif
        <tr><td class="label">IVA trasladado</td><td class="value">${{ number_format((float) $comprobante['Total'] - (float) $comprobante['SubTotal'] + (float) ($comprobante['Descuento'] ?: 0), 2) }}</td></tr>
        <tr class="grand"><td class="label">Total</td><td class="value">${{ number_format((float) $comprobante['Total'], 2) }}</td></tr>
    </table>

    <div class="section-title">Timbre Fiscal Digital</div>
    <div class="stamps">
        <div class="qr-cell"><img src="{{ $qr }}" style="width:120px;height:120px;" alt="QR SAT"></div>
        <div class="seals-cell">
            <div class="kv" style="font-size:8px;">
                <b>UUID:</b> <span style="font-family:monospace;">{{ $uuid }}</span><br>
                <b>Fecha de timbrado:</b> {{ str_replace('T', ' ', $tfd['FechaTimbrado'] ?? '') }} &middot;
                <b>Cert. SAT:</b> {{ $tfd['NoCertificadoSAT'] ?? '' }} &middot;
                <b>Cert. emisor:</b> {{ $comprobante['NoCertificado'] }}
            </div>
            <div class="seal-title">Sello digital del CFDI</div>
            <div class="seal">{{ $comprobante['Sello'] }}</div>
            <div class="seal-title">Sello del SAT</div>
            <div class="seal">{{ $tfd['SelloSAT'] ?? '' }}</div>
        </div>
    </div>

    <div class="footer">
        Este documento es una representación impresa de un CFDI 4.0 &middot; Verifique en https://verificacfdi.facturaelectronica.sat.gob.mx
    </div>
</div>
</body>
</html>
