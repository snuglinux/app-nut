#!/usr/bin/env bash
#
# Build RPM for app-nut.
#
# Usage:
#   ./build-rpm.sh
#   VERSION=0.1.57 ./build-rpm.sh
#   RELEASE=1 ./build-rpm.sh
#   TOPDIR=/tmp/rpmbuild ./build-rpm.sh
#
# The script downloads the GitHub release archive and builds the RPM using
# app-nut.spec from the current directory.
#
# Verification after build:
# - RPM must not contain ClearOS daemon descriptors that caused Webconfig 500.
# - RPM must not generate Requires: group(nut).
# - RPM must require the real NUT runtime packages: nut and nut-client.

set -euo pipefail

PKG_NAME="app-nut"
VERSION="${VERSION:-0.1.57}"
RELEASE="${RELEASE:-1}"
TOPDIR="${TOPDIR:-$HOME/rpmbuild}"
SPEC_FILE="${SPEC_FILE:-$(pwd)/app-nut.spec}"
SOURCE_URL="${SOURCE_URL:-https://github.com/snuglinux/app-nut/archive/refs/tags/${VERSION}.tar.gz}"
SOURCE_FILE="$TOPDIR/SOURCES/${VERSION}.tar.gz"
RPM_SPEC="$TOPDIR/SPECS/$PKG_NAME.spec"

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
need_cmd rpm
need_cmd curl
need_cmd tar
need_cmd sed
need_cmd find
need_cmd grep
need_cmd sort

if [ ! -f "$SPEC_FILE" ]; then
    err "ERROR: spec file not found: $SPEC_FILE"
    exit 1
fi

msg "============================================================"
msg " Building RPM: $PKG_NAME $VERSION-$RELEASE"
msg "============================================================"
printf 'TOPDIR     : %s\n' "$TOPDIR"
printf 'SPEC       : %s\n' "$SPEC_FILE"
printf 'RPM_SPEC   : %s\n' "$RPM_SPEC"
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

msg "Preparing spec with requested Version/Release..."
sed \
    -e "s/^Version:[[:space:]].*/Version:        ${VERSION}/" \
    -e "s/^Release:[[:space:]].*/Release:        ${RELEASE}%{?dist}/" \
    "$SPEC_FILE" > "$RPM_SPEC"

msg "Running rpmbuild..."
rpmbuild -ba \
    --define "_topdir $TOPDIR" \
    "$RPM_SPEC"

mapfile -t RPM_FILES < <(find "$TOPDIR/RPMS" -type f -name "${PKG_NAME}-${VERSION}-${RELEASE}"'*.rpm' -print | sort)

if [ "${#RPM_FILES[@]}" -eq 0 ]; then
    err "ERROR: built RPM not found for $PKG_NAME-$VERSION-$RELEASE"
    exit 1
fi

RPM_FILE="${RPM_FILES[0]}"

msg "Checking RPM file list..."
if rpm -qpl "$RPM_FILE" | grep -E '/(var/clearos/base/daemon|usr/clearos/apps/nut/deploy)/nut-(server|monitor)\.php$' >/dev/null; then
    err "ERROR: RPM still contains forbidden daemon descriptor files:"
    rpm -qpl "$RPM_FILE" | grep -E '/(var/clearos/base/daemon|usr/clearos/apps/nut/deploy)/nut-(server|monitor)\.php$' >&2
    exit 1
fi

msg "Checking generated RPM requirements..."
REQUIRES="$(rpm -qp --requires "$RPM_FILE")"

if printf '%s\n' "$REQUIRES" | grep -E '^group\(nut\)$' >/dev/null; then
    err "ERROR: RPM still generates forbidden requirement: group(nut)"
    printf '%s\n' "$REQUIRES" | grep -E '^group\(nut\)$' >&2
    exit 1
fi

if ! printf '%s\n' "$REQUIRES" | grep -E '^nut([[:space:]]|$)' >/dev/null; then
    err "ERROR: RPM does not require nut"
    printf '%s\n' "$REQUIRES" >&2
    exit 1
fi

if ! printf '%s\n' "$REQUIRES" | grep -E '^nut-client([[:space:]]|$)' >/dev/null; then
    err "ERROR: RPM does not require nut-client"
    printf '%s\n' "$REQUIRES" >&2
    exit 1
fi

msg "============================================================"
msg " Build complete ✅"
msg "============================================================"

warn "RPM files:"
find "$TOPDIR/RPMS" -type f -name "${PKG_NAME}-*.rpm" -print | sort

warn "SRPM files:"
find "$TOPDIR/SRPMS" -type f -name "${PKG_NAME}-*.src.rpm" -print | sort

msg "============================================================"
msg " Verification complete ✅"
msg "============================================================"
printf 'Checked RPM : %s\n' "$RPM_FILE"
printf 'Required    : nut, nut-client\n'
printf 'Forbidden   : nut-server.php, nut-monitor.php\n'
printf 'Forbidden   : Requires: group(nut)\n'
