# Kleaner OS — Brand Guide

> The operating system for your car wash. **Agua + chispa**: water and a spark.

## 1. Idea

Kleaner OS replaces the notebook of a Colombian car wash with a fast, friendly system.
The brand has to feel like **clean water and a job done fast**, and it has to be readable
on a phone with wet hands in bright sunlight.

| Trait | What it means in the product |
|---|---|
| **Limpio** (clean) | Plenty of white space, one clear action per screen, no clutter. |
| **Rápido** (fast) | Big touch targets, autocomplete by plate, motion that is short and useful. |
| **Confiable** (trustworthy) | Deep blue, honest numbers, no invented metrics or testimonials. |
| **Con chispa** (a spark) | One warm yellow accent, borrowed from Colombian license plates, used sparingly. |

## 2. Name and mark

- Write it **Kleaner OS** (capital K, capital O and S). In UI chrome the "OS" sits in a small yellow pill.
- The mark is a **water drop with a spark**: a cyan-to-blue gradient drop and a four-point yellow star.
  Source of truth: `public/favicon.svg` (with navy tile) and `public/logo.svg` (mark only).
  The Vue component `AppLogoIcon.vue` must stay in sync with them.
- Keep clear space equal to half the drop's width around the mark. Do not stretch, recolor, outline, rotate or put it on busy photos.
- Raster icons (`favicon.ico`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`) are rendered from the same geometry.

## 3. Color

Tokens live in `resources/css/app.css` (HSL triplets) and are exposed by `tailwind.config.js`.

| Role | Light | Dark | Notes |
|---|---|---|---|
| Primary (actions, links, prices) | `#0369A1` | cyan `hsl(188 86% 53%)` | Deep aqua in light, bright cyan in dark. |
| Ink (text, sidebar) | `#0C2036` / navy `hsl(210 62% 11%)` | `hsl(200 30% 96%)` | The sidebar stays navy in both themes. |
| Spark (CTA highlight, plates, "OS" pill) | `hsl(48 96% 53%)` | same | Always with **black** text. Use sparingly: one spark per screen area. |
| Brand cyan (decoration, gradients) | `hsl(188 86% 53%)` | same | Decorative only in light mode. |

Order status colors (fills, borders and dots; text uses the normal foreground):

| Status | Token | Fill |
|---|---|---|
| En cola | `status-queued` | amber |
| Lavando | `status-washing` | sky blue |
| Listo | `status-ready` | green |
| Entregado | `status-delivered` | slate |

For **text** in amber use `status-queued-text`, which is darker in light mode. Never use the fill amber as text on white.

### Verified contrast (WCAG 2.x, computed from the token values)

| Pair | Ratio |
|---|---|
| Body text on background (light / dark) | 16.1 / 17.3 |
| White on primary button (light) | 5.9 |
| Primary-foreground on primary button (dark) | 10.2 |
| Muted text on white card (light) | 6.4 |
| Muted text on card (dark) | 7.9 |
| Black on spark yellow | 13.7 |
| Sidebar text on navy | 13.9 |
| Destructive text on white (light) | 4.8 |
| Destructive text on card (dark, after fix) | 5.5 |
| `status-queued-text` on white (after fix) | 6.0 |

Everything used for text clears 4.5:1. Re-run the numbers if you change a token.

## 4. Typography

Both families are **self-hosted** through `@fontsource-variable` (no third-party request, works offline).

- **Outfit** (display): headings, numbers, the wordmark. Bold to extra-bold, tight tracking.
- **Plus Jakarta Sans** (body): everything else. 16px base, line-height 1.5.
- Use `tabular-nums` for money and counters so digits do not jump.

## 5. Shape, motion, texture

- **Radius** 0.75rem. Cards are white with a hairline border and a soft shadow.
- **License-plate chip** (`.plate`): yellow, black bold letters, wide tracking. It is the signature element of the board.
- **Motion** is short (250–450 ms) and means something: cards rise in, vehicles slide between columns, numbers count up,
  the register button pulses only when it is ready. Bubbles on the login panel are decorative.
  `prefers-reduced-motion` switches all of it off globally (see `app.css`).
- **Icons**: Lucide only. No emoji as icons.

## 6. Voice

Spanish (Colombia), informal but respectful, direct, practical. Short sentences. Talk like a good shop owner, not like software.

| Do | Don't |
|---|---|
| "Entra a tu lavadero" | "Bienvenido al portal de autenticación" |
| "Registra cada vehículo por placa en segundos" | "Solución integral de gestión vehicular" |
| "Sin celular válido para WhatsApp." | "Error 422: invalid phone" |

Never invent numbers, customers or testimonials. If a figure is not real yet, leave it out.

## 7. Charts

Charts are hand-built SVG/HTML (no chart library) in `resources/js/components/charts/`, following the dataviz rules:

- **One axis, thin marks.** Bars are at most 24px thick, 4px rounded at the data end and square on the baseline.
  Stacked segments are separated by a 2px surface gap, never a border. Grid and axes are solid hairlines.
- **Series colors** are `--viz-1` (blue) and `--viz-2` (orange), exact hex values in `resources/css/app.css`.
  They were run through the palette validator (lightness band, chroma floor, CVD separation, normal-vision
  floor, 3:1 contrast against the surface) in both themes. Dark mode uses a deeper blue (`#0a93d3`) to stay inside the dark band.
  Re-validate before changing either value or adding a third series.
- **Never more series than can be told apart.** A single series needs no legend; two or more get one. Nominal
  categories (vehicle type, service) are ranked bars in one color, not a value ramp. A long tail folds into "Otros".
- **Every chart has a table twin** (toggle in the card header) and a tooltip that also opens with the arrow keys.
- **One filter row above the charts** (7 / 30 / 90 days) scopes every number below it; while it reloads the
  previous render stays on screen, dimmed.
- Deltas always carry an icon and a sign, never color alone, and compare against the previous period of the same length.

## 8. Where things live

| Thing | File |
|---|---|
| Color and radius tokens, plate chip, reduced-motion rule | `resources/css/app.css` |
| Fonts, keyframes, status colors | `tailwind.config.js` |
| Chart components and tokens | `resources/js/components/charts/`, `--viz-*` in `resources/css/app.css` |
| Logo mark and wordmark | `resources/js/components/AppLogoIcon.vue`, `AppLogo.vue` |
| Login and brand panel | `resources/js/layouts/auth/AuthSplitLayout.vue` |
| Landing | `resources/js/pages/Welcome.vue` |
| Icons, manifest, theme color | `public/`, `resources/views/app.blade.php` |

## 9. Open items

- Trademark and domain checks are still pending (SIC search; `kleaneros.com` / `.co` looked free on RDAP but were not purchased).
- The installable app (`manifest.webmanifest`) has no service worker yet, so it does not work offline.
- Type checking (`vue-tsc`) does not run because of the starter kit's `tsconfig`.
