# Studio Nova — One-Page Portfolio Site

A responsive one-page website built with plain **HTML, CSS and JavaScript** — no frameworks, no build step. Design and color mood matched to designgal.studio: butter-cream background, deep navy ink, bright yellow CTAs and candy pink / peach / lilac accents, set in the Figtree typeface.

## Sections

1. **Hero** — headline, CTA buttons, stats and a floating image collage, with a scrolling services marquee
2. **Work** — selected projects grid with hover effects
3. **Services** — three service cards
4. **About** — studio intro with portrait and client quote
5. **Contact** — validated contact form (just above the footer)

Plus a sticky header with mobile hamburger menu and a footer with navigation and social links.

## Run it locally

No build needed. Either open `index.html` directly in a browser, or serve the folder:

```bash
# Python
python3 -m http.server 8000

# or Node
npx serve .
```

Then open http://localhost:8000

## Features

- Fully responsive (desktop → tablet → mobile breakpoints at 900px / 720px)
- Scroll-reveal animations via `IntersectionObserver`
- Active nav link highlighting while scrolling
- Mobile fullscreen menu with animated hamburger
- Client-side contact form validation (required fields + email format) with success message
- Local SVG illustrations — works fully offline (only the Google Fonts link needs a connection; system fonts are used as fallback)
- Respects `prefers-reduced-motion`

## Structure

```
├── index.html
├── css/style.css
├── js/main.js
└── assets/         # local SVG illustrations
```

## Customizing

- Colors and fonts are defined as CSS custom properties at the top of `css/style.css` (`:root`)
- Replace the SVGs in `assets/` with your own photos/images (any format) — keep the same file names or update `index.html`
- The contact form is front-end only; wire the `submit` handler in `js/main.js` to your backend or a service like Formspree to actually send messages
