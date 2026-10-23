#!/bin/sh
set -eu
: "${EXPECTED_PHP:?Set the expected PHP minor version}"
php -r 'if (PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION !== getenv("EXPECTED_PHP")) { throw new RuntimeException("PHP version mismatch"); } foreach (["pdo_mysql", "mysqli", "gd", "mbstring", "openssl", "pdo_sqlite"] as $extension) { if (!extension_loaded($extension)) { throw new RuntimeException("Missing extension: " . $extension); } } if (!gd_info()["JPEG Support"]) { throw new RuntimeException("GD JPEG missing"); }'
php --version
node --version
python3 --version
pdftotext -v
git --version
node /opt/ci-browser/smoke.cjs
