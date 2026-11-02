#!/usr/bin/env python3
"""Génère les visuels de démonstration du catalogue Stive Clothing.

Usage :
    python3 tools/generate_demo_media.py

Les images produites (600 x 800, PNG) sont versionnées dans public/demo/products
afin que la boutique de démonstration dispose de visuels sans dépendance
externe. En production, les photos réelles sont téléversées par l'administration
sur le disque objet configuré (S3 ou équivalent).

Le script n'utilise que la bibliothèque standard de Python 3.
"""

from __future__ import annotations

import pathlib
import struct
import zlib

WIDTH = 600
HEIGHT = 800
ROOT = pathlib.Path(__file__).resolve().parent.parent
TARGET = ROOT / "public" / "demo" / "products"

# (fond haut, fond bas, couleur du vêtement)
PALETTES = [
    ((0x1F, 0x29, 0x37), (0x0B, 0x11, 0x20), (0xE5, 0xE7, 0xEB)),
    ((0x1E, 0x3A, 0x8A), (0x0F, 0x17, 0x2A), (0xBF, 0xDB, 0xFE)),
    ((0x3F, 0x3F, 0x46), (0x18, 0x18, 0x1B), (0xD4, 0xD4, 0xD8)),
    ((0x78, 0x35, 0x0F), (0x29, 0x25, 0x24), (0xFD, 0xE6, 0x8A)),
    ((0x14, 0x53, 0x2D), (0x05, 0x2E, 0x16), (0xBB, 0xF7, 0xD0)),
    ((0x7C, 0x2D, 0x12), (0x1C, 0x19, 0x17), (0xFE, 0xD7, 0xAA)),
    ((0x4C, 0x1D, 0x95), (0x1E, 0x1B, 0x4B), (0xDD, 0xD6, 0xFE)),
    ((0x0F, 0x76, 0x6E), (0x04, 0x2F, 0x2E), (0x99, 0xF6, 0xE4)),
]

SILHOUETTE = [
    (150, 220),
    (225, 180),
    (375, 180),
    (450, 220),
    (405, 310),
    (405, 690),
    (195, 690),
    (195, 310),
]


def chunk(tag: bytes, payload: bytes) -> bytes:
    """Assemble un chunk PNG (longueur, type, données, CRC)."""
    return (
        struct.pack(">I", len(payload))
        + tag
        + payload
        + struct.pack(">I", zlib.crc32(tag + payload) & 0xFFFFFFFF)
    )


def spans_for_row(y: float, polygon: list[tuple[int, int]]) -> list[tuple[int, int]]:
    """Intersections d'une ligne horizontale avec un polygone (remplissage)."""
    xs: list[float] = []
    count = len(polygon)

    for index in range(count):
        x1, y1 = polygon[index]
        x2, y2 = polygon[(index + 1) % count]

        if y1 == y2:
            continue

        if min(y1, y2) <= y < max(y1, y2):
            xs.append(x1 + (y - y1) * (x2 - x1) / (y2 - y1))

    xs.sort()

    return [
        (int(round(xs[i])), int(round(xs[i + 1])))
        for i in range(0, len(xs) - 1, 2)
    ]


def build_pixels(top: tuple[int, int, int], bottom: tuple[int, int, int], garment: tuple[int, int, int]) -> bytes:
    collar_dark = tuple(int(channel * 0.55) for channel in garment)
    frame = (255, 255, 255)
    rows = []

    for y in range(HEIGHT):
        ratio = y / HEIGHT
        background = tuple(
            int(round(top[channel] * (1 - ratio) + bottom[channel] * ratio))
            for channel in range(3)
        )
        row = [bytes(background)] * WIDTH

        if 180 <= y <= 690:
            for start, end in spans_for_row(y + 0.5, SILHOUETTE):
                for x in range(max(start, 0), min(end, WIDTH)):
                    row[x] = bytes(garment)

        # Col stylisé (ellipse).
        dy = (y - 230) / 35.0
        if abs(dy) <= 1:
            half = 55 * (1 - dy * dy) ** 0.5
            for x in range(max(int(300 - half), 0), min(int(300 + half), WIDTH)):
                row[x] = bytes(collar_dark)

        # Cadre discret.
        if y == 18 or y == HEIGHT - 19:
            for x in range(18, WIDTH - 18):
                row[x] = bytes(frame)
        if 18 <= y < HEIGHT - 18:
            for x in (18, WIDTH - 19):
                row[x] = bytes(frame)

        rows.append(b"\x00" + b"".join(row))

    return b"".join(rows)


def write_png(path: pathlib.Path, pixels: bytes) -> None:
    header = struct.pack(">IIBBBBB", WIDTH, HEIGHT, 8, 2, 0, 0, 0)
    payload = (
        b"\x89PNG\r\n\x1a\n"
        + chunk(b"IHDR", header)
        + chunk(b"IDAT", zlib.compress(pixels, 9))
        + chunk(b"IEND", b"")
    )
    path.write_bytes(payload)


def main() -> None:
    TARGET.mkdir(parents=True, exist_ok=True)

    for index, (top, bottom, garment) in enumerate(PALETTES, start=1):
        path = TARGET / f"placeholder-{index}.png"
        write_png(path, build_pixels(top, bottom, garment))
        print(f"Visuel généré : {path.relative_to(ROOT)} ({path.stat().st_size // 1024} Ko)")


if __name__ == "__main__":
    main()
