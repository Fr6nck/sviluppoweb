/* Arco del Vento — il solo JavaScript del sito.
 *
 * Aggiunge, non regge: senza questo file il sito funziona tutto. Le date sono
 * campi nativi, le domande sono <details>, le entrate allo scorrimento sono
 * CSS, ogni passo della prenotazione è una pagina con il suo indirizzo, le
 * fotografie mostrano la prima e le camere scorrono con il dito.
 *
 * Qui dentro: il menu su schermo stretto, la testata che si stringe
 * scorrendo, le fotografie che si alternano (con il pulsante per fermarle),
 * le frecce del carosello delle camere, la pillola in fondo allo schermo, il
 * passaggio automatico alla pagina di pagamento di SumUp.
 *
 * Nessuna dipendenza, nessuna richiesta di rete.
 */
(function () {
  'use strict';

  var menoMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------- menu */
  (function () {
    var pannello = document.querySelector('[data-menu]');
    var apri     = document.querySelector('[data-menu-apri]');
    var chiudi   = document.querySelector('[data-menu-chiudi]');

    if (!pannello || !apri) {
      return;
    }

    /* Chiudendo, il fuoco torna dov'era: chi naviga da tastiera non deve
       ripartire dall'inizio della pagina. */
    var fuocoPrecedente = null;

    function focalizzabili() {
      return Array.prototype.filter.call(
        pannello.querySelectorAll('a[href], button:not([disabled])'),
        function (el) { return el.offsetParent !== null; }
      );
    }

    function mostra() {
      fuocoPrecedente = document.activeElement;
      pannello.hidden = false;
      document.body.classList.add('adv-menu-aperto');
      apri.setAttribute('aria-expanded', 'true');
      var primi = focalizzabili();
      if (primi.length) {
        primi[0].focus();
      }
    }

    function nascondi() {
      pannello.hidden = true;
      document.body.classList.remove('adv-menu-aperto');
      apri.setAttribute('aria-expanded', 'false');
      if (fuocoPrecedente && typeof fuocoPrecedente.focus === 'function') {
        fuocoPrecedente.focus();
      }
    }

    apri.addEventListener('click', mostra);
    if (chiudi) {
      chiudi.addEventListener('click', nascondi);
    }

    document.addEventListener('keydown', function (evento) {
      if (pannello.hidden) {
        return;
      }
      if (evento.key === 'Escape') {
        nascondi();
        return;
      }
      /* Il fuoco resta dentro il pannello finché è aperto: sotto c'è una
         pagina coperta, e tabularci dentro vuol dire perdere il cursore. */
      if (evento.key !== 'Tab') {
        return;
      }
      var elementi = focalizzabili();
      if (!elementi.length) {
        return;
      }
      var primo  = elementi[0];
      var ultimo = elementi[elementi.length - 1];
      if (evento.shiftKey && document.activeElement === primo) {
        evento.preventDefault();
        ultimo.focus();
      } else if (!evento.shiftKey && document.activeElement === ultimo) {
        evento.preventDefault();
        primo.focus();
      }
    });

    /* Tornando oltre i 1180px con il pannello aperto, le voci sono di nuovo
       nella testata: il pannello va chiuso, altrimenti copre la pagina. */
    if (window.matchMedia) {
      var largo = window.matchMedia('(min-width: 1181px)');
      var reagisci = function (evento) {
        if (evento.matches && !pannello.hidden) {
          nascondi();
        }
      };
      if (largo.addEventListener) {
        largo.addEventListener('change', reagisci);
      } else if (largo.addListener) {
        largo.addListener(reagisci);
      }
    }
  })();

  /* ---------------------------------------------- testata e avanzamento */
  (function () {
    var testata = document.querySelector('[data-testata]');
    if (!testata) {
      return;
    }
    var riga = testata.querySelector('[data-progresso]');
    /* Dove il CSS sa legare la riga allo scorrimento lo fa lui; qui si
       calcola solo per gli altri browser. */
    var calcolaRiga = riga && !(window.CSS && CSS.supports && CSS.supports('animation-timeline: scroll()'));
    var inAttesa = false;

    function aggiorna() {
      inAttesa = false;
      var y = window.scrollY || document.documentElement.scrollTop || 0;
      testata.classList.toggle('adv-testata--compatta', y > 70);
      if (calcolaRiga) {
        var massimo = document.documentElement.scrollHeight - window.innerHeight;
        var quota = massimo > 20 ? Math.min(1, Math.max(0, y / massimo)) : 0;
        riga.style.setProperty('--avanzamento', quota.toFixed(4));
      }
    }

    window.addEventListener('scroll', function () {
      if (!inAttesa) {
        inAttesa = true;
        window.requestAnimationFrame(aggiorna);
      }
    }, { passive: true });
    aggiorna();
  })();

  /* ------------------------------------------------------ le fotografie */
  (function () {
    var giostra = document.querySelector('[data-diapositive]');
    if (!giostra) {
      return;
    }
    var foto = Array.prototype.slice.call(giostra.querySelectorAll('[data-diapositiva]'));
    if (foto.length < 2) {
      return;
    }
    var didascalia = giostra.querySelector('[data-diapositive-didascalia]');
    var numero     = giostra.querySelector('[data-diapositive-numero]');
    var pausa      = giostra.querySelector('[data-diapositive-pausa]');
    var corrente   = 0;
    var timer      = null;
    /* Chi chiede meno movimento le trova ferme, e le sfoglia con le frecce. */
    var ferma      = menoMovimento;

    giostra.classList.add('adv-diapositive--viva');

    /* Le fotografie dopo la prima arrivano senza indirizzo (data-src): si
       caricano una alla volta, appena prima di comparire. */
    function carica(figura) {
      if (!figura) {
        return;
      }
      Array.prototype.forEach.call(figura.querySelectorAll('[data-srcset], [data-src]'), function (el) {
        if (el.hasAttribute('data-srcset')) {
          el.setAttribute('srcset', el.getAttribute('data-srcset'));
          el.removeAttribute('data-srcset');
        }
        if (el.hasAttribute('data-src')) {
          el.setAttribute('src', el.getAttribute('data-src'));
          el.removeAttribute('data-src');
        }
      });
    }

    function mostra(indice, daUtente) {
      corrente = (indice + foto.length) % foto.length;
      carica(foto[corrente]);
      carica(foto[(corrente + 1) % foto.length]);
      foto.forEach(function (f, i) {
        var attiva = i === corrente;
        f.classList.toggle('is-attiva', attiva);
        f.setAttribute('aria-hidden', attiva ? 'false' : 'true');
      });
      if (didascalia) {
        didascalia.textContent = foto[corrente].getAttribute('data-didascalia') || '';
        /* La didascalia si annuncia solo quando la foto la cambia chi legge:
           ogni sei secondi sarebbe un'interruzione continua. */
        didascalia.setAttribute('aria-live', daUtente ? 'polite' : 'off');
      }
      if (numero) {
        numero.textContent = (corrente + 1 < 10 ? '0' : '') + (corrente + 1);
      }
    }

    function aggiornaPausa() {
      if (!pausa) {
        return;
      }
      pausa.setAttribute('aria-label', pausa.getAttribute(ferma ? 'data-etichetta-avvia' : 'data-etichetta-pausa'));
      var iconaPausa = pausa.querySelector('[data-icona-pausa]');
      var iconaAvvia = pausa.querySelector('[data-icona-avvia]');
      if (iconaPausa && iconaAvvia) {
        iconaPausa.hidden = ferma;
        iconaAvvia.hidden = !ferma;
      }
    }

    function riparti() {
      window.clearInterval(timer);
      timer = null;
      if (!ferma) {
        timer = window.setInterval(function () {
          if (!document.hidden) {
            mostra(corrente + 1, false);
          }
        }, 6000);
      }
    }

    var prec = giostra.querySelector('[data-diapositive-prec]');
    var succ = giostra.querySelector('[data-diapositive-succ]');
    if (prec) {
      prec.addEventListener('click', function () { mostra(corrente - 1, true); riparti(); });
    }
    if (succ) {
      succ.addEventListener('click', function () { mostra(corrente + 1, true); riparti(); });
    }
    if (pausa) {
      pausa.addEventListener('click', function () {
        ferma = !ferma;
        aggiornaPausa();
        riparti();
      });
    }

    aggiornaPausa();
    riparti();

    // La seconda si prepara quando la pagina ha finito di caricare il resto.
    if (document.readyState === 'complete') {
      carica(foto[1]);
    } else {
      window.addEventListener('load', function () { carica(foto[1]); });
    }
  })();

  /* -------------------------------------------------- il carosello camere */
  (function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-carosello]'), function (carosello) {
      var traccia = carosello.querySelector('[data-carosello-traccia]');
      var prec    = carosello.querySelector('[data-carosello-prec]');
      var succ    = carosello.querySelector('[data-carosello-succ]');
      var barra   = carosello.querySelector('[data-carosello-barra]');
      if (!traccia) {
        return;
      }

      function passo() {
        var prima = traccia.firstElementChild;
        if (!prima) {
          return traccia.clientWidth;
        }
        var stile = window.getComputedStyle(traccia);
        var spazio = parseFloat(stile.columnGap || stile.gap) || 0;
        return prima.getBoundingClientRect().width + spazio;
      }

      function aggiorna() {
        var massimo = traccia.scrollWidth - traccia.clientWidth;
        var x = traccia.scrollLeft;
        if (prec) {
          prec.disabled = x <= 2;
        }
        if (succ) {
          succ.disabled = x >= massimo - 2;
        }
        if (barra) {
          /* La riga parte da una scheda su tutte e arriva piena all'ultima. */
          var visibili = Math.max(1, Math.round(traccia.clientWidth / passo()));
          var totale = traccia.children.length;
          var quota = massimo > 0 ? (visibili + (totale - visibili) * (x / massimo)) / totale : 1;
          barra.style.setProperty('--avanzamento', Math.min(1, quota).toFixed(4));
        }
      }

      if (prec) {
        prec.addEventListener('click', function () {
          traccia.scrollBy({ left: -passo(), behavior: menoMovimento ? 'auto' : 'smooth' });
        });
      }
      if (succ) {
        succ.addEventListener('click', function () {
          traccia.scrollBy({ left: passo(), behavior: menoMovimento ? 'auto' : 'smooth' });
        });
      }

      var inAttesa = false;
      traccia.addEventListener('scroll', function () {
        if (!inAttesa) {
          inAttesa = true;
          window.requestAnimationFrame(function () { inAttesa = false; aggiorna(); });
        }
      }, { passive: true });
      window.addEventListener('resize', aggiorna);
      aggiorna();
    });
  })();

  /* ------------------------------------------- la pillola in fondo allo schermo */
  (function () {
    var pillola = document.querySelector('[data-sticky]');
    if (!pillola) {
      return;
    }
    var barre = document.querySelectorAll('[data-barra]');
    if (!barre.length || !('IntersectionObserver' in window)) {
      pillola.classList.add('adv-sticky--visibile');
      return;
    }
    /* Compare quando la barra di prenotazione della pagina non si vede più:
       due «Verifica disponibilità» uno sopra l'altro sono uno di troppo. */
    var inVista = new Set();
    var osservatore = new IntersectionObserver(function (voci) {
      voci.forEach(function (voce) {
        if (voce.isIntersecting) {
          inVista.add(voce.target);
        } else {
          inVista.delete(voce.target);
        }
      });
      pillola.classList.toggle('adv-sticky--visibile', inVista.size === 0);
    });
    Array.prototype.forEach.call(barre, function (b) { osservatore.observe(b); });
  })();

  /* ------------------------------------------------- verso la pagina di SumUp
     Il passaggio al pagamento è una pagina del sito con un pulsante: la
     politica di sicurezza non lascia che il modulo porti direttamente fuori.
     Qui il pulsante si preme da solo, dopo un attimo per leggere l'importo.
     Solo verso un indirizzo https: mai altrove. */
  (function () {
    var vai = document.querySelector('[data-vai-al-pagamento]');
    if (!vai || !/^https:\/\//.test(vai.href)) return;
    window.setTimeout(function () { window.location.assign(vai.href); }, 1500);
  })();
})();
