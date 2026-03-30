<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title') - Linn Games</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
      :root {
        --bg-dark: #030014;
        --bg-darker: #010008;
        --accent: #00d4ff;
        --accent-2: #a855f7;
        --text-main: #e5e7eb;
        --text-muted: #9ca3af;
        --border-subtle: rgba(148, 163, 184, 0.18);
        --max-width: 900px;
      }
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body {
        font-family: "Inter", system-ui, sans-serif;
        background: var(--bg-dark);
        color: var(--text-main);
        line-height: 1.7;
        -webkit-font-smoothing: antialiased;
      }
      a { color: var(--accent); text-decoration: none; }
      a:hover { text-decoration: underline; }
      .legal-wrap {
        max-width: var(--max-width);
        margin: 0 auto;
        padding: 6rem 2rem 4rem;
      }
      .legal-nav {
        position: fixed; top: 0; left: 0; right: 0; z-index: 100;
        background: rgba(3, 0, 20, 0.9);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--border-subtle);
        padding: 0.85rem 2rem;
      }
      .legal-nav-inner {
        max-width: var(--max-width);
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
      }
      .legal-nav a.back {
        display: inline-flex; align-items: center; gap: 0.5rem;
        color: var(--text-muted); font-size: 0.9rem; font-weight: 500;
      }
      .legal-nav a.back:hover { color: #fff; text-decoration: none; }
      .legal-nav .brand {
        font-weight: 800; font-size: 1rem;
        background: linear-gradient(135deg, #fff, var(--accent), var(--accent-2));
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent;
      }
      h1 { font-size: 2rem; font-weight: 800; margin-bottom: 1.5rem; }
      h2 { font-size: 1.6rem; font-weight: 700; margin: 2.5rem 0 1rem; color: #fff; }
      h3 { font-size: 1.2rem; font-weight: 600; margin: 1.5rem 0 0.8rem; color: var(--accent); }
      h4 { font-size: 1rem; font-weight: 600; margin: 1rem 0 0.5rem; color: #fff; }
      p { margin-bottom: 1rem; color: var(--text-muted); }
      ul, ol { margin-left: 1.5rem; margin-bottom: 1rem; color: var(--text-muted); }
      li { margin-bottom: 0.5rem; }
      strong { color: var(--text-main); }
      .last-update { font-style: italic; margin-top: 2rem; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem; }
      footer {
        border-top: 1px solid var(--border-subtle);
        padding: 2rem;
        text-align: center;
        font-size: 0.85rem;
        color: var(--text-muted);
      }
      footer a { margin: 0 0.8rem; }
    </style>
  </head>
  <body>
    <nav class="legal-nav">
      <div class="legal-nav-inner">
        <a href="/" class="back">← Zurück</a>
        <span class="brand">Linn Games</span>
      </div>
    </nav>
    <div class="legal-wrap">
      @yield('content')
    </div>
    <footer>
      <div>
        © <span id="year"></span> Linn Games · Made with 💜
      </div>
      <div style="margin-top: 0.5rem;">
        <a href="/">Startseite</a>
        <a href="/dsgvo.html">Datenschutz</a>
        <a href="/AGB.html">AGB</a>
        <a href="/Impressum.html">Impressum</a>
      </div>
    </footer>
    <script>
      const y = document.getElementById("year");
      if (y) y.textContent = new Date().getFullYear();
    </script>
  </body>
</html>
