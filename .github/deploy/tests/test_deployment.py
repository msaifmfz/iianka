"""Executable release-contract tests. All host state is temporary and synthetic."""

import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch

TOOLS = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("artifact", TOOLS / "artifact.py")
artifact = importlib.util.module_from_spec(spec)
spec.loader.exec_module(artifact)
REVISION = "a" * 40
RELEASE_ID = REVISION + "-123-1"


def application(path):
    files = {
        "artisan": "<?php // synthetic application\n",
        "bootstrap/app.php": "<?php // synthetic bootstrap\n",
        "config/app.php": "<?php return [];\n",
        "composer.lock": '{"packages": []}\n',
        "vendor/composer/installed.json": '{"dev": false, "packages": []}\n',
        "vendor/autoload.php": "<?php // synthetic autoload\n",
        "public/build/manifest.json": json.dumps({"app": {"file": "assets/app-new.js", "dynamicImports": ["lazy"]}, "lazy": {"file": "assets/lazy-new.js"}}),
        "public/build/assets/app-new.js": "new client",
        "public/build/assets/lazy-new.js": "lazy client",
        "public/build/assets/pdf.worker-test.js": "pdf worker",
    }
    for name, value in files.items():
        file = path / name
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text(value)


class ArchiveTests(unittest.TestCase):
    def setUp(self):
        self.workspace = tempfile.TemporaryDirectory()
        self.addCleanup(self.workspace.cleanup)
        self.root = Path(self.workspace.name)
        self.source = self.root / "source"
        self.source.mkdir()
        application(self.source)
        self.archive = self.root / "release.tar.gz"
        self.destination = self.root / "release"
        self.destination.mkdir()

    def pack(self):
        artifact.pack(self.source, REVISION, self.archive)

    def test_roundtrip_preserves_payload_and_checksum(self):
        self.pack()

        artifact.extract(self.archive, self.destination, REVISION)

        self.assertEqual((self.destination / "REVISION").read_text(), REVISION + "\n")
        self.assertEqual((self.destination / "public/build/assets/lazy-new.js").read_text(), "lazy client")
        self.assertEqual(self.archive.with_suffix(".gz.sha256").read_text().split()[0], artifact.digest(self.archive))

    def test_wrong_commit_is_rejected(self):
        self.pack()

        with self.assertRaisesRegex(ValueError, "revision mismatch"):
            artifact.extract(self.archive, self.destination, "b" * 40)

    def test_runtime_or_environment_files_cannot_be_packaged(self):
        for name in (".env", "database/database.sqlite", "bootstrap/cache/config.php", "bootstrap/ssr/ssr.js", "public/hot"):
            with self.subTest(name=name):
                file = self.source / name
                file.parent.mkdir(parents=True, exist_ok=True)
                file.touch()
                try:
                    with self.assertRaises(ValueError):
                        self.pack()
                finally:
                    file.unlink()
                    # Do not leave forbidden empty directories for the next case.
                    if name.startswith("bootstrap/"):
                        file.parent.rmdir()

    def test_missing_lazy_import_and_pdf_worker_are_rejected(self):
        for name in ("lazy-new.js", "pdf.worker-test.js"):
            with self.subTest(name=name):
                file = self.source / "public/build/assets" / name
                original = file.read_bytes()
                file.unlink()
                try:
                    with self.assertRaises(ValueError):
                        self.pack()
                finally:
                    file.write_bytes(original)

    def malicious_archive(self, name, kind=tarfile.REGTYPE, data=b"attack", linkname=""):
        with tarfile.open(self.archive, "w:gz") as archive:
            item = tarfile.TarInfo(name)
            item.type = kind
            item.linkname = linkname
            item.size = len(data) if kind == tarfile.REGTYPE else 0
            archive.addfile(item, io.BytesIO(data) if item.size else None)

    def test_archive_paths_links_and_special_files_are_rejected(self):
        for name, kind in (("../escaped", tarfile.REGTYPE), ("/absolute", tarfile.REGTYPE), ("public/link", tarfile.SYMTYPE), ("public/hardlink", tarfile.LNKTYPE), ("public/device", tarfile.CHRTYPE)):
            with self.subTest(name=name):
                self.malicious_archive(name, kind)
                with self.assertRaises(ValueError):
                    artifact.extract(self.archive, self.destination, REVISION)
                self.assertEqual(list(self.destination.iterdir()), [])
        self.assertFalse((self.root / "escaped").exists())

    def test_duplicate_archive_members_are_rejected(self):
        with tarfile.open(self.archive, "w:gz") as archive:
            for _ in range(2):
                item = tarfile.TarInfo("artisan")
                item.size = 1
                archive.addfile(item, io.BytesIO(b"x"))

        with self.assertRaisesRegex(ValueError, "Duplicate"):
            artifact.extract(self.archive, self.destination, REVISION)

    def test_payload_tampering_is_rejected(self):
        self.pack()
        tampered = self.root / "tampered.tar.gz"
        with tarfile.open(self.archive) as source, tarfile.open(tampered, "w:gz") as target:
            for member in source:
                data = source.extractfile(member).read()
                if member.name == "artisan":
                    data = b"changed"
                    member.size = len(data)
                target.addfile(member, io.BytesIO(data))

        with self.assertRaisesRegex(ValueError, "manifest"):
            artifact.extract(tampered, self.destination, REVISION)

    def test_development_vendor_is_rejected(self):
        (self.source / "vendor/composer/installed.json").write_text('{"dev": true, "packages": []}')

        with self.assertRaisesRegex(ValueError, "Development vendor"):
            self.pack()

    def test_extracted_size_limit_rejects_payload_before_writing(self):
        self.pack()

        with patch.object(artifact, "MAX_PAYLOAD_BYTES", 1):
            with self.assertRaisesRegex(ValueError, "deployment limits"):
                artifact.extract(self.archive, self.destination, REVISION)

        self.assertEqual(list(self.destination.iterdir()), [])

    def test_member_limit_rejects_an_archive_with_excessive_members(self):
        self.pack()

        with patch.object(artifact, "MAX_MEMBERS", 1):
            with self.assertRaisesRegex(ValueError, "excessive"):
                artifact.extract(self.archive, self.destination, REVISION)

    def test_existing_extraction_target_is_not_overwritten(self):
        self.pack()
        (self.destination / "keep").write_text("keep")

        with self.assertRaisesRegex(ValueError, "empty directory"):
            artifact.extract(self.archive, self.destination, REVISION)

        self.assertEqual((self.destination / "keep").read_text(), "keep")


