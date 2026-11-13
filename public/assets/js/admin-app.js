/* Arco del Vento — area riservata: l'app sul telefono e le notifiche.

   Si carica solo nelle pagine di chi ha fatto l'accesso (views/admin/layout.php).
   - Registra il service worker (/admin/sw.js), che mostra le notifiche.
   - Sul telefono, se l'area riservata non è ancora installata, invita a farlo:
     con il pulsante «Installa» dove il browser lo permette (Android, Chrome),
     con i tre passi di Safari sull'iPhone.
   - Nell'app installata invita ad attivare le notifiche.
   - In Account → Notifiche: attivare o disattivare su questo dispositivo.
   Un «Non ora» vale due settimane, su quel dispositivo. Senza JavaScript non
   compare niente, e il resto dell'area riservata funziona lo stesso. */
(function () {
  'use strict';

  var corpo = document.body;
  var SW = corpo.getAttribute('data-app-sw');
  var CAMPO = corpo.getAttribute('data-app-campo');
  var CHIAVE = corpo.getAttribute('data-push-chiave') || '';
  var URL_NOTIFICHE = corpo.getAttribute('data-push-url');
  var gettone = document.querySelector('input[name="_token"]');
  var GIORNI_ATTESA = 14;

  var ua = navigator.userAgent;
  var iphone = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var installata = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var telefono = window.matchMedia('(pointer: coarse)').matches && Math.min(screen.width, screen.height) < 820;
  // Sull'iPhone le notifiche arrivano solo all'app installata (iOS 16.4 o più recente): in Safari no, qualunque cosa dica il browser.
  var pushPossibile = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && CHIAVE !== '' && !(iphone && !installata);

  function leggi(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
  function scrivi(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* senza memoria: si richiede la prossima volta */ } }
  function rimandato(k) { var t = Number(leggi(k) || 0); return t > 0 && Date.now() - t < GIORNI_ATTESA * 864e5; }

  /* -------------------------------------------------- il service worker */
  var pronto = Promise.resolve(null);
  if ('serviceWorker' in navigator && SW) {
    pronto = navigator.serviceWorker.register(SW, { scope: CAMPO })
      .then(function () { return navigator.serviceWorker.ready; })
      .catch(function () { return null; });
  }

  /* ---------------------------------------------------- le notifiche */
  function daBase64(s) {
    var b = atob((s + '===='.slice(s.length % 4)).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(b.length);
    for (var i = 0; i < b.length; i++) out[i] = b.charCodeAt(i);
    return out;
  }
  function stessaChiave(iscrizione) {
    var k = iscrizione.options && iscrizione.options.applicationServerKey;
    if (!k) return true;
    var a = new Uint8Array(k), b = daBase64(CHIAVE);
    if (a.length !== b.length) return false;
    for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
    return true;
  }
  function chiedi(azione, iscrizione) {
    var j = iscrizione.toJSON();
    var dati = new URLSearchParams({ _token: gettone ? gettone.value : '', risposta: 'json', endpoint: j.endpoint,
      p256dh: (j.keys && j.keys.p256dh) || '', auth: (j.keys && j.keys.auth) || '' });
    return fetch(URL_NOTIFICHE + '/' + azione, { method: 'POST', body: dati, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.ok) return r.json();
        return { ok: false, testo: r.status === 403 ? 'La sessione è scaduta: ricarica la pagina e riprova.' : 'Il sito non ha risposto (' + r.status + '). Riprova.' };
      })
      .catch(function () { return { ok: false, testo: 'Senza connessione: riprova quando il telefono è in rete.' }; });
  }
  function nonPossibile() {
    if (iphone && !installata) return 'Sull’iPhone le notifiche arrivano all’app: prima installala (Condividi → Aggiungi alla schermata Home), poi aprila dall’icona e attivale da lì.';
    return 'Questo browser non riceve notifiche. Prova con Chrome, Edge, Firefox o Safari aggiornati.';
  }

  /* Attiva su questo dispositivo: permesso, iscrizione, e il sito la ricorda. */
  function attiva() {
    if (!pushPossibile) return Promise.resolve({ ok: false, testo: nonPossibile() });
    return Notification.requestPermission().then(function (permesso) {
      if (permesso !== 'granted') {
        return { ok: false, testo: permesso === 'denied'
          ? 'Le notifiche sono bloccate per questo sito: si sbloccano dalle impostazioni del browser o del telefono.'
          : 'Notifiche non attivate.' };
      }
      return pronto.then(function (reg) {
        if (!reg) return { ok: false, testo: 'Il servizio delle notifiche non è partito: ricarica la pagina e riprova.' };
        return reg.pushManager.getSubscription()
          .then(function (vecchia) {
            if (vecchia && !stessaChiave(vecchia)) return vecchia.unsubscribe().then(function () { return null; });
            return vecchia;
          })
          .then(function (iscrizione) {
            return iscrizione || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: daBase64(CHIAVE) });
          })
          .then(function (iscrizione) { return chiedi('iscrivi', iscrizione); })
          .then(function (r) { if (r.ok) scrivi('adv-push-allineata', String(Date.now())); return r; });
      });
    }).catch(function (e) {
      return { ok: false, testo: 'Il browser non ha attivato le notifiche' + (e && e.message ? ' (' + e.message + ')' : '') + '. Riprova.' };
    });
  }

  /* Disattiva su questo dispositivo. */
  function disattiva() {
    return pronto.then(function (reg) {
      if (!reg) return { ok: true, testo: 'Notifiche non attive su questo dispositivo.' };
      return reg.pushManager.getSubscription().then(function (iscrizione) {
        if (!iscrizione) return { ok: true, testo: 'Notifiche non attive su questo dispositivo.' };
        return chiedi('togli', iscrizione).then(function () {
          return iscrizione.unsubscribe().then(function () { return { ok: true, testo: 'Notifiche tolte da questo dispositivo.' }; });
        });
      });
    });
  }

  /* Com'è questo dispositivo: 'no' (non può), 'bloccate', 'attive', 'spente'. */
  function stato() {
    if (!pushPossibile) return Promise.resolve('no');
    if (Notification.permission === 'denied') return Promise.resolve('bloccate');
    return pronto.then(function (reg) {
      if (!reg) return 'spente';
      return reg.pushManager.getSubscription().then(function (s) {
        return s && Notification.permission === 'granted' && stessaChiave(s) ? 'attive' : 'spente';
      });
    });
  }

  /* Una volta al giorno il sito si fa ridire l'iscrizione di questo dispositivo:
     se l'archivio del server si fosse perso, le notifiche ripartono da sole. */
  if (pushPossibile && Notification.permission === 'granted' && Date.now() - Number(leggi('adv-push-allineata') || 0) > 864e5) {
    pronto.then(function (reg) {
      if (!reg) return;
      reg.pushManager.getSubscription().then(function (s) {
        if (s && stessaChiave(s)) chiedi('iscrivi', s).then(function (r) { if (r.ok) scrivi('adv-push-allineata', String(Date.now())); });
      });
    });
  }

  /* ------------------------------------------------------- l'invito */
  var invito = document.getElementById('adm-app');
  var richiestaInstallazione = null;
  var variante = null;

  if (invito) {
    var si = invito.querySelector('[data-app-si]');
    var no = invito.querySelector('[data-app-no]');
    var esito = invito.querySelector('[data-app-esito]');

    var mostra = function (nome) {
      variante = nome;
      var scelta = null;
      invito.querySelectorAll('[data-app-variante]').forEach(function (v) {
        var questa = v.getAttribute('data-app-variante') === nome;
        v.hidden = !questa;
        if (questa) scelta = v;
      });
      var testoSi = scelta.getAttribute('data-si');
      si.hidden = !testoSi;
      si.disabled = false;
      if (testoSi) si.textContent = testoSi;
      no.textContent = scelta.getAttribute('data-no') || 'Non ora';
      esito.textContent = '';
      invito.setAttribute('data-variante', nome);
      invito.hidden = false;
      corpo.classList.add('adm-app-aperta');
    };
    var chiudi = function (ricorda) {
      if (ricorda && variante) scrivi('adv-app-' + variante, String(Date.now()));
      invito.hidden = true;
      corpo.classList.remove('adm-app-aperta');
    };

    no.addEventListener('click', function () { chiudi(true); });
    si.addEventListener('click', function () {
      if (variante === 'installa' && richiestaInstallazione) {
        var r = richiestaInstallazione;
        richiestaInstallazione = null;
        r.prompt();
        r.userChoice.then(function (c) { chiudi(c.outcome !== 'accepted'); });
      } else if (variante === 'notifiche') {
        si.disabled = true;
        attiva().then(function (r) {
          esito.textContent = r.testo;
          if (r.ok) {
            si.hidden = true;
            no.textContent = 'Chiudi';
            window.setTimeout(function () { chiudi(false); }, 4000);
          } else {
            si.disabled = false;
          }
        });
      }
    });

    // Android, Chrome, Edge: il browser dice che si può installare.
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      richiestaInstallazione = e;
      if (!installata && telefono && !rimandato('adv-app-installa')) mostra('installa');
    });
    window.addEventListener('appinstalled', function () {
      richiestaInstallazione = null;
      if (variante === 'installa') chiudi(false);
    });

    if (!installata && telefono && iphone && !rimandato('adv-app-iphone')) {
      // iPhone e iPad: nessun pulsante, si installa da Condividi.
      mostra('iphone');
    } else if (installata && pushPossibile && Notification.permission === 'default' && !rimandato('adv-app-notifiche')) {
      // Nell'app installata: le notifiche.
      stato().then(function (s) { if (s === 'spente') mostra('notifiche'); });
    }
  }

  /* --------------------------------------- Account → Notifiche, questo dispositivo */
  var qui = document.querySelector('[data-notifiche-qui]');
  if (qui) {
    var testoStato = qui.querySelector('[data-notifiche-stato]');
    var bottoneAttiva = qui.querySelector('[data-notifiche-attiva]');
    var bottoneSpegni = qui.querySelector('[data-notifiche-spegni]');
    var esitoQui = qui.querySelector('[data-notifiche-esito]');
    var frasi = {
      no: nonPossibile(),
      bloccate: 'Bloccate su questo dispositivo: si sbloccano dalle impostazioni del browser o del telefono.',
      attive: 'Attive su questo dispositivo.',
      spente: 'Non attive su questo dispositivo.',
    };
    var aggiorna = function () {
      return stato().then(function (s) {
        qui.setAttribute('data-stato', s);
        testoStato.textContent = frasi[s];
        bottoneAttiva.hidden = s !== 'spente';
        bottoneSpegni.hidden = s !== 'attive';
      });
    };
    var dopo = function (r) {
      esitoQui.textContent = r.testo;
      // L'elenco dei dispositivi qui sotto lo scrive il server: si ricarica.
      if (r.ok) window.setTimeout(function () { window.location.reload(); }, 1200);
      return aggiorna();
    };
    bottoneAttiva.addEventListener('click', function () { bottoneAttiva.disabled = true; attiva().then(dopo).then(function () { bottoneAttiva.disabled = false; }); });
    bottoneSpegni.addEventListener('click', function () { bottoneSpegni.disabled = true; disattiva().then(dopo).then(function () { bottoneSpegni.disabled = false; }); });
    qui.hidden = false;
    aggiorna();
  }
})();
