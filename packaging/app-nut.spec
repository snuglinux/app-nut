Name:           app-nut
Version:        0.1.56
Release:        4%{?dist}
Summary:        ClearOS Network UPS Tools web interface

License:        GPLv3
URL:            https://github.com/snuglinux/app-nut
Source0:        https://github.com/snuglinux/app-nut/archive/refs/tags/%{version}.tar.gz

BuildArch:      noarch
Requires:       app-base
Requires:       app-base-core
Requires:       nut >= 2.8.0
Requires:       nut-client >= 2.8.0
Requires:       usbutils
Requires:       iproute
Requires(post): nut >= 2.8.0
Requires(post): nut-client >= 2.8.0
Requires(post): systemd

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
#   app-nut-%{version}/apps/nut/...
#   app-nut-%{version}/nut/...
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

# Do not package ClearOS daemon descriptors.
# They are not required for the app page and caused Webconfig 500 errors when
# ClearOS tried to load /var/clearos/base/daemon/nut-server.php.
rm -f \
    %{buildroot}/usr/clearos/apps/nut/deploy/nut-server.php \
    %{buildroot}/usr/clearos/apps/nut/deploy/nut-monitor.php

# Real package-installed helpers. Webconfig must not call a missing helper.
install -D -m 0755 %{buildroot}/usr/clearos/apps/nut/deploy/app-nut-detect \
    %{buildroot}/usr/sbin/app-nut-detect
install -D -m 0755 %{buildroot}/usr/clearos/apps/nut/deploy/app-nut-notify \
    %{buildroot}/usr/sbin/app-nut-notify

# app-nut state. /etc/ups belongs to the nut package and is not owned here.
# Ownership/mode are normalized by deploy/install and deploy/upgrade.
install -d -m 0755 %{buildroot}/var/clearos/nut
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
%dir %verify(not owner group mode) /var/clearos/nut
%dir /var/clearos/nut/backup
%ghost %config(noreplace) %attr(0600,root,root) /etc/clearos/nut.conf
%ghost %verify(not owner group mode) /var/clearos/nut/events.log
%ghost %config(noreplace) %attr(0644,root,root) /etc/tmpfiles.d/nut-run.conf

%changelog
* Sun May 17 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.1.56-4
- Require the full NUT runtime explicitly: nut >= 2.8.0 and nut-client >= 2.8.0.
- Add post-install runtime dependency ordering for nut, nut-client and systemd.
- Keep /var/clearos/nut traversable by Webconfig; only events.log uses group nut.
- Keep ClearOS daemon descriptors out of the RPM.

* Sun May 17 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.1.56-3
- Do not package ClearOS daemon descriptors nut-server.php and nut-monitor.php.
- Keep NUT service handling inside app-nut library code instead of base daemon descriptors.
- Avoid root:nut ownership in %%files to prevent generated Requires: group(nut).

* Sat May 16 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.1.56-2
- Remove root:nut ownership from RPM file metadata to avoid generated Requires: group(nut).

* Sat May 16 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.1.56-1
- Add RPM packaging files and remove event log information notice.
- Install real app-nut helpers.
- Prepare app-nut state directories during package install/upgrade.
