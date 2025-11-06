#!/usr/bin/env bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
DIFF_FILE=$(mktemp)
PHPCS_FILE=$(mktemp)

git remote set-branches --add origin master
git fetch origin master
git diff origin/master > $DIFF_FILE

$DIR/../vendor/bin/phpcs --extensions=php --standard=.phpcs.xml.dist --report=json > $PHPCS_FILE || true

$DIR/../vendor/bin/diffFilter --phpcs $DIFF_FILE $PHPCS_FILE 100
