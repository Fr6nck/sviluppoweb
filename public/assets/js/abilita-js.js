/* Una riga sola, caricata nel <head> senza defer.
 *
 * Dice al foglio di stile che il JavaScript c'è. Serve perché il CSS parte
 * dal caso SENZA JavaScript — voci di navigazione in chiaro, nessun pulsante
 * che apra un pannello che non si aprirebbe — e da lì accende l'altro.
 *
 * Sta in un file suo e non dentro site.js perché quello è «defer»: gira dopo
 * il parsing, e le voci di riserva farebbero in tempo a comparire e sparire,
 * spostando la pagina sotto le dita di chi sta già leggendo. Sono sessanta
 * byte, serviti dal nostro dominio e tenuti in cache per un anno.
 *
 * Non sta in un <script> in linea perché la politica di sicurezza dei
 * contenuti del sito non ammette script scritti dentro la pagina. */
document.documentElement.classList.add('adv-js');
