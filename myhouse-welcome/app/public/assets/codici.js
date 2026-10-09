/* Codici di accesso (6M): mentre l'host scrive, sotto il campo compare un avviso arancione se
   sembra esserci il codice di una porta, di una cassetta, di una cassaforte o di un allarme.
   È un avviso, non un errore: si salva comunque. Stessa regola di Sicurezza::codiceAccesso()
   (src/Sicurezza.php): se cambi una lista, cambiala anche lì. */
(function () {
  'use strict';
  var ACCESSO = ['codice', 'code', 'codigo', 'pin', 'combinazione', 'combination', 'combinaison', 'combinacion',
                 'digicode', 'zahlencode', 'kombination'];
  var LUOGO = ['porta', 'portone', 'cancello', 'ingresso', 'garage', 'box auto', 'cassetta', 'cassetta di sicurezza',
               'keybox', 'key box', 'lockbox', 'cassaforte', 'safe', 'coffre', 'coffre-fort', 'caja fuerte', 'tresor',
               'allarme', 'alarm', 'alarme', 'alarma', 'alarmanlage', 'lucchetto', 'serratura', 'door', 'gate', 'porte',
               'portail', 'puerta', 'tur', 'tor'];
  var FINESTRA = 60;
  var TESTO = 'Qui sembra esserci un codice di accesso. Te lo sconsigliamo: chi ha il link della guida può leggerlo. '
            + 'Comunicalo all\u2019ospite di persona o in privato, poco prima dell\u2019arrivo.';
  // Wi-Fi, CIN, telefoni, CAP, prezzi, indirizzi web, codice sconto per chi torna: non si controllano.
  var ESCLUSI = /(^|[\[_.])(ssid|password|phone|whatsapp|cin|postal_code|cap|price|prezzo|cost|costo|amount|tax_amount|direct_code|direct_url|maps_url|website|booking_url|url|email|_csrf)(\]|$)|_(url|phone|price|cost|prezzo|costo)(\]|$)/i;

  function normalizza(t) {
    t = t.toLowerCase().replace(/ß/g, 'ss');
    return t.normalize ? t.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : t;
  }
  function posizioni(t, parole) {
    var pos = [];
    parole.forEach(function (p) {
      var re = new RegExp('(^|[^a-z0-9])(' + p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/ /g, '[\\s\\-]+') + ')(?![a-z0-9])', 'g'), m;
      while ((m = re.exec(t))) pos.push(m.index + m[1].length);
    });
    return pos;
  }
  function codiceAccesso(testo) {
    var t = normalizza(testo || '');
    if (!/\d/.test(t)) return false;
    var a = posizioni(t, ACCESSO); if (!a.length) return false;
    var l = posizioni(t, LUOGO); if (!l.length) return false;
    var re = /(^|[^\d])(\d(?:[ .\-]?\d){2,7})(?!\d)/g, m, c = [];
    while ((m = re.exec(t))) c.push(m.index + m[1].length);
    for (var i = 0; i < c.length; i++) for (var j = 0; j < a.length; j++) for (var k = 0; k < l.length; k++) {
      if (Math.max(a[j], l[k], c[i]) - Math.min(a[j], l[k], c[i]) <= FINESTRA) return true;
    }
    return false;
  }
  window.mhwCodiceAccesso = codiceAccesso;

  function controllato(el) {
    if (!el.form || !/\/pannello\//.test(el.form.getAttribute('action') || '')) return false;
    if (el.tagName === 'INPUT' && !/^(text|search|)$/.test(el.type || '')) return false;
    if (el.closest('[data-no-codici]')) return false;
    return !ESCLUSI.test(el.name || '');
  }
  function segna(el) {
    if (!controllato(el)) return;
    var id = (el.id || el.name) + '-codice', avviso = el.parentNode.querySelector('[data-codice-avviso="' + id + '"]');
    var c = codiceAccesso(el.value);
    if (c && !avviso) {
      avviso = document.createElement('p');
      avviso.className = 'codice-avviso'; avviso.setAttribute('data-codice-avviso', id); avviso.setAttribute('role', 'status');
      avviso.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">'
        + '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3"/></svg>';
      var s = document.createElement('span'); s.textContent = TESTO; avviso.appendChild(s);
      el.insertAdjacentElement('afterend', avviso);
    } else if (!c && avviso) avviso.remove();
  }
  // Varianti camera: il riquadro di conferma compare quando un campo del modulo ha un codice,
  // e allora la casella diventa obbligatoria (il server la controlla comunque).
  function riquadro(form) {
    var box = form && form.querySelector('[data-codici-box]'); if (!box) return;
    var c = false;
    form.querySelectorAll('input, textarea').forEach(function (el) { if (controllato(el) && codiceAccesso(el.value)) c = true; });
    box.hidden = !c;
    box.querySelector('[data-codici-ok]').required = c;
  }
  document.addEventListener('input', function (e) { if (e.target.matches('input, textarea')) { segna(e.target); riquadro(e.target.form); } });
  // Dopo il salvataggio (la bozza non si perde) l'avviso resta sotto il campo.
  function tutti() { document.querySelectorAll('form input, form textarea').forEach(segna); document.querySelectorAll('form').forEach(riquadro); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tutti); else tutti();
})();
