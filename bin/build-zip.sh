#!/usr/bin/env sh
# Builds the installable plugin zip from the committed state (HEAD).
# Files marked export-ignore in .gitattributes (tests, CI, ...) are left out.
set -eu
cd "$(dirname "$0")/.."
version=$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["version"];' 2>/dev/null \
    || python3 -c 'import json; print(json.load(open("composer.json"))["version"])')
git archive --format=zip --prefix=SecondaryVariantProperties/ -o "SecondaryVariantProperties-$version.zip" HEAD
echo "SecondaryVariantProperties-$version.zip"
