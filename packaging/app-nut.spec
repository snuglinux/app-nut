Name:           app-nut
Version:        0.1.56
Release:        1%{?dist}
Summary:        ClearOS Network UPS Tools web interface

License:        GPLv3
URL:            https://github.com/snuglinux/app-nut
Source0:        https://github.com/snuglinux/app-nut/archive/refs/tags/%{version}.tar.gz

BuildArch:      noarch
Requires:       app-base
Requires:       app-base-core
Requires:       nut
Requires:       usbutils
Requires:       iproute

%description
app-nut provides a ClearOS Webconfig interface for configuring and monitoring
Network UPS Tools (NUT) with local USB UPS devices.

Features include USB UPS detection, managed NUT configuration, upsd listener
settings, upsmon settings, event logging, status diagnostics, and
Ukrainian/English language support.

%prep
%setup -q -n %{name}-%{version}

%build
# Nothing to build.

%install
rm -rf %{buildroot}

install -d -m 0755 %{buildroot}/usr/clearos/apps/nut

# Expected repository layouts:
#   app-nut-0.1.56/apps/nut/...
#   app-nut-0.1.56/nut/...
# Fallback supports archives where app files are placed directly in root.
if [ -d apps/nut ]; then
    cp -a apps/nut/. %{buildroot}/usr/clearos/apps/nut/
elif [ -d nut ]; then
    cp -a nut/. %{buildroot}/usr/clearos/apps/nut/
elif [ -d controllers ] && [ -d libraries ] && [ -d views ] && [ -d deploy ]; then
    cp -a controllers libraries views deploy %{buildroot}/usr/clearos/apps/nut/
    [ -d htdocs ] && cp -a htdocs %{buildroot}/usr/clearos/apps/nut/
    [ -d language ] && cp -a language %{buildroot}/usr/clearos/apps/nut/
else
    echo "ERROR: Cannot find ClearOS app-nut source layout." >&2
    exit 1
fi

# Real package-installed helpers.  Webconfig must not call a missing helper.
install -D -m 0755 %{buildroot}/usr/clearos/apps/nut/deploy/app-nut-detect \
    %{buildroot}/usr/sbin/app-nut-detect
install -D -m 0755 %{buildroot}/usr/clearos/apps/nut/deploy/app-nut-notify \
    %{buildroot}/usr/sbin/app-nut-notify

# ClearOS daemon descriptors.
install -D -m 0644 %{buildroot}/usr/clearos/apps/nut/deploy/nut-server.php \
    %{buildroot}/var/clearos/base/daemon/nut-server.php
install -D -m 0644 %{buildroot}/usr/clearos/apps/nut/deploy/nut-monitor.php \
    %{buildroot}/var/clearos/base/daemon/nut-monitor.php

# app-nut state.  /etc/ups belongs to the nut package and is not owned here.
install -d -m 0770 %{buildroot}/var/clearos/nut
install -d -m 0755 %{buildroot}/var/clearos/nut/backup

%post
if [ "$1" -eq 1 ]; then
    /usr/clearos/apps/nut/deploy/install >/dev/null 2>&1 || :
else
    /usr/clearos/apps/nut/deploy/upgrade >/dev/null 2>&1 || :
fi

%files
%defattr(-,root,root,-)
/usr/clearos/apps/nut
/usr/sbin/app-nut-detect
/usr/sbin/app-nut-notify
/var/clearos/base/daemon/nut-server.php
/var/clearos/base/daemon/nut-monitor.php
%dir %attr(0770,root,nut) /var/clearos/nut
%dir %attr(0755,root,root) /var/clearos/nut/backup
%ghost %config(noreplace) %attr(0600,root,root) /etc/clearos/nut.conf
%ghost %attr(0660,root,nut) /var/clearos/nut/events.log
%ghost %config(noreplace) %attr(0644,root,root) /etc/tmpfiles.d/nut-run.conf

%changelog
* Sat May 16 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.1.56-1
- Add RPM packaging files and remove event log information notice.
- Install real app-nut helpers and ClearOS daemon descriptors from the package.
- Prepare app-nut state directories during package install/upgrade.
