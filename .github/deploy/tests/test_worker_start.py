"""Worker entrypoint contract. All host state is temporary and synthetic; PHP is a stub."""

import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

TOOLS = Path(__file__).resolve().parents[1]
QUEUE = ["queue:work", "--sleep=1", "--tries=3", "--timeout=120", "--max-time=3600"]
SCHEDULER = ["schedule:work"]


class WorkerStartTests(unittest.TestCase):
    def setUp(self):
        self.workspace = tempfile.TemporaryDirectory()
        self.addCleanup(self.workspace.cleanup)
        self.root = Path(self.workspace.name).resolve()
        self.base = self.root / "var/www/iianka"
        self.staging = self.base / "staging"
        self.releases = self.staging / "releases"
        self.current = self.staging / "current"
        self.first = self.release("staging", "20260410-181616")
        self.second = self.release("staging", "f" * 40 + "-2-1")
        self.production = self.release("production", "20260410-174945")
        self.current.symlink_to(self.first)
        self.bin = self.root / "bin"
        self.bin.mkdir()
        self.log = self.root / "php.jsonl"
        self.environment = dict(os.environ, TEST_LOG=str(self.log), TEST_EUID="1000", TEST_USER="iianka", TEST_GROUP="nginx")
        # Only identity, command lookup, the fixed base and the PHP binary change in
        # this copy; the installed entrypoint has no test override.
        source = (TOOLS / "worker-start.sh").read_text()
        for token in ('"$EUID"', "export PATH=", "base=/var/www/iianka\n", "exec /usr/bin/php "):
            self.assertEqual(source.count(token), 1, token)
        source = source.replace('"$EUID"', '"${TEST_EUID:?}"')
        source = source.replace("export PATH=", "export PATH=" + str(self.bin) + ":", 1)
        source = source.replace("base=/var/www/iianka\n", "base=" + str(self.base) + "\n")
        source = source.replace("exec /usr/bin/php ", "exec " + str(self.bin / "php") + " ")
        self.entrypoint = self.root / "worker-start.sh"
        self.entrypoint.write_text(source)
        self.command("php", '''
import json,os,sys
with open(os.environ['TEST_LOG'],'a') as out:
    out.write(json.dumps({'cwd':os.getcwd(),'argv':sys.argv[1:],'pid':os.getpid()})+'\\n')
''')
        self.command("id", '''
import os,sys
print(os.environ['TEST_USER'] if sys.argv[1:]==['-un'] else os.environ['TEST_GROUP'])
''')

    def release(self, environment, name):
        path = self.base / environment / "releases" / name
        path.mkdir(parents=True)
        (path / "artisan").write_text("<?php // synthetic, never loaded by tests\n")
        return path

    def command(self, name, body):
        path = self.bin / name
        path.write_text("#!" + sys.executable + "\n" + body)
        path.chmod(0o755)

    def start(self, *arguments):
        process = subprocess.Popen(["bash", str(self.entrypoint), *arguments], env=self.environment,
                                   stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        stdout, stderr = process.communicate(timeout=30)
        return process.pid, process.returncode, stderr

    def starts(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()] if self.log.exists() else []

    def point_current(self, target):
        temporary = self.staging / "current.tmp"
        temporary.symlink_to(target)
        os.replace(temporary, self.current)

    def assert_refused(self, *arguments):
        pid, code, stderr = self.start(*arguments)
        self.assertEqual(code, 78, stderr)
        self.assertIn("iianka-worker-start:", stderr)
        self.assertEqual(self.starts(), [], "PHP must not run when startup is refused")
        return stderr

    def test_execs_php_in_the_current_release_with_arguments_preserved(self):
        for arguments in (QUEUE, SCHEDULER):
            with self.subTest(arguments=arguments):
                self.log.unlink(missing_ok=True)

                pid, code, stderr = self.start("staging", *arguments)

                self.assertEqual(code, 0, stderr)
                [started] = self.starts()
                self.assertEqual(started["cwd"], str(self.first))
                self.assertEqual(started["argv"], ["artisan", *arguments])
                # exec keeps one process: systemd's MainPID and KillSignal reach PHP directly.
                self.assertEqual(started["pid"], pid)
                self.assertIn("staging " + arguments[0] + " from release 20260410-181616", stderr)

    def test_each_start_selects_the_release_current_names_then_including_rollback(self):
        for target in (self.first, self.second, self.first):
            self.point_current(target)
            self.assertEqual(self.start("staging", *QUEUE)[1], 0)
        self.assertEqual([started["cwd"] for started in self.starts()], [str(self.first), str(self.second), str(self.first)])

    def test_relative_current_target_is_accepted(self):
        self.point_current(Path("releases") / self.second.name)

        self.assertEqual(self.start("staging", *SCHEDULER)[1], 0)
        self.assertEqual(self.starts()[0]["cwd"], str(self.second))

    def test_missing_broken_or_non_symlink_current_is_refused(self):
        self.current.unlink()
        self.assert_refused("staging", *QUEUE)
        self.current.symlink_to(self.releases / "deleted-release")
        self.assert_refused("staging", *QUEUE)
        self.current.unlink()
        self.current.mkdir()
        (self.current / "artisan").touch()
        self.assert_refused("staging", *QUEUE)

    def test_cross_environment_and_out_of_tree_targets_are_refused(self):
        outside = self.root / "outside"
        outside.mkdir()
        (outside / "artisan").touch()
        indirect = self.releases / "indirect"
        indirect.symlink_to(self.production)
        nested = self.first / "nested"
        nested.mkdir()
        (nested / "artisan").touch()
        artisan_file = self.first / "artisan"
        for target in (self.production, outside, indirect, self.releases, self.staging, nested, artisan_file):
            with self.subTest(target=str(target)):
                self.point_current(target)
                self.assert_refused("staging", *QUEUE)

    def test_symlinked_release_root_or_environment_root_is_refused(self):
        moved = self.staging / "releases-real"
        self.releases.rename(moved)
        self.releases.symlink_to(self.base / "production/releases")
        self.point_current(self.releases / self.production.name)
        self.assert_refused("staging", *QUEUE)
        self.releases.unlink()
        moved.rename(self.releases)
        self.point_current(self.first)
        real_staging = self.base / "staging-real"
        self.staging.rename(real_staging)
        self.staging.symlink_to(real_staging)
        self.assert_refused("staging", *QUEUE)

    def test_release_without_a_real_artisan_is_refused(self):
        (self.second / "artisan").unlink()
        self.point_current(self.second)
        self.assert_refused("staging", *QUEUE)
        (self.second / "artisan").symlink_to(self.first / "artisan")
        self.assert_refused("staging", *QUEUE)

    def test_root_or_other_identities_never_run_application_code(self):
        for key, value in (("TEST_EUID", "0"), ("TEST_USER", "root"), ("TEST_GROUP", "root"), ("TEST_USER", "nginx")):
            with self.subTest(key=key, value=value):
                self.environment[key] = value
                try:
                    self.assert_refused("staging", *QUEUE)
                finally:
                    self.environment.update(TEST_EUID="1000", TEST_USER="iianka", TEST_GROUP="nginx")

    def test_only_known_environments_and_worker_commands_start(self):
        for arguments in ((), ("staging",), ("other", *QUEUE), ("../staging", *QUEUE), ("staging", "tinker"),
                          ("staging", "migrate", "--force"), ("staging", "queue:workx"), ("staging", "artisan", *QUEUE)):
            with self.subTest(arguments=arguments):
                self.assert_refused(*arguments)

    def test_production_entrypoint_never_selects_staging(self):
        (self.base / "production/current").symlink_to(self.first)
        self.assert_refused("production", *QUEUE)
        (self.base / "production/current").unlink()
        (self.base / "production/current").symlink_to(self.production)
        self.assertEqual(self.start("production", *QUEUE)[1], 0)
        self.assertEqual(self.starts()[0]["cwd"], str(self.production))


if __name__ == "__main__":
    unittest.main()
