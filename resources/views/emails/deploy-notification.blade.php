<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Deployment erfolgreich</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333;">
    <h2>Deployment erfolgreich 🚀</h2>

    <p>Ein neues Release von <strong>{{ config('app.name') }}</strong> wurde soeben deployed.</p>

    <ul>
        <li><strong>Zeitpunkt:</strong> {{ $deployedAt }}</li>
        <li><strong>URL:</strong> <a href="{{ $appUrl }}">{{ $appUrl }}</a></li>
        <li><strong>Umgebung:</strong> {{ config('app.env') }}</li>
    </ul>

    <p>Diese E-Mail bestätigt, dass der Mailversand korrekt funktioniert.</p>

    <p>— {{ config('app.name') }} Bot</p>
</body>
</html>
