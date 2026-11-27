/* 8H · Le statistiche delle visite del sito pubblico (views/layout/_statistiche.php): solo sulle pagine
   che contano, mai nelle guide, nel pannello e in Amministrazione. Nessuna libreria.
   - Matomo, sul nostro server: senza cookie (disableCookies), sempre, se configurato.
   - Google Analytics 4 e Microsoft Clarity: NESSUNO script prima della scelta; si caricano solo dopo il
     consenso, per la categoria scelta (s = statistiche → GA4, a = analisi dell'uso → Clarity).
   - La scelta (anche il rifiuto) sta 6 mesi nel cookie tecnico mhw_consenso, per esempio «s1a0.20261015».
     «Preferenze cookie» riapre il banner; revocando si cancellano _ga*, _clck e _clsk e la pagina si ricarica.
   - Gli obiettivi (registrazione, prova, pagamento, contatto, sito-myhouse, vedi-esempio) vanno a Matomo come
     evento («obiettivo», nome) e, solo con il consenso alle statistiche, a GA4. */
(function () {
  'use strict';
  var nodo = document.getElementById('mhw-statistiche');
  if (!nodo) return;
  var C; try { C = JSON.parse(nodo.textContent); } catch (e) { return; }
  var pagina = location.origin + location.pathname;   // mai la query: codici e token restano qui

  /* ------------------------------------------------------------------ Matomo, senza cookie */
  var _paq = window._paq = window._paq || [];
  if (C.matomo) {
    _paq.push(['disableCookies']);
    _paq.push(['setCustomUrl', pagina]);
    _paq.push(['setReferrerUrl', document.referrer ? document.referrer.split('?')[0] : '']);
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    _paq.push(['setTrackerUrl', C.matomo.url + 'matomo.php']);
    _paq.push(['setSiteId', String(C.matomo.id)]);
    var m = document.createElement('script'); m.async = true; m.src = C.matomo.url + 'matomo.js';
    document.head.appendChild(m);
  }

  /* ------------------------------------------------------------------ il consenso */
  function leggi() {
    var v = (document.cookie.match(new RegExp('(?:^|; )' + C.cookie + '=([^;]*)')) || [])[1];
    return v && /^s[01]a[01]\.\d{8}$/.test(v) ? { s: v.charAt(1) === '1', a: v.charAt(3) === '1' } : null;
  }
  function scrivi(s, a) {
    var d = new Date(), oggi = d.getFullYear() + ('0' + (d.getMonth() + 1)).slice(-2) + ('0' + d.getDate()).slice(-2);
    document.cookie = C.cookie + '=s' + (s ? 1 : 0) + 'a' + (a ? 1 : 0) + '.' + oggi + '; Max-Age=' + C.durata + '; Path=' + C.percorso
      + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  function cancellaTerzi(prefissi) {
    var host = location.hostname.split('.'), domini = [''];
    for (var i = 0; i < host.length - 1; i++) domini.push('; domain=.' + host.slice(i).join('.'));
    document.cookie.split('; ').forEach(function (c) {
      var nome = c.split('=')[0];
      if (!prefissi.some(function (p) { return nome === p || nome.indexOf(p) === 0; })) return;
      domini.forEach(function (d) { ['/', C.percorso].forEach(function (pa) { document.cookie = nome + '=; Max-Age=0; Path=' + pa + d; }); });
    });
  }

  var gaAttivo = false;
  function avviaGa() {
    if (gaAttivo || !C.ga4) return;
    gaAttivo = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', C.ga4, { allow_google_signals: false, allow_ad_personalization_signals: false, page_location: pagina });
    var g = document.createElement('script'); g.async = true; g.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(C.ga4);
    document.head.appendChild(g);
  }
  var clAttivo = false;
  function avviaClarity() {
    if (clAttivo || !C.clarity) return;
    clAttivo = true;
    window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
    var t = document.createElement('script'); t.async = true; t.src = 'https://www.clarity.ms/tag/' + encodeURIComponent(C.clarity);
    document.head.appendChild(t);
  }
  function applica(scelta) {
    if (scelta.s) avviaGa();
    if (scelta.a) avviaClarity();
  }

  /* ------------------------------------------------------------------ il banner */
  var banner = document.getElementById('consenso');
  function mostra(scelte) {
    if (!banner) return;
    var pannello = banner.querySelector('#consenso-scelte'), salva = banner.querySelector('[data-consenso-azione="salva"]'),
        scegli = banner.querySelector('[data-consenso-azione="scegli"]'), att = leggi() || { s: false, a: false };
    banner.querySelectorAll('[data-consenso]').forEach(function (i) { i.checked = !!att[i.getAttribute('data-consenso')]; });
    pannello.hidden = !scelte; salva.hidden = !scelte; scegli.setAttribute('aria-expanded', scelte ? 'true' : 'false');
    banner.hidden = false;
  }
  function decidi(s, a) {
    var prima = leggi();
    scrivi(s, a);
    if (banner) banner.hidden = true;
    // Revoca di un consenso già dato: via i cookie di chi è stato tolto, e la pagina si ricarica per fermare gli script.
    var togli = [];
    if (prima && prima.s && !s) togli.push('_ga', '_gid', '_gat');
    if (prima && prima.a && !a) togli.push('_clck', '_clsk', 'CLID');
    if (togli.length) { cancellaTerzi(togli); location.reload(); return; }
    applica({ s: s, a: a });
  }
  if (banner) {
    banner.addEventListener('click', function (e) {
      var b = e.target.closest('[data-consenso-azione]'); if (!b) return;
      var az = b.getAttribute('data-consenso-azione');
      if (az === 'accetta') decidi(!!C.ga4, !!C.clarity);
      else if (az === 'rifiuta' || az === 'chiudi') decidi(false, false);
      else if (az === 'scegli') mostra(banner.querySelector('#consenso-scelte').hidden);
      else if (az === 'salva') {
        var v = { s: false, a: false };
        banner.querySelectorAll('[data-consenso]').forEach(function (i) { v[i.getAttribute('data-consenso')] = i.checked; });
        decidi(v.s, v.a);
      }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !banner.hidden && banner.contains(document.activeElement)) decidi(false, false); });
  }
  document.addEventListener('click', function (e) {
    var p = e.target.closest && e.target.closest('[data-consenso-apri]');
    if (!p) return;
    e.preventDefault(); mostra(true);
    var primo = banner && banner.querySelector('[data-consenso-azione="accetta"]'); if (primo) primo.focus();
  });
  var scelta = leggi();
  if (scelta) applica(scelta);
  else if (banner) mostra(false);   // nessuna scelta ancora: il banner, e niente di Google o Microsoft

  /* ------------------------------------------------------------------ gli obiettivi */
  function evento(nome, dettaglio) {
    if (C.matomo) _paq.push(dettaglio ? ['trackEvent', 'obiettivo', nome, dettaglio] : ['trackEvent', 'obiettivo', nome]);
    if (gaAttivo && window.gtag) window.gtag('event', nome, dettaglio ? { dettaglio: dettaglio } : {});
  }
  window.mhwStatistiche = { evento: evento };
  (C.eventi || []).forEach(function (ev) { evento(ev[0], ev[1]); });   // registrazione, pagamento: arrivati dal server
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target : null; if (!t) return;
    if (t.closest('[data-ha="si"]')) { evento('sito-myhouse'); return; }   // «Sì, ce l'ho» in «Anche il tuo sito»
    var a = t.closest('a[href]'); if (!a) return;
    var h = a.getAttribute('href') || '';
    if (/^https:\/\/wa\.me\//.test(h)) evento('contatto', 'whatsapp');
    else if (/^mailto:[^?]/.test(h)) evento('contatto', 'email');
    else if (/^tel:/.test(h)) evento('contatto', 'telefono');
    else if (/\/g\/[^\/?#]+/.test(h) && a.host === location.host) evento('vedi-esempio');   // la guida di esempio (la vetrina)
  }, true);
})();
