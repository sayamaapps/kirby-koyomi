#!/bin/sh
# Runs the PHP checks of the package: `composer test` or `sh tests/run.sh`.
# Every file is a standalone script (exit code 0 = pass), not PHPUnit — so
# they also run on a hoster PHP without a test framework.
#
# Only some files: `sh tests/run.sh IcsTest VTimezoneTest` (names without
# directory and without .php).
set -eu
cd "$(dirname "$0")/.."
echo "PHP $(php -r 'echo PHP_VERSION;')"
if [ "$#" -gt 0 ]; then
    files=""
    for name in "$@"; do
        files="$files tests/${name%.php}.php"
    done
else
    files=$(echo tests/*Test.php)
fi
failed=0
for f in $files; do
    [ -e "$f" ] || { echo "✗ $f does not exist"; failed=1; continue; }
    echo "→ $f"
    php "$f" || failed=1
done
exit "$failed"
