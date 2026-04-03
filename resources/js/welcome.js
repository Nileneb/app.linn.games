(function () {
  "use strict";

  // Jahr im Footer automatisch setzen
  const yearEl = document.getElementById("year");
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // Snake3D Card Click Handler
  const snake3dCard = document.getElementById("snake3d-card");
  const snake3dPreview = document.getElementById("snake3d-preview");
  let snake3dExpanded = false;

  if (snake3dCard && snake3dPreview) {
    snake3dCard.addEventListener("click", function (e) {
      // Don't toggle if clicking on buttons or links inside the preview
      if (e.target.closest(".game-preview-container")) return;

      snake3dExpanded = !snake3dExpanded;
      snake3dPreview.style.display = snake3dExpanded ? "block" : "none";
    });
  }

  // Load Snake3D iframe function
  window.loadSnake3DIframe = function (e) {
    e.stopPropagation();
    const frame = document.getElementById("snake3d-frame");
    const notice = document.getElementById("snake3d-notice");

    if (frame && notice) {
      const src = frame.getAttribute("data-src");
      if (!src) {
        return;
      }

      try {
        const url = new URL(src, window.location.origin);

        // Allow only http(s) URLs
        if (url.protocol === "http:" || url.protocol === "https:") {
          frame.setAttribute("src", url.toString());
          frame.style.display = "block";
          notice.style.display = "none";
        }
      } catch (err) {
        // Invalid URL in data-src; do not load it
      }
    }
  };

  // Mobile Navigation togglen
  const toggle = document.getElementById("menu-toggle");
  const mobileNav = document.getElementById("nav-mobile");

  if (toggle && mobileNav) {
    toggle.addEventListener("click", function () {
      toggle.classList.toggle("active");
      mobileNav.classList.toggle("open");
    });

    mobileNav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        toggle.classList.remove("active");
        mobileNav.classList.remove("open");
      });
    });
  }

  // Header scroll effect
  const header = document.querySelector(".site-header");
  let lastScroll = 0;

  window.addEventListener("scroll", function () {
    const currentScroll = window.scrollY;

    if (currentScroll > 50) {
      header.classList.add("scrolled");
    } else {
      header.classList.remove("scrolled");
    }

    lastScroll = currentScroll;
  });

  // Cursor Glow Effect
  const cursorGlow = document.getElementById("cursor-glow");
  let cursorX = 0,
    cursorY = 0;
  let glowX = 0,
    glowY = 0;

  if (cursorGlow && window.matchMedia("(hover: hover)").matches) {
    document.addEventListener("mousemove", function (e) {
      cursorX = e.clientX;
      cursorY = e.clientY;
    });

    function animateCursor() {
      glowX += (cursorX - glowX) * 0.08;
      glowY += (cursorY - glowY) * 0.08;
      cursorGlow.style.left = glowX + "px";
      cursorGlow.style.top = glowY + "px";
      requestAnimationFrame(animateCursor);
    }
    animateCursor();
  }

  // Scroll Reveal Animations
  const revealElements = document.querySelectorAll(
    ".service-card, .case-card, .process-step, .about-card, .contact-card, .section-header"
  );

  const revealObserver = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry, index) {
        if (entry.isIntersecting) {
          setTimeout(function () {
            entry.target.classList.add("visible");
          }, index * 100);
          revealObserver.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.1,
      rootMargin: "-50px",
    }
  );

  revealElements.forEach(function (el) {
    el.classList.add("reveal");
    revealObserver.observe(el);
  });

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });

  // Telemetry values animation (simulate live data)
  const telemetryValues = document.querySelectorAll(".telemetry-value");

  function updateTelemetry() {
    const values = [
      { min: 20, max: 26, suffix: " °C" },
      { min: 45, max: 65, suffix: " %" },
      { min: 750, max: 950, suffix: " ppm" },
      { static: "OK" },
    ];

    telemetryValues.forEach(function (el, i) {
      if (values[i]) {
        if (values[i].static) {
          el.textContent = values[i].static;
        } else {
          const val = (
            Math.random() * (values[i].max - values[i].min) +
            values[i].min
          ).toFixed(1);
          el.textContent = val + values[i].suffix;
        }
      }
    });
  }

  setInterval(updateTelemetry, 3000);

  // ============================================
  // CONTACT FORM SUBMISSION
  // ============================================
  const contactForm = document.querySelector(".contact-form");
  if (contactForm) {
    contactForm.addEventListener("submit", async function (e) {
      e.preventDefault();

      const submitBtn = contactForm.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.innerHTML;

      // Disable button and show loading state
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Wird gesendet...</span>';

      // Gather form data
      const formData = {
        name: contactForm.querySelector("#name").value,
        company: contactForm.querySelector("#company").value || null,
        email: contactForm.querySelector("#email").value,
        project_type: contactForm.querySelector("#project-type").value,
        message: contactForm.querySelector("#message").value,
        timeline: contactForm.querySelector("#timeline").value || null,
      };

      try {
        const response = await fetch("/contact", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
            "Accept": "application/json",
          },
          body: JSON.stringify(formData),
        });

        const result = await response.json();

        if (response.ok && result.success) {
          // Success - show message and reset form
          submitBtn.innerHTML = '<span>✓ Gesendet!</span>';
          submitBtn.style.background = "linear-gradient(135deg, #10b981, #059669)";
          contactForm.reset();

          // Reset button after 3 seconds
          setTimeout(function () {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.style.background = "";
            submitBtn.disabled = false;
          }, 3000);
        } else {
          // Error from server
          throw new Error(result.message || "Ein Fehler ist aufgetreten");
        }
      } catch (error) {
        console.error("[contact] Error:", error);
        submitBtn.innerHTML = '<span>✗ Fehler</span>';
        submitBtn.style.background = "linear-gradient(135deg, #ef4444, #dc2626)";

        // Show error alert
        alert("Fehler beim Senden: " + error.message);

        // Reset button after 2 seconds
        setTimeout(function () {
          submitBtn.innerHTML = originalBtnText;
          submitBtn.style.background = "";
          submitBtn.disabled = false;
        }, 2000);
      }
    });
  }

  // Console Easter Egg
  console.log(
    "%c🎮 Linn Games",
    "font-size: 32px; font-weight: bold; background: linear-gradient(135deg, #00d4ff, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"
  );
  console.log(
    "%cInteractive Games · AI Solutions · Web-Apps",
    "font-size: 14px; color: #9ca3af;"
  );
  console.log(
    "%c✨ Wir suchen immer nach Talenten! Schreib uns: info@linn.games",
    "font-size: 12px; color: #00d4ff;"
  );
})();
