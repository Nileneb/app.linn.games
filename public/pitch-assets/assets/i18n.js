// Linn.Games Pitch Deck — bilingual content dictionary
// Used by the DOM walker to swap [data-i18n] keys between DE and EN.

window.PITCH_I18N = {
  // ────────── CHROME ──────────
  "chrome.brand": { de: "Linn.Games", en: "Linn.Games" },
  "chrome.location": { de: "LAMPERTHEIM · DE", en: "LAMPERTHEIM · DE" },
  "chrome.event": { de: "IMPACT SPRINT LAB · BERLIN", en: "IMPACT SPRINT LAB · BERLIN" },
  "chrome.foot_left": { de: "PITCH DECK · 04·2026", en: "PITCH DECK · 04·2026" },
  "chrome.foot_right": { de: "INTERACTIVE · AI · WEB", en: "INTERACTIVE · AI · WEB" },

  // ────────── SLIDE 1 · COVER ──────────
  "s1.badge": { de: "Geschlossene Beta · Laufende Systeme", en: "Closed Beta · Systems in Production" },
  "s1.title_1": { de: "KI-gestützte", en: "AI-native" },
  "s1.title_2": { de: "Forschungs-", en: "research" },
  "s1.title_3": { de: "infrastruktur.", en: "infrastructure." },
  "s1.sub": {
    de: "Keine PowerPoint-Konzepte. Laufende Systeme. Zwei Produkte, eine Codebasis, eine Vision — Forschung radikal zugänglich machen.",
    en: "Not PowerPoint concepts — working systems. Two products, one codebase, one vision: make research radically accessible."
  },
  "s1.meta_1": { de: "PITCH · Impact Sprint Lab", en: "PITCH · Impact Sprint Lab" },
  "s1.meta_2": { de: "Valuedfriends Workspaces · Berlin", en: "Valuedfriends Workspaces · Berlin" },
  "s1.code_title": { de: "linn-games.config.yaml", en: "linn-games.config.yaml" },

  // ────────── SLIDE 2 · PROBLEM STARTER ──────────
  "s2.kicker": { de: "01 — Das Problem", en: "01 — The problem" },
  "s2.title_1": { de: "Forschungszugang", en: "Research access" },
  "s2.title_2": { de: "ist blockiert.", en: "is blocked." },
  "s2.sub": {
    de: "Wissenschaftliche Forschung — Literaturreviews, Inhaltsanalysen — ist der Goldstandard für evidenzbasierte Entscheidungen. Und sie bleibt für die meisten unerreichbar.",
    en: "Scientific research — literature reviews, content analysis — is the gold standard for evidence-based decisions. And it stays out of reach for most people."
  },
  "s2.caption": {
    de: "Feldnotiz · Paywall-Realität",
    en: "Field note · paywall reality"
  },

  // ────────── SLIDE 3 · PROBLEM DETAIL ──────────
  "s3.kicker": { de: "Drei Barrieren", en: "Three barriers" },
  "s3.title": { de: "Zeit. Kosten. Zugang.", en: "Time. Cost. Access." },
  "s3.b1_num": { de: "01", en: "01" },
  "s3.b1_label": { de: "Zeit", en: "Time" },
  "s3.b1_big": { de: "Wochen bis Monate", en: "Weeks to months" },
  "s3.b1_text": {
    de: "Ein systematischer Review ist ein Vollzeit-Projekt über Wochen. Für kleine Teams schlicht nicht leistbar.",
    en: "A systematic review is a full-time project spanning weeks. Simply not feasible for small teams."
  },
  "s3.b2_num": { de: "02", en: "02" },
  "s3.b2_label": { de: "Kosten", en: "Cost" },
  "s3.b2_big": { de: "Gute KI ist teuer", en: "Good AI is expensive" },
  "s3.b2_text": {
    de: "Günstige Modelle liefern bei wissenschaftlicher Analyse unbrauchbare Ergebnisse. Qualität hat einen Preis.",
    en: "Cheap models produce unusable output on scientific analysis. Quality has a price."
  },
  "s3.b3_num": { de: "03", en: "03" },
  "s3.b3_label": { de: "Zugang", en: "Access" },
  "s3.b3_big": { de: "Methoden bleiben akademisch", en: "Methods stay academic" },
  "s3.b3_text": {
    de: "Gründer, NGOs, Praktiker ohne akademischen Background haben keinen Weg rein. Das Potenzial ist da. Der Zugang fehlt.",
    en: "Founders, NGOs, practitioners without an academic background have no way in. The potential is there. Access isn't."
  },
  "s3.pull": {
    de: "\u201EDas Potenzial ist da. Der Zugang fehlt.\u201C",
    en: "\u201CThe potential is there. Access isn't.\u201D"
  },

  // ────────── SLIDE 4 · SOLUTION STARTER ──────────
  "s4.kicker": { de: "02 — Die Lösung", en: "02 — The solution" },
  "s4.title_1": { de: "Zwei Produkte.", en: "Two products." },
  "s4.title_2": { de: "Eine Pipeline.", en: "One pipeline." },
  "s4.sub": {
    de: "Ein cloud-basiertes Review-System für Tiefe und Methode. Ein lokales Inhaltsanalyse-Tool für Datenschutz und Querfinanzierung. Eine gemeinsame Code-DNA.",
    en: "A cloud-based review system for depth and method. A local content-analysis tool for privacy and cross-funding. One shared code DNA."
  },

  // ────────── SLIDE 5 · PRODUCT A ──────────
  "s5.kicker": { de: "Produkt A · Cloud", en: "Product A · Cloud" },
  "s5.title_1": { de: "app.linn.games", en: "app.linn.games" },
  "s5.title_2": { de: "KI-Literaturreview in 8 Phasen", en: "AI literature review in 8 phases" },
  "s5.sub": {
    de: "Von der Forschungsfrage bis zur Synthese — durchgechaint, agentisch, kreditbasiert. Live im Einsatz.",
    en: "From research question to synthesis — chained, agentic, credit-metered. Live in production."
  },
  "s5.phase_title": { de: "Die 8 Phasen", en: "The 8 phases" },
  "s5.phase_sub": { de: "P1 → P8 automatisch verkettet", en: "P1 → P8 automatically chained" },
  "s5.p1": { de: "P1 · Forschungsfrage", en: "P1 · Research question" },
  "s5.p2": { de: "P2 · PICO/SPIDER/PEO", en: "P2 · PICO/SPIDER/PEO" },
  "s5.p3": { de: "P3 · Kriterien", en: "P3 · Criteria" },
  "s5.p4": { de: "P4 · Suchstrings", en: "P4 · Search strings" },
  "s5.p5": { de: "P5 · KI-Screening", en: "P5 · AI screening" },
  "s5.p6": { de: "P6 · Qualität", en: "P6 · Quality rating" },
  "s5.p7": { de: "P7 · Extraktion", en: "P7 · Extraction" },
  "s5.p8": { de: "P8 · Synthese", en: "P8 · Synthesis" },
  "s5.stack_label": { de: "Stack", en: "Stack" },
  "s5.status_label": { de: "Status", en: "Status" },
  "s5.status_val": { de: "LIVE · app.linn.games", en: "LIVE · app.linn.games" },

  // ────────── SLIDE 6 · PRODUCT B ──────────
  "s6.kicker": { de: "Produkt B · Local / SaaS", en: "Product B · Local / SaaS" },
  "s6.title_1": { de: "MayringCoder", en: "MayringCoder" },
  "s6.title_2": { de: "KI-Inhaltsanalyse, offline-first", en: "AI content analysis, offline-first" },
  "s6.sub": {
    de: "Qualitative Inhaltsanalyse nach Mayring — vollständig lokal. Kein Cloud-API-Key. DSGVO-konform ohne Aufwand.",
    en: "Qualitative content analysis per Mayring — fully local. No cloud API key needed. GDPR-compliant by default."
  },
  "s6.cap_title": { de: "Was es kann", en: "What it does" },
  "s6.cap1": { de: "Deduktive & induktive Mayring-Analyse", en: "Deductive & inductive Mayring analysis" },
  "s6.cap2": { de: "YAML-Codebook-System", en: "YAML codebook system" },
  "s6.cap3": { de: "3-stufige Extraktions-Pipeline", en: "3-stage extraction pipeline" },
  "s6.cap4": { de: "Adversarial Validation (Advocatus Diaboli)", en: "Adversarial validation (Advocatus Diaboli)" },
  "s6.cap5": { de: "MCP Memory Layer (SQLite + ChromaDB)", en: "MCP memory layer (SQLite + ChromaDB)" },
  "s6.cap6": { de: "QLoRA → GGUF → Ollama Fine-tuning", en: "QLoRA → GGUF → Ollama fine-tuning" },
  "s6.license_label": { de: "Lizenz", en: "License" },
  "s6.license_val": { de: "AGPL-3.0 + kommerzielle Lizenz", en: "AGPL-3.0 + commercial license" },

  // ────────── SLIDE 7 · BUSINESS STARTER ──────────
  "s7.kicker": { de: "03 — Geschäftsmodell", en: "03 — Business model" },
  "s7.title_1": { de: "Querfinanzierung", en: "Cross-funding" },
  "s7.title_2": { de: "statt Investor-Druck.", en: "over investor pressure." },
  "s7.sub": {
    de: "Claude ist teuer — aber das einzige Modell, das wissenschaftliche Analyse zuverlässig liefert. MayringCoder SaaS finanziert die API-Kosten von app.linn.games.",
    en: "Claude is expensive — and the only model that reliably delivers on scientific analysis. MayringCoder SaaS covers the API cost of app.linn.games."
  },

  // ────────── SLIDE 8 · REVENUE STREAMS ──────────
  "s8.kicker": { de: "Vier Revenue Streams", en: "Four revenue streams" },
  "s8.title": { de: "Ein Produkt finanziert das andere.", en: "One product funds the other." },
  "s8.th_stream": { de: "Stream", en: "Stream" },
  "s8.th_product": { de: "Produkt", en: "Product" },
  "s8.th_model": { de: "Modell", en: "Model" },
  "s8.r1_stream": { de: "Credits", en: "Credits" },
  "s8.r1_product": { de: "app.linn.games", en: "app.linn.games" },
  "s8.r1_model": { de: "Token → Cent, nutzungsbasiert", en: "Token → cent, usage-based" },
  "s8.r2_stream": { de: "Hosted SaaS", en: "Hosted SaaS" },
  "s8.r2_product": { de: "MayringCoder", en: "MayringCoder" },
  "s8.r2_model": { de: "Subscription (kein lokales Ollama nötig)", en: "Subscription (no local Ollama required)" },
  "s8.r3_stream": { de: "Kommerzielle Lizenz", en: "Commercial license" },
  "s8.r3_product": { de: "MayringCoder", en: "MayringCoder" },
  "s8.r3_model": { de: "Einmalig / Jahresvertrag", en: "One-off / annual contract" },
  "s8.r4_stream": { de: "Campuslizenz", en: "Campus license" },
  "s8.r4_product": { de: "MayringCoder", en: "MayringCoder" },
  "s8.r4_model": { de: "Universitäten · auf Anfrage", en: "Universities · on request" },

  // ────────── SLIDE 9 · PRICING ──────────
  "s9.kicker": { de: "Pricing · MayringCoder SaaS", en: "Pricing · MayringCoder SaaS" },
  "s9.title": { de: "Vom Self-Hosted bis zum Campus.", en: "From self-hosted to campus-wide." },
  "s9.t1_name": { de: "Free", en: "Free" },
  "s9.t1_price": { de: "0 €", en: "€0" },
  "s9.t1_tag": { de: "Self-Hosted", en: "Self-hosted" },
  "s9.t1_who": { de: "Tech-affine Researcher", en: "Tech-savvy researchers" },
  "s9.t2_name": { de: "Starter", en: "Starter" },
  "s9.t2_price": { de: "~19 €", en: "~€19" },
  "s9.t2_tag": { de: "pro Monat", en: "per month" },
  "s9.t2_who": { de: "Einzelforscher, Studierende", en: "Individual researchers, students" },
  "s9.t3_name": { de: "Pro", en: "Pro" },
  "s9.t3_price": { de: "~79 €", en: "~€79" },
  "s9.t3_tag": { de: "pro Monat", en: "per month" },
  "s9.t3_who": { de: "Forschungsteams, NGOs", en: "Research teams, NGOs" },
  "s9.t4_name": { de: "Campus", en: "Campus" },
  "s9.t4_price": { de: "∞", en: "∞" },
  "s9.t4_tag": { de: "auf Anfrage", en: "on request" },
  "s9.t4_who": { de: "Universitäten, Forschungseinrichtungen", en: "Universities, research institutes" },

  // ────────── SLIDE 10 · WHY NOT VC ──────────
  "s10.kicker": { de: "Kein VC · Non-Dilutive", en: "No VC · Non-dilutive" },
  "s10.quote": {
    de: "\u201EInvestor-Kapital bedeutet Rendite-Erwartung. Das ist inkompatibel mit dem Ziel, Forschungszugang zu demokratisieren.\u201C",
    en: "\"Investor capital means an expected return. That's incompatible with democratising research access.\""
  },
  "s10.funding_title": { de: "Stattdessen · Non-Dilutive Funding", en: "Instead · non-dilutive funding" },
  "s10.f1_name": { de: "Prototype Fund", en: "Prototype Fund" },
  "s10.f1_amount": { de: "bis 47.500 €", en: "up to €47,500" },
  "s10.f1_for": { de: "Perfekt für MayringCoder (Open Source)", en: "Perfect for MayringCoder (open source)" },
  "s10.f2_name": { de: "EXIST-Gründerstipendium", en: "EXIST founder grant" },
  "s10.f2_amount": { de: "3.000 €/Monat · 12 Monate", en: "€3,000/month · 12 months" },
  "s10.f2_for": { de: "Gründungsphase + Sachkosten", en: "Founding phase + expenses" },
  "s10.f3_name": { de: "BMBF / BMWK", en: "BMBF / BMWK" },
  "s10.f3_amount": { de: "Grants", en: "Grants" },
  "s10.f3_for": { de: "KI + Forschungsinfrastruktur", en: "AI + research infrastructure" },
  "s10.f4_name": { de: "Anthropic for Startups", en: "Anthropic for Startups" },
  "s10.f4_amount": { de: "API-Credits", en: "API credits" },
  "s10.f4_for": { de: "Direkte Kostenreduktion", en: "Direct cost reduction" },
  "s10.f5_name": { de: "Revenue-first", en: "Revenue-first" },
  "s10.f5_amount": { de: "Sofort", en: "Immediate" },
  "s10.f5_for": { de: "MayringCoder SaaS früh monetarisieren", en: "Monetise MayringCoder SaaS early" },

  // ────────── SLIDE 11 · IMPACT FIT ──────────
  "s11.kicker": { de: "04 — Impact Fit", en: "04 — Impact fit" },
  "s11.title_1": { de: "Warum das ins Impact", en: "Why this fits the Impact" },
  "s11.title_2": { de: "Sprint Lab passt.", en: "Sprint Lab." },
  "s11.i1_label": { de: "Demokratisierung", en: "Democratisation" },
  "s11.i1_text": { de: "Evidenzbasierte Entscheidungen ohne akademischen Background.", en: "Evidence-based decisions without an academic background." },
  "s11.i2_label": { de: "Datenschutz", en: "Privacy" },
  "s11.i2_text": { de: "Lokale Verarbeitung. Sensible Daten bleiben im System.", en: "Local processing. Sensitive data stays on system." },
  "s11.i3_label": { de: "Nachhaltigkeit", en: "Sustainability" },
  "s11.i3_text": { de: "Querfinanzierung — langfristig stabil, kein Exit-Druck.", en: "Cross-funded — stable long-term, no exit pressure." },
  "s11.sdg_title": { de: "SDG-Alignment", en: "SDG alignment" },
  "s11.sdg1": { de: "SDG 4 · Bildung · Forschungszugang für alle", en: "SDG 4 · Education · research access for all" },
  "s11.sdg2": { de: "SDG 10 · Ungleichheit · keine akademischen Hürden", en: "SDG 10 · Inequality · no academic barriers" },
  "s11.sdg3": { de: "SDG 17 · Partnerschaften · Open-Source-Kern", en: "SDG 17 · Partnerships · open-source core" },

  // ────────── SLIDE 12 · TRACTION ──────────
  "s12.kicker": { de: "05 — Proof of Work", en: "05 — Proof of work" },
  "s12.title_1": { de: "Kein MVP-Konzept.", en: "Not an MVP concept." },
  "s12.title_2": { de: "Laufende Systeme.", en: "Running systems." },
  "s12.m1_label": { de: "Commits · app.linn.games", en: "Commits · app.linn.games" },
  "s12.m2_label": { de: "Tests · app.linn.games", en: "Tests · app.linn.games" },
  "s12.m3_label": { de: "Commits · MayringCoder", en: "Commits · MayringCoder" },
  "s12.m4_label": { de: "MCP-Endpunkte live", en: "MCP endpoints live" },
  "s12.f1": { de: "Credit-System · produktionsreif", en: "Credit system · production-ready" },
  "s12.f2": { de: "Fine-tuning Pipeline · QLoRA → GGUF → Ollama", en: "Fine-tuning pipeline · QLoRA → GGUF → Ollama" },
  "s12.f3": { de: "Deployment · automatisiert via deploy.sh", en: "Deployment · automated via deploy.sh" },

  // ────────── SLIDE 13 · TECH STACK ──────────
  "s13.kicker": { de: "Tech-Stack", en: "Tech stack" },
  "s13.title": { de: "Zwei Laufzeiten, ein Team.", en: "Two runtimes, one team." },
  "s13.c1_head": { de: "app.linn.games · Cloud", en: "app.linn.games · Cloud" },
  "s13.c2_head": { de: "MayringCoder · Local / SaaS", en: "MayringCoder · Local / SaaS" },

  // ────────── SLIDE 14 · ASK ──────────
  "s14.kicker": { de: "06 — Was wir suchen", en: "06 — What we're looking for" },
  "s14.title_1": { de: "Kein Kapital —", en: "Not capital —" },
  "s14.title_2": { de: "Pilotnutzer.", en: "pilot users." },
  "s14.primary_label": { de: "Primärer Ask", en: "Primary ask" },
  "s14.primary_text": {
    de: "Pilotnutzer aus Forschung und Impact-Startup-Umfeld, die app.linn.games an echten Reviews testen — und ehrliches Feedback geben.",
    en: "Pilot users from research and the impact-startup world who run real reviews on app.linn.games — and give honest feedback."
  },
  "s14.s_label": { de: "Sekundär", en: "Secondary" },
  "s14.s1": { de: "Netzwerk zu Universitäten → Campuslizenzen", en: "University network → campus licenses" },
  "s14.s2": { de: "Hinweise auf Förderprogramme (Prototype Fund, EXIST)", en: "Pointers to grant programmes (Prototype Fund, EXIST)" },
  "s14.s3": { de: "Feedback zur Impact-Positionierung", en: "Feedback on impact positioning" },

  // ────────── SLIDE 15 · CONTACT ──────────
  "s15.kicker": { de: "Kontakt", en: "Contact" },
  "s15.title_1": { de: "Lass uns reden.", en: "Let's talk." },
  "s15.name": { de: "Benedikt · Linn.Games", en: "Benedikt · Linn.Games" },
  "s15.loc": { de: "Lampertheim, DE", en: "Lampertheim, DE" },
  "s15.email_label": { de: "E-Mail", en: "Email" },
  "s15.web_label": { de: "Web", en: "Web" },
  "s15.github_label": { de: "GitHub", en: "GitHub" },
  "s15.tagline": { de: "Interactive Games · AI Solutions · Web-Apps", en: "Interactive Games · AI Solutions · Web Apps" },
  "s15.thanks": { de: "Danke.", en: "Thank you." },

  // ────────── SLIDE 5 · POSITIONING QUADRANT ──────────
  "s5q.kicker": { de: "Positioning · Aufwand vs. Qualität", en: "Positioning · Effort vs. Quality" },
  "s5q.title": { de: "Wenig Aufwand. Hohe Qualität.", en: "Low effort. High quality." },
  "s5q.caption": { de: "Forschungstools im\nAufwand-/Qualitäts-Raster", en: "Research tools in the\neffort-vs-quality matrix" },
  "s5q.y_label": { de: "Ergebnis-Qualität", en: "Result Quality" },
  "s5q.x_label": { de: "Aufwand", en: "Effort" },
  "s5q.y_low": { de: "Niedrig", en: "Low" },
  "s5q.y_high": { de: "Hoch", en: "High" },
  "s5q.x_low": { de: "Wenig", en: "Low" },
  "s5q.x_high": { de: "Viel", en: "High" },
  "s5q.q1_tag": { de: "Q1", en: "Q1" },
  "s5q.q1_name": { de: "Ideal Zone", en: "Ideal Zone" },
  "s5q.q2_tag": { de: "Q2", en: "Q2" },
  "s5q.q2_name": { de: "Zu aufwendig", en: "Too expensive" },
  "s5q.q3_tag": { de: "Q3", en: "Q3" },
  "s5q.q3_name": { de: "Nicht brauchbar", en: "Not usable" },
  "s5q.q4_tag": { de: "Q4", en: "Q4" },
  "s5q.q4_name": { de: "Grind Zone", en: "Grind zone" },
  "s5q.p1": { de: "app.linn.games", en: "app.linn.games" },
  "s5q.p2": { de: "MayringCoder", en: "MayringCoder" },
  "s5q.p3": { de: "Manueller Review", en: "Manual review" },
  "s5q.p4": { de: "ChatGPT freestyle", en: "ChatGPT freestyle" },
  "s5q.p5": { de: "Günstige LLMs", en: "Cheap LLMs" },
  "s5q.p6": { de: "Excel + Zotero", en: "Excel + Zotero" },
  "s5q.p7": { de: "Keine Analyse", en: "No analysis" },

  // ────────── SLIDE 8 · REVENUE FLOW ──────────
  "s8.kicker": { de: "Revenue Flow · Querfinanzierung", en: "Revenue flow · Cross-funding" },
  "s8.title": { de: "Ein Produkt finanziert das andere.", en: "One product funds the other." },
  "s8.col_in": { de: "Einnahmen", en: "Inflows" },
  "s8.col_out": { de: "Verwendung", en: "Allocation" },
  "s8.in1_kind": { de: "Stream A", en: "Stream A" },
  "s8.in1_name": { de: "MayringCoder SaaS", en: "MayringCoder SaaS" },
  "s8.in1_sub": { de: "Subscriptions · Starter → Pro", en: "Subscriptions · Starter → Pro" },
  "s8.in2_kind": { de: "Stream B", en: "Stream B" },
  "s8.in2_name": { de: "Campuslizenzen", en: "Campus licences" },
  "s8.in2_sub": { de: "Universitäten, NGOs", en: "Universities, NGOs" },
  "s8.in3_kind": { de: "Stream C", en: "Stream C" },
  "s8.in3_name": { de: "app.linn.games", en: "app.linn.games" },
  "s8.in3_sub": { de: "Credits, nutzungsbasiert", en: "Credits · usage-based" },
  "s8.out1_kind": { de: "Kosten", en: "Cost" },
  "s8.out1_name": { de: "Claude API", en: "Claude API" },
  "s8.out1_sub": { de: "Anthropic · pro Token", en: "Anthropic · per token" },
  "s8.out2_kind": { de: "Infrastruktur", en: "Infra" },
  "s8.out2_name": { de: "Hosting & Ops", en: "Hosting & ops" },
  "s8.out2_sub": { de: "Server, CDN, Backups", en: "Servers, CDN, backups" },
  "s8.out3_kind": { de: "Investition", en: "Invest" },
  "s8.out3_name": { de: "Weiterentwicklung", en: "R&D" },
  "s8.out3_sub": { de: "R&D, Features, Models", en: "R&D, features, models" },
  "s8.out4_kind": { de: "Reserve", en: "Reserve" },
  "s8.out4_name": { de: "Rücklagen", en: "Reserves" },
  "s8.out4_sub": { de: "Runway, Impact-Fond", en: "Runway, impact fund" }
};
