/**
 * Contrast audit — every visible text node on every prototype page, measured
 * against its effective background, checked at the WCAG AA threshold
 * (4.5:1 normal, 3:1 large).
 *
 *   PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers \
 *   NODE_PATH=$(npm root -g) node contrast-check.mjs
 *
 * This found three real faults on the first run: a nav CTA at 1.25:1 (a bare
 * `.nav__links a` rule outranking `.btn--primary`), --mh-ink-subtle at 2.78:1
 * on sunken surfaces, and the warning chip at 4.28:1. Values in
 * docs/19-design-system.md are the ones this script verifies.
 */
import { createRequire } from 'node:module';
// createRequire honours NODE_PATH, so this works with a local install or a
// global one (NODE_PATH=$(npm root -g)). A bare ESM import does neither.
const { chromium } = createRequire(import.meta.url)('playwright');

const BASE = 'file://' + process.cwd() + '/';
const b=await chromium.launch();
const p=await b.newPage({viewport:{width:1440,height:950}});
const L=c=>{const[r,g,bl]=c.match(/\d+/g).map(Number).map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)});return 0.2126*r+0.7152*g+0.0722*bl};
const ratio=(a,b2)=>{const l1=L(a),l2=L(b2);return ((Math.max(l1,l2)+0.05)/(Math.min(l1,l2)+0.05))};
for(const f of ['marketing','checkout','onboarding','dashboard','guide','admin','design-system','index']){
  await p.goto(BASE+f+'.html',{waitUntil:'networkidle'});
  const bad=await p.evaluate(()=>{
    const out=[];
    const eff=el=>{let n=el;while(n&&n!==document.documentElement){const bg=getComputedStyle(n).backgroundColor;
      if(bg&&!/rgba\(0, 0, 0, 0\)|transparent/.test(bg))return bg;n=n.parentElement;}return 'rgb(250, 248, 245)';};
    document.querySelectorAll('a,button,.chip,.field__label,.field__help,p,span,td,th,li,strong,h1,h2,h3').forEach(el=>{
      const r=el.getBoundingClientRect(); if(r.width<4||r.height<4)return;
      if(!el.textContent.trim())return;
      if([...el.children].some(c=>c.textContent.trim()===el.textContent.trim()))return;
      const cs=getComputedStyle(el);
      if(cs.visibility==='hidden'||cs.display==='none'||cs.opacity==='0')return;
      out.push({t:el.textContent.trim().slice(0,38),fg:cs.color,bg:eff(el),
                size:parseFloat(cs.fontSize),w:cs.fontWeight});
    });
    return out;
  });
  const fails=bad.map(x=>({...x,cr:ratio(x.fg,x.bg)}))
    .filter(x=>{const large=x.size>=24||(x.size>=18.66&&Number(x.w)>=700);return x.cr < (large?3:4.5);});
  if(fails.length){console.log(`\n${f}.html`);
    const seen=new Set();
    fails.forEach(x=>{const k=x.t+x.fg;if(seen.has(k))return;seen.add(k);
      console.log(`  ${x.cr.toFixed(2)}:1  ${x.size}px  "${x.t}"  fg=${x.fg} bg=${x.bg}`);});}
  else console.log(`${f}.html  all text passes`);
}
await b.close();
