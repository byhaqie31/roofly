# Roofly UI Standards

> **Status:** Locked-in for Phase 1 onwards. Update as decisions evolve.
> **Inspiration:** [UI-INSPIRED.md](UI-INSPIRED.md) — Lovable's warm-cream, opacity-driven, border-defined system. Read that first; this document adapts it to Roofly.
> **Override priority:** UI-STANDARDS.md > [PROJECT.md](../global/PROJECT.md) > earlier prompts/memory. When in conflict, this wins for visual + interaction language.

---

## Locked-in decisions

These were open in [PROJECT.md § 16](../global/PROJECT.md#L571) — locking now so Phase 1+2 can proceed without ambiguity.

| Decision | Resolved value | Note |
|---|---|---|
| Page background | Warm cream `#F7F4ED` | Supersedes the `#FAFAF7` in [CLAUDE-CODE-PROMPT.md](../global/CLAUDE-CODE-PROMPT.md). |
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

### 1.5 Status (Roofly extensions)

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

**Dark mode requires lighter variants** — the values above are nearly invisible on the `#1c1a17` page bg. Override every status + priority token in `[data-theme="dark"]` and bump `-soft` opacity to ~18%:

```css
/* dark-mode overrides — same semantic mapping, lifted for contrast */
--status-vacant:      #A8A5A1;  /* light grey, matches text-muted */
--status-occupied:    #5FB886;  /* lifted green, still muted */
--status-maintenance: #D99A47;  /* lifted amber */
--status-overdue:     #D96A45;  /* coral, near accent-hover */
--status-cancelled:   rgba(247, 244, 237, 0.5);
--status-expired:     rgba(247, 244, 237, 0.5);
--status-terminated:  #D96A45;
/* …and the corresponding -soft pairs at 18% opacity */
```

Both `:root` (default) and `[data-theme="light"]` (explicit override) carry the light values; `[data-theme="dark"]` carries the lifted ones. Keep the semantic mapping identical across themes — only the perceptual lightness changes.

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

- **Sentence case** in all UI strings (already in [PROJECT.md § 17](../global/PROJECT.md#L584)). No Title Case. No ALL CAPS except 3-letter status pills if absolutely needed.
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
bg: var(--surface-raised)          /* default — raised tone, #ffffff / #2e2b25 */
   | var(--surface-page)           /* opt-out via tone="flat" */
border: 1px solid var(--border-passive)
radius: var(--radius-lg)
padding: 24px (standard), 16px (compact)
box-shadow: NONE
```

Cards default to `tone="raised"` so they read as lifted surfaces against the cream/charcoal page. Drop to `tone="flat"` only when a card is purely a layout container with no content of its own to set apart (rare).

The same `var(--surface-raised)` is applied to other lifted surfaces — Modal, Select dropdown, popovers, and the mobile drawer — so all "panel" surfaces share one tone.

If you reach for `box-shadow` on a card, you're solving the wrong problem. Use a border or empty space — the raised tone already gives the lift.

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

- Stored as cents (per [ADR-002](../global/PROJECT.md#L370)), formatted at the render edge.
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

### 4.4 Detail-page header

<<<<<<< HEAD
Resource detail pages (property, tenant, agreement, etc.) share one header pattern: a single flex row with the **title block on the left** and **resource-level actions on the right, baseline-aligned with the bottom of the title block** (i.e. on the same line as the supporting/address copy, not the eyebrow pill).

```
┌───────────────────────────────────────────────────────────────┐
│ [Eyebrow pill]                                                │
│ Resource title (display-sub)                                  │
│ supporting line · ID · location          [Action]  [Action]   │
=======
Resource detail pages (property, tenant, agreement, etc.) share one header pattern that adapts per breakpoint:

- **Desktop (`sm:` and up)** — title block on the left, resource-level action on the right, baseline-aligned with the bottom of the title block (same line as the supporting copy, not the eyebrow pill). Back link sits alone on its own row above.
- **Mobile (`<sm`)** — back link + action share the top row; title block sits below at full width so a long name never has to compete with the action button.

```
Desktop:
┌───────────────────────────────────────────────────────────────┐
│ ← Back to list                                                │
│                                                               │
│ [Eyebrow pill]                                                │
│ Resource title (display-sub)                                  │
│ supporting line · ID · location                  [Delete]     │
└───────────────────────────────────────────────────────────────┘

Mobile:
┌───────────────────────────────────────────────────────────────┐
│ ← Back to list                                  [Delete]      │
│                                                               │
│ [Eyebrow pill]                                                │
│ Resource title — full width                                   │
│ supporting line                                               │
>>>>>>> 207df866d64c49e3bd853678b573f0023eb84e20
└───────────────────────────────────────────────────────────────┘
```

```vue
<<<<<<< HEAD
<header class="mb-6 flex items-end justify-between gap-4">
  <div class="min-w-0">
=======
<!-- Back-link row: on mobile this row also carries the action; on desktop the action moves down to the title row -->
<div class="mb-6 flex items-center justify-between gap-2">
  <NuxtLink to="/owner/<resource>" class="inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink">
    <Icon name="ArrowLeft" :size="14" />
    {{ $t("...detail.back") }}
  </NuxtLink>
  <!-- Mobile-only action -->
  <Button v-if="resource" variant="ghost" size="sm" class="sm:hidden" @click="...">
    <Icon name="Trash2" :size="14" class="mr-1" />
    {{ $t("...detail.delete") }}
  </Button>
</div>

<!-- Title block: stacks vertically on mobile, becomes a flex row with the action on desktop -->
<header class="mb-6 sm:flex sm:items-end sm:justify-between sm:gap-4">
  <div class="sm:min-w-0">
>>>>>>> 207df866d64c49e3bd853678b573f0023eb84e20
    <Pill tone="neutral" class="mb-3">{{ eyebrow }}</Pill>
    <h1 class="text-display-sub font-semibold tracking-snug text-ink">
      {{ title }}
    </h1>
    <p class="mt-2 text-caption text-ink-muted">{{ supporting }}</p>
  </div>
<<<<<<< HEAD
  <Button variant="ghost" size="sm" class="shrink-0" @click="...">
    <Icon name="Trash2" :size="14" class="mr-1" />
    Delete
=======
  <!-- Desktop-only action -->
  <Button v-if="resource" variant="ghost" size="sm" class="hidden shrink-0 sm:inline-flex" @click="...">
    <Icon name="Trash2" :size="14" class="mr-1" />
    {{ $t("...detail.delete") }}
>>>>>>> 207df866d64c49e3bd853678b573f0023eb84e20
  </Button>
</header>
```

Rules:
<<<<<<< HEAD
- `items-end` on the flex parent so actions sit next to the supporting line, not floating up to the eyebrow.
- `min-w-0` on the title block + `shrink-0` on the action so a long title truncates instead of pushing the button out of view.
- Use `Button size="sm" variant="ghost"` for destructive / utility actions here. Reserve `variant="primary"` for the page's main CTA, which usually lives on the *list* page (e.g. `+ Add property`), not the detail page.
- Don't stack actions on a separate row above the content card — that introduces a stray gap and breaks the rhythm.
=======
- **Render the action twice**, gated by `sm:hidden` (mobile) and `hidden sm:inline-flex` (desktop). Both bind to the same handler. Trying to position a single instance with `order` / `absolute` either wastes a row on mobile or knocks the title around on desktop.
- Desktop: `sm:items-end` on the header so the action aligns with the supporting-line baseline, not the eyebrow pill. `sm:min-w-0` on the title block + `shrink-0` on the action keeps long titles truncating instead of pushing the button off-screen.
- Use `Button size="sm" variant="ghost"` for destructive / utility actions here. Reserve `variant="primary"` for the page's main CTA, which usually lives on the *list* page (e.g. `+ Add property`), not the detail page.
- Gate both buttons with `v-if="resource"` so they disappear during loading / not-found states.
- For multiple actions, stack them in a `flex gap-2` wrapper at the action position, smallest-priority leftmost, primary-destructive rightmost.
>>>>>>> 207df866d64c49e3bd853678b573f0023eb84e20

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

- **Library:** Lucide (per [PROJECT.md § 7](../global/PROJECT.md#L226))
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
- Header: Roofly wordmark left, document type right (e.g., "Tenancy agreement")
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

<<<<<<< HEAD
## 11. Hard rules — do not break
=======
## 11. Mobile patterns

Living section — every design enhancement that adjusts behaviour at narrow widths gets captured here so it stays consistent across pages.

**Default stance:** mobile-first. Build the narrow layout, then layer on `sm:` (640px) / `md:` (768px) overrides. Always set `min-w-0` on flex children that contain truncating text — without it, long strings push siblings out of view.

### 11.1 Tabs → dropdown swap

Any page using `<TabsRoot>` (property detail, tenant detail, settings, etc.) collapses the tab strip to a `<Select>` on mobile. The TabsRoot becomes controlled with `v-model`, and both the strip and the dropdown bind to the same ref. **Always use `v-model="activeTab"` — reka-ui's TabsRoot uses `modelValue`, not `value`. `v-model:value` silently does nothing.**

```vue
<script setup lang="ts">
const activeTab = ref<string>("overview");
const tabOptions = computed(() => [
  { value: "overview", label: t("...tabs.overview") },
  { value: "details",  label: t("...tabs.details") },
  // ... filter optional tabs (e.g. Documents) here based on feature flags
]);
</script>

<template>
  <TabsRoot v-model="activeTab">
    <!-- Mobile picker -->
    <div class="mb-6 sm:hidden">
      <Select v-model="activeTab" :options="tabOptions" />
    </div>

    <!-- Desktop strip -->
    <TabsList class="mb-6 hidden gap-1 border-b border-line-passive sm:flex">
      <TabsTrigger value="overview" :class="tabTriggerClass">…</TabsTrigger>
      …
    </TabsList>

    <TabsContent value="overview">…</TabsContent>
    …
  </TabsRoot>
</template>
```

Completion-indicator dots / icons stay in the desktop strip; the dropdown is label-only. Don't try to recreate the dots inside `<Select>` options.

### 11.2 Card-row layout (status + main message)

For card-style list rows where each item has a status pill + supporting meta + a primary identifier (e.g. dashboard "Needs attention", future activity feeds), put the **pill + meta on top**, the **main message below** in `text-body font-medium`. Same layout on mobile and desktop — no responsive switch.

```vue
<NuxtLink class="group flex items-start gap-3 rounded-sm py-3 outline-none transition hover:bg-surface-hover focus-visible:shadow-focus">
  <div class="min-w-0 flex-1">
    <div class="mb-1 flex items-center gap-2">
      <Pill :tone="..." class="shrink-0">{{ statusLabel }}</Pill>
      <span v-if="meta" class="truncate text-caption text-ink-muted">{{ meta }}</span>
    </div>
    <p class="truncate text-body font-medium text-ink">{{ title }}</p>
  </div>
  <Icon name="ArrowRight" :size="14" class="mt-1 shrink-0 text-ink-faint" />
</NuxtLink>
```

### 11.3 Section header stacking

Section headers with a primary action (e.g. `<UnitsPanel>` with **+ Add unit**) stack on mobile and inline on tablet+. Action button uses `self-start` on mobile so it doesn't stretch full-width.

```vue
<header class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
  <div>
    <h2 class="text-card-title font-semibold text-ink">{{ title }} <span>(N)</span></h2>
    <p class="mt-1 text-caption text-ink-muted">{{ help }}</p>
  </div>
  <Button variant="ghost" class="self-start" @click="onAdd">+ {{ cta }}</Button>
</header>
```

### 11.4 Topbar mobile collapse

Secondary topbar controls (theme toggle, language switcher) move into the user-avatar dropdown on mobile. Wrap them in a single `hidden md:inline-flex md:items-center md:gap-1` div in the layout, and add equivalent rows inside `UserMenu.vue` gated by `md:hidden`. Avatar + hamburger stay in the topbar at all widths.

### 11.5 Page-level spacing

Tighten section spacing at the narrow end:
- `mb-8 → mb-6 sm:mb-8` between major sections
- `gap-6 → gap-4 sm:gap-6` on stat-tile grids
- Header `mb-8 → mb-6 sm:mb-8` for page titles

Don't change `Card` padding levels — `loose / standard / compact` are intentional design tokens and apply at all widths.

### 11.6 Detail-page header on mobile

The §4.4 layout shifts the action button per breakpoint:
- **Mobile**: action pairs with the back link on the top row (`sm:hidden`), title block gets full width below — title never competes for horizontal space.
- **Desktop**: action sits at the end of the title block's row, baseline-aligned with the supporting line (`hidden sm:inline-flex`), back link is alone on its own row above.

The pattern requires rendering the action twice, gated by viewport classes. Don't try to position a single instance — `order` wastes vertical space on mobile, `absolute` overlaps the title on desktop. If a *second* action joins, group them in a `flex gap-2` wrapper at the same position so both buttons travel together when the breakpoint flips.

### 11.7 Hover-only effects on touch

Browsers fire `:hover` once on tap and stick on phones. For tooltips and reveal-on-hover content, also wire `@touchstart` (see [MiniAreaChart.vue](../../frontend/app/components/ui/MiniAreaChart.vue)). Don't rely on hover for any *required* affordance — touch-only users should be able to discover everything.

### 11.8 Opacity-modifier gotcha

Tailwind's `bg-{color}/{opacity}` syntax silently produces no CSS when the color is a CSS variable holding a hex literal (most of our `--text-*` / `--border-*` tokens are hex). Use solid-color tokens for backgrounds instead — for stepped emphasis use `bg-line-passive` → `bg-ink-muted` → `bg-ink-strong` → `bg-ink`. The exception is `--surface-hover` which is already an rgba — `bg-surface-hover` works on its own.

### 11.9 Multi-section forms — sections, not nested cards

For tab content with multiple sub-sections (Settings Profile / Preferences / Notifications, property-detail Ownership form, tenant-detail Personal form, etc.), use plain `<section>` blocks separated by `border-t border-line-passive pt-6` — **not** nested `<Card>` components inside the page-level Card.

```vue
<form class="space-y-8">
  <section class="space-y-4">
    <header>
      <h2 class="text-card-title font-semibold text-ink">{{ sectionTitle }}</h2>
      <p class="mt-1 text-caption text-ink-muted">{{ help }}</p>
    </header>
    <!-- fields / content -->
  </section>

  <section class="space-y-4 border-t border-line-passive pt-6">
    <header>…</header>
    <!-- … -->
  </section>
</form>
```

Why: Card is `bg-surface-raised`. Input is `bg-surface-page`. If you nest a Card inside the page-level Card both default to `surface-raised` → inputs lose contrast against the inner card; setting `tone="flat"` on the inner card to fix that *also* makes inputs (page-bg) blend with the inner card (page-bg). Sections sidestep the conflict — inputs sit directly on the page-level raised Card and read as cleanly inset.

The same logic applies to PropertyOwnershipForm, TenantPersonalForm, and friends — that's why they were already section-based. Settings was the outlier and was migrated.

### 11.10 Status pill positioning (coming-soon indicators)

When a section header carries a status pill ("Coming soon", "Beta"), put the pill at the **top-right on desktop** and the **bottom-right on mobile** (so it never pushes the title down on narrow widths). Drives a `flex flex-col` → `sm:flex-row sm:justify-between` switch.

```vue
<header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
  <div>
    <h2 class="text-card-title font-semibold text-ink">{{ title }}</h2>
    <p class="mt-1 text-caption text-ink-muted">{{ help }}</p>
  </div>
  <Pill tone="draft" class="self-end shrink-0 sm:self-start">
    {{ $t("...comingSoon") }}
  </Pill>
</header>
```

`self-end` on mobile + `sm:self-start` on desktop pin the pill to the bottom-right and top-right respectively. `shrink-0` keeps it from squashing.

### 11.11 Repeater rows → cards on mobile

Field-array repeaters (co-owner list, future tag inputs, etc.) work as a horizontal row on desktop but break on phones — labels collide, fields wrap weirdly, action buttons end up disconnected from their row. Wrap each row in a bordered "card" on mobile only, stack the fields vertically inside it, and **pair the row's primary label with the action menu on one line** above the first input so the card doesn't waste a whole row on the ⋮ trigger.

```vue
<div
  v-for="(field, idx) in fields"
  :key="field.key"
  class="rounded-md border border-line-passive p-3 sm:border-0 sm:p-0"
>
  <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-3">
    <!-- Primary field — manual label so we can pair it with the action -->
    <div class="sm:min-w-[10rem] sm:flex-1">
      <div class="mb-1.5 flex items-center justify-between gap-2">
        <span class="text-caption font-normal text-ink-strong">{{ labelName }}</span>
        <!-- Mobile-only action: lives next to the label -->
        <DropdownMenuRoot>
          <DropdownMenuTrigger as-child>
            <button class="inline-flex h-7 w-7 items-center justify-center rounded-sm text-ink-muted hover:bg-surface-hover hover:text-ink sm:hidden">
              <Icon name="MoreVertical" :size="16" />
            </button>
          </DropdownMenuTrigger>
          …
        </DropdownMenuRoot>
      </div>
      <Input v-model="..." :label="undefined" :placeholder="..." />
    </div>

    <!-- Subsequent fields keep Input's built-in label -->
    <div class="sm:w-28">
      <Input v-model="..." :label="labelShare" />
    </div>

    <!-- Desktop-only action at end of fields row -->
    <DropdownMenuRoot>
      <DropdownMenuTrigger as-child>
        <button class="hidden h-10 w-10 items-center justify-center rounded-sm text-ink-muted hover:bg-surface-hover hover:text-ink sm:inline-flex">
          <Icon name="MoreVertical" :size="16" />
        </button>
      </DropdownMenuTrigger>
      …
    </DropdownMenuRoot>
  </div>
</div>
```

Rules:
- **Always render labels on every row** — the row-1-only column-header trick (`:label="idx === 0 ? ... : undefined"`) only works on desktop. On mobile each card is its own unit and needs its own labels.
- **Render the action menu twice** — once mobile-only (`sm:hidden`) inside the label row, once desktop-only (`hidden sm:inline-flex`) at the end of the fields row. Both bind to the same handler / state. Trying to position a single instance via `order` / `absolute` either overlaps the inputs or wastes a full row on mobile.
- The mobile action button is `h-7 w-7` so it sits flush with the caption-sized label without inflating the label row's height. Desktop variant stays `h-10 w-10` to align with the input.
- For row-level actions with multiple options (set primary / remove / reorder etc.), use a reka-ui `<DropdownMenuRoot>` with `<MoreVertical>` trigger — never a row of inline buttons.

### 11.12 "Coming soon" copy convention

When the UI exposes a feature that ships in a later phase (file storage, billing, real notifications, etc.), do **not** mention specific phase numbers ("Phase 4", "Phase 7") in user-facing strings — they leak roadmap detail and date faster than the UI does. Instead:

- **Empty-state titles / placeholder titles** → exactly `"Coming soon"`. Same wording in EN ("Coming soon") and MS ("Akan datang").
- **Body / help text** → lead with `"Coming soon — "` then describe what arrives, ending with `"in the next phase"` (EN) or `"dalam fasa seterusnya"` (MS).
- **Toasts** → same lead-with `"Coming soon — "` form, kept short.

The pill in §11.10 uses the same `"Coming soon"` wording for visual consistency across the app.

Code comments and dev-facing docs (PROJECT.md, MOCK-POC.md, this file) **may** still reference specific phase numbers — that information is useful for planning. The convention is strictly for end-user copy.

### 11.13 Modal mobile-safe overflow

A modal centered on the viewport with no max-height clips its top and bottom edges off-screen as soon as the form grows tall — the footer (Save/Cancel) vanishes, and there's no inner scroll to recover. The mobile keyboard popping up over an input makes it worse.

Modal layout rules:

- The dialog is a **flex column** with `max-h-[calc(100dvh-2rem)]`. Use `dvh`, not `vh` — `dvh` accounts for the mobile address bar shrinking the viewport, `vh` doesn't and overshoots.
- **Header** is `shrink-0` with its own padding and a bottom divider.
- **Body** is `min-h-0 flex-1 overflow-y-auto` so only the body scrolls; header and footer stay anchored.
- **Footer** is `shrink-0 flex-wrap` with its own padding and a top divider. `flex-wrap` so multiple buttons (Delete + Cancel + Save) wrap on tiny widths instead of overflowing.

The reusable component already enforces this (`components/ui/Modal.vue`). When building a modal-like surface from scratch, mirror the structure — don't put the form, header, and footer in a single `p-6` div with no max-height.

Forms longer than ~5–6 fields should not live in a modal at all — convert to a dedicated detail page (see Agreements: list / `[id]` / `new` triplet) so the user gets a real scroll area, the URL is shareable, and the mobile keyboard has somewhere to push.

### 11.14 Data tables → cards on mobile

A horizontally-scrolling table with 6 columns is unusable on a 375px viewport — half the row is off-screen and the action button is the part that's clipped. Swap the table for a stack of cards under `<sm`, keeping the same data fetch and pagination.

Pattern (see [pages/owner/payments.vue](../../frontend/app/pages/owner/payments.vue) + [components/owner/InvoiceCard.vue](../../frontend/app/components/owner/InvoiceCard.vue)):

- **Same data, two presentations.** Render the table inside `hidden sm:block` and the card stack inside `sm:hidden`. Both bind to the same `pageRows` so sort/filter/pagination state stays single-source.
- **Card structure** — status pill top-left, the most important number top-right (highlighted, `text-card-title`-sized, status-toned color for overdue/paid), primary identifier (e.g. tenant name) below, secondary identifier (invoice no) below that in muted caption, contextual lines (property, due date) with leading icons, and a footer line for the action button + paid-on stamp separated by a divider.
- **Page size halves on mobile** — desktop ~20, mobile ~8 (or whatever fits ~3–4 finger-scrolls). Hook into `useBreakpoint().isMobile` and call `table.setPageSize()` reactively. Reset to page 0 on each switch so the user doesn't land on an empty page.
- **Filter consolidation.** Pill rows that wrap acceptably on desktop turn into ugly multi-line stacks on mobile — convert chips to a `<Select>` dropdown, and collapse multi-field filters (like month + year) into a single popover trigger that opens a side-by-side scroll list. Keep the icon trigger compact (calendar icon + abbreviated label like "Aug · 2026") so it fits next to the status filter on one row.
- **Tap target = the whole card.** Wrap the card surface in a `<button>` that opens the detail/view modal; the inner action button uses `e.stopPropagation()` so it doesn't bubble.

The table is the truth; the cards are a presentation. Don't fork the data shape or duplicate the column definitions in two places.

---

## 12. Hard rules — do not break
>>>>>>> 207df866d64c49e3bd853678b573f0023eb84e20

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
