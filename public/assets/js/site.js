/* Arco del Vento — il solo JavaScript del sito.
 *
 * Serve a una cosa: aprire e chiudere il pannello di navigazione su schermo
 * stretto. Tutto il resto del sito funziona senza — le date sono campi
 * nativi, le domande sono <details>, la rivelazione allo scorrimento è CSS,
 * e ogni passo della prenotazione è una pagina con il suo indirizzo.
 *
 * Il file non ha dipendenze e non fa richieste di rete.
 */
(function () {
  'use strict';

  var pannello = document.querySelector('[data-menu]');
  var apri     = document.querySelector('[data-menu-apri]');
  var chiudi   = document.querySelector('[data-menu-chiudi]');

  if (!pannello || !apri) {
    return;
  }

  /* L'elemento che aveva il fuoco prima dell'apertura: chiudendo il pannello
     il fuoco torna lì, altrimenti chi naviga da tastiera riparte dall'inizio
     della pagina a ogni chiusura. */
  var fuocoPrecedente = null;

  function elementiFocalizzabili() {
    return Array.prototype.filter.call(
      pannello.querySelectorAll('a[href], button:not([disabled])'),
      function (el) { return el.offsetParent !== null; }
    );
  }

  function mostra() {
    fuocoPrecedente = document.activeElement;
    pannello.hidden = false;
    document.body.classList.add('adv-menu-aperto');
    apri.setAttribute('aria-expanded', 'true');

    var primi = elementiFocalizzabili();
    if (primi.length) {
      primi[0].focus();
    }
  }

  function nascondi() {
    pannello.hidden = true;
    document.body.classList.remove('adv-menu-aperto');
    apri.setAttribute('aria-expanded', 'false');

    if (fuocoPrecedente && typeof fuocoPrecedente.focus === 'function') {
      fuocoPrecedente.focus();
    }
  }

  apri.addEventListener('click', mostra);

  if (chiudi) {
    chiudi.addEventListener('click', nascondi);
  }

  document.addEventListener('keydown', function (evento) {
    if (pannello.hidden) {
      return;
    }

    if (evento.key === 'Escape') {
      nascondi();
      return;
    }

    /* Il fuoco resta dentro il pannello finché è aperto: è un pannello
       modale, e lasciare tabulare la pagina sotto significa perdere il
       cursore dietro a un fondo pieno. */
    if (evento.key !== 'Tab') {
      return;
    }

    var elementi = elementiFocalizzabili();
    if (!elementi.length) {
      return;
    }
    var primo  = elementi[0];
    var ultimo = elementi[elementi.length - 1];

    if (evento.shiftKey && document.activeElement === primo) {
      evento.preventDefault();
      ultimo.focus();
    } else if (!evento.shiftKey && document.activeElement === ultimo) {
      evento.preventDefault();
      primo.focus();
    }
  });

  /* Tornando oltre i 1000px con il pannello aperto, le voci sono di nuovo
     nella barra: il pannello va chiuso, altrimenti copre la pagina. */
  if (window.matchMedia) {
    var largo = window.matchMedia('(min-width: 1001px)');
    var reagisci = function (evento) {
      if (evento.matches && !pannello.hidden) {
        nascondi();
      }
    };
    if (largo.addEventListener) {
      largo.addEventListener('change', reagisci);
    } else if (largo.addListener) {
      largo.addListener(reagisci);
    }
  }
})();