class HostStateTests(unittest.TestCase):
    def setUp(self):
        self.workspace = tempfile.TemporaryDirectory()
        self.addCleanup(self.workspace.cleanup)
        self.base = Path(self.workspace.name).resolve()
        self.environment_root = self.base / "staging"
        self.previous = self.environment_root / "releases/previous"
        self.previous.mkdir(parents=True)
        application(self.previous)
        (self.previous / "public/build/assets/old-chunk.js").write_text("old browser tab")
        self.current = self.environment_root / "current"
        self.current.symlink_to(self.previous)
        for name in ("storage/framework", "storage/app/public", "database", "deploy-incoming", "deploy-artifacts"):
            (self.environment_root / "shared" / name).mkdir(parents=True, exist_ok=True)
        (self.environment_root / "shared/.env").touch()
        (self.environment_root / "shared/database/database.sqlite").touch()
        self.payload = self.base / "payload"
        self.payload.mkdir()
        application(self.payload)
        self.archive = self.base / "release.tar.gz"
        artifact.pack(self.payload, REVISION, self.archive)
        self.checksum = artifact.digest(self.archive)
        self.release = self.environment_root / "releases" / RELEASE_ID
        self.log = self.base / "commands.jsonl"
        self.failures = self.base / "failures"
        self.failures.mkdir()
        self.bin = self.base / "bin"
        self.bin.mkdir()
        self.environment = dict(os.environ, PATH=str(self.bin) + os.pathsep + os.environ["PATH"], IIANKA_DEPLOY_BASE=str(self.base), IIANKA_DEPLOY_HEALTH_URL="https://staging.example.test/up", IIANKA_DEPLOY_MIN_FREE_BYTES="0", IIANKA_DEPLOY_LAUNCHER="/owner/fixed/launcher", TEST_LOG=str(self.log), TEST_FAILURES=str(self.failures), TEST_ROOT=str(self.environment_root))
        self.command("php", '''
import json,os,sys
from pathlib import Path
args=sys.argv[1:]
root=Path(os.environ['TEST_ROOT']); failures=Path(os.environ['TEST_FAILURES'])
with open(os.environ['TEST_LOG'],'a') as out: out.write(json.dumps(['php',str(Path.cwd()),*args])+'\\n')
if 'config:cache' in args and (failures/'cache').exists(): sys.exit(137)
if 'migrate:status' in args and (failures/'migration').exists(): sys.exit(17)
if any(arg.endswith('runtime.php') for arg in args) and (failures/'boot').exists() and root.joinpath('current').resolve().name.startswith('a'*40): sys.exit(1)
if 'down' in args: root.joinpath('shared/storage/framework/down').touch()
if 'up' in args: root.joinpath('shared/storage/framework/down').unlink(missing_ok=True)
''')
        self.command("sudo", '''
import json,os,sys
from pathlib import Path
action=sys.argv[3]; marker=Path(os.environ['TEST_FAILURES'])/'services'
with open(os.environ['TEST_LOG'],'a') as out: out.write(json.dumps(['services' if action.startswith('services-') else 'labels',action,*sys.argv[5:],sys.argv[4]])+'\\n')
if action=='services-state': print(os.environ.get('TEST_SERVICE_STATE','1 1'))
if action=='services-start' and marker.exists(): marker.unlink(); sys.exit(1)
''')
        self.command("curl", '''
import os,sys
from pathlib import Path
marker=Path(os.environ['TEST_FAILURES'])/'health'
if marker.exists(): marker.unlink(); sys.exit(22)
''')
        # Use real GNU primitives in CI. macOS adapters exercise the same transitions locally.
        if sys.platform != "linux":
            self.command("flock", '''
import fcntl,sys
try: fcntl.flock(int(sys.argv[-1]),fcntl.LOCK_EX|fcntl.LOCK_NB)
except BlockingIOError: sys.exit(1)
''')
            self.command("readlink", "import os,sys\nprint(os.path.realpath(sys.argv[-1]))\n")
            self.command("mv", "import os,sys\nos.replace(sys.argv[-2],sys.argv[-1])\n")

    def command(self, name, body):
        path = self.bin / name
        path.write_text("#!" + sys.executable + "\n" + body)
        path.chmod(0o755)

    def run_host(self, operation, checksum=None, release_id=RELEASE_ID):
        return subprocess.run(["bash", str(TOOLS / "host.sh"), operation, "staging", release_id, REVISION, checksum or self.checksum], input=self.archive.read_bytes() if operation == "prepare" else b"", env=self.environment, capture_output=True)

    def prepare(self):
        result = self.run_host("prepare")
        self.assertEqual(result.returncode, 0, result.stderr.decode())

    def commands(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()] if self.log.exists() else []

    def test_preparation_is_inactive_and_preserves_old_chunks_and_bundle(self):
        self.prepare()

        self.assertEqual(self.current.resolve(), self.previous)
        self.assertEqual((self.release / "public/build/assets/old-chunk.js").read_text(), "old browser tab")
        self.assertEqual((self.release / "READY").read_text(), REVISION + "\n")
        self.assertEqual(artifact.digest(self.environment_root / "shared/deploy-artifacts" / (RELEASE_ID + ".tar.gz")), self.checksum)
        self.assertFalse(any("down" in command or command[0] == "services" for command in self.commands()))
        self.assertFalse(any("migrate" in command or "npm" in command or "composer" in command for command in self.commands()))

    def test_checksum_failure_leaves_current_untouched(self):
        result = self.run_host("prepare", checksum="0" * 64)

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertFalse(self.release.exists())
        self.assertEqual(self.commands(), [])

    def test_killed_cache_process_never_marks_release_ready(self):
        (self.failures / "cache").touch()

        result = self.run_host("prepare")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertFalse((self.release / "READY").exists())
        self.assertFalse(any(command[0] == "services" for command in self.commands()))

    def test_pending_migrations_block_activation_before_maintenance(self):
        self.prepare()
        (self.failures / "migration").touch()

        result = self.run_host("activate")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertFalse((self.environment_root / "shared/storage/framework/down").exists())
        self.assertFalse(any(command[0] == "services" for command in self.commands()))

    def test_successful_activation_can_be_rolled_back(self):
        self.prepare()

        result = self.run_host("activate")

        self.assertEqual(result.returncode, 0, result.stderr.decode())
        self.assertEqual(self.current.resolve(), self.release)
        self.assertEqual((self.release / "PREVIOUS").read_text().strip(), str(self.previous))
        self.assertFalse((self.environment_root / "shared/storage/framework/down").exists())
        result = self.run_host("rollback")
        self.assertEqual(result.returncode, 0, result.stderr.decode())
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertTrue(all(command[-1] == "staging" for command in self.commands() if command[0] == "services"))

    def test_stale_preparation_cannot_overwrite_a_later_release(self):
        self.prepare()
        later = self.environment_root / "releases/later"
        later.mkdir()
        self.current.unlink()
        self.current.symlink_to(later)

        result = self.run_host("activate")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), later)
        self.assertFalse((self.environment_root / "shared/storage/framework/down").exists())
        self.assertFalse(any(command[0] == "services" for command in self.commands()))

    def test_activation_preserves_inactive_worker_services(self):
        self.prepare()
        self.environment["TEST_SERVICE_STATE"] = "0 0"

        result = self.run_host("activate")

        self.assertEqual(result.returncode, 0, result.stderr.decode())
        self.assertIn(["services", "services-start", "0", "0", "staging"], self.commands())
        self.assertEqual((self.release / "SERVICES_PREVIOUS").read_text(), "0 0\n")

    def test_activation_failures_recover_previous_release(self):
        for failure in ("boot", "health", "services"):
            with self.subTest(failure=failure):
                # Each scenario has its own mutable application state.
                fixture = HostStateTests()
                fixture.setUp()
                try:
                    fixture.prepare()
                    (fixture.failures / failure).touch()
                    result = fixture.run_host("activate")
                    self.assertNotEqual(result.returncode, 0)
                    self.assertEqual(fixture.current.resolve(), fixture.previous)
                    self.assertFalse((fixture.environment_root / "shared/storage/framework/down").exists())
                finally:
                    fixture.doCleanups()

    def test_in_place_layout_is_rejected_without_application_commands(self):
        self.current.unlink()
        self.current.mkdir()

        result = self.run_host("prepare")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.commands(), [])

    def test_failed_preparation_cannot_be_activated(self):
        self.release.mkdir()

        result = self.run_host("activate")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertEqual(self.commands(), [])

    def test_maintenance_and_existing_identity_require_owner_recovery(self):
        self.prepare()
        again = self.run_host("prepare")
        (self.environment_root / "shared/storage/framework/down").touch()

        result = self.run_host("activate")

        self.assertNotEqual(again.returncode, 0)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertTrue((self.environment_root / "shared/storage/framework/down").exists())

    def test_insufficient_disk_space_does_not_start_preparation(self):
        self.environment["IIANKA_DEPLOY_MIN_FREE_BYTES"] = str(2**60)

        result = self.run_host("prepare")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertFalse(self.release.exists())
        self.assertEqual(self.commands(), [])

    def test_shared_host_lock_prevents_concurrent_deployments(self):
        import fcntl
        with (self.base / ".artifact-deploy.lock").open("w") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)

            result = self.run_host("prepare")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertFalse(self.release.exists())
        self.assertEqual(self.commands(), [])

    def test_asset_name_collision_aborts_preparation(self):
        (self.previous / "public/build/assets/app-new.js").write_text("different bytes under same URL")

        result = self.run_host("prepare")

        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.current.resolve(), self.previous)
        self.assertFalse((self.release / "READY").exists())


if __name__ == "__main__":
    unittest.main()
