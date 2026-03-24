<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
        .header { background: #059669; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 24px; background: #fff; border: 1px solid #e5e7eb; }
        .footer { padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; }
        .btn { display: inline-block; background: #059669; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin-top: 16px; }
        .detail { background: #f0fdf4; padding: 12px; border-radius: 6px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:20px;">Pago Confirmado ✓</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $payment->invoice->client->legal_name }}</strong>,</p>
        <p>Hemos recibido su pago correctamente:</p>
        <div class="detail">
            <p><strong>Factura:</strong> {{ $payment->invoice->folio() }}<br>
            <strong>Monto:</strong> ${{ number_format($payment->amount, 2) }} {{ $payment->currency }}<br>
            <strong>Método:</strong> {{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'N/A')) }}<br>
            <strong>Fecha:</strong> {{ $payment->paid_at?->format('d/m/Y H:i') }}</p>
        </div>
        @if($payment->invoice->client->portal_token)
        <a href="{{ $payment->invoice->client->portalUrl() }}" class="btn">Ver en Portal</a>
        @endif
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no responda a este mensaje.</p>
    </div>
</body>
</html>
