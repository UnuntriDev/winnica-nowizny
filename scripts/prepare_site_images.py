from __future__ import annotations

import sys
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT / ".review-deps"))

from PIL import Image, ImageEnhance, ImageOps
from pillow_heif import register_heif_opener


register_heif_opener()

SOURCE = Path("zdjecia-zrodlowe")
OUTPUT = PROJECT / "wp-theme" / "winnica-nowizny" / "assets" / "images"
OUTPUT.mkdir(parents=True, exist_ok=True)

# output name: (source name, width, height, crop centering, explicit rotation)
IMAGES = {
    "hero-winnica.webp": ("IMG_0033 (1).HEIC", 1920, 1080, (0.5, 0.58), 0),
    "historia-winnica.webp": ("IMG_6949 (1).HEIC", 900, 1200, (0.5, 0.56), 0),
    "doswiadczenie-degustacja.webp": ("DSCN6029 (1).JPG", 750, 1000, (0.48, 0.52), 90),
    "doswiadczenie-spacer.webp": ("IMG_2306.HEIC", 750, 1000, (0.5, 0.54), 0),
    "doswiadczenie-warsztaty.webp": ("IMG_3389 (1).HEIC", 750, 1000, (0.54, 0.55), 0),
    "doswiadczenie-piknik.webp": ("IMG_0025 (1).HEIC", 750, 1000, (0.58, 0.5), 0),
    "piwnica-1891.webp": ("f8cda327-130f-4a30-a650-1c5ab8951ef4.jpg", 1280, 960, (0.48, 0.5), 0),
    "galeria-wnetrze.webp": ("IMG_2957 (1).HEIC", 800, 1200, (0.5, 0.54), 0),
    "galeria-taras.webp": ("IMG_0025 (1).HEIC", 1400, 700, (0.5, 0.46), 0),
    "galeria-pierogi.webp": ("IMG_3314 (1).HEIC", 800, 800, (0.5, 0.58), 0),
    "galeria-daniele.webp": ("Daniele przy winnicy.JPG", 800, 800, (0.5, 0.5), 0),
    "galeria-winogrona.webp": ("IMG_2366.HEIC", 800, 800, (0.5, 0.52), 0),
    "galeria-biesiada.webp": ("potrawy regionalne 1.JPG", 1400, 700, (0.5, 0.5), 0),
    "wizyta-tlo.webp": ("IMG_5195 (2).HEIC", 1800, 1200, (0.5, 0.54), 0),
}


for output_name, (source_name, width, height, centering, rotation) in IMAGES.items():
    with Image.open(SOURCE / source_name) as source:
        image = ImageOps.exif_transpose(source).convert("RGB")
        if rotation:
            image = image.rotate(rotation, expand=True)
        image = ImageOps.fit(
            image,
            (width, height),
            method=Image.Resampling.LANCZOS,
            centering=centering,
        )
        image = ImageEnhance.Contrast(image).enhance(1.03)
        image = ImageEnhance.Color(image).enhance(0.96)
        image.save(OUTPUT / output_name, "WEBP", quality=84, method=6)
        print(f"{output_name}: {width}x{height}")
