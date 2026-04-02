<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" type="image/png" href="/favicon-96x96.png?v=20260402" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260402" />
<link rel="shortcut icon" href="/favicon.ico?v=20260402" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260402" />
<meta name="apple-mobile-web-app-title" content="linn.games" />
<link rel="manifest" href="/site.webmanifest?v=20260402" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

{{-- Dark-mode init: read localStorage BEFORE Vite/Alpine loads to prevent flash --}}
<script>
    (function () {
        var appearance = localStorage.getItem('appearance');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (appearance === 'dark' || (appearance !== 'light' && prefersDark)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
