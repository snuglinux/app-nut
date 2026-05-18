<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'nut';
$app['version'] = '0.1.57';
$app['release'] = '1';
$app['vendor'] = 'SnugLinux';
$app['packager'] = 'SnugLinux';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('nut_app_description');
$app['tooltip'] = lang('nut_app_tooltip');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('nut_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_monitoring');

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['nut']['title'] = lang('nut_app_name');
$app['controllers']['settings']['title'] = lang('base_settings');
$app['controllers']['devices']['title'] = lang('nut_usb_devices');
$app['controllers']['diagnostics']['title'] = lang('nut_diagnostics');
$app['controllers']['event_log']['title'] = lang('nut_event_log');
$app['controllers']['server']['title'] = lang('nut_server');
$app['controllers']['monitor']['title'] = lang('nut_monitor');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-base',
);

$app['core_requires'] = array(
    'app-base-core',
    'nut',
    'usbutils',
);

$app['core_directory_manifest'] = array(
    // Keep only the top-level app directory here.
    // Nested dirs and /etc/clearos/nut.conf are created safely by deploy/install.
    '/var/clearos/nut' => array(
        'mode' => '0770',
        'owner' => 'root',
        'group' => 'nut',
    ),
    '/var/clearos/nut/backup' => array(
        'mode' => '0755',
        'owner' => 'root',
        'group' => 'root',
    ),
);

$app['core_file_manifest'] = array(
    'app-nut-detect' => array(
        'target' => '/usr/sbin/app-nut-detect',
        'mode' => '0755',
    ),
    'app-nut-notify' => array(
        'target' => '/usr/sbin/app-nut-notify',
        'mode' => '0755',
    ),
    'nut-server.php' => array(
        'target' => '/var/clearos/base/daemon/nut-server.php',
    ),
    'nut-monitor.php' => array(
        'target' => '/var/clearos/base/daemon/nut-monitor.php',
    ),
);

// Deliberately do not remove the "nut" RPM on app removal. UPS/NUT configs can be
// safety-critical and may have been managed manually before this app was installed.
$app['delete_dependency'] = array(
    'app-nut-core',
);
