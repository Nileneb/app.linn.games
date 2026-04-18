<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Linn.Games · Pitch Deck</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="/favicon-96x96.png?v=20260402" sizes="96x96">
<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260402">
<link rel="shortcut icon" href="/favicon.ico?v=20260402">
<link rel="stylesheet" href="{{ asset('pitch/assets/deck.css') }}?v=3">
<script src="{{ asset('pitch/assets/i18n.js') }}?v=2"></script>
</head>
<body>

<!-- Logo reusable ------------------------------------------------------- -->
<template id="logo-mark-tpl">
  <svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <!-- connector lines -->
    <line x1="9" y1="9" x2="9" y2="31" opacity="0.28"/>
    <line x1="9" y1="31" x2="31" y2="31" opacity="0.28"/>
    <line x1="9" y1="9" x2="20" y2="20" opacity="0.14"/>
    <line x1="20" y1="20" x2="31" y2="31" opacity="0.14"/>
    <!-- secondary nodes -->
    <circle cx="20" cy="20" r="2" fill="currentColor" opacity="0.5"/>
    <circle cx="9" cy="20" r="1.6" fill="currentColor" opacity="0.5"/>
    <!-- primary nodes -->
    <circle cx="9" cy="9" r="3.2" fill="currentColor"/>
    <circle cx="9" cy="31" r="3.2" fill="currentColor"/>
    <circle cx="31" cy="31" r="3.2" fill="currentColor"/>
  </svg>
</template>

<!-- Lang toggle -->
<div class="lang-toggle" role="tablist" aria-label="Language">
  <button data-lang="de" class="active" type="button">DE</button>
  <button data-lang="en" type="button">EN</button>
</div>

