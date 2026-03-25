@php
    $companyName = \App\Models\Setting::get('company_name', config('mail.from.name', 'CRM Mosley'));
    $companyLogo = \App\Models\Setting::get('company_logo', '');
    $companyEmail = config('mail.from.address', 'no-reply@mosley.digital');
    $logoUrl = $companyLogo ? asset('storage/' . $companyLogo) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 24px 12px; background: #f3f6fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: #0f172a; }
        .wrap { max-width: 620px; margin: 0 auto; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #15803d, #16a34a); color: #fff; padding: 22px 24px; }
        .logo-wrap { margin-bottom: 10px; }
        .logo { max-height: 36px; max-width: 180px; display: block; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; opacity: 0.92; font-size: 13px; }
        .content { padding: 22px 24px; line-height: 1.6; }
        .detail { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px; margin: 16px 0; }
        .amount { font-size: 30px; font-weight: 800; color: #166534; margin: 8px 0 0; }
        .btn { display: inline-block; background: #15803d; color: #ffffff !important; text-decoration: none; padding: 12px 22px; border-radius: 8px; font-weight: 700; }
        .items-table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 14px; }
        .items-table th { background: #f1f5f9; text-align: left; padding: 8px 10px; color: #334155; }
        .items-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .items-table .text-right { text-align: right; }
        .foot { color: #64748b; font-size: 12px; text-align: center; padding: 14px 10px 0; }
    </style>
</head>
<body>
    <div class="wrap">
    <div class="card">
    <div class="header">
        @if($logoUrl)
        <div class="logo-wrap"><img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="logo"></div>
        @endif
        <h1>Link de pago</h1>
        <p>{{ $companyName }}</p>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $invoice->client->legal_name }}</strong>,</p>
        <p>Te compartimos el link para realizar el pago de tu orden de servicio:</p>

        <div class="detail">
            <p style="margin:0 0 8px; font-size:14px;">
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
            Si tienes dudas, responde este correo o contáctanos por WhatsApp.
        </p>
    </div>
    </div>
    <div class="foot">
        Correo automatico de {{ $companyName }} - {{ $companyEmail }}.
    </div>
    </div>
</body>
</html>
