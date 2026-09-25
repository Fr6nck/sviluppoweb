/* Arco del Vento — area riservata.
   Solo comodità: l'anteprima del file scelto prima di caricarlo, e il file
   trascinato sul riquadro. Senza JavaScript il modulo funziona lo stesso. */
(function () {
  'use strict';

  var TIPI = ['image/jpeg', 'image/png', 'image/webp'];

  function misura(byte) {
    return byte >= 1048576
      ? (byte / 1048576).toFixed(1).replace('.', ',') + ' MB'
      : Math.max(1, Math.round(byte / 1024)) + ' KB';
  }

  document.querySelectorAll('[data-carica]').forEach(function (modulo) {
    var campo = modulo.querySelector('input[type="file"]');
    var zona = modulo.querySelector('[data-carica-zona]');
    var anteprima = modulo.querySelector('[data-carica-anteprima]');
    var nome = modulo.querySelector('[data-carica-nome]');
    if (!campo || !zona) return;

    function mostra() {
      var file = campo.files && campo.files[0];
      zona.classList.toggle('adm-zona--scelto', !!file);
      if (!file) {
        if (anteprima) anteprima.hidden = true;
        if (nome) nome.textContent = '';
        return;
      }
      if (nome) {
        nome.textContent = file.name + ' · ' + misura(file.size) +
          (TIPI.indexOf(file.type) === -1 ? ' — non è una JPG, PNG o WebP' : '');
      }
      // Un data: URL e non un blob:, che la politica di sicurezza non ammette.
      if (anteprima && TIPI.indexOf(file.type) !== -1 && file.size < 16 * 1048576 && window.FileReader) {
        var lettore = new FileReader();
        lettore.onload = function () {
          anteprima.src = String(lettore.result);
          anteprima.hidden = false;
        };
        lettore.readAsDataURL(file);
      } else if (anteprima) {
        anteprima.hidden = true;
      }
    }

    campo.addEventListener('change', mostra);

    ['dragenter', 'dragover'].forEach(function (evento) {
      zona.addEventListener(evento, function (e) {
        e.preventDefault();
        zona.classList.add('adm-zona--sopra');
      });
    });
    ['dragleave', 'drop'].forEach(function (evento) {
      zona.addEventListener(evento, function () {
        zona.classList.remove('adm-zona--sopra');
      });
    });
    zona.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      try {
        campo.files = e.dataTransfer.files;
      } catch (err) {
        return;
      }
      mostra();
    });

    // Il pulsante si ferma mentre il file sale: un secondo click lo
    // caricherebbe due volte.
    modulo.addEventListener('submit', function () {
      var bottone = modulo.querySelector('button[type="submit"]');
      if (bottone && campo.files && campo.files.length) {
        window.setTimeout(function () { bottone.disabled = true; }, 0);
        bottone.classList.add('adm-bottone--attesa');
      }
    });
  });
})();
