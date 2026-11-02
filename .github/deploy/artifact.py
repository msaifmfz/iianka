#!/usr/bin/env python3
"""Build and validate iianka release archives without loading application config."""

import argparse
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import tarfile

MAX_ARCHIVE_BYTES = 256 * 1024 * 1024
MAX_PAYLOAD_BYTES = 1024 * 1024 * 1024
MAX_MEMBERS = 50000
MAX_RETAINED_ASSET_BYTES = 256 * 1024 * 1024
ROOTS = {"app", "bootstrap", "config", "database", "lang", "public", "resources", "routes", "vendor"}
ROOT_FILES = {"artisan", "composer.json", "composer.lock", "REVISION"}
REQUIRED = {"artisan", "bootstrap/app.php", "config/app.php", "composer.lock", "vendor/autoload.php", "public/build/manifest.json", "vendor/composer/installed.json", "REVISION"}


def digest(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def valid_path(name):
    path = PurePosixPath(name)
    if not name or name != path.as_posix() or path.is_absolute() or ".." in path.parts or "\\" in name:
        raise ValueError("Unsafe archive path")
    if any(part.startswith(".") or part.endswith((".sqlite", ".sqlite-shm", ".sqlite-wal", ".key")) for part in path.parts):
        raise ValueError("Runtime, hidden or secret file in payload")
    if path.parts[0] not in ROOTS and name not in ROOT_FILES:
        raise ValueError("Unexpected payload root")
    if name.startswith(("bootstrap/cache/", "bootstrap/ssr/", "database/seeders/")):
        raise ValueError("Generated cache, SSR or seed data in payload")
    if path.name in {"auth.json", "credentials.json"}:
        raise ValueError("Credential file in payload")
    if name in {"public/hot", "public/storage"} or name.startswith("public/storage/"):
        raise ValueError("Runtime public file in payload")
    return path


def check_vendor(root):
    installed = json.loads((root / "vendor/composer/installed.json").read_text())
    locked = json.loads((root / "composer.lock").read_text())
    if installed.get("dev") is not False:
        raise ValueError("Development vendor tree is not deployable")
    if {item["name"] for item in installed["packages"]} != {item["name"] for item in locked["packages"]}:
        raise ValueError("Production vendor differs from the lock")


def check_build(root):
    build = root / "public/build"
    manifest = json.loads((build / "manifest.json").read_text())
    if not isinstance(manifest, dict) or not manifest:
        raise ValueError("Empty Vite manifest")
    for entry in manifest.values():
        if not isinstance(entry, dict) or not isinstance(entry.get("file"), str):
            raise ValueError("Invalid Vite manifest entry")
        for name in [entry["file"], *entry.get("css", []), *entry.get("assets", [])]:
            path = PurePosixPath(name)
            if path.is_absolute() or ".." in path.parts or "\\" in name or not (build / name).is_file():
                raise ValueError("Missing or unsafe built asset")
        for name in [*entry.get("imports", []), *entry.get("dynamicImports", [])]:
            if name not in manifest:
                raise ValueError("Missing Vite manifest import")
    if not list((build / "assets").glob("pdf.worker*.js")):
        raise ValueError("Missing JavaScript PDF worker")


def pack(root, revision, output):
    if not re.fullmatch(r"[0-9a-f]{40}", revision):
        raise ValueError("Invalid revision")
    (root / "REVISION").write_text(revision + "\n")
    files = {}
    for path in sorted(root.rglob("*")):
        if path.is_symlink() or not (path.is_dir() or path.is_file()):
            raise ValueError("Links and special files are not permitted")
        name = path.relative_to(root).as_posix()
        valid_path(name)
        if path.is_file():
            files[name] = {"sha256": digest(path), "size": path.stat().st_size}
    if not REQUIRED <= files.keys():
        raise ValueError("Incomplete application payload")
    check_build(root)
    check_vendor(root)
    if len(files) > MAX_MEMBERS or sum(item["size"] for item in files.values()) > MAX_PAYLOAD_BYTES:
        raise ValueError("Payload exceeds deployment limits")
    output.parent.mkdir(parents=True, exist_ok=True)
    with tarfile.open(output, "w:gz") as archive:
        for name in files:
            archive.add(root / name, arcname=name, recursive=False)
        metadata = json.dumps({"revision": revision, "files": files}, sort_keys=True).encode()
        info = tarfile.TarInfo("release-manifest.json")
        info.size = len(metadata)
        info.mode = 0o644
        archive.addfile(info, io.BytesIO(metadata))
    if output.stat().st_size > MAX_ARCHIVE_BYTES:
        output.unlink()
        raise ValueError("Archive exceeds deployment limit")
    output.with_suffix(output.suffix + ".sha256").write_text(digest(output) + "  " + output.name + "\n")


def extract(archive_path, destination, revision):
    if archive_path.stat().st_size > MAX_ARCHIVE_BYTES:
        raise ValueError("Archive exceeds deployment limit")
    if not re.fullmatch(r"[0-9a-f]{40}", revision):
        raise ValueError("Invalid revision")
    if destination.is_symlink() or not destination.is_dir() or any(destination.iterdir()):
        raise ValueError("Extraction requires a new empty directory")
    # Iterate with bounded metadata; never trust tar ownership, links or permissions.
    seen = set()
    total = 0
    metadata = None
    files = {}
    with tarfile.open(archive_path, "r|gz") as archive:
        for member in archive:
            if len(seen) >= MAX_MEMBERS + 1 or member.name in seen or not member.isfile():
                raise ValueError("Duplicate, special or excessive archive members")
            seen.add(member.name)
            total += member.size
            if member.size < 0 or total > MAX_PAYLOAD_BYTES:
                raise ValueError("Payload exceeds deployment limits")
            stream = archive.extractfile(member)
            if member.name == "release-manifest.json":
                if member.size > 16 * 1024 * 1024:
                    raise ValueError("Oversized release manifest")
                metadata = json.load(stream)
                continue
            valid_path(member.name)
            target = destination / member.name
            target.parent.mkdir(parents=True, exist_ok=True)
            with target.open("xb") as output:
                shutil.copyfileobj(stream, output, 1024 * 1024)
            os.chmod(target, 0o755 if member.mode & stat.S_IXUSR else 0o644)
            files[member.name] = {"sha256": digest(target), "size": target.stat().st_size}
    if not isinstance(metadata, dict) or metadata.get("revision") != revision or metadata.get("files") != files:
        raise ValueError("Payload manifest or revision mismatch")
    if not REQUIRED <= files.keys() or (destination / "REVISION").read_text() != revision + "\n":
        raise ValueError("Incomplete or wrong revision")
    check_build(destination)
    check_vendor(destination)


def carry_assets(previous, candidate):
    """Preserve old tabs' hashed chunks; reject symlinks and hash collisions."""
    source = previous / "public/build/assets"
    target = candidate / "public/build/assets"
    if source.is_symlink() or not source.is_dir():
        raise ValueError("Previous built assets are missing or linked")
    paths = list(source.rglob("*"))
    if sum(path.stat().st_size for path in paths if path.is_file()) > MAX_RETAINED_ASSET_BYTES:
        raise ValueError("Retained assets exceed 256 MiB; owner cleanup is required")
    for path in paths:
        if path.is_symlink() or not (path.is_dir() or path.is_file()):
            raise ValueError("Unexpected previous asset type")
        if path.is_file():
            output = target / path.relative_to(source)
            if output.exists():
                if not output.is_file() or digest(output) != digest(path):
                    raise ValueError("Built asset filename collision")
            else:
                output.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(path, output)
                os.chmod(output, 0o644)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    for name in ("pack", "extract"):
        command = commands.add_parser(name)
        command.add_argument("source", type=Path)
        command.add_argument("destination", type=Path)
        command.add_argument("revision")
    command = commands.add_parser("carry-assets")
    command.add_argument("previous", type=Path)
    command.add_argument("candidate", type=Path)
    args = parser.parse_args()
    try:
        if args.command == "pack":
            pack(args.source, args.revision, args.destination)
        elif args.command == "extract":
            extract(args.source, args.destination, args.revision)
        else:
            carry_assets(args.previous, args.candidate)
    except (ValueError, OSError, tarfile.TarError, json.JSONDecodeError) as error:
        parser.exit(1, f"Artifact rejected: {error}\n")


if __name__ == "__main__":
    main()
