<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu enlace de acceso</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 40px 20px; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .header { background: #4f46e5; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
        .body { padding: 36px 40px; }
        .body p { color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 20px; }
        .btn { display: inline-block; background: #4f46e5; color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; }
        .hint { font-size: 13px; color: #9ca3af; margin-top: 28px; }
        .hint a { color: #6b7280; word-break: break-all; }
        .footer { background: #f9fafb; padding: 20px 40px; text-align: center; }
        .footer p { font-size: 12px; color: #9ca3af; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Enlace de acceso</h1>
        </div>
        <div class="body">
            <p>Hola,</p>
            <p>Haz clic en el botón de abajo para acceder. El enlace es válido durante <strong>15 minutos</strong> y solo puede usarse una vez.</p>
            <p style="text-align:center; margin: 32px 0;">
                <a href="{{ $magicUrl }}" class="btn">{{ $buttonText }}</a>
            </p>
            <p>Si no solicitaste este enlace, puedes ignorar este mensaje.</p>
            <p class="hint">
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                <a href="{{ $magicUrl }}">{{ $magicUrl }}</a>
            </p>
        </div>
        <div class="footer">
            <p>Este correo fue enviado automáticamente. Por favor no respondas.</p>
        </div>
    </div>
</body>
</html>
