#!/usr/bin/env bash

## MIT License
##
## Copyright (c) 2019 Helen Hou-Sandi
## Copyright (c) 2019 Erick Hitter
##
## Permission is hereby granted, free of charge, to any person obtaining a copy
## of this software and associated documentation files (the "Software"), to deal
## in the Software without restriction, including without limitation the rights
## to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
## copies of the Software, and to permit persons to whom the Software is
## furnished to do so, subject to the following conditions:
##
## The above copyright notice and this permission notice shall be included in all
## copies or substantial portions of the Software.
##
## THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
## IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
## FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
## AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
## LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
## OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
## SOFTWARE.

# Note that this does not use pipefail
# because if the grep later doesn't match any deleted files,
# which is likely the majority case,
# it does not exit with a 0, and I only care about the final exit.
set -eo

# Ensure SVN username and password are set
# IMPORTANT: while secrets are encrypted and not viewable in the GitHub UI,
# they are by necessity provided as plaintext in the context of the Action,
# so do not echo or use debug mode unless you want your secrets exposed!
if [[ -z "$CI" ]]; then
	echo "Script is only to be run by GitLab CI" 1>&2
	exit 1
fi

if [[ -z "$WP_ORG_USERNAME" ]]; then
	echo "WordPress.org password not set" 1>&2
	exit 1
fi

if [[ -z "$WP_ORG_PASSWORD" ]]; then
	echo "WordPress.org password not set" 1>&2
	exit 1
fi

if [[ -z "$PLUGIN_SLUG" ]]; then
	echo "Plugin's SVN slug is not set" 1>&2
	exit 1
fi

if [[ -z "$PLUGIN_VERSION" ]]; then
	echo "Plugin's version is not set" 1>&2
	exit 1
fi

echo "ℹ︎ PLUGIN_SLUG is $PLUGIN_SLUG"
echo "ℹ︎ PLUGIN_VERSION is $PLUGIN_VERSION"

SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
SVN_DIR="$CI_BUILDS_DIR/svn-${PLUGIN_SLUG}"
TMP_DIR="$CI_BUILDS_DIR/git-archive"

# Checkout just trunk for efficiency
# Tagging will be handled on the SVN level
echo "➤ Checking out .org repository..."
svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
cd "$SVN_DIR"
svn update --set-depth infinity trunk

# Ensure we are in the $CI_PROJECT_DIR directory, just in case
echo "➤ Copying files..."
cd "$CI_PROJECT_DIR"

git config --global user.email "git-contrib+ci@ethitter.com"
git config --global user.name "Erick Hitter (GitLab CI)"

# If there's no .gitattributes file, write a default one into place
if [[ ! -e "$CI_PROJECT_DIR/.gitattributes" ]]; then
	cat > "$CI_PROJECT_DIR/.gitattributes" <<-EOL
	/.gitattributes export-ignore
	/.gitignore export-ignore
	/.github export-ignore
	EOL

	# The .gitattributes file has to be committed to be used
	# Just don't push it to the origin repo :)
	git add .gitattributes && git commit -m "Add .gitattributes file"
fi

# This will exclude everything in the .gitattributes file with the export-ignore flag
mkdir "$TMP_DIR"
git archive HEAD | tar x --directory="$TMP_DIR"

cd "$SVN_DIR"

# Copy from clean copy to /trunk
# The --delete flag will delete anything in destination that no longer exists in source
rsync -r "$TMP_DIR/" trunk/ --delete

# Add everything and commit to SVN
# The force flag ensures we recurse into subdirectories even if they are already added
# Suppress stdout in favor of svn status later for readability
echo "➤ Preparing files..."
svn add . --force > /dev/null

# SVN delete all deleted files
# Also suppress stdout here
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm % > /dev/null

# Copy tag locally to make this a single commit
echo "➤ Copying tag..."
svn cp "trunk" "tags/$PLUGIN_VERSION"

svn status

# Stop here unless this is a merge into master.
if [[ -z "$CI_MERGE_REQUEST_TARGET_BRANCH_NAME" || "$CI_MERGE_REQUEST_TARGET_BRANCH_NAME" != "master" ]]; then
	echo "ℹ︎ EXITING before commit step as this is the '${CI_MERGE_REQUEST_TARGET_BRANCH_NAME}' branch, not the 'master' branch." 1>&2
	exit 0
fi

echo "➤ Committing files..."
svn commit -m "Update to version $PLUGIN_VERSION from GitLab ($CI_PROJECT_URL)" --no-auth-cache --non-interactive  --username "$SVN_USERNAME" --password "$SVN_PASSWORD"

echo "✓ Plugin deployed!"
