<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 24px; background: #fff; border: 1px solid #e5e7eb; }
        .footer { padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin-top: 16px; }
        .detail { background: #eff6ff; padding: 12px; border-radius: 6px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:20px;">Cotización {{ $quote->quote_number }}</h1>
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
    <div class="footer">
        <p>Este es un correo automático, por favor no responda a este mensaje.</p>
    </div>
</body>
</html>
