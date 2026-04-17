<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 24px; }
        .btn { display: inline-block; background: #1a1a1a; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin: 24px 0; }
        .note { font-size: 0.85em; color: #666; }
    </style>
</head>
<body>
    <h2>Hallo {{ $pending->name }},</h2>
    <p>bitte bestätige deine E-Mail-Adresse, um deine Registrierung bei <strong>app.linn.games</strong> abzuschließen.</p>
    <p>Nach der Bestätigung wird dein Konto auf die Warteliste gesetzt und manuell freigeschaltet.</p>

    <a href="{{ route('register.verify', $pending->token) }}" class="btn">
        E-Mail-Adresse bestätigen
    </a>

    <p class="note">
        Dieser Link ist 24 Stunden gültig. Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.
    </p>
    <p class="note">
        Direktlink: {{ route('register.verify', $pending->token) }}
    </p>
</body>
</html>
