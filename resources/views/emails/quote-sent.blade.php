<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 24px 12px; background: #f3f6fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: #0f172a; }
        .wrap { max-width: 620px; margin: 0 auto; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; padding: 22px 24px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; opacity: 0.92; font-size: 13px; }
        .content { padding: 22px 24px; line-height: 1.6; }
        .detail { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px; margin: 16px 0; }
        .btn { display: inline-block; background: #1d4ed8; color: #ffffff !important; text-decoration: none; padding: 11px 18px; border-radius: 8px; font-weight: 600; margin-top: 8px; }
        .foot { color: #64748b; font-size: 12px; text-align: center; padding: 14px 10px 0; }
    </style>
</head>
<body>
    <div class="wrap">
    <div class="card">
    <div class="header">
        <h1>Cotización {{ $quote->quote_number }}</h1>
        <p>Tu propuesta está lista</p>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $quote->lead->name }}</strong>,</p>
        <p>Le enviamos la cotización solicitada:</p>
        <div class="detail">
            <p><strong>Cotización:</strong> {{ $quote->quote_number }}<br>
            <strong>Total:</strong> ${{ number_format($quote->total, 2) }} MXN<br>
            <strong>Válida hasta:</strong> {{ $quote->valid_until?->format('d/m/Y') }}</p>
        </div>
        <p>Puede ver el detalle completo y aceptar o rechazar la cotización desde el siguiente enlace:</p>
        <a href="{{ $portalUrl }}" class="btn">Ver Cotización</a>
    </div>
    </div>
    <div class="foot">
        Correo automático de CRM Mosley.
    </div>
    </div>
</body>
</html>
