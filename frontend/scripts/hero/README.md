# Hero slideshow photos (public/marketing/hero/)

The `/coming-soon` hero crossfades property photos (pattern:
[UI-STANDARDS § 11.21](../../../docs/frontend/UI-STANDARDS.md)). They live at
`public/marketing/hero/<subject>.webp` in the playback order set by `slides` in
`app/components/marketing/HeroSection.vue`. Live set: `bungalow`, `terrace`,
`condo`, `semid`, `apartment` (all generated 2026-10-06 from the prompts below).
To grow the set, add a prompt to `generate.py`, pack the photo, then append the
name to `slides`.

## Spec

16:9, up to 1920×1080 (the packer never upscales), WebP, each under ~250 KB. Blue-hour / dusk, warm window light,
subject centred with breathing room either side (portrait phones centre-crop),
dark exposure, no people / text / signage.

## Generate with Gemini

```bash
# from frontend/
export GEMINI_API_KEY=...                 # Google AI Studio key
pip install google-genai pillow           # once
python3 scripts/hero/generate.py          # → scripts/hero/raw/*.png (gitignored)
python3 scripts/hero/pack.py              # → public/marketing/hero/*.webp
```

`--flash` uses the cheaper draft model; `--only condo semid` regenerates a subset.

## Generate elsewhere

```bash
python3 scripts/hero/generate.py --print-prompts
```

prints the five full prompts. Paste them into any image tool at 16:9, save the
results as `scripts/hero/raw/<subject>.png` (or pass the paths directly), then
run `pack.py`.
