/* Password e email nei moduli di accesso (7A): «Mostra» su ogni campo password, la barra di
   robustezza sulle password nuove, e il controllo che l'email ripetuta sia uguale. Il server
   controlla comunque tutto: senza JavaScript i moduli funzionano lo stesso. */
(function () {
  'use strict';
  var en = document.documentElement.lang === 'en';   // 9N: /en/register, /en/login e il pannello in inglese
  document.querySelectorAll('[data-mostra-pw]').forEach(function (bt) {
    var pw = document.getElementById(bt.getAttribute('aria-controls')); if (!pw) return;
    bt.hidden = false;
    bt.addEventListener('click', function () {
      var vedi = pw.type === 'password';
      pw.type = vedi ? 'text' : 'password';
      bt.textContent = vedi ? (en ? 'Hide' : 'Nascondi') : (en ? 'Show' : 'Mostra');
      bt.setAttribute('aria-pressed', vedi ? 'true' : 'false');
      pw.focus();
    });
  });
  document.querySelectorAll('[data-forza]').forEach(function (fz) {
    var pw = document.getElementById(fz.getAttribute('data-forza')); if (!pw) return;
    var min = +(pw.getAttribute('minlength') || 8), barra = fz.querySelector('b'), testo = fz.querySelector('span');
    fz.hidden = false;
    function stima() {
      var v = pw.value, p = 0;
      if (v.length >= min) p++;
      if (v.length >= min + 4) p++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) p++;
      if (/\d/.test(v)) p++;
      if (/[^A-Za-z0-9]/.test(v)) p++;
      if (v.length < min) p = Math.min(p, 1);
      var livelli = en ? [['', 0], ['Too short', 1], ['Weak', 2], ['Fair', 3], ['Good', 4], ['Excellent', 5]] : [['', 0], ['Troppo corta', 1], ['Debole', 2], ['Discreta', 3], ['Buona', 4], ['Ottima', 5]];
      var l = v ? livelli[Math.max(1, Math.min(5, p))] : livelli[0];
      barra.style.width = (l[1] * 20) + '%';
      fz.setAttribute('data-livello', String(l[1]));
      testo.textContent = l[0] ? 'Password: ' + l[0].toLowerCase() : '';
    }
    pw.addEventListener('input', stima); stima();
  });
  // «Ripeti l'email»: incollare è permesso; l'avviso compare solo quando le due sono diverse.
  document.querySelectorAll('[data-ripeti]').forEach(function (r) {
    var prima = document.getElementById(r.getAttribute('data-ripeti')), avviso = document.getElementById(r.id + '-diversa');
    if (!prima || !avviso) return;
    function confronta() {
      var diverse = r.value.trim() !== '' && r.value.trim().toLowerCase() !== prima.value.trim().toLowerCase();
      avviso.hidden = !diverse;
      r.setAttribute('aria-invalid', diverse ? 'true' : 'false');
    }
    r.addEventListener('input', confronta); prima.addEventListener('input', confronta); r.addEventListener('blur', confronta);
  });
})();
