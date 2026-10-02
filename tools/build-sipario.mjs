/**
 * La fotografia sfocata del sito, dietro la pagina del codice d'accesso.
 *
 *     node tools/build-sipario.mjs http://127.0.0.1:8080
 *
 * Fotografa la home (schermo grande e telefono) da un sito con la porta
 * spenta (SITE_ACCESS_CODE=off), la sfoca e scrive due JPG in
 * public/assets/img/sipario/. Lo sfocato si fa qui, prima di salvare: nel
 * file non resta niente di leggibile, e chi apre l'immagine non legge il sito.
 *
 * Strumento da tavolo: vuole Node e Playwright (npm i -D playwright, oppure
 * PLAYWRIGHT=/percorso/di/playwright/index.mjs). Non sale sul server.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT || 'playwright');
const sito = (process.argv[2] || 'http://127.0.0.1:8080').replace(/\/$/, '');
const cartella = fileURLToPath(new URL('../public/assets/img/sipario/', import.meta.url));
mkdirSync(cartella, { recursive: true });

const browser = await chromium.launch();
for (const [larghezza, altezza, sfocato, nome] of [[1440, 900, 28, 'sito-1440.jpg'], [390, 844, 14, 'sito-390.jpg']]) {
  const pagina = await (await browser.newContext({
    viewport: { width: larghezza, height: altezza }, reducedMotion: 'reduce', deviceScaleFactor: 1,
  })).newPage();
  const risposta = await pagina.goto(sito + '/it/', { waitUntil: 'networkidle' });
  if (!risposta || risposta.status() !== 200 || (await pagina.locator('.adm--sipario').count()) > 0) {
    throw new Error(`La home non si apre (o c'è la porta del codice): avvia il sito con SITE_ACCESS_CODE=off.`);
  }
  await pagina.evaluate(() => document.fonts.ready);
  const foto = (await pagina.screenshot({ type: 'png' })).toString('base64');

  // Sfocata su una tela un po' più grande, così i bordi restano pieni.
  const dati = await pagina.evaluate(async ({ foto, larghezza, altezza, sfocato }) => {
    const img = new Image();
    img.src = 'data:image/png;base64,' + foto;
    await img.decode();
    const tela = document.createElement('canvas');
    tela.width = larghezza; tela.height = altezza;
    const c = tela.getContext('2d');
    c.filter = `blur(${sfocato}px)`;
    const margine = sfocato * 3;
    c.drawImage(img, -margine, -margine, larghezza + margine * 2, altezza + margine * 2);
    return tela.toDataURL('image/jpeg', 0.72).split(',')[1];
  }, { foto, larghezza, altezza, sfocato });

  writeFileSync(cartella + nome, Buffer.from(dati, 'base64'));
  console.log(`  ok  ${nome} — ${larghezza}×${altezza}, sfocata ${sfocato}px`);
}
await browser.close();
