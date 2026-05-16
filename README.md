# 🔋 app-nut for ClearOS

[![ClearOS](https://img.shields.io/badge/ClearOS-Webconfig-blue.svg)](#)
[![NUT](https://img.shields.io/badge/NUT-Network%20UPS%20Tools-green.svg)](#)
[![License](https://img.shields.io/badge/License-GPLv3-lightgrey.svg)](#)
[![Status](https://img.shields.io/badge/Status-development-orange.svg)](#)

**app-nut** is a ClearOS Webconfig application for configuring, monitoring and diagnosing **Network UPS Tools (NUT)** on ClearOS systems.

The application is focused on a safe local USB UPS workflow:

```text
USB UPS → NUT driver → upsd → upsmon → ClearOS Webconfig
```

It provides a friendly Webconfig interface for USB UPS detection, NUT configuration, live status monitoring, diagnostics, and local event logging.

---

## 📸 Screenshots

<p align="center">
  <img src="https://raw.githubusercontent.com/snuglinux/app-nut/main/images/screenshot-1.png" alt="app-nut ClearOS dashboard" width="42%">
  <img src="https://raw.githubusercontent.com/snuglinux/app-nut/main/images/screenshot-2.png" alt="app-nut ClearOS settings" width="42%">
</p>

---

## ✨ Features

### 🔍 USB UPS detection

- Detects connected USB UPS devices.
- Filters out irrelevant USB devices such as Linux Foundation root hubs.
- Opens a confirmation/configuration form before writing NUT configuration.
- Does not write UPS configuration directly from the USB device list.

### ⚙️ Managed NUT configuration

The app manages the main NUT configuration files:

```text
/etc/ups/nut.conf
/etc/ups/ups.conf
/etc/ups/upsd.conf
/etc/ups/upsd.users
/etc/ups/upsmon.conf
```

Supported settings include:

- `MODE` in `nut.conf`
- local `LISTEN` addresses and ports in `upsd.conf`
- `ALLOW_NO_DEVICE`
- `ALLOW_NOT_ALL_LISTENERS`
- `DEBUG_MIN`
- multiple `upsd.users` users with different roles
- local `upsmon` parameters
- event logging settings

### 📊 Live UPS status

The dashboard shows runtime UPS data from `upsc`, including:

- UPS status
- battery charge
- low/warning battery thresholds
- estimated runtime
- battery temperature
- input/output voltage
- UPS load
- manufacturer, model and serial data

UPS status values are shown with clear labels and emoji indicators.

### 📝 Event logging

app-nut can log selected `upsmon` events into a local app-owned log:

```text
/var/clearos/nut/events.log
```

Supported event types include:

```text
ONLINE
ONBATT
LOWBATT
FSD
SHUTDOWN
COMMOK
COMMBAD
NOCOMM
REPLBATT
```

### 🧪 Diagnostics

The diagnostics page provides read-only checks for:

- configured NUT mode
- local `LISTEN` addresses
- NUT service status
- selected TCP ports
- runtime `upsc` output
- configuration file permissions
- recent service status messages

The diagnostics page does not modify configuration, firewall rules, Zabbix settings or service state.

---

## 🧱 Design principles

app-nut is intentionally conservative:

- ✅ no firewall changes
- ✅ no Zabbix configuration
- ✅ no hidden Webconfig `sudo` calls
- ✅ no private `/usr/sbin/app-nutctl`
- ✅ `/etc/ups` permissions are handled by package install/upgrade scripts
- ✅ Webconfig reads and applies only app-owned configuration
- ✅ NUT package files remain owned by the real NUT package

---

## 📦 RPM packaging

Packaging files are stored in:

```text
packaging/
├── app-nut.spec
└── build-rpm.sh
```

Build an RPM from a GitHub release tag:

```bash
cd packaging
./build-rpm.sh
```

Or specify version and release explicitly:

```bash
cd packaging
VERSION=0.1.56 RELEASE=1 ./build-rpm.sh
```

By default the build script downloads:

```text
https://github.com/snuglinux/app-nut/archive/refs/tags/<VERSION>.tar.gz
```

So the corresponding GitHub tag must exist before building with the default source URL.

---

## 🧩 Package layout

The RPM installs the ClearOS app to:

```text
/usr/clearos/apps/nut
```

It also installs real package-owned helpers:

```text
/usr/sbin/app-nut-detect
/usr/sbin/app-nut-notify
```

And ClearOS daemon descriptors:

```text
/var/clearos/base/daemon/nut-server.php
/var/clearos/base/daemon/nut-monitor.php
```

app-nut state is stored in:

```text
/var/clearos/nut
/var/clearos/nut/backup
/var/clearos/nut/events.log
```

---

## 🔧 Runtime requirements

The core package expects:

```text
app-base
app-base-core
nut
usbutils
iproute
```

NUT itself provides the real UPS tools and services, for example:

```text
upsc
upsd
upsmon
upsdrvctl
nut-server.service
nut-monitor.service
nut-driver@.service
```

---

## 🔐 NUT access model

For local monitoring, app-nut creates or manages an `upsmon primary` user in:

```text
/etc/ups/upsd.users
```

Example:

```ini
[upsmon-local]
    password = ********
    upsmon primary
```

The local monitor is then referenced in:

```text
/etc/ups/upsmon.conf
```

Example:

```ini
MONITOR ups-name@localhost 1 upsmon-local ******** primary
```

---

## 🌐 Languages

The interface includes:

- Ukrainian
- English

Event log messages are translated in Webconfig, not stored as fixed-language text in the raw event log.

---

## ⚠️ Safety notes

NUT can initiate system shutdown when a UPS reaches a critical battery state.

Important events include:

- `OB` — running on battery
- `LB` — low battery
- `FSD` — forced shutdown
- `SHUTDOWN` — shutdown sequence started

Review `upsmon.conf`, user roles and event logging settings carefully before using app-nut on production systems.

---

## 📜 License

GPLv3

---

## 👤 Author

SnugLinux  
<https://github.com/snuglinux>
