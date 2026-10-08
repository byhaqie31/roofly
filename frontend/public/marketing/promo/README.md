# Coming-soon promo videos

Drop the two files here with exactly these names (PromoVideo.vue looks them up):

| File | Shown on | Notes |
|---|---|---|
| `promo-web.mp4` | screens 768px and wider | landscape, e.g. 1920x1080 |
| `promo-mobile.mp4` | screens under 768px | portrait, e.g. 1080x1920 |
| `promo-web-poster.webp` | optional | still frame shown before the web video loads |
| `promo-mobile-poster.webp` | optional | still frame shown before the mobile video loads |

- H.264 MP4 plays everywhere. Keep each file under ~10 MB; they're committed to git and served as static files.
- The videos autoplay **muted** and loop while on screen. Visitors can unmute from the controls.
- If a file is missing, the section hides itself, so the page never shows a broken player.

Shrink an export with ffmpeg:

    ffmpeg -i in.mp4 -c:v libx264 -crf 26 -preset slow -movflags +faststart -c:a aac -b:a 96k promo-web.mp4
    ffmpeg -ss 1 -i promo-web.mp4 -frames:v 1 promo-web-poster.webp
