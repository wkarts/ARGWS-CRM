#!/usr/bin/env python3
"""Offline package/provenance checks for the reviewed MA112 PNG assets. No image editing."""
import hashlib
import json
from pathlib import Path
import struct
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
manifest = json.loads((ROOT / 'modules/ma/docs/ptbr-assets-112.json').read_text())
plan = json.loads((ROOT / 'modules/ma/docs/ptbr-image-plan.json').read_text())
sources = {item['source_url']: item for item in plan['assets']}
assert manifest['version'] == 112 and manifest['locale'] == 'pt-BR'
assert len(manifest['assets']) == 7
seen = set()
for item in manifest['assets']:
    relative = Path(item['path'])
    assert relative.parent.as_posix() == 'modules/ma/assets/email-templates/ptbr'
    assert relative.name not in seen and relative.suffix == '.png'
    seen.add(relative.name)
    path = ROOT / relative
    assert not path.is_symlink()
    data = path.read_bytes()
    assert data[:8] == b'\x89PNG\r\n\x1a\n' and data[12:16] == b'IHDR'
    width, height = struct.unpack('>II', data[16:24])
    assert [width, height] == item['dimensions']
    assert data[24] == 8 and data[25] == item['png_color_type']
    assert len(data) == item['bytes'] and hashlib.sha256(data).hexdigest() == item['sha256']
    source = sources[item['source_url']]
    assert source['source_sha256'] == item['source_sha256']
    assert source['dimensions'] == item['source_dimensions']
    assert source['design_references'] == item['design_references']
    # Existing email width/height attributes remain unchanged; generated raster ratio must match.
    sw, sh = item['source_dimensions']
    assert abs((width / height) / (sw / sh) - 1) < 0.002
    if item['method'] == 'original_svg_placeholder':
        svg_path = ROOT / item['svg_source']
        svg = svg_path.read_bytes()
        assert hashlib.sha256(svg).hexdigest() == item['svg_sha256']
        node = ET.fromstring(svg)
        assert [int(node.attrib['width']), int(node.attrib['height'])] == [width, height] == [sw, sh]
        assert data[25] == 6, 'placeholder must retain RGBA transparency'
        assert b'<image' not in svg and b'<script' not in svg, 'original vector only'
    else:
        assert item['method'] == 'imagegen_text_localization'
assert seen == {p.name for p in (ROOT / 'modules/ma/assets/email-templates/ptbr').glob('*.png')}
print(f'MA112: {len(seen)} packaged PNGs, provenance, hashes, dimensions and ratios verified; no network.')
