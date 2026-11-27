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

    /* ---------- Titoli: le parole entrano una dopo l'altra ---------- */
    function splitWords(el) {
      var text = el.textContent.replace(/\s+/g, ' ').trim();
      var visual = document.createElement('span');
      visual.setAttribute('aria-hidden', 'true');
      while (el.firstChild) visual.appendChild(el.firstChild);
      var i = 0;
      (function walk(node) {
        Array.prototype.slice.call(node.childNodes).forEach(function (child) {
          if (child.nodeType === 3) {
            var frag = document.createDocumentFragment();
            child.nodeValue.split(/(\s+)/).forEach(function (part) {
              if (part === '') return;
              if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(' ')); return; }
              var w = document.createElement('span');
              w.className = 'w';
              w.style.setProperty('--i', String(i++));
              w.textContent = part;
              frag.appendChild(w);
            });
            node.replaceChild(frag, child);
          } else if (child.nodeType === 1) {
            walk(child);
          }
        });
      })(visual);
      // Il testo intero resta in un'unica frase per i lettori di schermo.
      var spoken = document.createElement('span');
      spoken.className = 'sr-only';
      spoken.textContent = text;
      el.appendChild(spoken);
      el.appendChild(visual);
    }
    if (!reduced) document.querySelectorAll('[data-split]').forEach(splitWords);

    /* ---------- Apertura: ingresso a caratteri caricati ---------- */
    var heroText = document.querySelector('.hero-text');
    if (heroText) {
      var entered = false;
      var enter = function () {
        if (entered) return;
        entered = true;
        requestAnimationFrame(function () { heroText.classList.add('is-in'); });
      };
      if (reduced || !document.fonts || !document.fonts.ready) enter();
      else { document.fonts.ready.then(enter); setTimeout(enter, 900); }
    }

    /* ---------- Movimento legato allo scorrimento ----------
     * Un solo ciclo per: ombra dell'intestazione, barra di avanzamento,
     * parallasse delle fotografie e nastro che scorre con la pagina. */
    var header = document.querySelector('.site-header');
    var progressBar = document.querySelector('.scroll-progress span');
    var heroBg = reduced ? null : document.querySelector('.hero-bg');
    var parallaxEls = reduced ? [] : Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    var track = reduced ? null : document.querySelector('.marquee-track');
    var runWidth = 0;
    var ticking = false;
    function measure() {
      var run = track ? track.querySelector('.mq-run') : null;
      runWidth = run ? run.getBoundingClientRect().width : 0;
    }
    function frame() {
      ticking = false;
      var sy = window.pageYOffset || 0;
      var vh = window.innerHeight;
      if (header) header.classList.toggle('is-scrolled', sy > 8);
      if (progressBar) {
        var max = document.documentElement.scrollHeight - vh;
        progressBar.style.setProperty('--progress', max > 0 ? Math.min(1, sy / max).toFixed(4) : '0');
      }
      if (heroBg && sy < vh * 1.3) heroBg.style.setProperty('--py', (sy * 0.12).toFixed(1) + 'px');
      parallaxEls.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.bottom < -80 || r.top > vh + 80) return;
        var p = ((r.top + r.height / 2) - vh / 2) / (vh / 2 + r.height / 2);
        var amount = r.height * (el.classList.contains('panel') ? 0.07 : 0.05);
        el.style.setProperty('--py', (-p * amount).toFixed(1) + 'px');
      });
      if (track && runWidth > 0) {
        track.style.setProperty('--mx', (-((sy * 0.55 + runWidth * 0.2) % runWidth)).toFixed(1) + 'px');
      }
    }
    function requestFrame() {
      if (!ticking) { ticking = true; requestAnimationFrame(frame); }
    }
    window.addEventListener('scroll', requestFrame, { passive: true });
    window.addEventListener('resize', function () { measure(); requestFrame(); });
    window.addEventListener('load', function () { measure(); requestFrame(); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { measure(); requestFrame(); });
    measure();
    frame();

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

    /* ---------- Domande frequenti (accordion con altezza animata) ---------- */
    document.querySelectorAll('.faq-q').forEach(function (btn) {
      var panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (!panel) return;
      var fallback = null;
      btn.setAttribute('aria-expanded', 'false');
      panel.hidden = true;
      function finish(ev) {
        if (ev && ev.propertyName !== 'height') return;
        panel.removeEventListener('transitionend', finish);
        clearTimeout(fallback);
        if (btn.getAttribute('aria-expanded') !== 'true') panel.hidden = true;
        panel.style.height = '';
      }
      btn.addEventListener('click', function () {
        var open = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (reduced) { panel.hidden = !open; return; }
        panel.removeEventListener('transitionend', finish);
        clearTimeout(fallback);
        if (open) {
          panel.hidden = false;
          var full = panel.scrollHeight;
          panel.style.height = '0px';
          panel.getBoundingClientRect();
          panel.style.height = full + 'px';
        } else {
          panel.style.height = panel.getBoundingClientRect().height + 'px';
          panel.getBoundingClientRect();
          panel.style.height = '0px';
        }
        panel.addEventListener('transitionend', finish);
        fallback = setTimeout(finish, 800);
      });
    });

    /* ---------- Come funziona: i passaggi comandano la dimostrazione ---------- */
    var how = document.querySelector('.how');
    var stage = document.querySelector('[data-how-stage]');
    if (how && stage) {
      var steps = Array.prototype.slice.call(how.querySelectorAll('.how-step'));
      var picks = Array.prototype.slice.call(stage.querySelectorAll('.ph-opt[data-pick]'));
      var phoneBtn = stage.querySelector('.ph-btn');
      var counters = Array.prototype.slice.call(stage.querySelectorAll('[data-count]'));
      var timers = [];
      var run = 0;
      var later = function (fn, ms) { timers.push(setTimeout(fn, ms)); };
      var clearTimers = function () { timers.forEach(clearTimeout); timers = []; run++; };
      var finalOf = function (el) { return parseInt(el.getAttribute('data-count'), 10) || 0; };
      var tickOf = function (el) { return el.hasAttribute('data-tick') ? 1 : 0; };
      var flash = function (el) {
        var row = el.closest('li') || el;
        row.classList.add('flash');
        setTimeout(function () { row.classList.remove('flash'); }, 700);
      };
      var countUp = function (el, to, ms) {
        var id = run, start = null;
        requestAnimationFrame(function step(ts) {
          if (id !== run) return;
          if (start === null) start = ts;
          var k = Math.min(1, (ts - start) / ms);
          el.textContent = String(Math.round(to * (1 - Math.pow(1 - k, 3))));
          if (k < 1) requestAnimationFrame(step);
        });
      };
      var go = function (i, instant) {
        var state = Math.min(i, 2);
        stage.classList.remove('s0', 's1', 's2');
        stage.classList.add('s' + state);
        steps.forEach(function (li, n) {
          li.classList.toggle('is-active', n === i);
          li.classList.toggle('is-done', n < i);
          var b = li.querySelector('.how-trigger');
          if (b) b.setAttribute('aria-pressed', n === i ? 'true' : 'false');
        });
        // Telefono: il cliente sceglie e conferma.
        var quick = instant && state === 2;
        if (state >= 1) {
          picks.forEach(function (pick, n) {
            if (quick) pick.classList.add('on');
            else later(function () { pick.classList.add('on'); }, 250 + n * 380);
          });
          if (phoneBtn) {
            var at = 250 + picks.length * 380 + 200;
            if (quick) phoneBtn.classList.add('done');
            else {
              later(function () { phoneBtn.classList.add('pressed'); }, at);
              later(function () { phoneBtn.classList.remove('pressed'); phoneBtn.classList.add('done'); }, at + 220);
            }
          }
        } else {
          picks.forEach(function (pick) { pick.classList.remove('on'); });
          if (phoneBtn) phoneBtn.classList.remove('pressed', 'done');
        }
        // Cucina: quando arriva la prenotazione i conteggi scattano di uno.
        counters.forEach(function (el) {
          var end = finalOf(el);
          if (state < 2) { el.textContent = String(end - tickOf(el)); return; }
          if (tickOf(el) && parseInt(el.textContent, 10) !== end) {
            later(function () { el.textContent = String(end); flash(el); }, instant ? 180 : 500);
          } else {
            el.textContent = String(end);
          }
        });
      };

      // I titoli dei passaggi diventano pulsanti.
      steps.forEach(function (li, i) {
        var title = li.querySelector('.how-title');
        if (title) {
          var trigger = document.createElement('button');
          trigger.type = 'button';
          trigger.className = 'how-trigger';
          trigger.setAttribute('aria-pressed', 'false');
          title.parentNode.insertBefore(trigger, title);
          trigger.appendChild(title);
        }
        li.addEventListener('click', function () { clearTimers(); go(i, true); });
      });

      if (reduced || !('IntersectionObserver' in window)) {
        go(Math.max(0, steps.length - 1), true);
      } else {
        go(0, true);
        var played = false;
        var stageIo = new IntersectionObserver(function (entries) {
          if (played || !entries[0].isIntersecting) return;
          played = true;
          stageIo.disconnect();
          clearTimers();
          go(0, true);
          counters.forEach(function (el) { countUp(el, finalOf(el) - tickOf(el), 900); });
          later(function () { go(1); }, 1400);
          later(function () { go(Math.min(2, steps.length - 1)); }, 1400 + 250 + picks.length * 380 + 1000);
        }, { threshold: 0.35 });
        stageIo.observe(stage);
      }
    }

    /* ---------- Pulsanti che seguono il puntatore ---------- */
    if (!reduced && window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      document.querySelectorAll('.hero-full .btn, .site-nav .btn, .ask-form .btn').forEach(function (b) {
        b.addEventListener('mousemove', function (ev) {
          var r = b.getBoundingClientRect();
          var x = (ev.clientX - r.left - r.width / 2) * 0.2;
          var y = (ev.clientY - r.top - r.height / 2) * 0.3;
          b.style.translate = x.toFixed(1) + 'px ' + y.toFixed(1) + 'px';
        });
        b.addEventListener('mouseleave', function () { b.style.translate = ''; });
      });
    }

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
