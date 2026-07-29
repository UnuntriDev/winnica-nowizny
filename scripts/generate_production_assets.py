from __future__ import annotations

from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
IMAGES = ROOT / "wp-theme" / "winnica-nowizny" / "assets" / "images"


def build_cellar_avif() -> None:
    for suffix in ("-480", "-960", ""):
        source = IMAGES / f"piwnica-1891{suffix}.webp"
        target = IMAGES / f"piwnica-1891{suffix}.avif"
        with Image.open(source) as image:
            image.convert("RGB").save(target, "AVIF", quality=52, speed=6)
        print(f"{target.name}: {target.stat().st_size} B")


def build_site_icon() -> None:
    source = IMAGES / "logo-nowizny-nav.webp"
    target = IMAGES / "site-icon.png"

    with Image.open(source) as image:
        mark = image.convert("RGBA")
        alpha_box = mark.getchannel("A").getbbox()
        if alpha_box:
            mark = mark.crop(alpha_box)
        mark.thumbnail((410, 410), Image.Resampling.LANCZOS)

        canvas = Image.new("RGBA", (512, 512), (26, 21, 16, 255))
        position = ((512 - mark.width) // 2, (512 - mark.height) // 2)
        canvas.alpha_composite(mark, position)
        canvas.convert("RGB").save(target, "PNG", optimize=True)

    print(f"{target.name}: {target.stat().st_size} B")


if __name__ == "__main__":
    build_cellar_avif()
    build_site_icon()
