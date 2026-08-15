from __future__ import annotations

from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
IMAGES = ROOT / "wp-theme" / "winnica-nowizny" / "assets" / "images"

RESPONSIVE_VARIANTS = {
    "historia-winnica": (640,),
    "piwnica-1891": (640,),
    "doswiadczenie-degustacja": (240,),
    "doswiadczenie-spacer": (240,),
    "doswiadczenie-daniele": (240,),
    "doswiadczenie-park-linowy": (240,),
    "galeria-wnetrze": (320, 640),
    "galeria-taras": (320, 640),
    "galeria-pierogi-domowe": (320, 640),
    "galeria-daniele": (320, 640),
    "galeria-winogrona": (320, 640),
    "galeria-biesiada": (320, 640),
}


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


def build_responsive_variants() -> None:
    """Build fallback-theme srcset candidates from the checked-in masters."""
    for stem, widths in RESPONSIVE_VARIANTS.items():
        source = IMAGES / f"{stem}.webp"
        with Image.open(source) as master:
            source_image = master.convert("RGB")
            for width in widths:
                height = round(source_image.height * width / source_image.width)
                resized = source_image.resize((width, height), Image.Resampling.LANCZOS)
                for extension, options in (
                    ("webp", {"quality": 82, "method": 6}),
                    ("avif", {"quality": 52, "speed": 6}),
                ):
                    target = IMAGES / f"{stem}-{width}.{extension}"
                    resized.save(target, extension.upper(), **options)
                    print(f"{target.name}: {target.stat().st_size} B")


def build_signature_formats() -> None:
    source = IMAGES / "podpis-urszula-boguslaw-kaminscy.png"
    with Image.open(source) as master:
        signature = master.convert("RGBA")
        for width in (380, 760):
            height = round(signature.height * width / signature.width)
            resized = signature.resize((width, height), Image.Resampling.LANCZOS)
            suffix = "" if width == 760 else f"-{width}"
            webp = IMAGES / f"podpis-urszula-boguslaw-kaminscy{suffix}.webp"
            avif = IMAGES / f"podpis-urszula-boguslaw-kaminscy{suffix}.avif"
            resized.save(webp, "WEBP", lossless=True, method=6)
            resized.save(avif, "AVIF", quality=60, speed=6)
            print(f"{webp.name}: {webp.stat().st_size} B")
            print(f"{avif.name}: {avif.stat().st_size} B")


if __name__ == "__main__":
    build_cellar_avif()
    build_site_icon()
    build_responsive_variants()
    build_signature_formats()
