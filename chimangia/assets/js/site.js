/* ChiMangia — script del sito pubblico. Nessuna risorsa esterna, nessun cookie. */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('js');

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Intestazione: ombra quando si scorre ---------- */
    var header = document.querySelector('.site-header');
    if (header) {
      var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    /* ---------- Slider dell'apertura ---------- */
    document.querySelectorAll('[data-slider]').forEach(function (slider) {
      var slides = slider.querySelectorAll('.hero-slide');
      var ui = slider.querySelector('.slider-ui');
      var pauseBtn = slider.querySelector('.slider-pause');
      var dots = slider.querySelectorAll('.slider-dot');
      if (slides.length < 2 || !ui) return;
      ui.hidden = false;
      var current = 0, timer = null, userPaused = reduced, hoverPaused = false;

      function show(i) {
        current = (i + slides.length) % slides.length;
        slides.forEach(function (s, n) {
          s.classList.toggle('is-active', n === current);
          if (n === current) s.removeAttribute('aria-hidden'); else s.setAttribute('aria-hidden', 'true');
        });
        dots.forEach(function (d, n) {
          if (n === current) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
        });
      }
      function stop() { if (timer) { clearInterval(timer); timer = null; } }
      function start() {
        stop();
        if (userPaused || hoverPaused || document.hidden) return;
        timer = setInterval(function () { show(current + 1); }, 5500);
      }
      function syncPause() {
        pauseBtn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
        pauseBtn.setAttribute('aria-label', userPaused ? 'Riprendi lo scorrimento delle fotografie' : 'Metti in pausa lo scorrimento delle fotografie');
      }
      pauseBtn.addEventListener('click', function () { userPaused = !userPaused; syncPause(); start(); });
      dots.forEach(function (d) {
        d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-slide'), 10)); start(); });
      });
      slider.addEventListener('mouseenter', function () { hoverPaused = true; stop(); });
      slider.addEventListener('mouseleave', function () { hoverPaused = false; start(); });
      slider.addEventListener('focusin', function () { hoverPaused = true; stop(); });
      slider.addEventListener('focusout', function () { hoverPaused = false; start(); });
      slider.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowRight') { show(current + 1); start(); }
        if (ev.key === 'ArrowLeft') { show(current - 1); start(); }
      });
      document.addEventListener('visibilitychange', start);
      syncPause();
      start();
    });

    /* ---------- Menu a scomparsa ---------- */
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    function closeNav(focusToggle) {
      if (!nav || !toggle) return;
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      if (focusToggle) toggle.focus();
    }
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = !nav.classList.contains('open');
        nav.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
          var first = nav.querySelector('a');
          if (first) first.focus();
        }
      });
      nav.addEventListener('click', function (ev) {
        if (ev.target.closest('a')) closeNav(false);
      });
      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && nav.classList.contains('open')) closeNav(true);
      });
    }

    /* ---------- Ancore: scorrimento fluido e focus sulla sezione ---------- */
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest('a[href^="#"]');
      if (!a) return;
      var id = a.getAttribute('href').slice(1);
      var target = id ? document.getElementById(id) : null;
      if (!target) return;
      ev.preventDefault();
      target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      if (history.pushState) history.pushState(null, '', '#' + id);
      if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    });

    /* ---------- Comparsa delle sezioni ---------- */
    var reveals = document.querySelectorAll('.reveal');
    if (reduced || !('IntersectionObserver' in window)) {
      reveals.forEach(function (el) { el.classList.add('is-visible'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
      reveals.forEach(function (el) { io.observe(el); });
    }

    /* ---------- Domande frequenti (accordion) ---------- */
    document.querySelectorAll('.faq-q').forEach(function (btn) {
      var panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (!panel) return;
      btn.setAttribute('aria-expanded', 'false');
      panel.hidden = true;
      btn.addEventListener('click', function () {
        var open = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
      });
    });

    /* ---------- Selettore Mensile / Annuale ---------- */
    var prices = document.querySelector('.prices');
    var bt = document.querySelector('.billing-toggle');
    if (prices && bt) {
      bt.hidden = false;
      bt.addEventListener('click', function (ev) {
        var opt = ev.target.closest('.bt-opt');
        if (!opt) return;
        var mode = opt.getAttribute('data-mode');
        prices.setAttribute('data-mode', mode);
        bt.querySelectorAll('.bt-opt').forEach(function (b) {
          b.setAttribute('aria-pressed', b === opt ? 'true' : 'false');
        });
      });
    }

    /* ---------- Modulo contatti ---------- */
    var form = document.querySelector('[data-contact-form]');
    if (form) {
      var status = form.querySelector('.f-status');
      var submit = form.querySelector('button[type="submit"]');

      function setError(name, msg) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) return;
        var err = document.getElementById(input.id + '-err');
        if (msg) {
          input.setAttribute('aria-invalid', 'true');
          if (err) { err.textContent = msg; err.hidden = false; }
        } else {
          input.removeAttribute('aria-invalid');
          if (err) { err.textContent = ''; err.hidden = true; }
        }
      }
      function showStatus(ok, msg) {
        status.innerHTML = '';
        var p = document.createElement('p');
        p.className = ok ? 'f-ok' : 'f-ko';
        p.textContent = msg;
        status.appendChild(p);
        status.focus();
      }
      function validate() {
        var errors = {};
        var nome = form.nome.value.trim();
        var email = form.email.value.trim();
        if (!nome) errors.nome = 'Scrivi il tuo nome.';
        if (!email) errors.email = 'Scrivi la tua email.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) errors.email = 'L\'indirizzo email non sembra corretto.';
        if (!form.privacy.checked) errors.privacy = 'Per inviare la richiesta serve il consenso.';
        return errors;
      }

      form.addEventListener('submit', function (ev) {
        if (!window.fetch || !window.FormData) return; // invio classico
        ev.preventDefault();
        ['nome', 'attivita', 'email', 'telefono', 'clienti', 'messaggio', 'privacy'].forEach(function (n) { setError(n, ''); });
        var errors = validate();
        var names = Object.keys(errors);
        if (names.length) {
          names.forEach(function (n) { setError(n, errors[n]); });
          var first = form.querySelector('[name="' + names[0] + '"]');
          if (first) first.focus();
          return;
        }
        submit.disabled = true;
        status.innerHTML = '<p>Invio in corso…</p>';
        fetch(form.getAttribute('action'), {
          method: 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        }).then(function (r) {
          return r.json().catch(function () { return { ok: false, message: 'Risposta non valida dal server.' }; });
        }).then(function (data) {
          submit.disabled = false;
          if (data.ok) {
            form.reset();
            showStatus(true, data.message || form.getAttribute('data-success') || 'Richiesta inviata.');
          } else {
            if (data.errors) Object.keys(data.errors).forEach(function (n) { setError(n, data.errors[n]); });
            showStatus(false, data.message || 'Invio non riuscito. Riprova.');
          }
        }).catch(function () {
          submit.disabled = false;
          showStatus(false, 'Connessione non riuscita. Controlla la rete e riprova.');
        });
      });
    }
  });
})();
