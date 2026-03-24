<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
        .header { background: #d97706; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 24px; background: #fff; border: 1px solid #e5e7eb; }
        .footer { padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; }
        .btn { display: inline-block; background: #d97706; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin-top: 16px; }
        .detail { background: #fffbeb; padding: 12px; border-radius: 6px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:20px;">Recordatorio de Pago</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $invoice->client->legal_name }}</strong>,</p>
        <p>Le recordamos que tiene una factura pendiente de pago:</p>
        <div class="detail">
            <p><strong>Folio:</strong> {{ $invoice->folio() }}<br>
            <strong>Total:</strong> ${{ number_format($invoice->total, 2) }} MXN<br>
            <strong>Emitida:</strong> {{ $invoice->created_at->format('d/m/Y') }}</p>
        </div>
        @if($invoice->client->portal_token)
        <p>Puede realizar su pago fácilmente desde su portal:</p>
        <a href="{{ route('portal.checkout', [$invoice->client->portal_token, $invoice]) }}" class="btn">Pagar Ahora</a>
        @endif
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no responda a este mensaje.</p>
    </div>
</body>
</html>