<deck-stage width="1920" height="1080">

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 1 · COVER (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Cover">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="chrome.location">LAMPERTHEIM · DE</span>
      <span>·</span>
      <span data-i18n="chrome.event">IMPACT SPRINT LAB · BERLIN</span>
      <span>·</span>
      <span class="page-num">01 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding-top: 220px;">
    <div style="display: grid; grid-template-columns: 1.35fr 1fr; gap: 88px; align-items: center; flex: 1;">
      <div>
        <div class="badge" style="margin-bottom: 48px;">
          <span class="dot"></span>
          <span data-i18n="s1.badge">Geschlossene Beta · Laufende Systeme</span>
        </div>
        <h1 class="hero-title" style="font-size: 132px;">
          <span data-i18n="s1.title_1">KI-gestützte</span><br>
          <span class="grad" data-i18n="s1.title_2">Forschungs-</span><br>
          <span data-i18n="s1.title_3">infrastruktur.</span>
        </h1>
        <p class="lead" style="margin-top: 40px; font-size: 26px;" data-i18n="s1.sub">
          Keine PowerPoint-Konzepte. Laufende Systeme. Zwei Produkte, eine Codebasis, eine Vision — qualitative Forschung radikal zugänglich machen.
        </p>
      </div>

      <div class="code-card" style="align-self: start; margin-top: 0;">
        <div class="code-header">
          <span class="code-dot r"></span>
          <span class="code-dot y"></span>
          <span class="code-dot g"></span>
          <span class="code-title" data-i18n="s1.code_title">linn-games.config.yaml</span>
        </div>
        <pre class="code-body" style="margin:0;"><span class="cl-c"># identity</span>
<span class="cl-k">studio</span>:     <span class="cl-s">"Linn.Games"</span>
<span class="cl-k">location</span>:   <span class="cl-s">"Lampertheim, DE"</span>
<span class="cl-k">focus</span>:      [<span class="cl-s">interactive</span>, <span class="cl-s">ai</span>, <span class="cl-s">web</span>]

<span class="cl-c"># products</span>
<span class="cl-k">cloud</span>:
  <span class="cl-k">name</span>:     <span class="cl-s">"app.linn.games"</span>
  <span class="cl-k">domain</span>:   <span class="cl-s">systematic_review</span>
  <span class="cl-k">status</span>:   <span class="cl-a">LIVE</span>
<span class="cl-k">local</span>:
  <span class="cl-k">name</span>:     <span class="cl-s">"MayringCoder"</span>
  <span class="cl-k">domain</span>:   <span class="cl-s">content_analysis</span>
  <span class="cl-k">runtime</span>:  <span class="cl-s">offline_first</span>

<span class="cl-c"># model</span>
<span class="cl-k">funding</span>:    <span class="cl-a">non_dilutive</span>
<span class="cl-k">revenue</span>:    [<span class="cl-s">credits</span>, <span class="cl-s">saas</span>, <span class="cl-s">license</span>]</pre>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="s1.meta_1">PITCH · Impact Sprint Lab</span>
    <span data-i18n="s1.meta_2">Valuedfriends Workspaces · Berlin</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 2 · PROBLEM STARTER (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Problem">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s2.kicker">01 — Das Problem</span>
      <span>·</span>
      <span class="page-num">02 / 15</span>
    </div>
  </div>

  <div class="section-numeral">01</div>

  <div class="slide-pad" style="justify-content: center;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s2.kicker">01 — Das Problem</span>
    </div>
    <h2 class="hero-title">
      <span data-i18n="s2.title_1">Forschungszugang</span><br>
      <span class="grad" data-i18n="s2.title_2">ist blockiert.</span>
    </h2>
    <p class="lead" style="margin-top: 56px;" data-i18n="s2.sub">
      Qualitative Forschung — Literaturreviews, Inhaltsanalysen — ist der Goldstandard für evidenzbasierte Entscheidungen. Und sie bleibt für die meisten unerreichbar.
    </p>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 3 · PROBLEM DETAIL (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="Three barriers">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s3.kicker">Drei Barrieren</span>
      <span>·</span>
      <span class="page-num">03 / 15</span>
    </div>
  </div>

  <div class="slide-pad">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 88px; margin-top: 40px;">
      <div>
        <div class="eyebrow-line">
          <span class="bar"></span>
          <span data-i18n="s3.kicker">Drei Barrieren</span>
        </div>
        <h2 class="sub-title" data-i18n="s3.title">Zeit. Kosten. Zugang.</h2>
      </div>
      <p class="pull-quote" style="font-size: 34px; max-width: 560px; text-align: right; font-weight: 500;">
        <span data-i18n="s3.pull">„Das Potenzial ist da. Der Zugang fehlt."</span>
      </p>
    </div>

    <div class="col-3" style="gap: 40px; margin-top: auto; margin-bottom: auto;">
      <div class="app-card" style="padding: 44px 40px;">
        <div style="display: flex; align-items: baseline; gap: 16px; margin-bottom: 24px;">
          <span style="font-family: var(--font-mono); font-size: 20px; color: var(--zinc-400); font-weight: 500;" data-i18n="s3.b1_num">01</span>
          <span class="badge-light" data-i18n="s3.b1_label">Zeit</span>
        </div>
        <h3 style="font-size: 42px; font-weight: 800; letter-spacing: -0.03em; line-height: 1.1; color: var(--zinc-950); margin-bottom: 20px;" data-i18n="s3.b1_big">Wochen bis Monate</h3>
        <p style="font-size: 19px; line-height: 1.55; color: var(--zinc-600);" data-i18n="s3.b1_text">Ein systematischer Review ist ein Vollzeit-Projekt über Wochen. Für kleine Teams schlicht nicht leistbar.</p>
      </div>

      <div class="app-card" style="padding: 44px 40px;">
        <div style="display: flex; align-items: baseline; gap: 16px; margin-bottom: 24px;">
          <span style="font-family: var(--font-mono); font-size: 20px; color: var(--zinc-400); font-weight: 500;" data-i18n="s3.b2_num">02</span>
          <span class="badge-light" data-i18n="s3.b2_label">Kosten</span>
        </div>
        <h3 style="font-size: 42px; font-weight: 800; letter-spacing: -0.03em; line-height: 1.1; color: var(--zinc-950); margin-bottom: 20px;" data-i18n="s3.b2_big">Gute KI ist teuer</h3>
        <p style="font-size: 19px; line-height: 1.55; color: var(--zinc-600);" data-i18n="s3.b2_text">Günstige Modelle liefern bei wissenschaftlicher Analyse unbrauchbare Ergebnisse. Qualität hat einen Preis.</p>
      </div>

      <div class="app-card" style="padding: 44px 40px;">
        <div style="display: flex; align-items: baseline; gap: 16px; margin-bottom: 24px;">
          <span style="font-family: var(--font-mono); font-size: 20px; color: var(--zinc-400); font-weight: 500;" data-i18n="s3.b3_num">03</span>
          <span class="badge-light" data-i18n="s3.b3_label">Zugang</span>
        </div>
        <h3 style="font-size: 42px; font-weight: 800; letter-spacing: -0.03em; line-height: 1.1; color: var(--zinc-950); margin-bottom: 20px;" data-i18n="s3.b3_big">Methoden bleiben akademisch</h3>
        <p style="font-size: 19px; line-height: 1.55; color: var(--zinc-600);" data-i18n="s3.b3_text">Gründer, NGOs, Praktiker ohne akademischen Background haben keinen Weg rein. Das Potenzial ist da. Der Zugang fehlt.</p>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 4 · SOLUTION STARTER (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Solution">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s4.kicker">02 — Die Lösung</span>
      <span>·</span>
      <span class="page-num">04 / 15</span>
    </div>
  </div>

  <div class="section-numeral">02</div>

  <div class="slide-pad" style="justify-content: center;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s4.kicker">02 — Die Lösung</span>
    </div>
    <h2 class="hero-title">
      <span data-i18n="s4.title_1">Zwei Produkte.</span><br>
      <span class="grad" data-i18n="s4.title_2">Eine Pipeline.</span>
    </h2>
    <p class="lead" style="margin-top: 56px;" data-i18n="s4.sub">
      Ein cloud-basiertes Review-System für Tiefe und Methode. Ein lokales Inhaltsanalyse-Tool für Datenschutz und Querfinanzierung. Eine gemeinsame Code-DNA.
    </p>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 80px; max-width: 1100px;">
      <div style="padding: 28px 32px; border: 1px solid var(--border-subtle); border-radius: 18px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px); position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--gradient-brand); opacity: 0.8; border-radius: 18px 18px 0 0;"></div>
        <div style="font-family: var(--font-mono); font-size: 14px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px;">CLOUD · LIVE</div>
        <div style="font-family: var(--font-mono); font-size: 26px; color: #fff; font-weight: 500;">app.linn.games</div>
        <div style="color: var(--text-muted); font-size: 18px; margin-top: 8px;">Systematic Review · 8 Phasen</div>
      </div>
      <div style="padding: 28px 32px; border: 1px solid var(--border-subtle); border-radius: 18px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px); position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--gradient-brand); opacity: 0.8; border-radius: 18px 18px 0 0;"></div>
        <div style="font-family: var(--font-mono); font-size: 14px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent-2); margin-bottom: 12px;">LOCAL · OFFLINE-FIRST</div>
        <div style="font-family: var(--font-mono); font-size: 26px; color: #fff; font-weight: 500;">MayringCoder</div>
        <div style="color: var(--text-muted); font-size: 18px; margin-top: 8px;">Content Analysis · Mayring</div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 5 · PRODUCT A · app.linn.games (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="app.linn.games">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s5.kicker">Produkt A · Cloud</span>
      <span>·</span>
      <span class="page-num">05 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 112px;">
    <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 80px; flex: 1;">
      <div style="display: flex; flex-direction: column;">
        <div class="eyebrow-line">
          <span class="bar"></span>
          <span data-i18n="s5.kicker">Produkt A · Cloud</span>
        </div>
        <h2 style="font-family: var(--font-mono); font-size: 44px; font-weight: 500; color: var(--zinc-950); letter-spacing: -0.02em; margin-bottom: 16px;" data-i18n="s5.title_1">app.linn.games</h2>
        <h3 class="sub-title" style="font-size: 54px; margin-bottom: 28px;" data-i18n="s5.title_2">KI-Literaturreview in 8 Phasen</h3>
        <p class="lead" style="font-size: 22px; color: var(--zinc-600); margin-bottom: 40px;" data-i18n="s5.sub">
          Von der Forschungsfrage bis zur Synthese — durchgechaint, agentisch, kreditbasiert. Live im Einsatz.
        </p>

        <div style="margin-top: auto;">
          <div style="display: grid; grid-template-columns: auto 1fr; gap: 20px 28px; align-items: baseline; font-size: 16px;">
            <span style="font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--zinc-500);" data-i18n="s5.stack_label">Stack</span>
            <div>
              <span class="tech-chip">Laravel 12</span>
              <span class="tech-chip">PHP 8.4</span>
              <span class="tech-chip">Livewire/Volt</span>
              <span class="tech-chip">Filament</span>
              <span class="tech-chip">PostgreSQL 16 + pgvector</span>
              <span class="tech-chip">Redis</span>
              <span class="tech-chip">Claude API</span>
              <span class="tech-chip">Docker</span>
            </div>
            <span style="font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--zinc-500);" data-i18n="s5.status_label">Status</span>
            <div style="display: flex; align-items: center; gap: 12px;">
              <span style="width: 10px; height: 10px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,0.5);"></span>
              <span style="font-family: var(--font-mono); font-size: 16px; color: var(--zinc-900); font-weight: 500;" data-i18n="s5.status_val">LIVE · app.linn.games</span>
            </div>
          </div>
        </div>
      </div>

      <div style="background: #fff; border: 1px solid var(--zinc-200); border-radius: 18px; padding: 40px; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 32px;">
          <h4 style="font-family: var(--font-display); font-size: 24px; font-weight: 700; letter-spacing: -0.02em; color: var(--zinc-950);" data-i18n="s5.phase_title">Die 8 Phasen</h4>
          <span style="font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.15em; text-transform: uppercase; color: var(--zinc-400);" data-i18n="s5.phase_sub">P1 → P8 automatisch verkettet</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; flex: 1;">
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; background: linear-gradient(135deg, rgba(0,212,255,0.04) 0%, transparent 100%);">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Scope</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p1">P1 · Forschungsfrage</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Scope</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p2">P2 · PICO/SPIDER/PEO</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Scope</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p3">P3 · Kriterien</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; background: linear-gradient(135deg, rgba(168,85,247,0.04) 0%, transparent 100%);">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Search</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p4">P4 · Suchstrings</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; background: linear-gradient(135deg, rgba(168,85,247,0.04) 0%, transparent 100%);">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Screen</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p5">P5 · KI-Screening</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px;">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Screen</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p6">P6 · Qualität</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; background: linear-gradient(135deg, rgba(255,107,107,0.04) 0%, transparent 100%);">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Extract</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p7">P7 · Extraktion</span>
          </div>
          <div class="phase-tile" style="border: 1px solid var(--zinc-200); border-radius: 12px; padding: 20px 22px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; background: linear-gradient(135deg, rgba(255,107,107,0.04) 0%, transparent 100%);">
            <span style="font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.2em; color: var(--zinc-400); text-transform: uppercase;">Synth</span>
            <span style="font-family: var(--font-display); font-size: 18px; font-weight: 600; color: var(--zinc-950); letter-spacing: -0.01em;" data-i18n="s5.p8">P8 · Synthese</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 6 · PRODUCT B · MayringCoder (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="MayringCoder">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s6.kicker">Produkt B · Local / SaaS</span>
      <span>·</span>
      <span class="page-num">06 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 112px;">
    <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 80px; flex: 1;">
      <div style="display: flex; flex-direction: column;">
        <div class="eyebrow-line">
          <span class="bar"></span>
          <span data-i18n="s6.kicker">Produkt B · Local / SaaS</span>
        </div>
        <h2 style="font-family: var(--font-mono); font-size: 44px; font-weight: 500; color: var(--zinc-950); letter-spacing: -0.02em; margin-bottom: 16px;" data-i18n="s6.title_1">MayringCoder</h2>
        <h3 class="sub-title" style="font-size: 54px; margin-bottom: 28px;" data-i18n="s6.title_2">KI-Inhaltsanalyse, offline-first</h3>
        <p class="lead" style="font-size: 22px; color: var(--zinc-600); margin-bottom: 40px;" data-i18n="s6.sub">
          Qualitative Inhaltsanalyse nach Mayring — vollständig lokal. Kein Cloud-API-Key. DSGVO-konform ohne Aufwand.
        </p>

        <div style="margin-top: auto;">
          <div style="display: grid; grid-template-columns: auto 1fr; gap: 20px 28px; align-items: baseline; font-size: 16px;">
            <span style="font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--zinc-500);" data-i18n="s5.stack_label">Stack</span>
            <div>
              <span class="tech-chip">Python 3.11+</span>
              <span class="tech-chip">Ollama</span>
              <span class="tech-chip">ChromaDB</span>
              <span class="tech-chip">SQLite</span>
              <span class="tech-chip">FastMCP stdio</span>
              <span class="tech-chip">QLoRA/Unsloth</span>
              <span class="tech-chip">Docker</span>
            </div>
            <span style="font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--zinc-500);" data-i18n="s6.license_label">Lizenz</span>
            <div style="font-family: var(--font-mono); font-size: 16px; color: var(--zinc-900); font-weight: 500;" data-i18n="s6.license_val">AGPL-3.0 + kommerzielle Lizenz</div>
          </div>
        </div>
      </div>

      <div style="background: #fff; border: 1px solid var(--zinc-200); border-radius: 18px; padding: 44px 44px 40px; display: flex; flex-direction: column;">
        <h4 style="font-family: var(--font-display); font-size: 24px; font-weight: 700; letter-spacing: -0.02em; color: var(--zinc-950); margin-bottom: 28px;" data-i18n="s6.cap_title">Was es kann</h4>

        <ul style="list-style: none; padding: 0; margin: 0; flex: 1; display: flex; flex-direction: column; justify-content: space-around;">
          <li style="display: flex; gap: 20px; padding: 16px 0; border-top: 1px solid var(--zinc-100); align-items: baseline;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0; width: 24px;">01</span>
            <span style="font-size: 20px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s6.cap1">Deduktive & induktive Mayring-Analyse</span>
          </li>
          <li style="display: flex; gap: 20px; padding: 16px 0; border-top: 1px solid var(--zinc-100); align-items: baseline;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0; width: 24px;">02</span>
            <span style="font-size: 20px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s6.cap2">YAML-Codebook-System</span>
          </li>
          <li style="display: flex; gap: 20px; padding: 16px 0; border-top: 1px solid var(--zinc-100); align-items: baseline;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0; width: 24px;">03</span>
            <span style="font-size: 20px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s6.cap3">3-stufige Extraktions-Pipeline</span>
          </li>
          <li style="display: flex; gap: 20px; padding: 16px 0; border-top: 1px solid var(--zinc-100); align-items: baseline;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0; width: 24px;">04</span>
            <span style="font-size: 20px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s6.cap4">Adversarial Validation (Advocatus Diaboli)</span>
          </li>
          <li style="display: flex; gap: 20px; padding: 16px 0; border-top: 1px solid var(--zinc-100); align-items: baseline;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0; width: 24px;">05</span>
            <span style="font-size: 20px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s6.cap5">MCP Memory Layer (SQLite + ChromaDB)</span>
          </li>
          <li style="display: flex; gap: 20px; padding: 16px 0; border-top: 1px solid var(--zinc-100); align-items: baseline;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0; width: 24px;">06</span>
            <span style="font-size: 20px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s6.cap6">QLoRA → GGUF → Ollama Fine-tuning</span>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 7 · BUSINESS STARTER (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Business">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s7.kicker">03 — Geschäftsmodell</span>
      <span>·</span>
      <span class="page-num">07 / 15</span>
    </div>
  </div>

  <div class="section-numeral">03</div>

  <div class="slide-pad" style="justify-content: center;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s7.kicker">03 — Geschäftsmodell</span>
    </div>
    <h2 class="hero-title">
      <span data-i18n="s7.title_1">Querfinanzierung</span><br>
      <span class="grad" data-i18n="s7.title_2">statt Investor-Druck.</span>
    </h2>
    <p class="lead" style="margin-top: 56px;" data-i18n="s7.sub">
      Claude ist teuer — aber das einzige Modell, das wissenschaftliche Analyse zuverlässig liefert. MayringCoder SaaS finanziert die API-Kosten von app.linn.games.
    </p>

    <div style="display: flex; align-items: center; gap: 32px; margin-top: 80px; font-family: var(--font-mono); font-size: 16px;">
      <div style="padding: 20px 28px; border: 1px solid rgba(168,85,247,0.4); border-radius: 14px; background: rgba(168,85,247,0.08); color: #fff;">
        <div style="color: var(--accent-2); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; margin-bottom: 6px;">Revenue</div>
        <div>MayringCoder SaaS</div>
      </div>
      <div style="color: var(--accent); font-size: 24px;">→</div>
      <div style="padding: 20px 28px; border: 1px solid var(--border-subtle); border-radius: 14px; background: rgba(10,10,30,0.6); color: var(--text-muted);">
        <div style="font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; margin-bottom: 6px;">Covers</div>
        <div>Claude API · app.linn.games</div>
      </div>
      <div style="color: var(--accent); font-size: 24px;">→</div>
      <div style="padding: 20px 28px; border: 1px solid rgba(0,212,255,0.4); border-radius: 14px; background: rgba(0,212,255,0.08); color: #fff;">
        <div style="color: var(--accent); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; margin-bottom: 6px;">Impact</div>
        <div>Freier Forschungszugang</div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 8 · REVENUE TABLE (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="Revenue streams">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s8.kicker">Vier Revenue Streams</span>
      <span>·</span>
      <span class="page-num">08 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 160px 140px;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s8.kicker">Vier Revenue Streams</span>
    </div>
    <h2 class="sub-title" style="margin-bottom: 72px;" data-i18n="s8.title">Ein Produkt finanziert das andere.</h2>

    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 24%;" data-i18n="s8.th_stream">Stream</th>
          <th style="width: 30%;" data-i18n="s8.th_product">Produkt</th>
          <th data-i18n="s8.th_model">Modell</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong data-i18n="s8.r1_stream">Credits</strong></td>
          <td class="mono" data-i18n="s8.r1_product">app.linn.games</td>
          <td data-i18n="s8.r1_model">Token → Cent, nutzungsbasiert</td>
        </tr>
        <tr>
          <td><strong data-i18n="s8.r2_stream">Hosted SaaS</strong></td>
          <td class="mono" data-i18n="s8.r2_product">MayringCoder</td>
          <td data-i18n="s8.r2_model">Subscription (kein lokales Ollama nötig)</td>
        </tr>
        <tr>
          <td><strong data-i18n="s8.r3_stream">Kommerzielle Lizenz</strong></td>
          <td class="mono" data-i18n="s8.r3_product">MayringCoder</td>
          <td data-i18n="s8.r3_model">Einmalig / Jahresvertrag</td>
        </tr>
        <tr>
          <td><strong data-i18n="s8.r4_stream">Campuslizenz</strong></td>
          <td class="mono" data-i18n="s8.r4_product">MayringCoder</td>
          <td data-i18n="s8.r4_model">Universitäten · auf Anfrage</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 9 · PRICING (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="Pricing">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s9.kicker">Pricing · MayringCoder SaaS</span>
      <span>·</span>
      <span class="page-num">09 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 140px;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s9.kicker">Pricing · MayringCoder SaaS</span>
    </div>
    <h2 class="sub-title" style="margin-bottom: 80px;" data-i18n="s9.title">Vom Self-Hosted bis zum Campus.</h2>

    <div class="col-4" style="gap: 28px;">
      <div class="app-card" style="padding: 40px 36px;">
        <div class="card-kicker" data-i18n="s9.t1_tag">Self-Hosted</div>
        <h3 style="font-size: 30px; margin-bottom: 8px;" data-i18n="s9.t1_name">Free</h3>
        <div style="font-family: var(--font-display); font-size: 56px; font-weight: 800; letter-spacing: -0.04em; color: var(--zinc-950); line-height: 1; margin: 24px 0 16px;" data-i18n="s9.t1_price">0 €</div>
        <p style="font-size: 17px; color: var(--zinc-500); line-height: 1.5;" data-i18n="s9.t1_who">Tech-affine Researcher</p>
      </div>

      <div class="app-card" style="padding: 40px 36px;">
        <div class="card-kicker" data-i18n="s9.t2_tag">pro Monat</div>
        <h3 style="font-size: 30px; margin-bottom: 8px;" data-i18n="s9.t2_name">Starter</h3>
        <div style="font-family: var(--font-display); font-size: 56px; font-weight: 800; letter-spacing: -0.04em; color: var(--zinc-950); line-height: 1; margin: 24px 0 16px;" data-i18n="s9.t2_price">~19 €</div>
        <p style="font-size: 17px; color: var(--zinc-500); line-height: 1.5;" data-i18n="s9.t2_who">Einzelforscher, Studierende</p>
      </div>

      <div class="app-card" style="padding: 40px 36px; background: var(--zinc-950); color: #fff; border-color: var(--zinc-950); position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gradient-brand); border-radius: 16px 16px 0 0;"></div>
        <div style="font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-bottom: 16px;" data-i18n="s9.t3_tag">pro Monat</div>
        <h3 style="font-size: 30px; margin-bottom: 8px; color: #fff;" data-i18n="s9.t3_name">Pro</h3>
        <div style="font-family: var(--font-display); font-size: 56px; font-weight: 800; letter-spacing: -0.04em; color: #fff; line-height: 1; margin: 24px 0 16px;" data-i18n="s9.t3_price">~79 €</div>
        <p style="font-size: 17px; color: rgba(255,255,255,0.6); line-height: 1.5;" data-i18n="s9.t3_who">Forschungsteams, NGOs</p>
      </div>

      <div class="app-card" style="padding: 40px 36px;">
        <div class="card-kicker" data-i18n="s9.t4_tag">auf Anfrage</div>
        <h3 style="font-size: 30px; margin-bottom: 8px;" data-i18n="s9.t4_name">Campus</h3>
        <div style="font-family: var(--font-display); font-size: 56px; font-weight: 800; letter-spacing: -0.04em; color: var(--zinc-950); line-height: 1; margin: 24px 0 16px;" data-i18n="s9.t4_price">∞</div>
        <p style="font-size: 17px; color: var(--zinc-500); line-height: 1.5;" data-i18n="s9.t4_who">Universitäten, Forschungseinrichtungen</p>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 10 · WHY NO VC / FUNDING (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="No VC">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s10.kicker">Kein VC · Non-Dilutive</span>
      <span>·</span>
      <span class="page-num">10 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 120px;">
    <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 80px; flex: 1; align-items: stretch;">
      <div style="display: flex; flex-direction: column; justify-content: center;">
        <div class="eyebrow-line">
          <span class="bar"></span>
          <span data-i18n="s10.kicker">Kein VC · Non-Dilutive</span>
        </div>
        <div class="quote-mark">"</div>
        <p class="pull-quote" style="font-size: 48px;" data-i18n="s10.quote">
          „Investor-Kapital bedeutet Rendite-Erwartung. Das ist inkompatibel mit dem Ziel, Forschungszugang zu demokratisieren."
        </p>
      </div>

      <div style="display: flex; flex-direction: column;">
        <h3 style="font-family: var(--font-mono); font-size: 14px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 32px; font-weight: 600;" data-i18n="s10.funding_title">Stattdessen · Non-Dilutive Funding</h3>

        <div style="display: flex; flex-direction: column; gap: 14px;">
          <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; padding: 20px 24px; border: 1px solid var(--border-subtle); border-radius: 12px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px);">
            <div>
              <div style="font-family: var(--font-display); font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 4px;" data-i18n="s10.f1_name">Prototype Fund</div>
              <div style="font-size: 14px; color: var(--text-muted); line-height: 1.4;" data-i18n="s10.f1_for">Perfekt für MayringCoder (Open Source)</div>
            </div>
            <div style="font-family: var(--font-mono); font-size: 18px; color: var(--accent); text-align: right; align-self: center;" data-i18n="s10.f1_amount">bis 47.500 €</div>
          </div>

          <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; padding: 20px 24px; border: 1px solid var(--border-subtle); border-radius: 12px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px);">
            <div>
              <div style="font-family: var(--font-display); font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 4px;" data-i18n="s10.f2_name">EXIST-Gründerstipendium</div>
              <div style="font-size: 14px; color: var(--text-muted); line-height: 1.4;" data-i18n="s10.f2_for">Gründungsphase + Sachkosten</div>
            </div>
            <div style="font-family: var(--font-mono); font-size: 18px; color: var(--accent); text-align: right; align-self: center;" data-i18n="s10.f2_amount">3.000 €/Monat · 12 Monate</div>
          </div>

          <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; padding: 20px 24px; border: 1px solid var(--border-subtle); border-radius: 12px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px);">
            <div>
              <div style="font-family: var(--font-display); font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 4px;" data-i18n="s10.f3_name">BMBF / BMWK</div>
              <div style="font-size: 14px; color: var(--text-muted); line-height: 1.4;" data-i18n="s10.f3_for">KI + Forschungsinfrastruktur</div>
            </div>
            <div style="font-family: var(--font-mono); font-size: 18px; color: var(--accent); text-align: right; align-self: center;" data-i18n="s10.f3_amount">Grants</div>
          </div>

          <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; padding: 20px 24px; border: 1px solid var(--border-subtle); border-radius: 12px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px);">
            <div>
              <div style="font-family: var(--font-display); font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 4px;" data-i18n="s10.f4_name">Anthropic for Startups</div>
              <div style="font-size: 14px; color: var(--text-muted); line-height: 1.4;" data-i18n="s10.f4_for">Direkte Kostenreduktion</div>
            </div>
            <div style="font-family: var(--font-mono); font-size: 18px; color: var(--accent); text-align: right; align-self: center;" data-i18n="s10.f4_amount">API-Credits</div>
          </div>

          <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; padding: 20px 24px; border: 1px solid rgba(0,212,255,0.3); border-radius: 12px; background: rgba(0,212,255,0.08); backdrop-filter: blur(20px);">
            <div>
              <div style="font-family: var(--font-display); font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 4px;" data-i18n="s10.f5_name">Revenue-first</div>
              <div style="font-size: 14px; color: var(--text-muted); line-height: 1.4;" data-i18n="s10.f5_for">MayringCoder SaaS früh monetarisieren</div>
            </div>
            <div style="font-family: var(--font-mono); font-size: 18px; color: #fff; text-align: right; align-self: center;" data-i18n="s10.f5_amount">Sofort</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 11 · IMPACT FIT (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Impact fit">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s11.kicker">04 — Impact Fit</span>
      <span>·</span>
      <span class="page-num">11 / 15</span>
    </div>
  </div>

  <div class="section-numeral">04</div>

  <div class="slide-pad" style="padding: 180px 128px 140px;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s11.kicker">04 — Impact Fit</span>
    </div>
    <h2 class="sub-title" style="margin-bottom: 80px;">
      <span data-i18n="s11.title_1">Warum das ins Impact</span><br>
      <span class="grad" data-i18n="s11.title_2">Sprint Lab passt.</span>
    </h2>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 32px; margin-bottom: 56px;">
      <div style="padding: 32px; border: 1px solid var(--border-subtle); border-radius: 16px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--accent), transparent); opacity: 0.7;"></div>
        <div style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px; font-weight: 600;" data-i18n="s11.i1_label">Demokratisierung</div>
        <p style="font-size: 22px; line-height: 1.45; color: #fff; letter-spacing: -0.01em;" data-i18n="s11.i1_text">Evidenzbasierte Entscheidungen ohne akademischen Background.</p>
      </div>

      <div style="padding: 32px; border: 1px solid var(--border-subtle); border-radius: 16px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--accent-2), transparent); opacity: 0.7;"></div>
        <div style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent-2); margin-bottom: 16px; font-weight: 600;" data-i18n="s11.i2_label">Datenschutz</div>
        <p style="font-size: 22px; line-height: 1.45; color: #fff; letter-spacing: -0.01em;" data-i18n="s11.i2_text">Lokale Verarbeitung. Sensible Daten bleiben im System.</p>
      </div>

      <div style="padding: 32px; border: 1px solid var(--border-subtle); border-radius: 16px; background: rgba(10,10,30,0.5); backdrop-filter: blur(20px); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--accent-3), transparent); opacity: 0.7;"></div>
        <div style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent-3); margin-bottom: 16px; font-weight: 600;" data-i18n="s11.i3_label">Nachhaltigkeit</div>
        <p style="font-size: 22px; line-height: 1.45; color: #fff; letter-spacing: -0.01em;" data-i18n="s11.i3_text">Querfinanzierung — langfristig stabil, kein Exit-Druck.</p>
      </div>
    </div>

    <div class="divider-glow" style="margin: 40px 0 40px;"></div>

    <div>
      <div style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 24px; font-weight: 600;" data-i18n="s11.sdg_title">SDG-Alignment</div>
      <div style="display: flex; gap: 48px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 16px;">
          <span style="width: 40px; height: 40px; border-radius: 10px; background: var(--gradient-duo); display: flex; align-items: center; justify-content: center; font-family: var(--font-mono); font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0;">04</span>
          <span style="font-size: 18px; color: var(--text-main);" data-i18n="s11.sdg1">SDG 4 · Bildung · Forschungszugang für alle</span>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <span style="width: 40px; height: 40px; border-radius: 10px; background: var(--gradient-duo); display: flex; align-items: center; justify-content: center; font-family: var(--font-mono); font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0;">10</span>
          <span style="font-size: 18px; color: var(--text-main);" data-i18n="s11.sdg2">SDG 10 · Ungleichheit · keine akademischen Hürden</span>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <span style="width: 40px; height: 40px; border-radius: 10px; background: var(--gradient-duo); display: flex; align-items: center; justify-content: center; font-family: var(--font-mono); font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0;">17</span>
          <span style="font-size: 18px; color: var(--text-main);" data-i18n="s11.sdg3">SDG 17 · Partnerschaften · Open-Source-Kern</span>
        </div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 12 · TRACTION (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="Traction">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s12.kicker">05 — Proof of Work</span>
      <span>·</span>
      <span class="page-num">12 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 140px;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s12.kicker">05 — Proof of Work</span>
    </div>
    <h2 class="sub-title" style="margin-bottom: 80px;">
      <span data-i18n="s12.title_1">Kein MVP-Konzept.</span><br>
      <span class="grad" data-i18n="s12.title_2">Laufende Systeme.</span>
    </h2>

    <div class="col-4" style="gap: 24px; margin-bottom: 56px;">
      <div class="metric">
        <div class="num">530<span class="suffix">+</span></div>
        <div class="label" data-i18n="s12.m1_label">Commits · app.linn.games</div>
      </div>
      <div class="metric">
        <div class="num">440<span class="suffix">+</span></div>
        <div class="label" data-i18n="s12.m2_label">Tests · app.linn.games</div>
      </div>
      <div class="metric">
        <div class="num">147<span class="suffix">+</span></div>
        <div class="label" data-i18n="s12.m3_label">Commits · MayringCoder</div>
      </div>
      <div class="metric">
        <div class="num">3<span class="suffix"> ·</span></div>
        <div class="label" data-i18n="s12.m4_label">MCP-Endpunkte live</div>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: auto;">
      <div style="display: flex; align-items: center; gap: 14px; padding: 20px 24px; background: #fff; border: 1px solid var(--zinc-200); border-radius: 12px;">
        <span style="width: 10px; height: 10px; border-radius: 50%; background: #22c55e; flex-shrink: 0;"></span>
        <span style="font-size: 17px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s12.f1">Credit-System · produktionsreif</span>
      </div>
      <div style="display: flex; align-items: center; gap: 14px; padding: 20px 24px; background: #fff; border: 1px solid var(--zinc-200); border-radius: 12px;">
        <span style="width: 10px; height: 10px; border-radius: 50%; background: #22c55e; flex-shrink: 0;"></span>
        <span style="font-size: 17px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s12.f2">Fine-tuning Pipeline · QLoRA → GGUF → Ollama</span>
      </div>
      <div style="display: flex; align-items: center; gap: 14px; padding: 20px 24px; background: #fff; border: 1px solid var(--zinc-200); border-radius: 12px;">
        <span style="width: 10px; height: 10px; border-radius: 50%; background: #22c55e; flex-shrink: 0;"></span>
        <span style="font-size: 17px; color: var(--zinc-800); letter-spacing: -0.01em;" data-i18n="s12.f3">Deployment · automatisiert via deploy.sh</span>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 13 · TECH STACK SIDE-BY-SIDE (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Tech stack">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s13.kicker">Tech-Stack</span>
      <span>·</span>
      <span class="page-num">13 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 140px;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s13.kicker">Tech-Stack</span>
    </div>
    <h2 class="sub-title" style="margin-bottom: 64px;" data-i18n="s13.title">Zwei Laufzeiten, ein Team.</h2>

    <div class="col-2" style="gap: 40px; flex: 1;">
      <div class="code-card">
        <div class="code-header">
          <span class="code-dot r"></span>
          <span class="code-dot y"></span>
          <span class="code-dot g"></span>
          <span class="code-title" data-i18n="s13.c1_head">app.linn.games · Cloud</span>
        </div>
        <pre class="code-body" style="margin:0; font-size: 18px;"><span class="cl-c"># runtime</span>
<span class="cl-k">framework</span>:   <span class="cl-s">"Laravel 12"</span>
<span class="cl-k">language</span>:    <span class="cl-s">"PHP 8.4"</span>
<span class="cl-k">frontend</span>:    [<span class="cl-s">Livewire</span>, <span class="cl-s">Volt</span>, <span class="cl-s">Filament</span>]

<span class="cl-c"># data</span>
<span class="cl-k">database</span>:    <span class="cl-s">"PostgreSQL 16"</span>
<span class="cl-k">extension</span>:   <span class="cl-s">pgvector</span>
<span class="cl-k">cache</span>:       <span class="cl-s">"Redis"</span>

<span class="cl-c"># ai + ops</span>
<span class="cl-k">llm</span>:         <span class="cl-s">"Claude API (Anthropic)"</span>
<span class="cl-k">runtime</span>:     [<span class="cl-s">Docker</span>, <span class="cl-s">Nginx</span>]
<span class="cl-k">deploy</span>:      <span class="cl-s">deploy.sh</span>  <span class="cl-c"># CI/CD</span></pre>
      </div>

      <div class="code-card">
        <div class="code-header">
          <span class="code-dot r"></span>
          <span class="code-dot y"></span>
          <span class="code-dot g"></span>
          <span class="code-title" data-i18n="s13.c2_head">MayringCoder · Local / SaaS</span>
        </div>
        <pre class="code-body" style="margin:0; font-size: 18px;"><span class="cl-c"># runtime</span>
<span class="cl-k">language</span>:    <span class="cl-s">"Python 3.11+"</span>
<span class="cl-k">llm</span>:         <span class="cl-s">"Ollama (lokale LLMs)"</span>

<span class="cl-c"># data</span>
<span class="cl-k">vector_db</span>:   <span class="cl-s">"ChromaDB"</span>
<span class="cl-k">metadata</span>:    <span class="cl-s">"SQLite"</span>
<span class="cl-k">mcp</span>:         <span class="cl-s">"FastMCP stdio"</span>

<span class="cl-c"># fine-tuning</span>
<span class="cl-k">training</span>:    [<span class="cl-s">QLoRA</span>, <span class="cl-s">Unsloth</span>]
<span class="cl-k">export</span>:      [<span class="cl-s">GGUF</span>, <span class="cl-s">Ollama</span>]
<span class="cl-k">runtime</span>:     <span class="cl-s">"Docker"</span>
<span class="cl-k">deploy</span>:      <span class="cl-s">run.sh</span>     <span class="cl-c"># pipeline</span></pre>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 14 · ASK (light)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide light" data-label="Ask">
  <div class="grid-bg"></div>
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s14.kicker">06 — Was wir suchen</span>
      <span>·</span>
      <span class="page-num">14 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="padding: 180px 128px 140px;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s14.kicker">06 — Was wir suchen</span>
    </div>
    <h2 class="hero-title" style="font-size: 112px; margin-bottom: 80px;">
      <span data-i18n="s14.title_1">Kein Kapital —</span><br>
      <span class="grad" data-i18n="s14.title_2">Pilotnutzer.</span>
    </h2>

    <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 56px; flex: 1; align-items: stretch;">
      <div style="background: var(--zinc-950); color: #fff; border-radius: 20px; padding: 48px 48px 44px; position: relative; overflow: hidden; display: flex; flex-direction: column;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gradient-brand); opacity: 0.9;"></div>
        <div style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.25em; text-transform: uppercase; color: var(--accent); margin-bottom: 24px; font-weight: 600;" data-i18n="s14.primary_label">Primärer Ask</div>
        <p style="font-size: 34px; line-height: 1.3; color: #fff; letter-spacing: -0.02em; font-weight: 500;" data-i18n="s14.primary_text">
          Pilotnutzer aus Forschung und Impact-Startup-Umfeld, die app.linn.games an echten Reviews testen — und ehrliches Feedback geben.
        </p>
      </div>

      <div style="display: flex; flex-direction: column;">
        <div style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.25em; text-transform: uppercase; color: var(--zinc-500); margin-bottom: 24px; font-weight: 600;" data-i18n="s14.s_label">Sekundär</div>

        <div style="display: flex; flex-direction: column; gap: 16px;">
          <div style="padding: 20px 24px; background: #fff; border: 1px solid var(--zinc-200); border-radius: 12px; display: flex; align-items: baseline; gap: 16px;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0;">01</span>
            <span style="font-size: 19px; color: var(--zinc-800); line-height: 1.4; letter-spacing: -0.01em;" data-i18n="s14.s1">Netzwerk zu Universitäten → Campuslizenzen</span>
          </div>
          <div style="padding: 20px 24px; background: #fff; border: 1px solid var(--zinc-200); border-radius: 12px; display: flex; align-items: baseline; gap: 16px;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0;">02</span>
            <span style="font-size: 19px; color: var(--zinc-800); line-height: 1.4; letter-spacing: -0.01em;" data-i18n="s14.s2">Hinweise auf Förderprogramme (Prototype Fund, EXIST)</span>
          </div>
          <div style="padding: 20px 24px; background: #fff; border: 1px solid var(--zinc-200); border-radius: 12px; display: flex; align-items: baseline; gap: 16px;">
            <span style="font-family: var(--font-mono); font-size: 13px; color: var(--zinc-400); flex-shrink: 0;">03</span>
            <span style="font-size: 19px; color: var(--zinc-800); line-height: 1.4; letter-spacing: -0.01em;" data-i18n="s14.s3">Feedback zur Impact-Positionierung</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="chrome.foot_left">PITCH DECK · 04·2026</span>
    <span data-i18n="chrome.foot_right">INTERACTIVE · AI · WEB</span>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════════════
     SLIDE 15 · CONTACT (dark)
     ══════════════════════════════════════════════════════════════════ -->
<section class="slide dark" data-label="Contact">
  <div class="chrome">
    <div class="brand-lock">
      <span class="logo-wrap" data-logo></span>
      <span class="brand-name">Linn<span class="dot-grad">.</span>Games</span>
    </div>
    <div class="meta">
      <span data-i18n="s15.kicker">Kontakt</span>
      <span>·</span>
      <span class="page-num">15 / 15</span>
    </div>
  </div>

  <div class="slide-pad" style="justify-content: center;">
    <div class="eyebrow-line">
      <span class="bar"></span>
      <span data-i18n="s15.kicker">Kontakt</span>
    </div>
    <h2 class="hero-title" style="font-size: 160px; margin-bottom: 64px;">
      <span class="grad-tri" data-i18n="s15.thanks">Danke.</span>
    </h2>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 80px; max-width: 1500px; align-items: start;">
      <div>
        <div style="font-family: var(--font-display); font-size: 32px; font-weight: 700; color: #fff; margin-bottom: 12px; letter-spacing: -0.02em;" data-i18n="s15.name">Benedikt · Linn.Games</div>
        <div style="font-size: 20px; color: var(--text-muted); margin-bottom: 40px;" data-i18n="s15.loc">Lampertheim, DE</div>

        <p class="body-md" style="font-size: 20px; max-width: 520px;" data-i18n="s1.sub">
          Keine PowerPoint-Konzepte. Laufende Systeme. Zwei Produkte, eine Codebasis, eine Vision — qualitative Forschung radikal zugänglich machen.
        </p>
      </div>

      <div style="display: flex; flex-direction: column; gap: 20px;">
        <div style="display: grid; grid-template-columns: 140px 1fr; gap: 32px; align-items: baseline; padding: 20px 0; border-bottom: 1px solid var(--border-subtle);">
          <span style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--text-muted);" data-i18n="s15.email_label">E-Mail</span>
          <span style="font-family: var(--font-mono); font-size: 22px; color: #fff;">info@linn.games</span>
        </div>
        <div style="display: grid; grid-template-columns: 140px 1fr; gap: 32px; align-items: baseline; padding: 20px 0; border-bottom: 1px solid var(--border-subtle);">
          <span style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--text-muted);" data-i18n="s15.web_label">Web</span>
          <span style="font-family: var(--font-mono); font-size: 22px; color: #fff;">app.linn.games</span>
        </div>
        <div style="display: grid; grid-template-columns: 140px 1fr; gap: 32px; align-items: baseline; padding: 20px 0; border-bottom: 1px solid var(--border-subtle);">
          <span style="font-family: var(--font-mono); font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--text-muted);" data-i18n="s15.github_label">GitHub</span>
          <span style="font-family: var(--font-mono); font-size: 22px; color: #fff;">github.com/nileneb</span>
        </div>
      </div>
    </div>
  </div>

  <div class="chrome-bottom">
    <span data-i18n="s15.tagline">Interactive Games · AI Solutions · Web-Apps</span>
    <span>© 2026 LINN.GAMES</span>
  </div>
</section>

</deck-stage>

<script src="{{ asset('pitch/assets/deck-stage.js') }}"></script>

<script>
// ────────── Logo injection ──────────
(() => {
  const tpl = document.getElementById('logo-mark-tpl');
  document.querySelectorAll('[data-logo]').forEach((slot) => {
    slot.appendChild(tpl.content.cloneNode(true));
  });
})();

// ────────── Language toggle ──────────
(() => {
  const btns = document.querySelectorAll('.lang-toggle button');
  let currentLang = localStorage.getItem('pitch-lang') || 'de';

  const applyLang = (lang) => {
    currentLang = lang;
    document.documentElement.lang = lang;
    btns.forEach((b) => b.classList.toggle('active', b.dataset.lang === lang));
    document.querySelectorAll('[data-i18n]').forEach((el) => {
      const key = el.getAttribute('data-i18n');
      const entry = window.PITCH_I18N && window.PITCH_I18N[key];
      if (entry && entry[lang]) {
        el.textContent = entry[lang];
      }
    });
    localStorage.setItem('pitch-lang', lang);
  };

  btns.forEach((b) => {
    b.addEventListener('click', () => applyLang(b.dataset.lang));
  });

  applyLang(currentLang);
})();
</script>

</body>
</html>
