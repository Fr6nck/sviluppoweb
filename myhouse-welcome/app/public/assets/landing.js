/* La landing: animazioni allo scorrimento e interazioni.

   Regole:
   - senza JavaScript (o senza IntersectionObserver) la pagina è completa e
     ferma: niente è nascosto in partenza, perché la classe che nasconde
     (html.anima) la mette solo questo script;
   - con «riduci movimento» nel sistema non si anima niente: restano solo le
     schede di «Come funziona» e le FAQ, senza transizioni;
   - mai un ascoltatore di scroll per le comparse: IntersectionObserver.
     L'unico ascoltatore di scroll (barra di avanzamento, header) è passivo e
     passa da requestAnimationFrame. */
(function () {
  'use strict';
  var doc = document.documentElement;
  var fermo = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var conIO = 'IntersectionObserver' in window;
  var anima = conIO && !fermo;
  if (anima) doc.classList.add('anima', 'anima-pronta');
  else doc.classList.remove('anima');

  /* ------------------------------------------------------------ comparse
     Ogni voce: [selettore, gruppo?]. Con gruppo i figli entrano uno dopo
     l'altro (ritardo in --i). */
  var COMPARSE = [
    ['.hero2__testo', true],
    ['.device-link', false],
    ['.scene', true],
    ['.prodotto__testa', true],
    ['.features8', true],
    ['.tempo__intro', true],
    ['.vantaggi', true],
    ['.frase__sotto', false],
    ['.guadagno__testo', true],
    ['.congedo-mock', false],
    ['.come__testa', true],
    ['.passi__lista', true],
    ['.passi__schermate', false],
    ['.qrband > .stack', true],
    ['.qrband__foglio', false],
    ['.voci .grid', true],
    ['.faq__testa', true],
    ['.faq__lista', true],
    ['#piani .spread', true],
    ['.piani', true],
    ['.chiusura__testo', true],
    ['.chi', false]
  ];

  function osserva(el, quando, margine) {
    if (!conIO) { quando(); return; }
    var io = new IntersectionObserver(function (voci) {
      voci.forEach(function (v) { if (v.isIntersecting) { io.disconnect(); quando(); } });
    }, { rootMargin: margine || '0px 0px -12% 0px', threshold: 0.01 });
    io.observe(el);
  }

  if (anima) {
    COMPARSE.forEach(function (c) {
      document.querySelectorAll(c[0]).forEach(function (el) {
        var bersagli = c[1] ? Array.prototype.slice.call(el.children) : [el];
        bersagli.forEach(function (b, i) {
          if (b.tagName === 'SCRIPT') return;
          b.classList.add('comparsa');
          b.style.setProperty('--i', Math.min(i, 8));
        });
        osserva(el, function () {
          bersagli.forEach(function (b) {
            b.classList.add('comparsa--vista');
            // Finita l'entrata, via il ritardo: i passaggi del puntatore devono rispondere subito.
            setTimeout(function () { b.classList.remove('comparsa'); b.style.removeProperty('--i'); }, 1700);
          });
        });
      });
    });
  }

  /* ------------------------------------------- il telefono segue il puntatore */
  var link = document.querySelector('.device-link');
  var tel = link && link.querySelector('.device');
  if (tel && !fermo && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    var hero = document.querySelector('.hero2'), rif = 0;
    hero.addEventListener('pointermove', function (e) {
      if (rif) return;
      rif = requestAnimationFrame(function () {
        rif = 0;
        var r = tel.getBoundingClientRect();
        var x = (e.clientX - (r.left + r.width / 2)) / window.innerWidth;
        var y = (e.clientY - (r.top + r.height / 2)) / window.innerHeight;
        tel.style.setProperty('--ry', (x * 10).toFixed(2) + 'deg');
        tel.style.setProperty('--rx', (-y * 8).toFixed(2) + 'deg');
      });
    });
    hero.addEventListener('pointerleave', function () { tel.style.setProperty('--ry', '0deg'); tel.style.setProperty('--rx', '0deg'); });
  }

  /* --------------------------------------- le domande arrivano, la guida risponde */
  var chat = document.querySelector('.tempo__msgs');
  if (chat && anima) {
    var msgs = Array.prototype.slice.call(chat.querySelectorAll('.msg'));
    var risposta = chat.querySelector('.risposta');
    chat.classList.add('chat--attesa');
    osserva(chat, function () {
      msgs.forEach(function (m, i) { setTimeout(function () { m.classList.add('msg--arrivato'); }, 250 + i * 650); });
      if (risposta) {
        var dopo = 250 + msgs.length * 650;
        setTimeout(function () { risposta.classList.add('risposta--scrive'); }, dopo);
        setTimeout(function () { risposta.classList.remove('risposta--scrive'); risposta.classList.add('risposta--arrivata'); }, dopo + 1100);
      }
    }, '0px 0px -25% 0px');
  }

  /* ---------------------------------------- la frase si colora parola per parola */
  var frase = document.querySelector('.frase__testo');
  if (frase && anima) {
    var parole = [];
    (function dividi(nodo) {
      Array.prototype.slice.call(nodo.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var pezzi = n.nodeValue.split(/(\s+)/), f = document.createDocumentFragment();
          pezzi.forEach(function (p) {
            if (p === '') return;
            if (/^\s+$/.test(p)) { f.appendChild(document.createTextNode(p)); return; }
            var s = document.createElement('span'); s.className = 'parola'; s.textContent = p;
            f.appendChild(s); parole.push(s);
          });
          n.parentNode.replaceChild(f, n);
        } else if (n.nodeType === 1 && n.tagName !== 'BR') dividi(n);
      });
    })(frase);
    var ioP = new IntersectionObserver(function (voci) {
      voci.forEach(function (v) { if (v.isIntersecting) { v.target.classList.add('parola--accesa'); ioP.unobserve(v.target); } });
    }, { rootMargin: '0px 0px -38% 0px' });
    parole.forEach(function (p, i) { p.style.setProperty('--i', i % 6); ioP.observe(p); });
  }

  /* -------------------------------------------- Come funziona: i passi a schede */
  var box = document.querySelector('[data-passi]');
  if (box) (function () {
    var passi = Array.prototype.slice.call(box.querySelectorAll('.passo'));
    var schede = passi.map(function (p) { return document.getElementById(p.getAttribute('href').slice(1)); });
    if (schede.some(function (s) { return !s; })) return;
    var lista = box.querySelector('.passi__lista');
    lista.setAttribute('role', 'tablist');
    lista.setAttribute('aria-label', 'I passi');
    passi.forEach(function (p, i) {
      p.parentNode.setAttribute('role', 'presentation');
      p.setAttribute('role', 'tab');
      p.setAttribute('aria-controls', schede[i].id);
      schede[i].setAttribute('role', 'tabpanel');
      schede[i].setAttribute('aria-labelledby', p.id);
      schede[i].setAttribute('tabindex', '0');
    });
    var attuale = 0;
    function scegli(n, fuoco) {
      attuale = n;
      passi.forEach(function (p, i) {
        var si = i === n;
        p.setAttribute('aria-selected', si ? 'true' : 'false');
        p.setAttribute('tabindex', si ? '0' : '-1');
        schede[i].hidden = !si;
      });
      if (fuoco) passi[n].focus();
    }

    /* Avanzano da soli UN giro, solo quando la sezione si vede, e si fermano
       per sempre al primo clic, tasto o passaggio del puntatore. */
    var giro = null, finito = fermo;
    function ferma() {
      finito = true; box.classList.remove('passi--giro');
      if (giro) { clearTimeout(giro); giro = null; }
    }
    function prossimo() {
      if (finito) return;
      if (attuale === passi.length - 1) { ferma(); return; }
      box.classList.remove('passi--giro'); void box.offsetWidth; box.classList.add('passi--giro');
      giro = setTimeout(function () { scegli(attuale + 1, false); prossimo(); }, 5200);
    }
    if (conIO && !fermo) {
      var ioG = new IntersectionObserver(function (voci) {
        voci.forEach(function (v) {
          if (finito) { ioG.disconnect(); return; }
          if (v.isIntersecting && !giro) prossimo();
          else if (!v.isIntersecting && giro) { clearTimeout(giro); giro = null; box.classList.remove('passi--giro'); }
        });
      }, { threshold: 0.5 });
      ioG.observe(box);
    }
    box.addEventListener('pointerenter', ferma);
    box.addEventListener('focusin', ferma);

    passi.forEach(function (p, i) {
      p.addEventListener('click', function (e) { e.preventDefault(); ferma(); scegli(i, false); });
      p.addEventListener('keydown', function (e) {
        var n = { ArrowDown: i + 1, ArrowRight: i + 1, ArrowUp: i - 1, ArrowLeft: i - 1, Home: 0, End: passi.length - 1 }[e.key];
        if (n === undefined) return;
        e.preventDefault(); ferma();
        scegli((n + passi.length) % passi.length, true);
      });
    });
    scegli(0, false);
  })();

  /* ------------------------------------- FAQ: si aprono e si chiudono morbide */
  if (!fermo && 'animate' in HTMLElement.prototype) {
    document.querySelectorAll('.faq__voce').forEach(function (d) {
      var s = d.querySelector('summary'), corpo = d.querySelector('p');
      if (!s || !corpo) return;
      var inCorso = null;
      s.addEventListener('click', function (e) {
        e.preventDefault();
        if (inCorso) inCorso.cancel();
        var aprendo = !d.open;
        if (aprendo) d.open = true;
        var alto = corpo.offsetHeight;
        inCorso = corpo.animate(
          aprendo ? [{ height: '0px', opacity: 0 }, { height: alto + 'px', opacity: 1 }]
                  : [{ height: alto + 'px', opacity: 1 }, { height: '0px', opacity: 0 }],
          { duration: 380, easing: 'cubic-bezier(.32,.72,0,1)' });
        corpo.style.overflow = 'hidden';
        inCorso.onfinish = function () { inCorso = null; corpo.style.overflow = ''; if (!aprendo) d.open = false; };
      });
    });
  }

  /* ------------------------- header: ombra dopo lo scorrimento, barra di lettura,
     e nel menu la voce della sezione che si sta guardando */
  var testa = document.querySelector('.topbar--sito');
  var barra = document.createElement('div');
  barra.className = 'lettura'; barra.setAttribute('aria-hidden', 'true');
  document.body.appendChild(barra);
  var inCoda = false;
  function aggiornaScorrimento() {
    inCoda = false;
    var y = window.scrollY, tot = doc.scrollHeight - window.innerHeight;
    if (testa) testa.classList.toggle('topbar--staccata', y > 8);
    barra.style.transform = 'scaleX(' + (tot > 0 ? Math.min(1, y / tot) : 0).toFixed(4) + ')';
  }
  window.addEventListener('scroll', function () { if (!inCoda) { inCoda = true; requestAnimationFrame(aggiornaScorrimento); } }, { passive: true });
  aggiornaScorrimento();

  if (conIO) {
    var voci = Array.prototype.slice.call(document.querySelectorAll('.topbar__nav a[href*="#"]'));
    var perId = {};
    voci.forEach(function (a) { perId[a.getAttribute('href').split('#')[1]] = a; });
    var ioN = new IntersectionObserver(function (vv) {
      vv.forEach(function (v) {
        var a = perId[v.target.id]; if (!a) return;
        if (v.isIntersecting) {
          voci.forEach(function (x) { x.classList.remove('attiva'); x.removeAttribute('aria-current'); });
          a.classList.add('attiva'); a.setAttribute('aria-current', 'location');
        } else if (a.classList.contains('attiva')) { a.classList.remove('attiva'); a.removeAttribute('aria-current'); }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(perId).forEach(function (id) { var s = document.getElementById(id); if (s) ioN.observe(s); });
  }
})();
