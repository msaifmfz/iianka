Server Setup Runbook

Summary

- Run the automation from a server cron entry that calls .codex/scripts/run-nightly-automation.sh.
- Run the same automation manually with composer nightly:issues.
- Use the same Unix user for cron, gh, codex, and Git credentials.
- Prefer a dedicated checkout on the same server, not the live web root, because the automation switches branches while working.
- /srv is a common Linux convention for service-owned application checkouts, but it is not required. Use whichever dedicated checkout path you choose.

Setup Steps

1. Deploy the automation files to the server.

export RUNNER_CHECKOUT=/path/to/iianka-codex-runner
cd "$RUNNER_CHECKOUT"
git fetch origin
git checkout develop
git pull --ff-only origin develop

2. Install runtime dependencies in that checkout.

composer install
npm ci
command -v git gh node php npm codex

3. Authenticate the cron user.

gh auth login
gh auth status
codex login

Also confirm the Git remote can fetch and push branches:

git fetch origin develop
git push --dry-run origin HEAD:refs/heads/codex/permission-check

4. Validate the automation environment.

cd "$RUNNER_CHECKOUT"
composer nightly:issues:check
shellcheck .codex/scripts/\*.sh

5. Create or update the GitHub labels once.

.codex/scripts/nightly-issues.sh labels

6. Preview the cron entry.

.codex/scripts/install-nightly-cron.sh print

Expected shape:

# BEGIN iianka-codex-nightly

0 0 \* \* \* cd '/path/to/iianka-codex-runner' && TIMEZONE='Asia/Tokyo' '/path/to/iianka-codex-runner/.codex/scripts/run-nightly-automation.sh' >> '/path/to/iianka-codex-runner/.codex-nightly/cron.log' 2>&1

# END iianka-codex-nightly

The cron entry uses the absolute path of the checkout where install-nightly-cron.sh is run. If you move the checkout later, reinstall the cron entry.

7. Install the cron entry.

.codex/scripts/install-nightly-cron.sh install
crontab -l | sed -n '/BEGIN iianka-codex-nightly/,/END iianka-codex-nightly/p'

8. Prepare issues for the nightly run.

gh issue edit ISSUE_NUMBER --add-label agent-ready

Use agent-high-risk-ok only for issues that may touch protected files.

Operations

- Manual run:

composer nightly:issues:check
composer nightly:issues

You can override runtime settings for manual runs, for example:

RUNS_DIR=/path/to/runtime composer nightly:issues

- Logs:
    - .codex-nightly/cron.log
    - .codex-nightly/logs/\*.log
- Runtime state:
    - .codex-nightly/current
    - .codex-nightly/\*/state.env
- Remove cron later:

.codex/scripts/install-nightly-cron.sh uninstall

Assumptions

- Base branch is develop.
- Default schedule is midnight in Asia/Tokyo.
- The cron user has GitHub permissions to edit issues, create labels, push branches, and open draft PRs.
- The automation checkout can safely switch branches and run tests without affecting the live app process.
