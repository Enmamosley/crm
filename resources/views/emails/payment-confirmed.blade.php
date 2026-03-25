<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 24px 12px; background: #f3f6fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: #0f172a; }
        .wrap { max-width: 620px; margin: 0 auto; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #047857, #10b981); color: #fff; padding: 22px 24px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; opacity: 0.92; font-size: 13px; }
        .content { padding: 22px 24px; line-height: 1.6; }
        .detail { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px; margin: 16px 0; }
        .btn { display: inline-block; background: #047857; color: #ffffff !important; text-decoration: none; padding: 11px 18px; border-radius: 8px; font-weight: 600; margin-top: 8px; }
        .foot { color: #64748b; font-size: 12px; text-align: center; padding: 14px 10px 0; }
    </style>
</head>
<body>
    <div class="wrap">
    <div class="card">
    <div class="header">
        <h1>Pago confirmado</h1>
        <p>Hemos recibido tu pago correctamente</p>
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
    </div>
    <div class="foot">
        Correo automático de CRM Mosley.
    </div>
    </div>
</body>
</html>
