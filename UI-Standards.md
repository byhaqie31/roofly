# Hauz UI Standards

> **Status:** Locked-in for Phase 1 onwards. Update as decisions evolve.
> **Inspiration:** [UI-Inspired.md](UI-Inspired.md) — Lovable's warm-cream, opacity-driven, border-defined system. Read that first; this document adapts it to Hauz.
> **Override priority:** UI-Standards.md > [PROJECT.md](PROJECT.md) > earlier prompts/memory. When in conflict, this wins for visual + interaction language.

---

## Locked-in decisions

These were open in [PROJECT.md § 16](PROJECT.md#L571) — locking now so Phase 1+2 can proceed without ambiguity.

| Decision | Resolved value | Note |
|---|---|---|
| Page background | Warm cream `#F7F4ED` | Supersedes the `#FAFAF7` in [CLAUDE_CODE_PROMPT.md](CLAUDE_CODE_PROMPT.md). |
| Brand accent | Deep terracotta `#993C1D` | Used **sparingly** — primary financial CTAs, brand marks, and overdue status only. Supersedes the brighter `#E76F51`. |
| Display + body font | Inter Variable (self-hosted via `@fontsource-variable/inter`) | Camera Plain Variable from Lovable is the spiritual model; Inter is the implementable substitute. Apply Lovable's principles (negative letter-spacing at display, narrow weight range) to Inter. |
| Status colors | Single muted set, see § 1.5 | Lovable's "no saturated accents" rule is bent — but only inside status pills. |
| Dark mode | Required (per Phase 1) | Strategy in § 7. |

Still open: logo direction, domain — UI-agnostic, defer.

---

## 1. Foundation tokens

All design tokens live in `frontend/assets/css/tokens.css` and mirror to `tailwind.config.ts`. Use the CSS variable everywhere; never hardcode the hex.

### 1.1 Surface

```css
--surface-page:        #F7F4ED;  /* page bg, default card surface */
--surface-button-dark: #1C1C1C;  /* primary dark button */
--surface-on-dark:     #FCFBF8;  /* text + icons on dark surfaces */
```

### 1.2 Text (opacity-derived from #1C1C1C)

```css
--text-primary: #1C1C1C;                 /* headings, primary text */
--text-strong:  rgba(28, 28, 28, 0.83);  /* strong secondary */
--text-body:    rgba(28, 28, 28, 0.82);  /* body copy */
--text-muted:   #5F5F5D;                 /* secondary, captions, placeholders */
--text-faint:   rgba(28, 28, 28, 0.40);  /* disabled, decorative */
```

### 1.3 Borders

```css
--border-passive:     #ECEAE4;                /* cards, dividers, images — default */
--border-interactive: rgba(28, 28, 28, 0.40); /* outlined buttons, focused inputs */
--border-hover:       rgba(28, 28, 28, 0.60); /* on hover for bordered elements */
```

### 1.4 Brand accent (use sparingly)

```css
--accent:       #993C1D;                  /* deep terracotta */
--accent-hover: #7A2F17;
--accent-soft:  rgba(153, 60, 29, 0.08);  /* tint for active nav items, badges */
```

### 1.5 Status (Hauz extensions)

The Lovable system bans saturated accents. We bend the rule **only** for status pills, where meaning can't be encoded in neutrals alone. Keep them muted, warm-toned, never neon.

```css
/* Unit status */
--status-vacant:      #5F5F5D;  /* same as muted text — neutral */
--status-occupied:    #2F6B4A;  /* deep, calm green */
--status-maintenance: #B0731A;  /* warm amber */

/* Invoice / payment status */
--status-paid:      #2F6B4A;
--status-pending:   #5F5F5D;
--status-overdue:   #993C1D;  /* the only place accent doubles up */
--status-cancelled: rgba(28, 28, 28, 0.40);

/* Agreement status */
--status-draft:      #5F5F5D;
--status-active:     #2F6B4A;
--status-expired:    rgba(28, 28, 28, 0.40);
--status-terminated: #993C1D;

/* Ticket priority */
--priority-low:    #5F5F5D;
--priority-medium: #B0731A;
--priority-high:   #993C1D;
```

Each status color has a paired `-soft` variant at 8% opacity for pill backgrounds. Status colors appear **only inside pills** — never in headings, body, or card backgrounds.

### 1.6 Shadows

```css
--shadow-button-inset:
  rgba(255, 255, 255, 0.20) 0 0.5px 0 0 inset,
  rgba(0, 0, 0, 0.20)       0 0 0 0.5px inset,
  rgba(0, 0, 0, 0.05)       0 1px 2px 0;
--shadow-focus: rgba(0, 0, 0, 0.10) 0 4px 12px;
--shadow-modal: 0 16px 48px rgba(28, 28, 28, 0.12);
```

### 1.7 Radius

```css
--radius-xs:   4px;    /* micro */
--radius-sm:   6px;    /* buttons, inputs, nav items */
--radius-md:   8px;    /* compact cards */
--radius-lg:   12px;   /* standard cards, image containers */
--radius-xl:   16px;   /* large containers, modal */
--radius-pill: 9999px; /* status pills, icon toggles */
```

---

## 2. Typography

### 2.1 Family

- **Primary:** `'Inter Variable', ui-sans-serif, system-ui, sans-serif`
- Self-host via `@fontsource-variable/inter`. No Google Fonts CDN (privacy + perf).
- BM and EN both use Inter — no language-specific font swap. BM lines run ~10–15% longer than EN; give containers breathing room.

### 2.2 Weights

Two weights only. **Do not use 700+.**

- **400** — body, UI labels, links, button text
- **600** — headings, emphasis

### 2.3 Scale

| Role | Size | Weight | Line height | Letter-spacing |
|---|---|---|---|---|
| Display hero | 60px (3.75rem) | 600 | 1.05 | -1.5px |
| Section heading | 48px (3rem) | 600 | 1.00 | -1.2px |
| Subheading | 36px (2.25rem) | 600 | 1.10 | -0.9px |
| Card title | 20px (1.25rem) | 400 | 1.25 | normal |
| Body large | 18px (1.125rem) | 400 | 1.38 | normal |
| Body | 16px (1rem) | 400 | 1.50 | normal |
| Caption | 14px (0.875rem) | 400 | 1.50 | normal |
| Micro | 12px (0.75rem) | 400 | 1.40 | normal |

### 2.4 Rules

- **Sentence case** in all UI strings (already in [PROJECT.md § 17](PROJECT.md#L584)). No Title Case. No ALL CAPS except 3-letter status pills if absolutely needed.
- Negative letter-spacing only at display sizes (≥36px). Body always normal.
- `tabular-nums` font-feature on all money columns and timestamps so digits align.

---

## 3. Components

### 3.1 Buttons

| Variant | Bg | Text | Border | Use |
|---|---|---|---|---|
| **Primary dark** | `#1C1C1C` | `#FCFBF8` | none + `--shadow-button-inset` | The single primary action per screen — Pay rent, Send for review, Activate agreement |
| **Ghost** | transparent | `#1C1C1C` | `1px solid var(--border-interactive)` | Secondary action when a primary is present |
| **Cream** | `#F7F4ED` | `#1C1C1C` | none | Tertiary, toolbar, table-row actions |
| **Pill icon** | `#F7F4ED` | `#1C1C1C` | `--shadow-button-inset` at 0.5 opacity | Filter toggles, view-mode switches — never primary CTA |
| **Accent** | `#993C1D` | `#FCFBF8` | none + `--shadow-button-inset` | Reserved: tenant "Pay now", brand wordmark CTA. Not a default. |

**Sizes:**
- Default — 8px / 16px padding, 16px text
- Small — 6px / 12px padding, 14px text (tables, inline)
- Large — 12px / 24px padding, 16px text (auth, empty-state CTAs)

**States:**
- Hover — bg shifts to `rgba(28,28,28,0.04)` on light variants; opacity 0.92 on dark variants
- Active — opacity 0.80
- Focus-visible — adds `--shadow-focus`. Never disable.
- Disabled — opacity 0.40, no pointer

### 3.2 Cards

```
bg: var(--surface-page)
border: 1px solid var(--border-passive)
radius: var(--radius-lg)
padding: 24px (standard), 16px (compact)
box-shadow: NONE
```

If you reach for `box-shadow` on a card, you're solving the wrong problem. Use a border or empty space.

### 3.3 Inputs & forms

```
bg: var(--surface-page)
border: 1px solid var(--border-passive)
radius: var(--radius-sm)
height: 40px (default), 48px (auth)
placeholder: var(--text-muted)
focus: border var(--border-interactive) + var(--shadow-focus)
```

VeeValidate error messages: 14px, `var(--accent)`, 4px below the field. Pair with a Lucide `alert-circle` icon — never color-only.

### 3.4 Status pills

```
display: inline-flex
padding: 4px 10px
radius: var(--radius-pill)
font: 12px / 1 / weight 500, tabular-nums
bg: var(--status-X-soft)   /* 8% tint of the status color */
text: var(--status-X)
border: none
```

One pill per row; never stack pills.

### 3.5 Tables (TanStack)

- Header row — bg `var(--surface-page)`, text `var(--text-strong)` 14px weight 600
- Row divider — `1px solid var(--border-passive)`
- Hover — row bg `rgba(28,28,28,0.03)`
- Selected — row bg `var(--accent-soft)`
- Sort indicators — Lucide `chevrons-up-down` / `chevron-up` / `chevron-down`, `var(--text-muted)`

### 3.6 Money display

- Stored as cents (per [ADR-002](PROJECT.md#L370)), formatted at the render edge.
- Format: `RM 1,500.00` — non-breaking space between `RM` and the number, comma thousands, always two decimals.
- Negative: `−RM 250.00` (Unicode minus `−`, not hyphen).
- Totals / emphasis lines: `var(--text-primary)`, weight 600.
- Always `tabular-nums` for column alignment.
- BM and EN both use `RM ...` — no `MYR` symbol in UI.

### 3.7 Navigation (sidebar + topbar)

**Sidebar:**
- Bg `var(--surface-page)`, no border (right edge separated by content shift)
- Item — 12px / 16px padding, radius `var(--radius-sm)`, 14px text
- Inactive — `var(--text-strong)`, transparent bg
- Active — `var(--accent-soft)` bg, `var(--accent)` text, no left bar
- Lucide icon at 18px, paired with label

**Topbar:**
- 64px tall, `1px solid var(--border-passive)` on bottom
- Right-aligned controls: language switcher, dark-mode toggle, user menu

### 3.8 Empty states

Every list view (properties, units, tenants, agreements, invoices, tickets) needs an empty state. Critical for Cik Aminah's first hour.

```
center column, vertical:
  Lucide icon 64px, var(--text-faint)
  ↓ 24px
  Title 20px weight 600
  ↓ 8px
  Description 14px var(--text-muted)  /* one sentence — never paragraphs */
  ↓ 24px
  Primary CTA
```

---

## 4. Layout

### 4.1 Spacing

Tailwind defaults: `2 (8px)`, `3 (12px)`, `4 (16px)`, `6 (24px)`, `8 (32px)`, `10 (40px)`, `14 (56px)`, `20 (80px)`, `24 (96px)`, `32 (128px)`.

- **App pages** — default `gap-6` between cards, `gap-3` inside cards.
- **Marketing pages (Phase 7)** — `py-20` to `py-32` between sections for editorial breathing room.

### 4.2 Containers

- Marketing — `max-w-[1200px]` centered, `px-6` mobile / `px-10` desktop.
- App tables — `max-w-[1280px]`.
- Forms — `max-w-[840px]` for readable single-column.
- Auth — `max-w-[420px]` centered card.

### 4.3 Breakpoints

Tailwind defaults are fine. Sidebar collapses to drawer at `md` (768px).

---

## 5. Depth & elevation

Reach for the lowest level that works.

| Level | Treatment | Use |
|---|---|---|
| 0 — Flat | nothing | Page bg, paragraph text — **default** |
| 1 — Bordered | `1px solid var(--border-passive)` | Cards, images, dividers |
| 2 — Inset | `var(--shadow-button-inset)` | Primary dark / accent buttons only |
| 3 — Soft focus | `var(--shadow-focus)` | Active / focused interactive elements |
| 4 — Modal lift | `var(--shadow-modal)` + `1px solid var(--border-passive)` | Modals, popovers — sparingly |

**No drop shadows on cards. Ever.**

---

## 6. Iconography

- **Library:** Lucide (per [PROJECT.md § 7](PROJECT.md#L226))
- **Stroke:** 1.5px (default — do not change)
- **Sizes:** 16px inline, 18px nav, 24px headers, 64px empty states
- **Color:** inherit from text — never colorize
- One concept = one icon, mapped centrally in `frontend/components/ui/Icon.vue`

Domain icon vocabulary (lock these now to stay consistent):

| Concept | Lucide icon |
|---|---|
| Property | `building-2` |
| Unit | `door-open` |
| Tenant | `user` |
| Owner | `user-cog` |
| Agreement | `file-text` |
| Invoice | `receipt` |
| Payment | `credit-card` |
| Ticket / maintenance | `wrench` |
| Notification | `bell` |
| Settings | `settings` |
| Dashboard | `layout-dashboard` |

---

## 7. Dark mode

Lovable doesn't ship a dark mode. We have to. Strategy: invert the foundation, keep the warmth — never go cold-blue.

```css
[data-theme="dark"] {
  --surface-page:        #1C1A17;  /* warm near-black, NOT pure black */
  --surface-button-dark: #F7F4ED;  /* swap roles */
  --surface-on-dark:     #1C1A17;

  --text-primary: #F7F4ED;
  --text-strong:  rgba(247, 244, 237, 0.92);
  --text-body:    rgba(247, 244, 237, 0.82);
  --text-muted:   #9A9794;
  --text-faint:   rgba(247, 244, 237, 0.40);

  --border-passive:     #2D2A26;
  --border-interactive: rgba(247, 244, 237, 0.30);
  --border-hover:       rgba(247, 244, 237, 0.50);

  --accent:       #C44D26;  /* lifted for contrast on dark */
  --accent-hover: #D55D32;
  --accent-soft:  rgba(196, 77, 38, 0.12);

  --shadow-button-inset:
    rgba(255, 255, 255, 0.06) 0 0.5px 0 0 inset,
    rgba(0, 0, 0, 0.40)       0 0 0 0.5px inset,
    rgba(0, 0, 0, 0.20)       0 1px 2px 0;
  --shadow-focus: rgba(255, 255, 255, 0.10) 0 4px 12px;
}
```

Every Phase 2+ component is verified in **both** modes before merge. Dark-mode-only bugs are not acceptable.

---

## 8. PDF outputs (agreements, receipts)

Browsershot renders Tailwind HTML to PDF. PDFs are brand surfaces too — same system, with print-fidelity caveats:

- Background: pure white `#FFFFFF` (cream tints unpredictably across PDF readers / printers)
- Text: `#1C1C1C` solid (no opacity — PDFs flatten alpha unevenly)
- Borders: `#ECEAE4` solid (survives flattening)
- Font: Inter, embedded via `@font-face` data URL inside the Blade template (no external network at render time)
- Margins: 24mm top/bottom, 20mm left/right
- Header: Hauz wordmark left, document type right (e.g., "Tenancy agreement")
- Footer: generated date, "Page X of Y", document ID — `var(--text-muted)` 10pt
- Money: same `RM 1,500.00` format, `tabular-nums`

PDFs are the **only** place we deviate from cream.

---

## 9. Accessibility

- **Contrast:** WCAG AA minimum (4.5:1 body, 3:1 large). Opacity-derived text passes against cream — verified.
- **Focus visible:** `--shadow-focus` on every `:focus-visible`. Never `outline: none` without a replacement.
- **Keyboard:** every flow tab-traversable. Modals trap focus.
- **Touch targets:** 40px minimum (default 8/16 button hits this).
- **Errors:** never color-only — pair with Lucide `alert-circle` + text.
- **Language:** `<html lang>` switches with i18n locale (`en` / `ms`).

---

## 10. Implementation map

| Concern | Lives in |
|---|---|
| CSS tokens | `frontend/assets/css/tokens.css` |
| Tailwind config (token mirror) | `frontend/tailwind.config.ts` |
| Inter font | `frontend/plugins/fonts.client.ts` via `@fontsource-variable/inter` |
| UI primitives | `frontend/components/ui/` — `Button.vue`, `Card.vue`, `Input.vue`, `Pill.vue`, `EmptyState.vue`, `Icon.vue`, `MoneyDisplay.vue` |
| Storybook (Phase 6) | `frontend/.storybook/` |
| PDF templates | `backend/resources/views/pdfs/agreement.blade.php`, `.../receipt.blade.php` |
| Money format helper | `frontend/composables/useMoney.ts` |

---

## 11. Hard rules — do not break

1. Page background is **always** `#F7F4ED` (light) or `#1C1A17` (dark). Pure white only inside generated PDFs.
2. **No box-shadow on cards.** Borders only.
3. **Two weights** — 400 and 600. Never 700.
4. **Sentence case** in all strings, BM and EN.
5. **Cents to display** at the render edge — never store formatted strings.
6. Status colors live **only** inside pills.
7. Negative letter-spacing **only** at display sizes (≥36px).
8. Lucide icons inherit text color. No icon recoloring.
9. Every interactive element has a visible focus state via `--shadow-focus`.
10. When in doubt, reach for empty space before reaching for a divider or a shadow.
