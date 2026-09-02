#!/bin/sh
#
# Build a ZIP that the Moodle plugin installer accepts.
#
# The installer checks the name of the root directory inside the archive against the plugin
# component, so mod_pagecheck has to arrive in a folder called exactly "pagecheck". The archive
# GitHub offers under "Download source code" names that folder after the repository and branch,
# which is why it is rejected with "Invalid plugin name".
#
# Usage: tools/package.sh [ref]
#
#   ref   The commit, branch or tag to package. Defaults to HEAD.

set -eu

root=$(git rev-parse --show-toplevel)
cd "$root"

ref=${1:-HEAD}

# git archive reads the named commit, not the working tree, so anything uncommitted, untracked
# included, would be left out of the package without any sign of it. Say so rather than shipping a
# ZIP that does not match what is on disk.
if [ "$ref" = "HEAD" ] && [ -n "$(git status --porcelain)" ]; then
    echo "There are uncommitted changes, and they would not reach the package." >&2
    echo "Commit them, or pass an explicit ref: tools/package.sh <ref>" >&2
    exit 1
fi

mkdir -p dist
output="dist/pagecheck.zip"

git archive --format=zip --prefix=pagecheck/ -o "$output" "$ref"

size=$(du -h "$output" | cut -f1)
echo "Wrote $output ($size) from $(git rev-parse --short "$ref")."
echo "Upload it under Site administration > Plugins > Install plugins."
