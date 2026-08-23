# Social preview card (og.png)

`public/marketing/og.png` (1200×630) is the Open Graph / Twitter card for
`/coming-soon`, referenced via `useSeoMeta` with `runtimeConfig.public.siteUrl`.

Regenerate after changing the hero copy:

```bash
# from frontend/ on macOS (uses the installed Chrome; needs network for Google Fonts)
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --disable-gpu \
  --hide-scrollbars --window-size=1200,630 --virtual-time-budget=4000 \
  --screenshot=public/marketing/og.png "file://$PWD/scripts/og/og-card.html"
```

Edit `og-card.html` to change the wording; it mirrors `marketing.hero.*` in `en.json`.
