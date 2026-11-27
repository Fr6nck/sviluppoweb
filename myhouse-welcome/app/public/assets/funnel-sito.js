/* «Anche il tuo sito» a domande (8A, views/pub/_funnel_sito.php). Tre passi senza librerie:
   1 Hai già un sito? · 2 cosa migliorare / cosa facciamo · 3 come sentirci.
   Le risposte finiscono nel link verso blackout.in/myhouse, nel messaggio di WhatsApp e nell'email;
   al clic su uno dei tre bottoni un segnale (sendBeacon) le salva in Amministrazione → Prospetti.
   Più componenti nella stessa pagina vanno ognuno per conto suo. */
(function () {
  'use strict';
  function avvia(f) {
    if (f.getAttribute('data-pronto')) return;
    f.setAttribute('data-pronto', '1');
    f.classList.remove('funnel--nojs');
    var stato = { ha: '', bisogni: [], altro: '' };
    var passi = f.querySelectorAll('[data-passo]');
    var d = function (k) { return f.getAttribute('data-' + k) || ''; };

    function mostra(nome) {
      passi.forEach(function (p) { p.hidden = p.getAttribute('data-passo') !== nome; });
      var p = f.querySelector('[data-passo="' + nome + '"]');
      var h = p && p.querySelector('.funnel__domanda');
      if (h && f.getAttribute('data-toccato')) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
      if (nome === '3') prepara();
    }

    function bisogniTesto() {
      var etichette = [];
      f.querySelectorAll('[data-bisogno]:checked').forEach(function (c) {
        if (c.value === 'altro') { if (stato.altro) etichette.push(stato.altro); return; }
        var t = c.parentNode.textContent.trim();
        etichette.push(t.charAt(0).toLowerCase() + t.slice(1));
      });
      return etichette.join(', ');
    }

    function messaggio() {
      var centro;
      if (stato.ha === 'si') { var b = bisogniTesto(); centro = d('msg-si').split('{bisogni}').join(b ? ': ' + b : ''); }
      else centro = d('msg-no');
      return [d('msg-inizio'), centro, d('msg-fine')].filter(Boolean).join(' ');
    }

    function prepara() {
      stato.bisogni = []; f.querySelectorAll('[data-bisogno]:checked').forEach(function (c) { stato.bisogni.push(c.value); });
      var altro = f.querySelector('[data-altro] input'); stato.altro = altro ? altro.value.trim().slice(0, 200) : '';
      var m = messaggio();
      f.querySelector('[data-messaggio]').textContent = m;
      var scopri = f.querySelector('[data-canale="scopri"]');
      if (scopri) scopri.href = d('scopri') + '&ha=' + stato.ha + (stato.ha === 'si' && stato.bisogni.length ? '&bisogni=' + encodeURIComponent(stato.bisogni.join(',')) : '');
      var wa = f.querySelector('[data-canale="whatsapp"]');
      if (wa) wa.href = 'https://wa.me/' + d('wa') + '?text=' + encodeURIComponent(m);
      var em = f.querySelector('[data-canale="email"]');
      if (em) em.href = 'mailto:' + d('email') + '?subject=' + encodeURIComponent(d('oggetto')) + '&body=' + encodeURIComponent(m);
    }

    f.addEventListener('click', function (e) {
      var scelta = e.target.closest('[data-ha]');
      if (scelta) { f.setAttribute('data-toccato', '1'); stato.ha = scelta.getAttribute('data-ha'); mostra(stato.ha); return; }
      var vai = e.target.closest('[data-vai]');
      if (vai) {
        f.setAttribute('data-toccato', '1');
        var n = vai.getAttribute('data-vai');
        mostra(n === '2' ? (stato.ha || 'si') : n);
        return;
      }
      var canale = e.target.closest('[data-canale]');
      if (canale) {
        prepara();
        try {
          var fd = new FormData();
          fd.append('_csrf', d('csrf')); fd.append('ha_sito', stato.ha); fd.append('bisogni', stato.bisogni.join(','));
          fd.append('altro', stato.altro); fd.append('canale', canale.getAttribute('data-canale')); fd.append('da', d('da'));
          if (navigator.sendBeacon) navigator.sendBeacon(d('url'), fd);
        } catch (err) { /* il contatto parte comunque */ }
      }
    });
    f.addEventListener('change', function (e) {
      if (e.target.matches('[data-bisogno][value="altro"]')) {
        var a = f.querySelector('[data-altro]'); a.hidden = !e.target.checked;
        if (e.target.checked) a.querySelector('input').focus();
      }
    });

    var iniziale = d('passo-iniziale');
    if (iniziale) { stato.ha = iniziale; mostra(iniziale); } else mostra('1');
    f.funnelVai = function (passo) { f.setAttribute('data-toccato', '1'); stato.ha = passo; mostra(passo); };   // per i bottoni del pannello (8B)
  }
  function tutti() { document.querySelectorAll('[data-funnel]').forEach(avvia); }
  /* 8B · nel pannello il percorso sta in un <dialog>: i bottoni lo aprono già al passo giusto. */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-funnel-apri]');
    if (b) {
      var d = document.getElementById(b.getAttribute('data-funnel-dialogo'));
      if (!d) return;
      e.preventDefault();
      var f = d.querySelector('[data-funnel]'); if (f && !f.funnelVai) avvia(f);
      if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
      if (f && f.funnelVai) f.funnelVai(b.getAttribute('data-funnel-apri'));
      return;
    }
    var c = e.target.closest('[data-funnel-chiudi]');
    if (c) { var dd = c.closest('dialog'); if (dd) { if (dd.close) dd.close(); else dd.removeAttribute('open'); } }
  });
  document.addEventListener('click', function (e) {   // un clic fuori dalla finestra la chiude
    if (e.target.matches && e.target.matches('dialog.funnel-dialogo')) e.target.close();
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tutti); else tutti();
  window.mhwFunnel = { avvia: tutti };
})();
