/* 9V · «La casa: foto e descrizione» nel pannello dell'host (views/host/casa.php).
   - Caricamento multiplo: ogni foto si riduce nel browser (lato lungo 1600 px, JPEG) e parte da sola, una per volta:
     così dodici foto da 6 MB non superano mai il limite del server. Il server le riduce comunque (1600 px, 300 KB) e
     ferma la galleria a 10: di quelle rimaste fuori si dice quante sono.
   - Scelta una foto (un radio vero), «Sposta prima», «Sposta dopo» e «Togli» rispondono senza ricaricare la pagina:
     i testi scritti e non ancora salvati non si perdono. Con il mouse o con il dito le foto si trascinano (sul
     telefono: tieni premuto un attimo, poi sposta).
   - Il contatore delle battute conta i caratteri veri (una lettera accentata o un'emoji semplice valgono uno), come il
     server: oltre il massimo non si scrive.
   Senza JavaScript la pagina funziona lo stesso: ogni bottone è un modulo normale. */
(function () {
  'use strict';
  var box = document.querySelector('[data-casa]');
  if (!box || !window.fetch || !window.FormData || !window.Promise) return;
  var T = {}; try { T = JSON.parse(box.getAttribute('data-testi') || '{}'); } catch (e) {}
  var MAX = parseInt(box.getAttribute('data-max'), 10) || 10, LATO = parseInt(box.getAttribute('data-lato'), 10) || 1600;
  var griglia = box.querySelector('[data-casa-griglia]'), formFoto = box.querySelector('[data-casa-foto]'), formCarica = box.querySelector('[data-casa-carica]');
  var input = formCarica.querySelector('input[type=file]'), statoCarica = box.querySelector('[data-casa-carica-stato]');
  var barra = box.querySelector('[data-casa-azioni]'), scelta = box.querySelector('[data-casa-scelta]');
  var bSu = box.querySelector('[data-casa-su]'), bGiu = box.querySelector('[data-casa-giu]'), bTogli = box.querySelector('[data-casa-togli]');
  var aggiungi = box.querySelector('[data-casa-aggiungi]'), piena = box.querySelector('[data-casa-piena]');
  var azione = formFoto.getAttribute('action'), csrf = (formFoto.querySelector('input[name=_csrf]') || {}).value || '';
  var altBox = document.querySelector('[data-casa-alt]'), altDettagli = document.querySelector('.casa-alt');

  function t(k, sost) { var s = T[k] || ''; for (var x in sost || {}) s = s.replace('{' + x + '}', sost[x]); return s; }
  function peso(b) { return b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1).replace('.', ',').replace(/,0$/, '') + ' MB'; }
  function tessere() { return Array.prototype.slice.call(griglia.querySelectorAll('[data-foto]')); }
  function scelto() { var r = griglia.querySelector('input[name=foto_id]:checked'); return r ? r.closest('[data-foto]') : null; }
  function anteprima() { var b = document.querySelector('[data-ricarica-anteprima]'); if (b) b.click(); }

  // Dopo ogni cambio: i numeri d'ordine, «8 di 10 foto», la casella «+», le caselle vuote, i bottoni ai bordi.
  function aggiorna() {
    var tt = tessere(), n = tt.length;
    tt.forEach(function (el, i) {
      el.querySelector('.casa-foto__n').textContent = i + 1;
      var img = el.querySelector('img'); if (img && !img.getAttribute('data-alt-suo')) img.alt = t('foto', { i: i + 1 });
    });
    var vuote = griglia.querySelectorAll('[data-casa-vuota]');
    for (var v = 0; v < vuote.length; v++) vuote[v].remove();
    if (aggiungi) { aggiungi.hidden = n >= MAX; griglia.appendChild(aggiungi); }
    for (var k = n + 1; k < MAX; k++) { var li = document.createElement('li'); li.className = 'casa-foto casa-foto--vuota'; li.setAttribute('data-casa-vuota', ''); li.setAttribute('aria-hidden', 'true'); griglia.appendChild(li); }
    var conta = box.querySelector('[data-casa-conta-foto]');
    if (conta) conta.textContent = t('stato', { n: n, max: MAX }) + (n > 1 ? ' · ' + t('trascina') : '');
    formCarica.hidden = n >= MAX; if (piena) piena.hidden = n < MAX;
    barra.hidden = n === 0;
    var ok = box.querySelector('[data-casa-ok]'); if (ok) ok.hidden = n === 0;
    // Le descrizioni delle foto (nel modulo dei testi): una riga per foto, nell'ordine della galleria.
    if (altBox) {
      tt.forEach(function (el, i) {
        var id = el.getAttribute('data-foto'), riga = altBox.querySelector('[data-casa-alt-riga="' + id + '"]');
        if (!riga) {
          riga = document.createElement('div'); riga.className = 'field'; riga.style.margin = '0'; riga.setAttribute('data-casa-alt-riga', id);
          riga.innerHTML = '<label for="casa-alt-' + id + '"></label><input type="text" id="casa-alt-' + id + '" name="alt[' + id + ']" maxlength="250">';
        }
        riga.querySelector('label').textContent = t('foto', { i: i + 1 });
        altBox.appendChild(riga);
      });
      var righe = altBox.querySelectorAll('[data-casa-alt-riga]');
      for (var r = 0; r < righe.length; r++) if (!griglia.querySelector('[data-foto="' + righe[r].getAttribute('data-casa-alt-riga') + '"]')) righe[r].remove();
      if (altDettagli) altDettagli.hidden = n === 0;
    }
    segna();
  }
  // La foto scelta: «Foto 3 di 8», e i bottoni che ai bordi non servono si spengono.
  function segna() {
    var el = scelto(), tt = tessere(), i = el ? tt.indexOf(el) : -1;
    scelta.textContent = el ? t('scelta', { i: i + 1, n: tt.length }) : t('scegli');
    bSu.disabled = !el || i === 0; bGiu.disabled = !el || i === tt.length - 1; bTogli.disabled = !el;
  }
  griglia.addEventListener('change', segna);

  function manda(dati) {
    dati.append('_csrf', csrf);
    return fetch(azione, { method: 'POST', body: dati, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return j; }, function () { return { ok: false }; }); });
  }
  function stato(testo, errore) { statoCarica.textContent = testo; statoCarica.classList.toggle('casa-errore', !!errore); }

  /* ---- Sposta prima, sposta dopo, togli: senza ricaricare --------------------------------------------- */
  formFoto.addEventListener('submit', function (e) {
    var b = e.submitter, cosa = b ? b.value : '', el = scelto();
    e.preventDefault();
    if (!el || ['su', 'giu', 'togli'].indexOf(cosa) < 0) { segna(); return; }
    var dati = new FormData(); dati.append('azione', cosa); dati.append('foto_id', el.getAttribute('data-foto'));
    box.setAttribute('aria-busy', 'true');
    manda(dati).then(function (j) {
      box.removeAttribute('aria-busy');
      if (!j.ok) { location.reload(); return; }
      if (cosa === 'togli') { var vicina = el.nextElementSibling && el.nextElementSibling.hasAttribute('data-foto') ? el.nextElementSibling : el.previousElementSibling; el.remove();
        if (vicina && vicina.hasAttribute && vicina.hasAttribute('data-foto')) vicina.querySelector('input').checked = true; stato(t('tolta')); }
      else if (cosa === 'su' && el.previousElementSibling) griglia.insertBefore(el, el.previousElementSibling);
      else if (cosa === 'giu' && el.nextElementSibling && el.nextElementSibling.hasAttribute('data-foto')) griglia.insertBefore(el.nextElementSibling, el);
      aggiorna(); anteprima();
      if (b.disabled) { var r = (scelto() || {}).querySelector ? scelto().querySelector('input') : null; if (r) r.focus(); } else b.focus();
    }, function () { location.reload(); });
  });

  /* ---- Caricamento: ridotte nel browser, una per volta ------------------------------------------------- */
  function apri(file) {
    if (window.createImageBitmap) return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return conImg(file); });
    return conImg(file);
  }
  function conImg(file) {
    return new Promise(function (ok, no) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () { ok(img); setTimeout(function () { URL.revokeObjectURL(url); }, 1000); };
      img.onerror = function () { URL.revokeObjectURL(url); no(new Error('illeggibile')); };
      img.src = url;
    });
  }
  function heic(f) { return /image\/hei[cf]/i.test(f.type) || /\.hei[cf]$/i.test(f.name); }
  // La foto a 1600 px sul lato lungo, in JPEG. Una già piccola e leggera parte com'è; una che il browser non legge la decide il server.
  function riduci(file) {
    if (!window.HTMLCanvasElement) return Promise.resolve(file);
    return apri(file).then(function (img) {
      var w = img.width, h = img.height;
      if (Math.max(w, h) <= LATO && file.size <= 400 * 1024 && !heic(file)) return file;
      var k = Math.min(1, LATO / Math.max(w, h)), c = document.createElement('canvas');
      c.width = Math.max(1, Math.round(w * k)); c.height = Math.max(1, Math.round(h * k));
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, 0, 0, c.width, c.height);
      if (img.close) img.close();
      return new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', 0.84); }).then(function (blob) {
        if (!blob) return file;
        return new File([blob], file.name.replace(/\.[a-z0-9]+$/i, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
      });
    }, function () { if (heic(file)) throw new Error('heic'); return file; });
  }
  function tessera(f) {
    var li = document.createElement('li'); li.className = 'casa-foto'; li.setAttribute('data-foto', f.id);
    li.innerHTML = '<label><input type="radio" name="foto_id" value="' + f.id + '"><img width="160" height="160" draggable="false" alt=""><b class="casa-foto__n" aria-hidden="true"></b></label>';
    var img = li.querySelector('img'); img.src = f.url; if (f.alt) { img.alt = f.alt; img.setAttribute('data-alt-suo', '1'); }
    return li;
  }
  var inCorso = false;
  function carica(files) {
    if (inCorso || !files.length) return;
    inCorso = true; input.disabled = true;
    var n = files.length, fatte = 0, fuori = 0, errori = [], prima = 0, dopo = 0, i = 0;
    function prossima() {
      if (i >= n) return finito();
      var file = files[i++];
      if (tessere().length >= MAX) { fuori++; return prossima(); }
      stato(t('prepara', { i: i, n: n }));
      return riduci(file).then(function (pronta) {
        stato(t('carica', { i: i, n: n }));
        var dati = new FormData(); dati.append('azione', 'carica'); dati.append('foto[]', pronta, pronta.name);
        return manda(dati).then(function (j) {
          if (j.foto && j.foto.length) { griglia.insertBefore(tessera(j.foto[0]), aggiungi || null); fatte++; prima += file.size; dopo += j.dopo || 0; aggiorna(); }
          else if (j.fuori) fuori += j.fuori;
          else errori.push((j.errori && j.errori[0]) || j.errore || j.messaggio || ('«' + file.name + '»: ' + t('errore')));
        });
      }, function (err) { errori.push('«' + file.name + '»: ' + (err && err.message === 'heic' ? t('heic') : t('errore'))); })
        .then(prossima, function () { errori.push('«' + file.name + '»: ' + t('errore')); return prossima(); });
    }
    function finito() {
      inCorso = false; input.disabled = false; input.value = '';
      var parti = [];
      if (fatte) parti.push(fatte === 1 ? t('fatto1') : t('fatto', { n: fatte }));
      if (fuori) parti.push(fuori === 1 ? t('fuori1', { max: MAX }) : t('fuori', { max: MAX, n: fuori }));
      stato(parti.concat(errori).join(' '), errori.length > 0 || (!fatte && fuori > 0));
      var rid = box.querySelector('[data-casa-ridotte]');
      if (fatte && rid && prima > dopo) rid.textContent = t('ridotte') + ' ' + t('da', { prima: peso(prima), dopo: peso(dopo) });
      if (fatte) anteprima();
    }
    prossima();
  }
  input.addEventListener('change', function () { carica(Array.prototype.slice.call(input.files || [])); });
  formCarica.addEventListener('submit', function (e) { e.preventDefault(); carica(Array.prototype.slice.call(input.files || [])); });
  var bCarica = box.querySelector('[data-casa-carica-btn]'); if (bCarica) bCarica.hidden = true;   // con JavaScript partono appena scelte
  var bScegli = box.querySelector('[data-casa-scegli]'); if (bScegli) { bScegli.hidden = false; input.classList.add('casa-file--nascosto'); }
  // Sul computer le foto si possono anche trascinare dentro il riquadro.
  box.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0) { e.preventDefault(); box.classList.add('casa--sopra'); } });
  box.addEventListener('dragleave', function (e) { if (e.target === box) box.classList.remove('casa--sopra'); });
  box.addEventListener('drop', function (e) {
    if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
    e.preventDefault(); box.classList.remove('casa--sopra');
    carica(Array.prototype.filter.call(e.dataTransfer.files, function (f) { return /^image\//.test(f.type) || heic(f); }));
  });

  /* ---- Trascina per ordinare: mouse e dito ------------------------------------------------------------- */
  var presa = null, attiva = false, partenza = null, timer = null, ordinePrima = '';
  function ordine() { return tessere().map(function (el) { return el.getAttribute('data-foto'); }).join(','); }
  function comincia() { attiva = true; presa.classList.add('casa-foto--trascina'); griglia.classList.add('casa-griglia--trascina'); ordinePrima = ordine(); }
  function sopra(x, y) {
    var sotto = document.elementFromPoint(x, y), el = sotto && sotto.closest ? sotto.closest('[data-foto]') : null;
    if (!el || el === presa || el.parentNode !== griglia) return;
    var tt = tessere();
    griglia.insertBefore(presa, tt.indexOf(el) > tt.indexOf(presa) ? el.nextSibling : el);
    tt = tessere(); tt.forEach(function (x2, i) { x2.querySelector('.casa-foto__n').textContent = i + 1; });
  }
  function lascia() {
    clearTimeout(timer);
    var era = attiva, el = presa;
    presa = null; attiva = false;
    if (!el) return;
    el.classList.remove('casa-foto--trascina'); griglia.classList.remove('casa-griglia--trascina');
    if (!era) return;
    var r = el.querySelector('input'); if (r) r.checked = true;
    aggiorna();
    if (ordine() === ordinePrima) return;
    var dati = new FormData(); dati.append('azione', 'ordina'); dati.append('ordine', ordine());
    manda(dati).then(function (j) { if (j.ok) { stato(t('ordine')); anteprima(); } else location.reload(); }, function () { location.reload(); });
  }
  griglia.addEventListener('mousedown', function (e) {
    var el = e.target.closest('[data-foto]'); if (!el || e.button !== 0) return;
    presa = el; partenza = { x: e.clientX, y: e.clientY };
  });
  document.addEventListener('mousemove', function (e) {
    if (!presa) return;
    if (!attiva && Math.abs(e.clientX - partenza.x) + Math.abs(e.clientY - partenza.y) > 6) comincia();
    if (attiva) { e.preventDefault(); sopra(e.clientX, e.clientY); }
  });
  document.addEventListener('mouseup', lascia);
  // Sul telefono: un attimo fermi sulla foto (per non rubare lo scorrimento della pagina), poi si sposta.
  griglia.addEventListener('touchstart', function (e) {
    var el = e.target.closest('[data-foto]'); if (!el || e.touches.length !== 1) return;
    presa = el; partenza = { x: e.touches[0].clientX, y: e.touches[0].clientY };
    timer = setTimeout(function () { if (presa) { comincia(); if (navigator.vibrate) navigator.vibrate(15); } }, 280);
  }, { passive: true });
  griglia.addEventListener('touchmove', function (e) {
    if (!presa) return;
    var p = e.touches[0];
    if (!attiva) { if (Math.abs(p.clientX - partenza.x) + Math.abs(p.clientY - partenza.y) > 10) { clearTimeout(timer); presa = null; } return; }
    e.preventDefault(); sopra(p.clientX, p.clientY);
  }, { passive: false });
  griglia.addEventListener('touchend', lascia);
  griglia.addEventListener('touchcancel', lascia);
  // Dopo un trascinamento il «clic» che segue non deve cambiare la scelta a caso.
  griglia.addEventListener('contextmenu', function (e) { if (attiva) e.preventDefault(); });

  /* ---- Il contatore delle battute ---------------------------------------------------------------------- */
  function punti(s) { return Array.from ? Array.from(s) : s.split(''); }
  // Come il server (Casa::unaRiga, Casa::paragrafi): senza spazi in testa e in coda, gli a capo contano uno.
  function pulito(s, righe) {
    if (s.normalize) s = s.normalize('NFC');
    s = s.replace(/\r\n?/g, '\n');
    return righe ? s.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim() : s.replace(/\s+/g, ' ').trim();
  }
  var campi = document.querySelectorAll('[data-casa-campo]');
  for (var c = 0; c < campi.length; c++) (function (campo) {
    var max = parseInt(campo.getAttribute('data-max'), 10), righe = campo.tagName === 'TEXTAREA';
    var out = document.querySelector('[data-casa-conta="' + campo.id + '"]');
    function conta() {
      var n = punti(pulito(campo.value, righe)).length;
      if (n > max) {   // oltre il massimo non si scrive: si toglie l'eccedenza dalla fine
        var p = punti(campo.value);
        campo.value = p.slice(0, Math.max(0, p.length - (n - max))).join('');
        n = punti(pulito(campo.value, righe)).length;
      }
      if (out) { out.innerHTML = '<span class="sr-only">' + (out.querySelector('.sr-only') ? out.querySelector('.sr-only').textContent : '') + '</span>' + n + ' / ' + max; out.classList.toggle('contatore--oltre', n > max); }
    }
    campo.addEventListener('input', conta);
    conta();
  })(campi[c]);

  aggiorna();
})();
