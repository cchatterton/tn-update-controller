#!/usr/bin/env python3
"""Check that curated Techn membership requires matching verified releases."""
import importlib.util
import json
from copy import deepcopy
from pathlib import Path

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("render_catalogs", root / "scripts/render-catalogs.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
registry = json.loads((root / "catalogue-registry.json").read_text())
module.verify_techn_releases(registry)
bad = deepcopy(registry)
current = next(catalog for catalog in bad["catalogs"] if catalog["id"] == "techn-27")
meeting = next(plugin for plugin in current["plugins"] if plugin["id"] == "tn-meeting-polls")
meeting["repo"] = "unverified-repository"
try:
    module.verify_techn_releases(bad)
except ValueError:
    print("Verified curated collection requires a matching release")
else:
    raise AssertionError("Unverified collection entry was accepted")
