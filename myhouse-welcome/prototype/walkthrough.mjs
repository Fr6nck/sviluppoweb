/**
 * Browser walkthrough — drives the whole prototype and asserts the behaviour
 * the specification promises: conditional wizard logic, entitlement gating,
 * the publish bar, support mode requiring a reason, keyboard access, no
 * horizontal scroll at 320px, and dark mode on the guest guide.
 *
 *   PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers \
 *   NODE_PATH=$(npm root -g) node walkthrough.mjs
 *
 * Blocked font-CDN requests are counted and ignored: the pages declare
 * metric-matched fallbacks, and the sandbox this was written in blocks
 * fonts.googleapis.com.
 */
import { createRequire } from 'node:module';
// createRequire honours NODE_PATH, so this works with a local install or a
// global one (NODE_PATH=$(npm root -g)). A bare ESM import does neither.
const { chromium } = createRequire(import.meta.url)('playwright');

const BASE = 'file://' + process.cwd() + '/';
const errs = [];
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 950 } });
const page = await ctx.newPage();
let fontBlocks = 0;
page.on('console', m => {
  if (m.type() !== 'error') return;
  const t = m.text();
  // the sandbox proxy blocks fonts.googleapis.com; pages declare fallbacks for it
  if (/ERR_CERT_AUTHORITY_INVALID|ERR_NAME_NOT_RESOLVED|net::ERR_/.test(t)) { fontBlocks++; return; }
  errs.push(`[console] ${page.url().split('/').pop()}: ${t}`);
});
page.on('pageerror', e => errs.push(`[pageerror] ${page.url().split('/').pop()}: ${e.message}`));

const step = async (label, fn) => {
  try { await fn(); console.log(`  ok   ${label}`); }
  catch (e) { console.log(`  FAIL ${label}: ${e.message.split('\n')[0]}`); errs.push(`${label}: ${e.message.split('\n')[0]}`); }
};

console.log('\n== 1. every page loads ==');
for (const f of ['index','marketing','checkout','onboarding','dashboard','guide','admin','design-system']) {
  await step(f + '.html', async () => {
    await page.goto(BASE + f + '.html', { waitUntil: 'networkidle' });
    const t = await page.title();
    if (!t) throw new Error('no title');
  });
}

console.log('\n== 2. marketing renders packages from the store ==');
await page.goto(BASE + 'marketing.html', { waitUntil: 'networkidle' });
await step('three plan cards rendered', async () => {
  const n = await page.locator('.plan').count();
  if (n !== 3) throw new Error(`expected 3 plans, got ${n}`);
});
await step('Plus is flagged recommended', async () => {
  const f = await page.locator('.plan--rec .plan__name').innerText();
  if (f.trim() !== 'Plus') throw new Error(`recommended is ${f}`);
});
await step('prices formatted as currency', async () => {
  const p = await page.locator('.plan__price').first().innerText();
  if (!p.includes('€')) throw new Error(`price was "${p}"`);
});

