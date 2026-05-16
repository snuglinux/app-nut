<?php

/**
 * NUT system USB devices view.
 *
 * @category   apps
 * @package    nut
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('nut');

if (! isset($devices))
    $devices = array();

echo infobox_highlight(
    lang('nut_add_ups'),
    lang('nut_add_ups_help')
);

$headers = array(
    lang('nut_usb_id'),
    lang('base_description'),
    lang('nut_recommended_driver'),
    lang('nut_profile'),
    lang('nut_configured_as'),
);

$items = array();

foreach ($devices as $device) {
    $profile = $device['known'] ? lang('nut_known_profile') : lang('nut_generic_profile');
    $configured_as = empty($device['configured']) ? '-' : $device['configured'];

    if (empty($device['configured'])) {
        $anchors = button_set(array(
            anchor_custom(
                '/app/nut/devices/add/' . $device['vendorid'] . '/' . $device['productid'],
                lang('base_add'),
                'high'
            ),
        ));
    } else {
        $configured_names = explode(', ', $device['configured']);
        $anchors = button_set(array(
            anchor_edit('/app/nut/settings/edit/' . $configured_names[0]),
        ));
    }

    $items[] = array(
        'anchors' => $anchors,
        'details' => array(
            $device['usb_id'],
            htmlspecialchars($device['label']),
            $device['driver'],
            $profile,
            $configured_as,
        ),
    );
}

if (count($items) === 0) {
    echo infobox_warning(
        lang('nut_usb_devices'),
        lang('nut_no_usb_devices_found')
    );
}

echo summary_table(
    lang('nut_usb_devices_found'),
    array(anchor_custom('/app/nut', lang('nut_return_to_summary'))),
    $headers,
    $items
);
