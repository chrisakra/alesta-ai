#!/usr/bin/env python3
"""Package alesta-ai + alesta-ai-pro ZIPs for v2.0.6 release.
Bypass PowerShell Compress-Archive bug with accented paths.
"""
import os
import sys
import zipfile

sys.stdout.reconfigure(encoding="utf-8")

ROOT = os.path.dirname(os.path.abspath(__file__))
DIST = os.path.join(ROOT, "dist")
os.makedirs(DIST, exist_ok=True)


def zipdir(src_rel: str, zip_name: str, root_name: str) -> None:
    src = os.path.join(ROOT, src_rel)
    out_path = os.path.join(DIST, zip_name)
    zipf = zipfile.ZipFile(out_path, "w", zipfile.ZIP_DEFLATED)
    n = 0
    for dirpath, dirnames, filenames in os.walk(src):
        dirnames[:] = [d for d in dirnames if not d.startswith(".") and d not in ("node_modules", "vendor")]
        for fn in filenames:
            if fn.startswith(".") or fn.endswith((".zip", ".log")):
                continue
            full = os.path.join(dirpath, fn)
            rel = os.path.relpath(full, src)
            # Use POSIX separator inside ZIPs (WP wp-cli expects forward slashes)
            arcname = root_name + "/" + rel.replace(os.sep, "/")
            zipf.write(full, arcname)
            n += 1
    zipf.close()
    print(f"  -> {zip_name} ({n} files, {os.path.getsize(out_path)} bytes)")


print("=" * 60)
print("Alesta AI v2.0.6 — packaging Free + Pro Addon")
print("=" * 60)
zipdir("alesta-ai", "alesta-ai-2.0.7.zip", "alesta-ai")
zipdir("alesta-ai-pro", "alesta-ai-pro-2.0.7.zip", "alesta-ai-pro")
print()
print("Done. ZIPs in:", DIST)
