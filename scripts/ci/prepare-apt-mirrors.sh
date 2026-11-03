#!/usr/bin/env bash
set -euo pipefail
# Only disposable GitHub-hosted Linux runners, never an installed CRM server.
if [[ "${GITHUB_ACTIONS:-}" != true || "${RUNNER_OS:-}" != Linux ]]; then
  echo 'This helper is limited to GitHub Actions Linux runners.' >&2
  exit 1
fi
# Keep Ubuntu signature verification. The Azure HTTP mirror repeatedly stalled
# the real browser dependency step; retain the official HTTPS archive instead.
if [[ -f /etc/apt/apt-mirrors.txt ]]; then
  sudo sed -i 's#http://azure.archive.ubuntu.com/ubuntu#https://archive.ubuntu.com/ubuntu#g' /etc/apt/apt-mirrors.txt
fi
printf '%s\n' 'Acquire::http::Timeout "20";' 'Acquire::https::Timeout "20";' 'Acquire::Retries "1";' | sudo tee /etc/apt/apt.conf.d/99-crm-ci-timeouts >/dev/null
