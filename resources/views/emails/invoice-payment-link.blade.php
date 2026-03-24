<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
        .header { background: #16a34a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 24px; background: #fff; border: 1px solid #e5e7eb; }
        .footer { padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; }
        .btn { display: inline-block; background: #16a34a; color: white; padding: 14px 28px; border-radius: 6px; text-decoration: none; margin-top: 16px; font-size: 16px; font-weight: bold; }
        .detail { background: #f0fdf4; padding: 16px; border-radius: 6px; margin: 16px 0; border-left: 4px solid #16a34a; }
        .amount { font-size: 28px; font-weight: bold; color: #16a34a; }
        .items-table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
        .items-table th { background: #f3f4f6; text-align: left; padding: 8px 12px; }
        .items-table td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; }
        .items-table .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:22px;">💳 Link de Pago</h1>
        <p style="margin:4px 0 0;opacity:.85;">Tu orden está lista para pagarse</p>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $invoice->client->legal_name }}</strong>,</p>
        <p>Te compartimos el link para realizar el pago de tu orden de servicio:</p>

        <div class="detail">
            <p style="margin:0 0 8px;">
                @if($invoice->quote)
                <strong>Cotización:</strong> {{ $invoice->quote->quote_number }}<br>
                @endif
                <strong>Fecha:</strong> {{ $invoice->created_at->format('d/m/Y') }}
            </p>
            <p class="amount">${{ number_format($invoice->total, 2) }} MXN</p>
        </div>

        @if($invoice->quote && $invoice->quote->items->count())
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Cant.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->quote->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <p style="text-align:center;margin-top:24px;">
            <a href="{{ $checkoutUrl }}" class="btn">Pagar ahora →</a>
        </p>

        <p style="font-size:13px;color:#6b7280;margin-top:24px;">
            Puedes pagar con tarjeta de crédito/débito, OXXO o transferencia SPEI.
            Si tienes alguna duda, contáctanos respondiendo este correo.
        </p>
    </div>
    <div class="footer">
        <p>Este es un correo automático generado por el sistema CRM.</p>
    </div>
</body>
</html>
