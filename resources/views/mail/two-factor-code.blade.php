<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código de verificação — {{ config('app.name') }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f4f4f5; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; padding: 0 16px; }
        .card { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
        .header { background: #6366f1; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; font-size: 22px; font-weight: 700; margin: 0; }
        .body { padding: 36px 40px; }
        .body p { color: #52525b; font-size: 15px; line-height: 1.6; margin: 0 0 20px; }
        .code-box { background: #f4f4f5; border-radius: 10px; text-align: center; padding: 20px; margin: 24px 0; }
        .code { font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #3f3f46; font-family: 'Courier New', monospace; }
        .note { font-size: 13px; color: #a1a1aa; margin: 0; }
        .footer { padding: 20px 40px; border-top: 1px solid #f0f0f0; text-align: center; }
        .footer p { font-size: 12px; color: #a1a1aa; margin: 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>
        <div class="body">
            <p>Olá,</p>
            <p>Você solicitou um código de verificação para acessar sua conta. Use o código abaixo para concluir o login:</p>

            <div class="code-box">
                <div class="code">{{ $code }}</div>
            </div>

            <p>Este código expira em <strong>10 minutos</strong>. Se você não solicitou este código, ignore este e-mail — sua conta continua segura.</p>

            <p class="note">Por segurança, nunca compartilhe este código com ninguém.</p>
        </div>
        <div class="footer">
            <p>Este e-mail foi enviado automaticamente por {{ config('app.name') }}. Não responda a este e-mail.</p>
        </div>
    </div>
</div>
</body>
</html>
