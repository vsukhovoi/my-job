<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Код верифікації</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; padding: 40px;">
    <h2 style="color: #2563eb;">Код верифікації для My Job</h2>

    <p>Ваш код верифікації:</p>

    <div style="font-size: 40px; font-weight: bold; letter-spacing: 6px; margin: 24px 0; color: #1e40af;">
        {{ $code }}
    </div>

    <p>Код дійсний <strong>{{ $expiresInMinutes }} хвилин</strong>.</p>

    <p style="color: #6b7280; font-size: 14px;">
        Якщо ви не запитували цей код, просто ігноруйте це повідомлення.
    </p>
</body>
</html>
