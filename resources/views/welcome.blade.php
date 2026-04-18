<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <title>Linn Games · Interactive Games, KI-Lösungen & Web-Apps</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta
      name="description"
      content="Linn Games entwickelt interaktive 3D-Browsergames, KI-Lösungen und moderne Web-Apps für mittelständische Unternehmen – von der Idee bis zum laufenden System."
    />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon-96x96.png?v=20260402" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260402" />
    <link rel="shortcut icon" href="/favicon.ico?v=20260402" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260402" />
    <meta name="apple-mobile-web-app-title" content="linn.games" />
    <link rel="manifest" href="/site.webmanifest?v=20260402" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap"
      rel="stylesheet"
    />

    @vite(['resources/css/welcome.css'])
  </head>
  <body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>

    <!-- Floating Particles -->
    <div class="particles">
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
    </div>

    <!-- Grid Overlay -->
    <div class="grid-overlay"></div>

    <!-- Cursor Glow -->
    <div class="cursor-glow" id="cursor-glow"></div>

    <header class="site-header">
      <div class="nav-inner">
        <a href="#top" class="logo-stack">
          <div class="logo-mark">
            <img src="/images/logo.webp" alt="Linn Games Logo" />
          </div>
          <div>
            <div class="logo-text-main">Linn Games</div>
            <div class="logo-text-sub">Interactive · AI · Web</div>
          </div>
        </a>

        <nav class="nav-links">
          <a href="#services">Leistungen</a>
          <a href="#games">Games</a>
          <a href="#ai">AI</a>
          <a href="#web">Web</a>
          <a href="https://mau.linn.games/" target="_blank" rel="noopener"
            >🐱 MAU</a
          >
          <a href="#cases">Projekte</a>
          <a href="#process">Ablauf</a>
          <a href="#about">Über uns</a>
          <a href="#contact">Kontakt</a>
          <a href="{{ route('pitch') }}">Pitch Deck</a>
        </nav>

        <div class="nav-cta">
          @auth
            <a href="{{ url('/dashboard') }}" class="btn-small btn-small-primary">
              Dashboard
            </a>
          @else
            <a href="{{ route('login') }}" class="btn-small">
              Login
            </a>
            @if (Route::has('register'))
              <a href="{{ route('register') }}" class="btn-small btn-small-primary">
                Registrieren
              </a>
            @endif
          @endauth
        </div>

        <button
          class="menu-toggle"
          type="button"
          aria-label="Navigation öffnen"
          id="menu-toggle"
        >
          <span></span>
        </button>
      </div>

      <div class="nav-mobile" id="nav-mobile">
        <a href="#services">Leistungen</a>
        <a href="#games">Interactive Games</a>
        <a href="#ai">AI Solutions</a>
        <a href="#web">Web-Apps</a>
        <a href="https://mau.linn.games/" target="_blank" rel="noopener"
          >🐱 MAU Game</a
        >
        <a href="#cases">Projekte</a>
        <a href="#process">Ablauf</a>
        <a href="#about">Über uns</a>
        <a href="#contact">Kontakt</a>
        <a href="{{ route('pitch') }}">Pitch Deck</a>
        <div class="nav-mobile-cta">
          @auth
            <a href="{{ url('/dashboard') }}" class="btn-small btn-small-primary">Dashboard</a>
          @else
            <a href="{{ route('login') }}" class="btn-small">Login</a>
            @if (Route::has('register'))
              <a href="{{ route('register') }}" class="btn-small btn-small-primary">Registrieren</a>
            @endif
          @endauth
        </div>
      </div>
    </header>

    <main>
      <div class="page-wrap">
        <!-- HERO -->

        <section class="hero" id="top">
          <div>
            <div class="hero-kicker">
              <div class="hero-kicker-dot"></div>
              <span>Games · AI · Web</span>
            </div>

            <h1 class="hero-title">
              Interaktive Games,<br />
              <span>KI-Lösungen</span> und Web-Apps<br />
              für Unternehmen, die mehr wollen.
            </h1>

            <p class="hero-subtitle">
              Linn Games entwickelt 3D-Browsergames, praxisnahe AI Solutions und
              moderne Webanwendungen – fokussiert auf mittelständische
              Unternehmen, die digitale Erlebnisse und echte Use Cases verbinden
              wollen.
            </p>

            <div class="hero-tags">
              <div class="hero-tag">🎮 Unity · 3D-Browsergames</div>
              <div class="hero-tag">🤖 Object Detection & Tracking</div>
              <div class="hero-tag">💬 LLM-Chatbots</div>
              <div class="hero-tag">⚛️ React · APIs · Cloud</div>
            </div>

            <div class="hero-actions">
              <a href="#contact" class="btn-primary">
                ✨ Unverbindliches Erstgespräch
                <span>→</span>
              </a>
              <a
                href="https://mau.linn.games/"
                target="_blank"
                rel="noopener"
                class="btn-secondary"
              >
                🐱 MAU spielen
                <span class="arrow">↗</span>
              </a>
              <a href="#cases" class="btn-secondary">
                Projekte ansehen
                <span class="arrow">↗</span>
              </a>
            </div>

            <p class="hero-meta">
              <strong>Fokus:</strong> Interaktive Experiences, KI-Prototypen und
              Web-Apps mit klarem Praxisbezug – keine PowerPoint-Konzepte,
              sondern laufende Systeme.
            </p>
          </div>

          <div class="hero-visual-wrap" aria-hidden="true">
            <div class="hero-orbit">
              <div class="hero-orbit-inner">
                <div class="hero-orbit-header">
                  <span class="label">Realtime Telemetry</span>
                  <span class="dot"></span>
                </div>

                <div class="hero-orbit-main">
                  <div class="code-card">
                    <div class="code-card-header">
                      <div class="code-dots">
                        <span></span><span></span><span></span>
                      </div>
                      <span>growdash_agent.py</span>
                    </div>
                    <div class="code-body">
                      <span class="code-line"
                        ><span class="comment"
                          ># Hardware-Agent: sichere Verbindung zum
                          Backend</span
                        ></span
                      >
                      <span class="code-line"
                        ><span class="fn">class</span>
                        <span class="key">GrowDashAgent</span>:</span
                      >
                      <span class="code-line">
                        <span class="fn">def</span>
                        <span class="key">sync_sensor_data</span>(<span
                          class="val"
                          >self</span
                        >):</span
                      >
                      <span class="code-line"> payload = {</span>
                      <span class="code-line">
                        <span class="key">"temperature"</span>:
                        <span class="val">read_temp()</span>,</span
                      >
                      <span class="code-line">
                        <span class="key">"humidity"</span>:
                        <span class="val">read_humidity()</span>,</span
                      >
                      <span class="code-line">
                        <span class="key">"tds"</span>:
                        <span class="val">read_tds()</span>,</span
                      >
                      <span class="code-line">
                        <span class="key">"water_level"</span>:
                        <span class="val">read_level()</span>,</span
                      >
                      <span class="code-line"> }</span>
                      <span class="code-line">
                        <span class="fn">return</span>
                        <span class="val">self.post_secure</span>(<span
                          class="key"
                          >"/api/telemetry"</span
                        >, payload)</span
                      >
                      <span class="code-line"></span>
                      <span class="code-line"
                        ><span class="fn">def</span>
                        <span class="key">run</span>():</span
                      >
                      <span class="code-line">
                        <span class="comment"
                          ># AI + Hardware + Web – eine Pipeline</span
                        ></span
                      >
                    </div>
                  </div>

                  <div class="telemetry-card">
                    <div class="telemetry-header">
                      <span>GrowDash · Live</span>
                      <div class="telemetry-badges">
                        <span class="telemetry-badge">Secure</span>
                        <span class="telemetry-badge">Realtime</span>
                      </div>
                    </div>
                    <div class="telemetry-values">
                      <div>
                        <div class="telemetry-label">Temperature</div>
                        <div class="telemetry-value">23.4 °C</div>
                      </div>
                      <div>
                        <div class="telemetry-label">Humidity</div>
                        <div class="telemetry-value">54 %</div>
                      </div>
                      <div>
                        <div class="telemetry-label">TDS</div>
                        <div class="telemetry-value">830 ppm</div>
                      </div>
                      <div>
                        <div class="telemetry-label">Water Level</div>
                        <div class="telemetry-value">OK</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="hero-orbit-footer">
                  <span>Interactive Games · AI · Web</span>
                  <span class="sub">Linn Games · Lampertheim, DE</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- SERVICES OVERVIEW -->

        <section id="services">
          <div class="section-header">
            <div class="section-kicker">🚀 Leistungen</div>
            <h2 class="section-title">
              Ein Studio – <span>drei Schwerpunkte</span>
            </h2>
            <p class="section-lead">
              Wir verbinden Game-Development, AI Solutions und Web-Engineering
              zu einem durchgängigen Setup für Ihr Unternehmen: kein
              Flickenteppich aus Agenturen, sondern eine integrierte digitale
              Experience.
            </p>
          </div>

          <div class="services-grid">
            <article class="service-card" id="games">
              <div class="service-label">🎮 Interactive Experiences</div>
              <h3 class="service-title">
                3D-Browsergames & <span>Gamification</span>
              </h3>
              <p class="service-text">
                Unity-basierte Games und interaktive Experiences, die im Browser
                laufen – ideal für Kampagnen, Training und Events ohne
                Installationshürde.
              </p>
              <ul class="service-list">
                <li>3D-Browsergames mit Unity (Desktop & Mobile)</li>
                <li>Gamification für Marketing, Messestände & E-Learning</li>
                <li>
                  AR/Camera-basierte Interaktionen (z. B. Filter, Tracking)
                </li>
              </ul>
              <p class="service-meta">
                Ziel: Ihre Marke wird erlebbar – nicht nur „anklickbar“.
              </p>
            </article>

            <article class="service-card" id="ai">
              <div class="service-label">🤖 AI Solutions</div>
              <h3 class="service-title">
                KI, die <span>echte Use Cases</span> löst
              </h3>
              <p class="service-text">
                Von Objekterkennung bis LLM-Chatbots – wir bauen KI-Lösungen,
                die an realen Prozessen ausgerichtet sind, nicht an Folien.
              </p>
              <ul class="service-list">
                <li>Object Detection & Tracking für Streams und Kameras</li>
                <li>LLM-basierte Chatbots für Support & Wissensmanagement</li>
                <li>Generative KI für Visuals, Prototyping & Content</li>
              </ul>
              <p class="service-meta">
                Von MVP bis produktivem System – mit klar definierten
                Datenwegen.
              </p>
            </article>

            <article class="service-card" id="web">
              <div class="service-label">⚡ Web Development</div>
              <h3 class="service-title">
                Moderne <span>Web-Apps & APIs</span>
              </h3>
              <p class="service-text">
                Wir entwickeln Webanwendungen, die sich in Ihre bestehende
                Infrastruktur einfügen – oder bewusst daneben stehen.
              </p>
              <ul class="service-list">
                <li>Responsives Frontend mit React / Vue / Three.js</li>
                <li>APIs & Backends mit Node.js, Python oder Laravel</li>
                <li>Cloud-Deployments & PWA für App-ähnliche Experiences</li>
              </ul>
              <p class="service-meta">
                Tech-Stack abgestimmt auf Ihr Team – nicht umgekehrt.
              </p>
            </article>
          </div>
        </section>

        <!-- SHOWCASES -->

        <section id="cases">
          <div class="section-header">
            <div class="section-kicker">💎 Projekte</div>
            <h2 class="section-title">Ausgewählte <span>Beispiele</span></h2>
            <p class="section-lead">
              Ein Ausschnitt aus Projekten und internen Tools, die zeigen, wie
              Games, Hardware, AI und Web-Apps bei Linn Games zusammenlaufen.
            </p>
          </div>

          <div class="cases-grid">
            <article
              class="case-card"
              id="snake3d-card"
              style="cursor: pointer"
            >
              <div class="case-label">Browsergame</div>
              <h3 class="case-title"><span>Snake3D</span> · WebGPU-Beta</h3>
              <p class="case-text">
                3D-Snake als Unity-6.2-Browsergame mit experimenteller
                WebGPU-Unterstützung. Entwickelt, um die Grenzen von
                Web-Rendering zu testen – inklusive Gyroskop-Option auf Mobile
                und offenem Code als Spielwiese.
              </p>
              <div class="case-tags">
                <span class="case-tag">Unity 6.2</span>
                <span class="case-tag">WebGPU</span>
                <span class="case-tag">3D-Browsergame</span>
              </div>

              <!-- Game Preview Container -->
              <div
                class="game-preview-container"
                id="snake3d-preview"
                style="
                  display: none;
                  margin-top: 1rem;
                  padding: 1rem;
                  background: rgba(0, 0, 0, 0.3);
                  border-radius: 12px;
                "
              >
                <div class="cookie-notice" id="snake3d-notice">
                  <p
                    style="
                      font-size: 0.85rem;
                      color: var(--text-muted);
                      margin-bottom: 0.8rem;
                    "
                  >
                    Diese eingebettete Seite von play.unity.com verwendet
                    möglicherweise Cookies. Durch das Laden stimmen Sie der
                    Cookie-Nutzung von Unity Play zu.
                  </p>
                  <button
                    class="btn-secondary"
                    style="
                      margin-right: 0.5rem;
                      padding: 0.6rem 1rem;
                      font-size: 0.85rem;
                    "
                    onclick="loadSnake3DIframe(event)"
                  >
                    Inhalt laden
                  </button>
                  <a
                    href="https://play.unity.com/en/games/8be014ae-6b3c-409b-8f15-b801389ab292/snake3d"
                    target="_blank"
                    class="btn-secondary"
                    style="
                      padding: 0.6rem 1rem;
                      font-size: 0.85rem;
                      text-decoration: none;
                      display: inline-flex;
                      align-items: center;
                      gap: 0.3rem;
                    "
                    >Direkt auf Unity Play öffnen
                    <span class="arrow">↗</span></a
                  >
                </div>
                <iframe
                  id="snake3d-frame"
                  data-src="https://play.unity.com/en/games/8be014ae-6b3c-409b-8f15-b801389ab292/snake3d"
                  title="Snake3D - Powered by Unity WebGPU"
                  width="100%"
                  height="450"
                  frameborder="0"
                  allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                  loading="lazy"
                  style="display: none; border-radius: 8px; margin-top: 1rem"
                >
                </iframe>
              </div>
            </article>

            <article
              class="case-card"
              onclick="window.open('https://github.com/Nileneb/growdash', '_blank')"
              style="cursor: pointer"
            >
              <div class="case-label">IoT & AI-Ready</div>
              <h3 class="case-title">
                <span>GrowDash</span> · Smart Grow Control
              </h3>
              <p class="case-text">
                Web-Dashboard plus Hardware-Agent: Echtzeit-Sensorik
                (Temperatur, Feuchte, TDS, Wasserlevel) mit sicherem
                Command-Channel für Pumpen, Lüfter & Licht. Konzipiert als
                Blaupause für AI-unterstützte Automatisierung.
              </p>
              <div class="case-tags">
                <span class="case-tag">Python Agent</span>
                <span class="case-tag">API & Telemetrie</span>
                <span class="case-tag">Automation</span>
              </div>
            </article>

            <article
              class="case-card"
              onclick="window.open('https://mau.linn.games/', '_blank')"
              style="cursor: pointer"
            >
              <div class="case-label">Gamification</div>
              <h3 class="case-title"><span>MAU</span> · QR Cat Campaign</h3>
              <p class="case-text">
                Verspielte Kampagne mit einer „City Cat“, die per QR-Codes
                auftaucht und Leute in eine digitale Story führt – ideal, um
                Aufmerksamkeit, Social Posts und Traffic auf Projekte zu ziehen.
              </p>
              <div class="case-tags">
                <span class="case-tag">Storytelling</span>
                <span class="case-tag">QR Campaign</span>
                <span class="case-tag">Gamified Marketing</span>
              </div>
            </article>
          </div>
        </section>

        <!-- PROCESS -->

        <section id="process">
          <div class="section-header">
            <div class="section-kicker">⚙️ Ablauf</div>
            <h2 class="section-title">
              Klarer <span>Prozess</span> statt Blackbox.
            </h2>
            <p class="section-lead">
              Wir arbeiten iterativ und transparent; damit Sie jederzeit sehen,
              wo Ihr Projekt steht und wie nah wir am tatsächlichen
              Business-Value sind.
            </p>
          </div>

          <div class="process-grid">
            <div class="process-step">
              <div class="process-step-number">01 · Anforderungen</div>
              <div class="process-step-title">Use Cases schärfen</div>
              <p class="process-step-text">
                Wir klären Ziele, Zielgruppen, bestehende Systeme und technische
                Rahmenbedingungen,inkl. Grenzen und „No-Gos“.
              </p>
            </div>
            <div class="process-step">
              <div class="process-step-number">02 · Konzeption</div>
              <div class="process-step-title">Architektur & UX</div>
              <p class="process-step-text">
                Auf Basis Ihrer Anforderungen entsteht ein Architekturentwurf
                und ein klares UX-/UI-Konzept.
              </p>
            </div>
            <div class="process-step">
              <div class="process-step-number">03 · Entwicklung</div>
              <div class="process-step-title">Iterative Sprints</div>
              <p class="process-step-text">
                Umsetzung in Sprints mit Demos; Sie sehen früh einen klickbaren
                oder spielbaren Stand.
              </p>
            </div>
            <div class="process-step">
              <div class="process-step-number">04 · Testing</div>
              <div class="process-step-title">Qualität & Performance</div>
              <p class="process-step-text">
                Funktionale Tests, Lasttests und Security-Checks: je nach
                Projektumfang abgestuft.
              </p>
            </div>
            <div class="process-step">
              <div class="process-step-number">05 · Betrieb</div>
              <div class="process-step-title">
                Deployment & Weiterentwicklung
              </div>
              <p class="process-step-text">
                Rollout inklusive Monitoring; optional laufende Optimierung,
                Features und AI-Rollouts on top.
              </p>
            </div>
          </div>
        </section>

        <!-- ABOUT + CONTACT -->

        <section id="about">
          <div class="section-header">
            <div class="section-kicker">🎯 Über Linn Games</div>
            <h2 class="section-title">
              Aus einem Game-Portal entstand ein <span>Hybrid-Studio</span>.
            </h2>
            <p class="section-lead">
              Linn Games kommt ursprünglich aus der Welt der Browsergames und
              kombiniert diese DNA heute mit AI Solutions und Web-Engineering
              für Unternehmen.
            </p>
          </div>

          <div class="split-grid">
            <article class="about-card">
              <h3 class="about-title">Was uns ausmacht</h3>
              <p class="about-text">
                Wir sitzen in Lampertheim und arbeiten bevorzugt mit
                Unternehmen, die offen für Experimente sind. Aber zugleich
                realistische Anforderungen an Stabilität, Datenschutz und
                Wartbarkeit haben.
              </p>
              <ul class="about-list">
                <li>
                  Kombination aus Game-Dev, AI Engineering und
                  Web-Stack-Kompetenz
                </li>
                <li>
                  Fokus auf mittelständische Unternehmen & praxisnahe Prototypen
                </li>
                <li>
                  Hands-on statt „Enterprise-Pitch“ – klare Strukturen, kurze
                  Wege
                </li>
                <li>
                  Tech-Entscheidungen orientieren sich an Ihrer Situation, nicht
                  an Buzzwords
                </li>
              </ul>
            </article>

            <section id="contact" class="contact-card">
              <h3 class="contact-title">
                Lassen Sie uns über Ihr Projekt sprechen
              </h3>
              <p class="contact-text">
                Schicken Sie eine kurze Beschreibung Ihrer Idee – wir melden uns
                mit Vorschlägen, wie wir Games, AI oder Web sinnvoll einsetzen
                können.
              </p>

              <div class="contact-grid">
                <div class="contact-row">
                  <div class="contact-label">E-Mail</div>
                  <div class="contact-value">
                    <a href="mailto:info@linn.games">info@linn.games</a>
                  </div>
                </div>
                <div class="contact-row">
                  <div class="contact-label">Standort</div>
                  <div class="contact-value">
                    68623 Lampertheim · Deutschland
                  </div>
                </div>
              </div>

              <!-- Contact Form - Handled via JavaScript -->
              <form class="contact-form" id="contact-form">
                <div class="form-group">
                  <label for="name">Name</label>
                  <input
                    id="name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    required
                  />
                </div>
                <div class="form-group">
                  <label for="company">Unternehmen (optional)</label>
                  <input
                    id="company"
                    name="company"
                    type="text"
                    autocomplete="organization"
                  />
                </div>
                <div class="form-group">
                  <label for="email">E-Mail</label>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                  />
                </div>
                <div class="form-group">
                  <label for="project-type">Projektart</label>
                  <select id="project-type" name="project_type" required>
                    <option value="" disabled selected>Bitte auswählen</option>
                    <option value="game">
                      Interactive Game / Gamification
                    </option>
                    <option value="ai">
                      AI Solution (z. B. Object Detection, Chatbot)
                    </option>
                    <option value="web">Web-App / Plattform</option>
                    <option value="combo">
                      Kombination aus mehreren Bereichen
                    </option>
                    <option value="other">Sonstiges / noch unsicher</option>
                  </select>
                </div>
                <div class="form-group full">
                  <label for="message">Kurzbeschreibung des Vorhabens</label>
                  <textarea
                    id="message"
                    name="message"
                    required
                    placeholder="Ziele, Kontext, vorhandene Systeme – alles, was für den Start wichtig ist."
                  ></textarea>
                </div>
                <div class="form-group full">
                  <label for="timeline">Wunschtermin / Zeithorizont</label>
                  <input
                    id="timeline"
                    name="timeline"
                    type="text"
                    placeholder="z. B. Beta bis Q3, Launch nächstes Jahr, noch offen"
                  />
                </div>

                <div class="form-footer">
                  <button type="submit" class="btn-primary btn-submit">
                    Anfrage senden
                    <span>→</span>
                  </button>
                  <p class="form-note">
                    Hinweis: Diese Seite ist bewusst technisch schlank gehalten.
                    Hosting, Security & Logging richten wir projektabhängig ein.
                  </p>
                </div>
              </form>
            </section>
          </div>
        </section>
      </div>
    </main>

    <footer>
      <div class="footer-inner">
        <div>
          © <span id="year"></span> Linn Games · Interactive Games · AI
          Solutions · Web-Apps · Made with 💜
        </div>
        <div class="footer-links">
          <!-- Imprint / Datenschutz-Links hier bei Bedarf einfügen -->
          <a href="#top">↑ Nach oben</a>
          <a href="/dsgvo.html">Datenschutz</a>
          <a href="/AGB.html">AGB</a>
          <a href="/Impressum.html">Impressum</a>
        </div>
      </div>
    </footer>

    @vite(['resources/js/welcome.js'])
  </body>
</html>