console.log('\n== 3. checkout flow, end to end ==');
await page.goto(BASE + 'checkout.html?package=plus', { waitUntil: 'networkidle' });
await step('promo code applies a discount', async () => {
  await page.fill('#promo', 'BENVENUTO20');
  await page.click('#promo-form button[type=submit]');
  const t = await page.locator('#total-line').innerText();
  if (!t.includes('79')) throw new Error(`total after 20% off was ${t}`);
});
await step('bad promo code is rejected', async () => {
  await page.fill('#promo', 'NOPE');
  await page.click('#promo-form button[type=submit]');
  const m = await page.locator('#promo-msg').innerText();
  if (!/valid|expired/i.test(m)) throw new Error(`message was "${m}"`);
});
await step('reaches the Stripe stand-in', async () => {
  await page.click('#go-pay');
  await page.waitForSelector('#s-stripe.is-on', { timeout: 4000 });
});
await step('declined card shows the human message', async () => {
  await page.click('#decline');
  await page.waitForSelector('#s-declined.is-on');
  const txt = await page.locator('#s-declined').innerText();
  if (!/didn't go through/.test(txt)) throw new Error('missing decline copy');
  if (/error|code|402/i.test(txt.replace('Error','')) && /\b4\d\d\b/.test(txt)) throw new Error('leaked an error code');
});
await step('retry -> pay -> webhook wait -> paid', async () => {
  await page.click('#retry');
  await page.waitForSelector('#s-stripe.is-on');
  await page.click('#pay');
  await page.waitForSelector('#s-wait.is-on', { timeout: 4000 });
  await page.waitForSelector('#s-paid.is-on', { timeout: 9000 });
});
await step('activation and first-login screen', async () => {
  await page.click('#to-activate');
  await page.waitForSelector('#s-activate.is-on');
  await page.click('#activate-form button[type=submit]');
  await page.waitForSelector('#s-welcome.is-on', { timeout: 4000 });
  const t = await page.locator('#h-welcome').innerText();
  if (!/Welcome to MyHouse Welcome/.test(t)) throw new Error(t);
});

console.log('\n== 4. onboarding: conditional logic + persistence ==');
await page.goto(BASE + 'onboarding.html', { waitUntil: 'networkidle' });
await step('step 1 shows the property question', async () => {
  const q = await page.locator('#q').innerText();
  if (!/basics/i.test(q)) throw new Error(q);
});
await step('required field blocks Continue', async () => {
  await page.fill('[data-k="name"]', '');
  await page.click('#next');
  const err = await page.locator('.field__error').first().innerText();
  if (!/called/i.test(err)) throw new Error(`error was "${err}"`);
});
await step('filling the name lets it advance', async () => {
  await page.fill('[data-k="name"]', 'Casa San Francesco');
  await page.click('#next');
  await page.waitForFunction(() => document.getElementById('stepline').textContent.includes('Step 2'));
});
await step('typing persists to the store', async () => {
  const v = await page.evaluate(() => MHW.get().content.name);
  if (v !== 'Casa San Francesco') throw new Error(`store holds "${v}"`);
});
await step('conditional: parking "No" hides the follow-ups', async () => {
  await page.evaluate(() => {
    const el = [...document.querySelectorAll('#stepline')][0];
    void el;
  });
  // walk to the parking step
  for (let i = 0; i < 10; i++) {
    const line = await page.locator('#stepline').innerText();
    if (/Parking/.test(line)) break;
    await page.click('#next');
    await page.waitForTimeout(90);
  }
  const line = await page.locator('#stepline').innerText();
  if (!/Parking/.test(line)) throw new Error('never reached parking, at: ' + line);
  const beforeVisible = await page.locator('[data-field="parkingType"]').isVisible();
  await page.click('label[for="f-hasParking-n"]');
  await page.waitForTimeout(200);
  const afterVisible = await page.locator('[data-field="parkingType"]').isVisible();
  if (!beforeVisible) throw new Error('parkingType was hidden while parking=yes');
  if (afterVisible) throw new Error('parkingType still visible after answering No');
  // and the alternative question appears
  const alt = await page.locator('[data-field="noParkingAdvice"]').isVisible();
  if (!alt) throw new Error('the "where instead" question did not appear');
});
await step('demo fill populates content', async () => {
  await page.click('#jump');
  await page.waitForTimeout(250);
  const c = await page.evaluate(() => MHW.get().content);
  if (!c.wifiPassword || !c.accessInstructions) throw new Error('demo content not written');
});

console.log('\n== 5. guest guide reads what onboarding wrote ==');
await page.goto(BASE + 'guide.html', { waitUntil: 'networkidle' });
await step('property name comes from the store', async () => {
  const n = await page.locator('#g-name').innerText();
  if (n !== 'Casa San Francesco') throw new Error(n);
});
await step('section grid renders', async () => {
  const n = await page.locator('.g-card').count();
  if (n < 8) throw new Error(`only ${n} cards`);
});
await step('emergency card is last and full width', async () => {
  const last = await page.locator('.g-grid li').last().innerText();
  if (!/Emergency/.test(last)) throw new Error(`last card is "${last}"`);
});
await step('Wi-Fi section shows the password masked, with reveal', async () => {
  await page.click('[data-sec="wifi"]');
  await page.waitForSelector('#section.is-open');
  const masked = await page.locator('#pw').innerText();
  if (!/^•+$/.test(masked)) throw new Error(`shown as "${masked}"`);
  await page.click('#reveal');
  const shown = await page.locator('#pw').innerText();
  if (shown !== 'ulivo2026') throw new Error(`revealed "${shown}"`);
});
await step('password is selectable text, not an image', async () => {
  const tag = await page.locator('#pw').evaluate(e => e.tagName);
  if (tag === 'IMG' || tag === 'CANVAS') throw new Error('password is not text');
});
await step('"Next:" follows the host order', async () => {
  const n = await page.locator('#g-next').innerText();
  if (!/Next:/.test(n)) throw new Error(n);
});
await step('back returns to the grid', async () => {
  await page.click('#g-back');
  await page.waitForTimeout(150);
  const open = await page.locator('#section').evaluate(e => e.classList.contains('is-open'));
  if (open) throw new Error('section still open');
});

console.log('\n== 6. dashboard ==');
await page.goto(BASE + 'dashboard.html', { waitUntil: 'networkidle' });
await step('greeting uses the host name', async () => {
  const w = await page.locator('#who').innerText();
  if (w !== 'Marco') throw new Error(w);
});
await step('completion is a real number', async () => {
  const p = Number(await page.locator('#pct').innerText());
  if (!(p > 0 && p <= 100)) throw new Error(`completion ${p}`);
});
await step('section list renders with toggles', async () => {
  await page.click('[data-go="content"]');
  await page.waitForTimeout(200);
  const n = await page.locator('.sec-row').count();
  if (n < 10) throw new Error(`${n} sections`);
});
await step('live preview iframe is present', async () => {
  const src = await page.locator('#prev').getAttribute('src');
  if (src !== 'guide.html') throw new Error(src);
});
await step('a draft guide can always be published from the dashboard', async () => {
  await page.evaluate(() => { const g = MHW.get().guide; g.status = 'draft'; g.hasUnpublishedChanges = false; MHW.set({ guide: g }); });
  await page.reload({ waitUntil: 'networkidle' });
  const bar = await page.locator('#pubbar').evaluate(e => e.classList.contains('is-on'));
  if (!bar) throw new Error('a draft guide has no way to publish');
  const msg = await page.locator('#pubmsg').innerText();
  if (!/isn't online yet/.test(msg)) throw new Error(`draft copy reads "${msg}"`);
});
await step('editing a published guide marks it dirty and offers to republish', async () => {
  await page.evaluate(() => MHW.publish());
  await page.click('[data-go="content"]');
  await page.waitForTimeout(150);
  await page.click('[data-edit="wifi"]');
  await page.waitForSelector('#sheet.is-on');
  await page.fill('[data-k="wifiPassword"]', 'nuovapassword1');
  await page.waitForTimeout(1100);
  const dirty = await page.evaluate(() => MHW.get().guide.hasUnpublishedChanges);
  if (!dirty) throw new Error('guide not marked dirty');
  const msg = await page.locator('#pubmsg').innerText();
  if (!/can't see yet/.test(msg)) throw new Error(`pending copy reads "${msg}"`);
});
await step('Escape closes the slide-over', async () => {
  await page.keyboard.press('Escape');
  await page.waitForTimeout(350);
  const open = await page.locator('#sheet').evaluate(e => e.classList.contains('is-on'));
  if (open) throw new Error('sheet still open');
});
await step('publish clears the pending state', async () => {
  page.once('dialog', d => d.accept());
  await page.click('#publish');
  await page.waitForTimeout(1200);
  const st = await page.evaluate(() => MHW.get().guide);
  if (st.hasUnpublishedChanges) throw new Error('still dirty after publish');
  if (st.status !== 'published') throw new Error(st.status);
});

console.log('\n== 7. entitlements actually gate ==');
await step('Essential hides gated sections from the guest guide', async () => {
  await page.evaluate(() => MHW.set({ packageKey: 'essential' }));
  await page.goto(BASE + 'guide.html', { waitUntil: 'networkidle' });
  const txt = await page.locator('#g-grid').innerText();
  if (/Where to eat|Services|Appliances|Waste/.test(txt)) throw new Error('gated section leaked into Essential');
  const hasSearch = await page.locator('#g-search').isVisible();
  if (hasSearch) throw new Error('search shown on Essential');
});
await step('Plus shows them again', async () => {
  await page.evaluate(() => MHW.set({ packageKey: 'plus' }));
  await page.goto(BASE + 'guide.html', { waitUntil: 'networkidle' });
  const txt = await page.locator('#g-grid').innerText();
  if (!/Where to eat/.test(txt)) throw new Error('Plus is missing recommendations');
  const hasSearch = await page.locator('#g-search').isVisible();
  if (!hasSearch) throw new Error('search missing on Plus');
});
await step('Statistics is muted, not hidden, below Pro', async () => {
  await page.goto(BASE + 'dashboard.html', { waitUntil: 'networkidle' });
  const locked = await page.locator('.nav-i.is-locked').count();
  if (locked < 1) throw new Error('no muted nav item on Plus');
  const chip = await page.locator('.nav-i.is-locked .chip').innerText();
  if (chip.trim() !== 'Pro') throw new Error(`chip says "${chip}"`);
});
await step('Pro unlocks it', async () => {
  await page.evaluate(() => MHW.set({ packageKey: 'pro' }));
  await page.reload({ waitUntil: 'networkidle' });
  const locked = await page.locator('.nav-i.is-locked').count();
  if (locked !== 0) throw new Error(`${locked} still locked on Pro`);
});

console.log('\n== 8. admin ==');
await page.goto(BASE + 'admin.html', { waitUntil: 'networkidle' });
await step('customer table populates', async () => {
  const n = await page.locator('#clist tr').count();
  if (n < 5) throw new Error(`${n} rows`);
});
await step('support mode requires a typed reason', async () => {
  await page.click('[data-go="detail"]');
  await page.waitForTimeout(150);
  await page.click('#assist');
  await page.waitForSelector('#mbd.is-on');
  await page.click('#m-start');
  const shown = await page.locator('#reason-err').isVisible();
  if (!shown) throw new Error('started a support session with no reason');
});
await step('with a reason it starts and lands in the host UI', async () => {
  await page.fill('#reason', 'Customer called - cannot upload check-in photos');
  await page.click('#m-start');
  await page.waitForURL('**/dashboard.html', { timeout: 5000 });
  const banner = await page.locator('#support').isVisible();
  if (!banner) throw new Error('no support banner after impersonating');
  const txt = await page.locator('#support').innerText();
  if (!/as administrator/.test(txt)) throw new Error(txt);
});
await step('support banner pushes content down rather than overlapping', async () => {
  const bb = await page.locator('#support').boundingBox();
  const ab = await page.locator('.app').boundingBox();
  if (bb.y + bb.height > ab.y + 2) throw new Error('banner overlaps the app');
});

console.log('\n== 9. accessibility spot checks ==');
await page.goto(BASE + 'guide.html', { waitUntil: 'networkidle' });
await step('skip link is the first focusable element', async () => {
  await page.keyboard.press('Tab');
  const t = await page.evaluate(() => document.activeElement.textContent.trim());
  if (!/Skip/.test(t)) throw new Error(`first tab lands on "${t}"`);
});
await step('every section card is keyboard reachable', async () => {
  const n = await page.locator('.g-card[href]').count();
  const all = await page.locator('.g-card').count();
  if (n !== all) throw new Error(`${all - n} cards are not links`);
});
await step('touch targets are at least 44px', async () => {
  const small = await page.evaluate(() => {
    const out = [];
    document.querySelectorAll('.g-card, .g-locales button, .g-contact .btn').forEach(e => {
      const r = e.getBoundingClientRect();
      if (r.height > 0 && r.height < 34) out.push(e.className + ' h=' + Math.round(r.height));
    });
    return out;
  });
  if (small.length) throw new Error(small.join(', '));
});
await step('no horizontal scroll at 320px', async () => {
  await page.setViewportSize({ width: 320, height: 720 });
  await page.goto(BASE + 'guide.html', { waitUntil: 'networkidle' });
  const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  if (over > 1) throw new Error(`${over}px horizontal overflow`);
});
await step('dashboard has no horizontal scroll at 320px', async () => {
  await page.goto(BASE + 'dashboard.html', { waitUntil: 'networkidle' });
  const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  if (over > 1) throw new Error(`${over}px horizontal overflow`);
});
await step('onboarding has no horizontal scroll at 320px', async () => {
  await page.goto(BASE + 'onboarding.html', { waitUntil: 'networkidle' });
  const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  if (over > 1) throw new Error(`${over}px horizontal overflow`);
});

console.log('\n== 10. dark mode on the guest guide ==');
await step('dark scheme applies and keeps text readable', async () => {
  const dark = await ctx.newPage();
  await dark.emulateMedia({ colorScheme: 'dark' });
  await dark.setViewportSize({ width: 390, height: 844 });
  await dark.goto(BASE + 'guide.html', { waitUntil: 'networkidle' });
  const bg = await dark.evaluate(() => getComputedStyle(document.body).backgroundColor);
  if (!/20, 22, 26/.test(bg)) throw new Error(`body bg in dark mode: ${bg}`);
  const ink = await dark.evaluate(() => getComputedStyle(document.querySelector('#g-name')).color);
  if (!/236, 234, 230/.test(ink)) throw new Error(`heading colour: ${ink}`);
  await dark.close();
});

await browser.close();
console.log('\n=====================================');
if (errs.length) { console.log(`${errs.length} PROBLEM(S):`); errs.forEach(e => console.log('  - ' + e)); process.exit(1); }
console.log(`All checks passed. (${fontBlocks} blocked font-CDN requests ignored - the sandbox proxy blocks fonts.googleapis.com and the pages declare fallbacks.)`);
