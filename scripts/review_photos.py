from __future__ import annotations

import csv
import os
from pathlib import Path

# Load-bearing: puts .review-deps on sys.path and explains itself when the
# compiled wheels there do not match this interpreter.
import _deps

from PIL import Image, ImageDraw, ImageFont, ImageOps, ImageStat
from pillow_heif import register_heif_opener


register_heif_opener()

# Katalog ze zdjeciami zrodlowymi jest prywatny i lezy poza repozytorium, wiec
# sciezke podaje sie w zmiennej srodowiskowej zamiast wpisywac ja tutaj.
SOURCE = Path(os.environ.get("WINNICA_PHOTO_SOURCE", "")).expanduser()
if not SOURCE.is_dir():
    raise SystemExit(
        "Ustaw WINNICA_PHOTO_SOURCE na katalog ze zdjeciami zrodlowymi, np.\n"
        '  PowerShell: $env:WINNICA_PHOTO_SOURCE = "D:\\zdjecia"\n'
        '  bash:       export WINNICA_PHOTO_SOURCE="/d/zdjecia"'
    )

OUTPUT = Path(__file__).resolve().parents[1] / "photo-review"
OUTPUT.mkdir(exist_ok=True)

EXTENSIONS = {".jpg", ".jpeg", ".png", ".heic"}
FILES = sorted(
    (path for path in SOURCE.iterdir() if path.suffix.lower() in EXTENSIONS),
    key=lambda path: path.name.casefold(),
)

THUMB_W, THUMB_H = 420, 300
LABEL_H = 62
COLUMNS, ROWS = 3, 4
MARGIN, GAP = 30, 18
SHEET_W = MARGIN * 2 + COLUMNS * THUMB_W + (COLUMNS - 1) * GAP
SHEET_H = MARGIN * 2 + ROWS * (THUMB_H + LABEL_H) + (ROWS - 1) * GAP

font = ImageFont.truetype("arial.ttf", 19)
small_font = ImageFont.truetype("arial.ttf", 15)


def laplacian_variance(gray: Image.Image) -> float:
    small = gray.copy()
    small.thumbnail((800, 800))
    pixels = list(small.getdata())
    width, height = small.size
    if width < 3 or height < 3:
        return 0.0
    values = []
    for y in range(1, height - 1):
        row = y * width
        for x in range(1, width - 1):
            idx = row + x
            values.append(
                4 * pixels[idx]
                - pixels[idx - 1]
                - pixels[idx + 1]
                - pixels[idx - width]
                - pixels[idx + width]
            )
    if not values:
        return 0.0
    mean = sum(values) / len(values)
    return sum((value - mean) ** 2 for value in values) / len(values)


records = []
for index, path in enumerate(FILES, start=1):
    with Image.open(path) as source:
        image = ImageOps.exif_transpose(source).convert("RGB")
        gray = image.convert("L")
        records.append(
            {
                "index": index,
                "filename": path.name,
                "width": image.width,
                "height": image.height,
                "orientation": "poziome" if image.width >= image.height else "pionowe",
                "brightness": round(ImageStat.Stat(gray).mean[0], 1),
                "sharpness": round(laplacian_variance(gray), 1),
            }
        )

        sheet_number = (index - 1) // (COLUMNS * ROWS) + 1
        position = (index - 1) % (COLUMNS * ROWS)
        if position == 0:
            sheet = Image.new("RGB", (SHEET_W, SHEET_H), "#eee9df")
            draw = ImageDraw.Draw(sheet)

        row, column = divmod(position, COLUMNS)
        x = MARGIN + column * (THUMB_W + GAP)
        y = MARGIN + row * (THUMB_H + LABEL_H + GAP)
        thumb = ImageOps.fit(image, (THUMB_W, THUMB_H), method=Image.Resampling.LANCZOS)
        sheet.paste(thumb, (x, y))
        label = f"{index:02d}. {path.name}"
        if len(label) > 40:
            label = label[:37] + "..."
        draw.text((x, y + THUMB_H + 7), label, fill="#231f1a", font=font)
        draw.text(
            (x, y + THUMB_H + 34),
            f"{image.width}×{image.height}",
            fill="#665f55",
            font=small_font,
        )

        if position == COLUMNS * ROWS - 1 or index == len(FILES):
            sheet.save(OUTPUT / f"contact-sheet-{sheet_number}.jpg", quality=90)

with (OUTPUT / "photo-metrics.csv").open("w", newline="", encoding="utf-8-sig") as handle:
    writer = csv.DictWriter(handle, fieldnames=records[0].keys())
    writer.writeheader()
    writer.writerows(records)

SELECTED = {
    "Daniele przy winnicy.JPG",
    "DSCN6029 (1).JPG",
    "DSCN6073 (1).JPG",
    "f8cda327-130f-4a30-a650-1c5ab8951ef4.jpg",
    "IMG_0025 (1).HEIC",
    "IMG_0033 (1).HEIC",
    "IMG_0335.HEIC",
    "IMG_0790 (1).jpg",
    "IMG_2298 (1).HEIC",
    "IMG_2306.HEIC",
    "IMG_2366.HEIC",
    "IMG_2957 (1).HEIC",
    "IMG_3314 (1).HEIC",
    "IMG_3389 (1).HEIC",
    "IMG_5195 (2).HEIC",
    "IMG_5935 (1).HEIC",
    "IMG_6949 (1).HEIC",
    "IMG_9168.HEIC",
    "IMG_9703 (1).HEIC",
    "IMG_9755 (1).HEIC",
    "potrawy regionalne 1.JPG",
}

preview_dir = OUTPUT / "selected-previews"
preview_dir.mkdir(exist_ok=True)
for path in FILES:
    if path.name not in SELECTED:
        continue
    with Image.open(path) as source:
        image = ImageOps.exif_transpose(source).convert("RGB")
        image.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
        image.save(preview_dir / f"{path.stem}.jpg", quality=92)

print(f"Processed {len(records)} photos into {sheet_number} contact sheets.")
