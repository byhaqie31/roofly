#!/usr/bin/env python3
"""Generate the coming-soon hero slideshow photos with Gemini image models.

Writes raw PNGs to scripts/hero/raw/<subject>.png. Run pack.py afterwards to
crop/resize/convert them into public/marketing/hero/<subject>.webp.

    export GEMINI_API_KEY=...            # Google AI Studio key
    pip install google-genai pillow      # once
    python3 scripts/hero/generate.py                 # all five subjects, pro model
    python3 scripts/hero/generate.py --only condo    # one subject
    python3 scripts/hero/generate.py --flash         # cheaper/faster draft model
    python3 scripts/hero/pack.py

The prompts below are the source of truth for the look (see
docs/frontend/UI-STANDARDS.md § 11.21). Paste them into any other image tool
if you'd rather not use the API — pack.py accepts PNG/JPG from anywhere.
"""
import argparse
import os
import sys
import time
from pathlib import Path

HERE = Path(__file__).parent
RAW = HERE / "raw"

STYLE = (
    "Ultra-realistic architectural photograph, Malaysia, blue hour just after sunset. "
    "Deep indigo-to-charcoal sky with a faint warm band near the horizon. Warm amber light "
    "glowing from the windows, a few landscape lights; tropical palms and frangipani; wet "
    "tarmac or tiled driveway with soft reflections. Shot on a full-frame camera, 24mm lens, "
    "tripod, long exposure, low ISO, crisp detail, cinematic colour grade with teal shadows "
    "and warm highlights, slight haze. The building sits centred in frame with generous "
    "breathing room on both sides, horizon in the lower third. Dark overall exposure suitable "
    "as a website background that white text will sit over. "
    "No people, no moving cars, no text, no signage, no watermarks, no logos."
)

# Playback order mirrors `slides` in components/marketing/HeroSection.vue.
SUBJECTS = {
    "bungalow": (
        "A contemporary two-storey detached bungalow in Shah Alam or Bangi: flat concrete "
        "roofs mixed with a dark pitched roof, timber screens, floor-to-ceiling glass, a "
        "covered car porch, manicured lawn and a low gate"
    ),
    "terrace": (
        "A row of modern double-storey terrace houses in a Malaysian township like "
        "Setia Alam, viewed slightly from the street corner: clay-tiled pitched roofs, "
        "white render, timber-look cladding accents, matching car porches, roadside trees"
    ),
    "condo": (
        "A sleek high-rise condominium tower in Kuala Lumpur with glass balconies and "
        "vertical green planters, pool deck lights visible at the podium, seen from the "
        "landscaped entrance with the tower rising centre frame, scattered lit windows"
    ),
    "semid": (
        "A tropical modern semi-detached house in Penang or Johor Bahru: timber and "
        "charcoal brick facade, a cantilevered first floor, a small plunge pool with "
        "underwater light in front, lush garden, warm pendant lights under the porch"
    ),
    "apartment": (
        "A mid-rise serviced apartment block in Petaling Jaya at blue hour with a ground-floor "
        "lobby glowing warmly, stacked balconies with soft lights, the KL skyline faint and "
        "distant in the background, a quiet tree-lined access road in front"
    ),
}


def prompt_for(name: str) -> str:
    return f"{SUBJECTS[name]}. {STYLE}"


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--only", nargs="*", choices=list(SUBJECTS), help="subset of subjects")
    ap.add_argument("--flash", action="store_true", help="use gemini-2.5-flash-image instead of the pro model")
    ap.add_argument("--print-prompts", action="store_true", help="print the full prompts and exit")
    args = ap.parse_args()

    names = args.only or list(SUBJECTS)
    if args.print_prompts:
        for n in names:
            print(f"## {n}\n{prompt_for(n)}\n")
        return 0

    key = os.environ.get("GEMINI_API_KEY") or os.environ.get("GOOGLE_API_KEY")
    if not key:
        print("GEMINI_API_KEY is not set. Export it, or use --print-prompts and generate elsewhere.")
        return 2

    from google import genai  # imported late so --print-prompts works without the SDK
    from google.genai import types

    model = "gemini-2.5-flash-image" if args.flash else "gemini-3-pro-image-preview"
    client = genai.Client(api_key=key)
    RAW.mkdir(exist_ok=True)
    print(f"model={model} subjects={names}")

    failed = []
    for n in names:
        t0 = time.time()
        configs = [types.ImageConfig(aspect_ratio="16:9")]
        if not args.flash:
            configs.insert(0, types.ImageConfig(aspect_ratio="16:9", image_size="2K"))
        saved = None
        err = None
        for cfg in configs:
            try:
                r = client.models.generate_content(
                    model=model,
                    contents=prompt_for(n),
                    config=types.GenerateContentConfig(response_modalities=["IMAGE", "TEXT"], image_config=cfg),
                )
                for part in r.candidates[0].content.parts:
                    data = getattr(part, "inline_data", None)
                    if data and data.mime_type.startswith("image/"):
                        saved = RAW / f"{n}.png"
                        saved.write_bytes(data.data)
                        break
                if saved:
                    break
                err = "no image part in response"
            except Exception as e:  # noqa: BLE001 — retry without image_size
                err = f"{type(e).__name__}: {str(e)[:200]}"
        if saved:
            print(f"  ok {n} -> {saved.relative_to(HERE.parent.parent)} ({saved.stat().st_size // 1024} KB, {time.time() - t0:.0f}s)")
        else:
            failed.append(n)
            print(f"  FAILED {n}: {err}")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
