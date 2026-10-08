#!/usr/bin/env python3
"""Crop/resize/convert hero photos into public/marketing/hero/<name>.webp.

Reads every PNG/JPG in scripts/hero/raw/ (or the paths you pass), centre-crops
to 16:9, resizes to at most 1920x1080 (never upscales) and saves WebP. Warns when a file exceeds the
~250 KB budget from UI-STANDARDS § 11.21 — lower --quality or darken the image.

    python3 scripts/hero/pack.py                       # everything in raw/
    python3 scripts/hero/pack.py ~/Downloads/condo.png # specific files, named by stem
    python3 scripts/hero/pack.py --quality 70
"""
import argparse
import sys
from pathlib import Path

from PIL import Image

HERE = Path(__file__).parent
RAW = HERE / "raw"
OUT = HERE.parent.parent / "public" / "marketing" / "hero"
W, H = 1920, 1080
BUDGET_KB = 250


def pack(src: Path, quality: int) -> Path:
    im = Image.open(src).convert("RGB")
    w, h = im.size
    target = W / H
    if w / h > target:  # too wide → trim sides
        nw = int(h * target)
        im = im.crop(((w - nw) // 2, 0, (w - nw) // 2 + nw, h))
    else:  # too tall → trim top/bottom
        nh = int(w / target)
        im = im.crop((0, (h - nh) // 2, w, (h - nh) // 2 + nh))
    # Never upscale: a 1672-wide source stays 1672×941, a 4K one comes down to 1920×1080.
    w = min(W, im.size[0])
    im = im.resize((w, round(w * H / W)), Image.LANCZOS)
    dst = OUT / f"{src.stem}.webp"
    im.save(dst, "WEBP", quality=quality, method=6)
    return dst


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("files", nargs="*", help="source images; default: scripts/hero/raw/*.png|jpg")
    ap.add_argument("--quality", type=int, default=75)
    args = ap.parse_args()

    files = [Path(f) for f in args.files] or sorted(p for p in RAW.glob("*") if p.suffix.lower() in {".png", ".jpg", ".jpeg", ".webp"})
    if not files:
        print(f"nothing to pack — put PNG/JPG files in {RAW} or pass paths")
        return 1
    OUT.mkdir(parents=True, exist_ok=True)
    over = False
    for f in files:
        dst = pack(f, args.quality)
        kb = dst.stat().st_size // 1024
        flag = "  ⚠ over budget" if kb > BUDGET_KB else ""
        over = over or kb > BUDGET_KB
        print(f"{f.name} -> {dst.relative_to(OUT.parent.parent)} {kb} KB{flag}")
    return 1 if over else 0


if __name__ == "__main__":
    sys.exit(main())
