#!/usr/bin/env bash
#
# Build RPM for app-nut.
#
# Usage:
#   ./build-rpm.sh
#   VERSION=0.1.56 ./build-rpm.sh
#   RELEASE=2 ./build-rpm.sh
#   TOPDIR=/tmp/rpmbuild ./build-rpm.sh
#
# The script downloads the GitHub release archive and builds the RPM using
# app-nut.spec from the current directory.

set -euo pipefail

PKG_NAME="app-nut"
VERSION="${VERSION:-0.1.56}"
RELEASE="${RELEASE:-1}"
TOPDIR="${TOPDIR:-$HOME/rpmbuild}"
SPEC_FILE="${SPEC_FILE:-$(pwd)/app-nut.spec}"
SOURCE_URL="${SOURCE_URL:-https://github.com/snuglinux/app-nut/archive/refs/tags/${VERSION}.tar.gz}"
SOURCE_FILE="$TOPDIR/SOURCES/${VERSION}.tar.gz"

msg()  { printf '\033[1;32m%s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m%s\033[0m\n' "$*"; }
err()  { printf '\033[1;31m%s\033[0m\n' "$*" >&2; }

need_cmd() {
    command -v "$1" >/dev/null 2>&1 || {
        err "ERROR: command not found: $1"
        exit 1
    }
}

need_cmd rpmbuild
need_cmd curl
need_cmd tar

if [ ! -f "$SPEC_FILE" ]; then
    err "ERROR: spec file not found: $SPEC_FILE"
    exit 1
fi

msg "============================================================"
msg " Building RPM: $PKG_NAME $VERSION-$RELEASE"
msg "============================================================"
printf 'TOPDIR     : %s\n' "$TOPDIR"
printf 'SPEC       : %s\n' "$SPEC_FILE"
printf 'SOURCE_URL : %s\n' "$SOURCE_URL"
printf '\n'

mkdir -p \
    "$TOPDIR/BUILD" \
    "$TOPDIR/BUILDROOT" \
    "$TOPDIR/RPMS" \
    "$TOPDIR/SOURCES" \
    "$TOPDIR/SPECS" \
    "$TOPDIR/SRPMS"

msg "Downloading source archive..."
curl -L --fail --show-error --output "$SOURCE_FILE" "$SOURCE_URL"

msg "Checking archive..."
tar -tzf "$SOURCE_FILE" >/dev/null

msg "Copying spec..."
cp -f "$SPEC_FILE" "$TOPDIR/SPECS/$PKG_NAME.spec"

msg "Running rpmbuild..."
rpmbuild -ba \
    --define "_topdir $TOPDIR" \
    --define "version $VERSION" \
    --define "release $RELEASE" \
    "$TOPDIR/SPECS/$PKG_NAME.spec"

msg "============================================================"
msg " Build complete ✅"
msg "============================================================"

warn "RPM files:"
find "$TOPDIR/RPMS" -type f -name "${PKG_NAME}-*.rpm" -print | sort

warn "SRPM files:"
find "$TOPDIR/SRPMS" -type f -name "${PKG_NAME}-*.src.rpm" -print | sort
