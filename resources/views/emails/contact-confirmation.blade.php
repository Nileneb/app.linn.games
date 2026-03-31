<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ihre Anfrage bei Linn Games</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333;">
    <h2>Vielen Dank für Ihre Anfrage, {{ $contact->name }}!</h2>

    <p>Wir haben Ihre Nachricht erhalten und werden uns so schnell wie möglich bei Ihnen melden.</p>

    <p><strong>Zusammenfassung Ihrer Anfrage:</strong></p>
    <ul>
        <li><strong>Projekttyp:</strong> {{ $contact->project_type }}</li>
        @if($contact->timeline)
        <li><strong>Zeitrahmen:</strong> {{ $contact->timeline }}</li>
        @endif
        <li><strong>Nachricht:</strong> {{ $contact->message }}</li>
    </ul>

    <p>Mit freundlichen Grüßen,<br>Ihr Linn Games Team</p>
</body>
</html>
