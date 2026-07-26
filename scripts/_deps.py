"""Shared dependency bootstrap for the image scripts.

Pillow and pillow-heif ship compiled extensions, so .review-deps only works with
the interpreter it was installed for. Importing it from another one used to fail
with "cannot import name _imaging", which names neither the real problem nor the
fix. Importing this module first turns that into an answer.
"""

from __future__ import annotations

import sys
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
DEPS = PROJECT / ".review-deps"

sys.path.insert(0, str(DEPS))

try:
    from PIL import Image  # noqa: F401
    from pillow_heif import register_heif_opener  # noqa: F401
except ImportError as error:
    built_for = sorted({path.name.split(".")[1].split("-")[0] for path in DEPS.glob("**/*.pyd")})
    running = f"cp{sys.version_info.major}{sys.version_info.minor}"
    raise SystemExit(
        f"{DEPS} does not work with this interpreter.\n"
        f"  running:      {sys.executable} ({running})\n"
        f"  installed for: {', '.join(built_for) or 'nothing compiled found'}\n"
        f"Either run the script with the matching Python, or reinstall:\n"
        f'  "{sys.executable}" -m pip install --target "{DEPS}" --upgrade pillow pillow-heif\n'
        f"Original error: {error}"
    ) from error
