/* 9K · Foto leggere: prima di caricarle, nel browser.
   - Una foto oltre 1,5 MB o 2400 px si ridisegna su <canvas> a lato lungo 2400 px, JPEG qualità 0,85
     (il logo resta PNG, al massimo 800 px), con l'orientamento giusto (EXIF). Di solito sotto 1 MB.
   - Mentre la si prepara: «Sto preparando la foto…»; il modulo aspetta che sia pronta.
   - All'invio di un modulo con un file: la barra di caricamento (XMLHttpRequest con l'avanzamento).
     Con l'intestazione X-MHW-Carica il server, invece di reindirizzare, risponde {"vai": indirizzo} e la pagina
     ci va da sé (il messaggio verde o rosso si legge lì); altrimenti (un errore del modulo) si mostra la risposta.
   - Foto HEIC che il browser non sa leggere: il messaggio per scegliere «Più compatibile» sull'iPhone.
   Senza JavaScript, o in un browser vecchio, tutto come prima: il server riduce le foto da sé. */
(function () {
  'use strict';
  var LATO = 2400, LATO_LOGO = 800, SOGLIA = 1.5 * 1024 * 1024, QUALITA = 0.85;
  if (!window.HTMLCanvasElement || !window.Blob || !window.DataTransfer) return;

  function peso(b) { return b < 1024 * 1024 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
  function eHeic(f) { return /image\/hei[cf]/i.test(f.type) || /\.hei[cf]$/i.test(f.name); }
  function eLogo(input) { return input.hasAttribute('data-foto-logo') || /(^|[^a-z])logo/i.test(input.name || ''); }

  // Lo stato vicino al campo: nella zona di caricamento, se c'è, altrimenti subito dopo il campo.
  function stato(input) {
    var zona = input.closest('[data-drop]'), el = (zona || input.parentNode).querySelector(':scope > .foto-stato');
    if (!el) {
      el = document.createElement('span'); el.className = 'foto-stato small'; el.setAttribute('role', 'status'); el.setAttribute('aria-live', 'polite');
      (zona || input.parentNode).appendChild(el);
    }
    return el;
  }
  function scrivi(input, testo, attesa) {
    var el = stato(input); el.textContent = testo; el.classList.toggle('foto-stato--attesa', !!attesa); el.hidden = testo === '';
  }

  // L'immagine decodificata con l'orientamento giusto: createImageBitmap se c'è, altrimenti un <img>
  // (i browser di oggi raddrizzano da soli le foto con l'EXIF).
  function apri(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return apriConImg(file); });
    }
    return apriConImg(file);
  }
  function apriConImg(file) {
    return new Promise(function (ok, no) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () { ok(img); setTimeout(function () { URL.revokeObjectURL(url); }, 1000); };
      img.onerror = function () { URL.revokeObjectURL(url); no(new Error('illeggibile')); };
      img.src = url;
    });
  }

  function prepara(input, file) {
    var logo = eLogo(input);
    return apri(file).then(function (img) {
      var w = img.width, h = img.height, max = logo ? LATO_LOGO : LATO;
      if (file.size <= SOGLIA && Math.max(w, h) <= max && !eHeic(file)) return null;   // già leggera: si carica com'è
      var k = Math.min(1, max / Math.max(w, h)), cw = Math.max(1, Math.round(w * k)), ch = Math.max(1, Math.round(h * k));
      var c = document.createElement('canvas'); c.width = cw; c.height = ch;
      var ctx = c.getContext('2d');
      var png = logo && file.type !== 'image/jpeg';
      if (!png) { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch); }   // in JPEG la trasparenza diventerebbe nera
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, 0, 0, cw, ch);
      if (img.close) img.close();
      return new Promise(function (ok) { c.toBlob(ok, png ? 'image/png' : 'image/jpeg', png ? undefined : QUALITA); }).then(function (blob) {
        if (!blob || (blob.size >= file.size && !eHeic(file))) return null;
        var nome = file.name.replace(/\.[a-z0-9]+$/i, '') + (png ? '.png' : '.jpg');
        return new File([blob], nome, { type: blob.type, lastModified: Date.now() });
      });
    });
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.type !== 'file' || input._mhwRidotta) { if (input) input._mhwRidotta = false; return; }
    var file = input.files && input.files[0];
    if (!file || !(/^image\//.test(file.type) || eHeic(file))) { if (input.closest) scrivi(input, ''); return; }
    var form = input.form;
    if (form) form._mhwPreparazioni = (form._mhwPreparazioni || 0) + 1;
    scrivi(input, 'Sto preparando la foto…', true);
    prepara(input, file).then(function (nuova) {
      if (nuova) {
        var dt = new DataTransfer(); dt.items.add(nuova); input.files = dt.files;
        input._mhwRidotta = true;   // il «change» che segue aggiorna l'anteprima, senza rifare il lavoro
        input.dispatchEvent(new Event('change', { bubbles: true }));
        scrivi(input, 'Foto pronta: ' + peso(nuova.size) + ' (era ' + peso(file.size) + ').');
      } else scrivi(input, '');
    }, function () {
      if (eHeic(file)) {
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        scrivi(input, 'Questa foto è in formato HEIC: da iPhone scegli «Più compatibile» in Impostazioni → Fotocamera → Formati, o caricala dal telefono.');
      } else scrivi(input, '');   // la decide il server: un formato che non legge lo dice lui
    }).then(function () {
      if (!form) return;
      form._mhwPreparazioni--;
      if (!form._mhwPreparazioni && form._mhwInAttesa) { var s = form._mhwInAttesa; form._mhwInAttesa = null; invia(form, s); }
    });
  }, true);

  /* ---- L'invio con la barra di caricamento ---------------------------------- */
  var barra = null;
  function mostraBarra(perc) {
    if (!barra) {
      barra = document.createElement('div'); barra.className = 'caricamento'; barra.setAttribute('role', 'status'); barra.setAttribute('aria-live', 'polite');
      barra.innerHTML = '<span class="caricamento__testo"></span><span class="caricamento__barra"><i></i></span>';
      document.body.appendChild(barra);
    }
    barra.querySelector('.caricamento__testo').textContent = perc === null ? 'Sto preparando la foto…' : perc < 100 ? 'Sto caricando… ' + perc + '%' : 'Quasi fatto…';
    barra.querySelector('i').style.width = (perc === null ? 8 : perc) + '%';
  }
  function conFile(form) {
    var f = form.querySelectorAll('input[type=file]');
    for (var i = 0; i < f.length; i++) if (f[i].files && f[i].files.length && !f[i].disabled) return true;
    return false;
  }
  function invia(form, submitter) {
    var dati;
    try { dati = new FormData(form, submitter || undefined); }
    catch (err) { dati = new FormData(form); if (submitter && submitter.name) dati.append(submitter.name, submitter.value); }
    var azione = (submitter && submitter.getAttribute('formaction')) ? submitter.formAction : form.action;
    var x = new XMLHttpRequest();
    x.open('POST', azione);
    x.setRequestHeader('X-MHW-Carica', '1');
    mostraBarra(0);
    x.upload.onprogress = function (ev) { if (ev.lengthComputable) mostraBarra(Math.round(ev.loaded / ev.total * 100)); };
    x.onload = function () {
      var vai = null;
      if (/application\/json/.test(x.getResponseHeader('Content-Type') || '')) { try { vai = JSON.parse(x.responseText).vai || null; } catch (err) {} }
      if (vai) { location.assign(vai); return; }
      // Nessun reindirizzamento: la pagina della risposta (un errore nel modulo) al posto di questa.
      document.open(); document.write(x.responseText); document.close();
    };
    x.onerror = function () { if (barra) barra.remove(); barra = null; form.submit(); };
    x.send(dati);
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented || !form || (form.method || '').toLowerCase() !== 'post' || form.enctype !== 'multipart/form-data') return;
    if (form._mhwPreparazioni) { e.preventDefault(); form._mhwInAttesa = e.submitter || null; mostraBarra(null); return; }
    if (!conFile(form) || !window.XMLHttpRequest || !('upload' in new XMLHttpRequest())) return;
    e.preventDefault();
    invia(form, e.submitter || null);
  });
})();
