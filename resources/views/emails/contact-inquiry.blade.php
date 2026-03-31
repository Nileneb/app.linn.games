<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Kontaktanfrage</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333;">
    <h2>Neue Kontaktanfrage über linn.games</h2>

    <table style="border-collapse: collapse; width: 100%; max-width: 600px;">
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Name</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->name }}</td>
        </tr>
        @if($contact->company)
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Firma</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->company }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">E-Mail</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->email }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Projekttyp</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->project_type }}</td>
        </tr>
        @if($contact->timeline)
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Zeitrahmen</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->timeline }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Nachricht</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->message }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Eingegangen am</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $contact->created_at->format('d.m.Y H:i') }}</td>
        </tr>
    </table>
</body>
</html>
