#!/usr/bin/env bash
#
# ProjectHub Agent Runner — caffeinated launcher
#
# Wraps agent-runner.php in `caffeinate` so macOS does not suspend the
# system (or its network polls) while the runner is working. Without this,
# on battery the system sleeps after ~1 min idle and the poller stalls.
#
# Flags passed to this script are forwarded to the PHP runner, e.g.:
#   scripts/agent-runner.sh --dry-run
#   scripts/agent-runner.sh --once
#
# caffeinate flags:
#   -i  prevent idle system sleep
#   -m  prevent disk from idle-sleeping
#   -s  prevent sleep while on AC power (harmless on battery)
#
# Note: the display is still allowed to sleep — that does not stop execution.
# On a MacBook, closing the lid will still sleep the machine; leave it open
# for unattended runs.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

exec caffeinate -ims php "${SCRIPT_DIR}/agent-runner.php" "$@"
