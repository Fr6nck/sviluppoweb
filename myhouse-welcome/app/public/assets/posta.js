/* I bottoni «Email» (link mailto:). Su un computer senza un programma di posta impostato il clic
   non apre niente e sembra rotto: il link resta un vero mailto: (sul telefono e con Outlook o Mail
   si apre come sempre), ma in più l'indirizzo si copia negli appunti e compare un riquadro con
   «Scrivi con Gmail», «Scrivi con Outlook» e l'indirizzo da copiare a mano. Nessuna libreria. */
(function () {
  'use strict';
  var en = (document.documentElement.lang || '').slice(0, 2) === 'en';
  var T = en
    ? { copiato: 'Address copied', se: 'If your email app did not open, write to', gmail: 'Write with Gmail', outlook: 'Write with Outlook', chiudi: 'Close' }
    : { copiato: 'Indirizzo copiato', se: 'Se non si è aperto il programma di posta, scrivi a', gmail: 'Scrivi con Gmail', outlook: 'Scrivi con Outlook', chiudi: 'Chiudi' };
  var box = null, timer = null;

  function parti(href) {
    var resto = href.slice(7), q = resto.indexOf('?');
    var indirizzo = decodeURIComponent(q < 0 ? resto : resto.slice(0, q));
    var p = new URLSearchParams(q < 0 ? '' : resto.slice(q + 1));
    return { a: indirizzo, oggetto: p.get('subject') || '', corpo: p.get('body') || '' };
  }
  // Copia negli appunti; se il browser lo permette, il riquadro lo dice («Indirizzo copiato.»).
  function copia(testo, fatto) {
    try { if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(testo).then(fatto, function () { /* niente: resta l'indirizzo da copiare */ }); }
    catch (e) { /* niente */ }
  }
  function chiudi() { if (box) { box.remove(); box = null; } clearTimeout(timer); }
  function link(testo, url) {
    var a = document.createElement('a'); a.className = 'btn btn--ghost btn--sm'; a.href = url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = testo; return a;
  }
  function mostra(m) {
    chiudi();
    var e = encodeURIComponent, extra = function (nome, v) { return v ? '&' + nome + '=' + e(v) : ''; };
    box = document.createElement('div'); box.className = 'posta-aiuto'; box.setAttribute('role', 'status');
    var riga = document.createElement('p');
    var inizio = document.createElement('span'); inizio.textContent = T.se + ' '; riga.appendChild(inizio);
    var b = document.createElement('b'); b.textContent = m.a; riga.appendChild(b);
    var x = document.createElement('button'); x.type = 'button'; x.className = 'posta-aiuto__x'; x.setAttribute('aria-label', T.chiudi); x.textContent = '×';
    x.addEventListener('click', chiudi);
    var azioni = document.createElement('div'); azioni.className = 'posta-aiuto__azioni';
    azioni.appendChild(link(T.gmail, 'https://mail.google.com/mail/?view=cm&fs=1&to=' + e(m.a) + extra('su', m.oggetto) + extra('body', m.corpo)));
    azioni.appendChild(link(T.outlook, 'https://outlook.live.com/mail/0/deeplink/compose?to=' + e(m.a) + extra('subject', m.oggetto) + extra('body', m.corpo)));
    box.appendChild(x); box.appendChild(riga); box.appendChild(azioni);
    document.body.appendChild(box);
    box.addEventListener('pointerenter', function () { clearTimeout(timer); });
    timer = setTimeout(chiudi, 15000);
    var questo = box;
    copia(m.a, function () { if (box === questo) inizio.textContent = T.copiato + '. ' + T.se + ' '; });
  }

  // Dopo gli altri gestori (il percorso «Anche il tuo sito» riscrive l'href al clic): si legge l'href finale.
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a[href^="mailto:"]');
    if (!a || ev.defaultPrevented || ev.button !== 0) return;
    var m = parti(a.getAttribute('href'));
    if (m.a === '' || m.a.indexOf('@') < 0) return;   // «mailto:?subject=…» (inviti a un amico): l'indirizzo lo sceglie chi scrive
    setTimeout(function () { mostra(m); }, 300);
  });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && box) chiudi(); });
})();
