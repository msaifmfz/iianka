"""Run launcher decisions without root; opt-in Linux CI also exercises systemd."""

import json
import os
from pathlib import Path
import pwd
import grp
import subprocess
import sys
import tempfile
import unittest
import uuid

import test_deployment

TOOLS = Path(__file__).resolve().parents[1]
REVISION = test_deployment.REVISION
RELEASE_ID = test_deployment.RELEASE_ID
CHECKSUM = "b" * 64
SYSTEMD_TESTS = os.environ.get("IIANKA_TEST_SYSTEMD") == "1"
INSTALLED_ENTRYPOINT = "/usr/local/sbin/iianka-worker-start"
ENTRYPOINT_ANCESTORS = "for path in / /usr /usr/local /usr/local/sbin "
QUEUE_ARGUMENTS = "queue:work --sleep=1 --tries=3 --timeout=120 --max-time=3600"


class LauncherTests(unittest.TestCase):
    def setUp(self):
        self.workspace = tempfile.TemporaryDirectory()
        self.addCleanup(self.workspace.cleanup)
        self.root = Path(self.workspace.name).resolve()
        self.bin = self.root / "bin"
        self.bin.mkdir()
        self.parent = self.root / "etc"
        self.config_root = self.parent / "iianka-artifact-deploy"
        self.config_root.mkdir(parents=True)
        self.config = self.config_root / "staging.conf"
        self.settings = ("MEMORY_MAX_BYTES=201326592\nMIN_AVAILABLE_BYTES=562036736\n"
                         "MIN_FREE_DISK_BYTES=10737418240\nHEALTH_URL=https://staging.example.test/up\n")
        self.config.write_text(self.settings)
        for path in (self.parent, self.config_root):
            path.chmod(0o755)
        self.config.chmod(0o644)
        self.log = self.root / "commands.jsonl"
        self.environment = dict(os.environ, TEST_LOG=str(self.log), TEST_EUID="0")
        # Only the execution identity, command lookup and fixed config location
        # change in this unprivileged copy. No test overrides ship in the launcher.
        source = (TOOLS / "launcher.sh").read_text()
        self.assertEqual(source.count('"$EUID"'), 1)
        self.assertEqual(source.count("export PATH="), 1)
        source = source.replace('"$EUID"', '"${TEST_EUID:?}"')
        source = source.replace("export PATH=", "export PATH=" + str(self.bin) + ":", 1)
        source = source.replace("/etc", str(self.root / "etc"))
        # The trusted worker entrypoint and its ancestors move into the fixture too.
        self.assertEqual(source.count(INSTALLED_ENTRYPOINT), 1)
        self.assertEqual(source.count(ENTRYPOINT_ANCESTORS), 1)
        self.sbin = self.root / "usr/local/sbin"
        self.sbin.mkdir(parents=True)
        for path in (self.root / "usr", self.root / "usr/local", self.sbin):
            path.chmod(0o755)
        self.entrypoint = self.sbin / "iianka-worker-start"
        self.entrypoint.write_text("#!/bin/bash\nexit 0\n")
        self.entrypoint.chmod(0o755)
        source = source.replace(INSTALLED_ENTRYPOINT, str(self.entrypoint))
        source = source.replace(ENTRYPOINT_ANCESTORS, "for path in / " + " ".join(str(self.root / p) for p in ("usr", "usr/local", "usr/local/sbin")) + " ")
        self.environment["TEST_ENTRYPOINT"] = str(self.entrypoint)
        self.launcher = self.root / "launcher.sh"
        self.launcher.write_text(source)
        self.command("stat", '''
import os,stat,sys
from pathlib import Path
if sys.argv[1:3]==['-fc','%T']:
    print(os.environ.get('TEST_CGROUP','cgroup2fs'))
elif sys.argv[2]=='%u':
    print(1 if sys.argv[-1]==os.environ.get('TEST_UNTRUSTED') else 0)
else:
    print(format(stat.S_IMODE(Path(sys.argv[-1]).lstat().st_mode),'o'))
''')
        self.command("awk", "import os\nprint(os.environ.get('TEST_AVAILABLE','1073741824'))\n")
        self.command("systemctl", '''
import json,os,sys
args=sys.argv[1:]
with open(os.environ['TEST_LOG'],'a') as out: out.write(json.dumps(['systemctl',*args])+'\\n')
if args[0]=='show':
    unit=args[1]; property=args[args.index('-p')+1]
    role='queue' if unit.startswith('iianka-queue@') else 'scheduler'
    environment=unit.split('@',1)[-1]
    entry=os.environ['TEST_ENTRYPOINT']
    command={'queue':'queue:work --sleep=1 --tries=3 --timeout=120 --max-time=3600','scheduler':'schedule:work'}[role]
    exec_start=('{ path='+entry+' ; argv[]='+entry+' '+environment+' '+command+
                ' ; ignore_errors=no ; start_time=[n/a] ; stop_time=[n/a] ; pid=0 ; code=(null) ; status=0/0 }')
    values={'MemoryMax':os.environ.get('TEST_SLICE_MAX','201326592'),'MemorySwapMax':os.environ.get('TEST_SLICE_SWAP','0'),
            'User':os.environ.get('TEST_USER','iianka'),'Group':os.environ.get('TEST_GROUP','nginx'),
            'WorkingDirectory':os.environ.get('TEST_WORKING_DIRECTORY','/var/www/iianka/'+environment),
            'ExecStart':os.environ.get('TEST_EXEC_START_'+role.upper(),exec_start).replace('ENTRY',entry)}
    print(values[property])
elif args[0]=='is-active':
    sys.exit(0 if os.environ.get('TEST_ACTIVE','1')=='1' else 3)
''')
        self.command("systemd-run", '''
import json,os,sys
with open(os.environ['TEST_LOG'],'a') as out: out.write(json.dumps(['systemd-run',*sys.argv[1:]])+'\\n')
''')
        # Prove negative cases start from a usable fixture, including on macOS
        # where the real /etc is a symlink and must not stand in for Linux /etc.
        baseline = self.run_launcher()
        self.assertEqual(baseline.returncode, 0, baseline.stderr)
        self.log.unlink()

    def command(self, name, body):
        path = self.bin / name
        path.write_text("#!" + sys.executable + "\n" + body)
        path.chmod(0o755)

    def run_launcher(self, operation="prepare", environment="staging", arguments=None):
        if arguments is None:
            arguments = [operation, environment, RELEASE_ID, REVISION, CHECKSUM]
        return subprocess.run(["bash", str(self.launcher), *arguments], env=self.environment, capture_output=True, text=True)

    def commands(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()] if self.log.exists() else []

    def assert_refused(self, result):
        self.assertNotEqual(result.returncode, 0, result.stdout)
        self.assertFalse(any(command[0] == "systemd-run" for command in self.commands()))

    def test_handoff_preserves_limits_identity_and_literal_configuration(self):
        self.config.write_text("# Owner-reviewed values\n\n" + self.settings.rstrip("\n"))

        result = self.run_launcher()

        self.assertEqual(result.returncode, 0, result.stderr)
        args = self.commands()[-1]
        for expected in ("--property=MemoryMax=201326592", "--property=MemorySwapMax=0", "--property=OOMPolicy=stop", "--property=KillMode=control-group",
                         "--uid=iianka", "--gid=nginx", "--slice=iianka-deploy.slice", "--setenv=IIANKA_DEPLOY_HEALTH_URL=https://staging.example.test/up"):
            self.assertIn(expected, args)
        self.assertFalse(any("MemoryOOMGroup" in arg for arg in args))
        self.assertEqual(args[-6:], ["/usr/local/libexec/iianka-deploy/host.sh", "prepare", "staging", RELEASE_ID, REVISION, CHECKSUM])

    def test_nonroot_and_malformed_arguments_never_reach_systemd(self):
        self.environment["TEST_EUID"] = "1000"
        self.assert_refused(self.run_launcher())
        self.environment["TEST_EUID"] = "0"
        for arguments in (["prepare", "other", RELEASE_ID, REVISION, CHECKSUM], ["prepare", "staging", "../escape", REVISION, CHECKSUM],
                          ["prepare", "staging", RELEASE_ID, REVISION, CHECKSUM, "extra"], ["services-start staging", "1", "1"],
                          ["services-state", "staging", "extra"], ["services-start", "staging", "1", "2"]):
            with self.subTest(arguments=arguments):
                self.assert_refused(self.run_launcher(arguments=arguments))

    def test_writable_or_nonroot_configuration_ancestors_are_refused(self):
        for path in (self.parent, self.config_root, self.config):
            for defect in ("owner", "group-write", "world-write"):
                with self.subTest(path=path.name, defect=defect):
                    original = path.stat().st_mode & 0o777
                    try:
                        if defect == "owner":
                            self.environment["TEST_UNTRUSTED"] = str(path)
                        else:
                            path.chmod(original | (0o020 if defect == "group-write" else 0o002))
                        self.assert_refused(self.run_launcher())
                    finally:
                        path.chmod(original)
                        self.environment.pop("TEST_UNTRUSTED", None)

    def test_symlinked_configuration_or_parent_is_refused(self):
        for path in (self.config, self.config_root, self.parent):
            with self.subTest(path=path.name):
                moved = path.with_name(path.name + "-moved")
                path.rename(moved)
                path.symlink_to(moved, target_is_directory=moved.is_dir())
                try:
                    self.assert_refused(self.run_launcher())
                finally:
                    path.unlink()
                    moved.rename(path)

    def test_configuration_is_never_executed_and_unknown_or_duplicate_keys_are_refused(self):
        marker = self.root / "must-not-exist"
        for payload in (f"$(touch {marker})", f"1; touch {marker}", "'201326592'", "0", "999999999999999999999"):
            with self.subTest(payload=payload):
                self.config.write_text(self.settings.replace("201326592", payload))
                self.assert_refused(self.run_launcher())
                self.assertFalse(marker.exists())
        for suffix in (f"touch {marker}\n", "PATH=/tmp\n", "MEMORY_MAX_BYTES=201326592\n", "HEALTH_URL=https://staging.example.test/up;false\n"):
            with self.subTest(suffix=suffix):
                self.config.write_text(self.settings + suffix)
                self.assert_refused(self.run_launcher())
                self.assertFalse(marker.exists())
        self.config.write_text(self.settings.replace("https://staging.example.test/up", f"https://staging.example.test/up;touch {marker}"))
        self.assert_refused(self.run_launcher())
        self.assertFalse(marker.exists())

    def test_missing_keys_cannot_be_filled_by_inherited_environment(self):
        self.config.write_text(self.settings.replace("MIN_AVAILABLE_BYTES=562036736\n", ""))
        self.environment["MIN_AVAILABLE_BYTES"] = "562036736"

        self.assert_refused(self.run_launcher())

    def test_unprepared_production_cannot_reach_systemd(self):
        self.assert_refused(self.run_launcher(environment="production"))

    def test_legacy_app_owned_configuration_is_not_a_fallback(self):
        legacy = self.parent / "iianka/artifact-deploy"
        legacy.mkdir(parents=True)
        legacy.parent.chmod(0o777)
        marker = self.root / "must-not-exist"
        (legacy / "staging.conf").write_text(self.settings + f"touch {marker}\n")
        self.config.unlink()

        self.assert_refused(self.run_launcher())

        self.assertFalse(marker.exists())

    def test_memory_cgroup_and_slice_gates_refuse_before_handoff(self):
        for key, value in (("TEST_AVAILABLE", "562036735"), ("TEST_CGROUP", "tmpfs"), ("TEST_SLICE_MAX", "infinity"),
                           ("TEST_SLICE_MAX", "201326593"), ("TEST_SLICE_SWAP", "infinity")):
            with self.subTest(key=key, value=value):
                self.environment[key] = value
                try:
                    self.assert_refused(self.run_launcher())
                finally:
                    self.environment.pop(key)

    def assert_service_actions_refused(self):
        for operation in ("services-state", "services-stop", "services-start"):
            with self.subTest(operation=operation):
                args = [operation, "staging"] + (["1", "1"] if operation == "services-start" else [])
                result = self.run_launcher(arguments=args)
                self.assertNotEqual(result.returncode, 0, result.stdout)
                self.assertEqual(result.stdout, "")
        self.assertFalse(any(command[1] in ("start", "stop", "is-active") for command in self.commands()))

    def test_entrypoint_contract_allows_service_actions(self):
        result = self.run_launcher(arguments=["services-state", "staging"])

        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout, "1 1\n")
        queried = {(command[2], command[4]) for command in self.commands() if command[1] == "show"}
        for unit in ("iianka-queue@staging", "iianka-scheduler@staging"):
            for prop in ("User", "Group", "WorkingDirectory", "ExecStart"):
                self.assertIn((unit, prop), queried)

    def test_pinned_or_current_symlink_working_directory_refuses_service_actions(self):
        # current is the configuration SELinux refused (200/CHDIR); pinned never follows a release.
        for directory in ("/var/www/iianka/staging/releases/old", "/var/www/iianka/staging/current", "/var/www/iianka/production", "/"):
            with self.subTest(directory=directory):
                self.environment["TEST_WORKING_DIRECTORY"] = directory
                self.assert_service_actions_refused()

    def test_exec_start_must_be_the_trusted_entrypoint_for_this_environment_and_role(self):
        tail = " ; ignore_errors=no ; start_time=[n/a] ; stop_time=[n/a] ; pid=0 ; code=(null) ; status=0/0 }"
        cases = {
            "direct php": "{ path=/usr/bin/php ; argv[]=/usr/bin/php artisan " + QUEUE_ARGUMENTS + tail,
            "other environment": "{ path=ENTRY ; argv[]=ENTRY production " + QUEUE_ARGUMENTS + tail,
            "scheduler role": "{ path=ENTRY ; argv[]=ENTRY staging schedule:work" + tail,
            "other command": "{ path=ENTRY ; argv[]=ENTRY staging tinker" + tail,
            "extended command": "{ path=ENTRY ; argv[]=ENTRY staging queue:workx" + tail,
            "untrusted copy": "{ path=/tmp/iianka-worker-start ; argv[]=/tmp/iianka-worker-start staging " + QUEUE_ARGUMENTS + tail,
            "second ExecStart": "{ path=ENTRY ; argv[]=ENTRY staging " + QUEUE_ARGUMENTS + tail + " { path=/usr/bin/php ; argv[]=/usr/bin/php -r 1" + tail,
            "missing": "",
        }
        for name, exec_start in cases.items():
            with self.subTest(case=name):
                self.environment["TEST_EXEC_START_QUEUE"] = exec_start
                try:
                    self.assert_service_actions_refused()
                finally:
                    self.environment.pop("TEST_EXEC_START_QUEUE")

    def test_worker_identity_must_remain_iianka_nginx(self):
        for key, value in (("TEST_USER", "root"), ("TEST_USER", ""), ("TEST_GROUP", "root")):
            with self.subTest(key=key, value=value):
                self.environment[key] = value
                try:
                    self.assert_service_actions_refused()
                finally:
                    self.environment.pop(key)

    def test_untrusted_or_replaced_entrypoint_refuses_service_actions(self):
        for path in (self.entrypoint, self.sbin, self.sbin.parent, self.sbin.parent.parent):
            for defect in ("owner", "group-write", "world-write"):
                with self.subTest(path=str(path), defect=defect):
                    original = path.stat().st_mode & 0o777
                    try:
                        if defect == "owner":
                            self.environment["TEST_UNTRUSTED"] = str(path)
                        else:
                            path.chmod(original | (0o020 if defect == "group-write" else 0o002))
                        self.assert_service_actions_refused()
                    finally:
                        path.chmod(original)
                        self.environment.pop("TEST_UNTRUSTED", None)
        moved = self.entrypoint.with_name("moved")
        self.entrypoint.rename(moved)
        try:
            with self.subTest(defect="missing"):
                self.assert_service_actions_refused()
            with self.subTest(defect="symlink"):
                self.entrypoint.symlink_to(moved)
                self.assert_service_actions_refused()
                self.entrypoint.unlink()
            with self.subTest(defect="directory"):
                self.entrypoint.mkdir()
                self.assert_service_actions_refused()
                self.entrypoint.rmdir()
        finally:
            moved.rename(self.entrypoint)
        with self.subTest(defect="not executable"):
            self.entrypoint.chmod(0o644)
            self.assert_service_actions_refused()
            self.entrypoint.chmod(0o755)

    def test_installed_entrypoint_path_matches_the_helper_and_documentation(self):
        helper = (TOOLS / "worker-start.sh").read_text()
        self.assertIn("as " + INSTALLED_ENTRYPOINT, helper.splitlines()[1])
        repository = TOOLS.parents[1]
        for document in ("docs/deploy-memory-proposal.md", "SERVER_SETUP.md"):
            with self.subTest(document=document):
                text = (repository / document).read_text()
                self.assertIn("ExecStart=" + INSTALLED_ENTRYPOINT, text)
                self.assertNotIn("WorkingDirectory=/var/www/iianka/%i/current", text)
                self.assertNotIn("WorkingDirectory=/var/www/iianka/staging/current\n", text)

    def test_services_restart_only_the_previously_active_staging_unit(self):
        result = self.run_launcher(arguments=["services-start", "staging", "1", "0"])

        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.commands()[-1], ["systemctl", "start", "iianka-queue@staging"])

    def systemd_command(self, unit):
        result = self.run_launcher()
        self.assertEqual(result.returncode, 0, result.stderr)
        # Use the properties actually emitted by the launcher, not a second list.
        properties = [arg for arg in self.commands()[-1] if arg.startswith("--property=")]
        command = ["sudo", "-n", "/usr/bin/systemd-run", "--quiet", "--wait", "--pipe", "--service-type=exec", "--unit=" + unit,
                   "--uid=" + pwd.getpwuid(os.getuid()).pw_name, "--gid=" + grp.getgrgid(os.getgid()).gr_name, *properties]
        self.addCleanup(subprocess.run, ["sudo", "-n", "systemctl", "reset-failed", unit], capture_output=True)
        self.addCleanup(subprocess.run, ["sudo", "-n", "systemctl", "stop", unit], capture_output=True)
        return command

    @unittest.skipUnless(SYSTEMD_TESTS, "real systemd tests run explicitly in Linux CI")
    def test_real_systemd_accepts_the_launchers_properties(self):
        command = self.systemd_command("iianka-ci-properties-" + uuid.uuid4().hex)

        result = subprocess.run([*command, "/bin/true"], capture_output=True, text=True, timeout=45)

        self.assertEqual(result.returncode, 0, result.stderr)

    @unittest.skipUnless(SYSTEMD_TESTS, "real systemd tests run explicitly in Linux CI")
    def test_real_child_oom_stops_activation_and_recovers_synthetic_previous_release(self):
        fixture = test_deployment.HostStateTests()
        fixture.setUp()
        self.addCleanup(fixture.doCleanups)
        fixture.prepare()
        (fixture.failures / "oom").touch()
        unit = "iianka-ci-oom-" + uuid.uuid4().hex
        command = self.systemd_command(unit)
        for key, value in fixture.environment.items():
            if key == "PATH" or key.startswith(("IIANKA_DEPLOY_", "TEST_")):
                command.append("--setenv=" + key + "=" + value)

        result = subprocess.run([*command, "/bin/bash", str(TOOLS / "host.sh"), "activate", "staging", RELEASE_ID, REVISION, fixture.checksum],
                                capture_output=True, text=True, timeout=45)

        self.assertNotEqual(result.returncode, 0)
        outcome = subprocess.run(["sudo", "-n", "systemctl", "show", unit, "-p", "Result", "--value"], capture_output=True, text=True, check=True)
        self.assertEqual(outcome.stdout.strip(), "oom-kill", result.stderr)
        self.assertEqual(fixture.current.resolve(), fixture.previous, result.stderr)
        self.assertFalse((fixture.environment_root / "shared/storage/framework/down").exists(), result.stderr)
        self.assertIn("Previous release restored", result.stderr)
