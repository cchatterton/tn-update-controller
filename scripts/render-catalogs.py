#!/usr/bin/env python3
"""Render readable GitHub catalog lists from the published registry."""

import argparse
import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "catalogs"


def markdown_text(value):
    return str(value).replace("\\", "\\\\").replace("*", "\\*").replace("[", "\\[").replace("]", "\\]")


def render(catalog):
    plugins = catalog["plugins"]
    lines = [
        "# " + markdown_text(catalog["name"]),
        "",
        "**Type:** " + catalog["type"].capitalize(),
        "**Status:** " + catalog["status"].replace("_", " ").capitalize(),
        "**Last updated:** " + catalog["last_updated"],
        "**Plugins:** " + str(len(plugins)),
        "",
    ]
    if catalog.get("notice"):
        lines += [markdown_text(catalog["notice"]), ""]
    for plugin in plugins:
        name = markdown_text(plugin["name"])
        beta = " (Beta)" if plugin.get("beta") else ""
        description = markdown_text(plugin.get("description", ""))
        lines.append(f"- **{name}**{beta}" + (f" — {description}" if description else ""))
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="Fail if the readable lists differ from the registry")
    args = parser.parse_args()
    registry = json.loads((ROOT / "catalogue-registry.json").read_text())
    expected = {OUTPUT / (catalog["id"] + ".md"): render(catalog) for catalog in registry["catalogs"]}
    if args.check:
        actual = set(OUTPUT.glob("*.md")) if OUTPUT.exists() else set()
        if actual != set(expected) or any(not path.is_file() or path.read_text() != content for path, content in expected.items()):
            parser.error("Readable catalog lists are out of sync; run scripts/render-catalogs.py")
        print(f"Verified {len(expected)} readable catalog lists")
        return
    OUTPUT.mkdir(exist_ok=True)
    for path, content in expected.items():
        path.write_text(content)
    print(f"Rendered {len(expected)} readable catalog lists")


if __name__ == "__main__":
    main()
