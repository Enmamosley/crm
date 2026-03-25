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
        .header { background: linear-gradient(135deg, #b45309, #f59e0b); color: #fff; padding: 22px 24px; }
        .logo-wrap { margin-bottom: 10px; }
        .logo { max-height: 36px; max-width: 180px; display: block; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; opacity: 0.92; font-size: 13px; }
        .content { padding: 22px 24px; line-height: 1.6; }
        .detail { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 14px; margin: 16px 0; }
        .btn { display: inline-block; background: #b45309; color: #ffffff !important; text-decoration: none; padding: 11px 18px; border-radius: 8px; font-weight: 600; margin-top: 8px; }
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
        <h1>Recordatorio de pago</h1>
        <p>{{ $companyName }}</p>
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
    </div>
    <div class="foot">
        Correo automatico de {{ $companyName }} - {{ $companyEmail }}.
    </div>
    </div>
</body>
</html>
