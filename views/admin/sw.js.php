<?php
/**
 * Il service worker dell'app dell'area riservata, servito da
 * AdminController a /admin/sw.js con il campo d'azione /admin.
 *
 * Fa tre cose:
 *   - mostra le notifiche che arrivano (Admin\Notifiche), con l'icona della
 *     rosa; toccandone una si apre la pagina giusta dell'area riservata;
 *   - senza rete, al posto di una pagina d'errore del browser mostra una
 *     pagina «senza connessione» con la grafica del sito;
 *   - nient'altro: non tiene in memoria nessuna pagina del pannello, che
 *     contiene dati personali.
 *
 * @var string $base    la cartella del sito, «» alla radice
 * @var string $icona   l'indirizzo dell'icona 192 px
 * @var string $badge   l'indirizzo del distintivo monocromo
 */
$campo = $base . '/admin';
$offline = '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<meta name="theme-color" content="#f8f1e3"><title>Senza connessione — Arco del Vento</title></head>'
    . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f8f1e3;color:#4b2c1f;font-family:Sora,-apple-system,\'Segoe UI\',Helvetica,Arial,sans-serif;">'
    . '<main style="max-width:340px;margin:24px;padding:32px 28px;background:#fffdf8;border:1px solid #e4d8c3;border-radius:25px;text-align:center;">'
    . '<img src="' . htmlspecialchars($icona, ENT_QUOTES, 'UTF-8') . '" alt="" width="72" height="72" style="border-radius:18px">'
    . '<p style="margin:18px 0 6px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#813131;font-weight:600">Area riservata</p>'
    . '<h1 style="margin:0 0 12px;font-family:Prata,Georgia,serif;font-weight:400;font-size:26px;line-height:1.2">Senza connessione</h1>'
    . '<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#6b6257">Il telefono non è in rete. L\'area riservata si apre appena torna la connessione.</p>'
    . '<a href="" style="display:inline-block;padding:13px 26px;border-radius:999px;background:#813131;color:#fffdf8;font-weight:600;text-decoration:none">Riprova</a>'
    . '</main></body></html>';
?>
/* Arco del Vento — area riservata. Notifiche e pagina senza connessione: vedi views/admin/sw.js.php. */
'use strict';

const CAMPO = <?= json_encode($campo, JSON_UNESCAPED_SLASHES) ?>;
const ICONA = <?= json_encode($icona, JSON_UNESCAPED_SLASHES) ?>;
const BADGE = <?= json_encode($badge, JSON_UNESCAPED_SLASHES) ?>;
const OFFLINE = <?= json_encode($offline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (evento) => evento.waitUntil(self.clients.claim()));

// Una notifica: titolo, testo, indirizzo e argomento arrivano cifrati dal sito.
self.addEventListener('push', (evento) => {
  let d = {};
  try { d = evento.data ? evento.data.json() : {}; } catch (e) { d = { b: evento.data ? evento.data.text() : '' }; }
  evento.waitUntil(self.registration.showNotification(d.t || 'Arco del Vento', {
    body: d.b || 'Novità nell\'area riservata.',
    icon: d.i || ICONA,
    badge: d.d || BADGE,
    tag: d.g || undefined,
    renotify: Boolean(d.g),
    lang: 'it',
    data: { url: d.u || (self.location.origin + CAMPO) },
  }));
});

// Toccandola: si torna all'app se è aperta, sulla pagina giusta; altrimenti si apre.
self.addEventListener('notificationclick', (evento) => {
  evento.notification.close();
  const url = new URL((evento.notification.data && evento.notification.data.url) || CAMPO, self.location.origin);
  const meta = url.origin === self.location.origin ? url.href : self.location.origin + CAMPO;
  evento.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((finestre) => {
    for (const f of finestre) {
      if (new URL(f.url).pathname.startsWith(CAMPO) && 'focus' in f) {
        return f.focus().then((g) => (g && 'navigate' in g ? g.navigate(meta) : g)).catch(() => self.clients.openWindow(meta));
      }
    }
    return self.clients.openWindow(meta);
  }));
});

// Senza rete: la pagina «senza connessione», non quella d'errore del browser. Niente in memoria.
self.addEventListener('fetch', (evento) => {
  if (evento.request.mode !== 'navigate') return;
  evento.respondWith(fetch(evento.request).catch(() => new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
});
