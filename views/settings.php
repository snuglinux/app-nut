<?php

/**
 * NUT UPS device settings view.
 *
 * @category   apps
 * @package    nut
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('nut');

if (! isset($settings))
    $settings = array('MODE' => 'standalone');
if (! isset($device))
    $device = array();
if (! isset($is_new))
    $is_new = FALSE;
if (! isset($form_action))
    $form_action = 'nut/settings/edit/' . $device['UPS_NAME'];
if (! isset($preview))
    $preview = '';

$buttons = array(
    $is_new ? form_submit_add('submit') : form_submit_update('submit'),
    anchor_cancel($is_new ? '/app/nut/devices/index' : '/app/nut')
);

$title = $is_new ? lang('nut_add_ups') : lang('nut_edit_ups');
$help = $is_new ? lang('nut_add_ups_dialog_help') : lang('nut_edit_ups_help');

 echo infobox_highlight(
    lang('base_information'),
    $help
);

echo form_open($form_action);
echo form_header($title);

if (! $is_new)
    echo form_hidden('ORIGINAL_UPS_NAME', $device['UPS_NAME']);

echo field_input('UPS_NAME', $device['UPS_NAME'], lang('nut_ups_name'), FALSE);
echo field_dropdown('DRIVER', $driver_options, $device['DRIVER'], lang('nut_driver'), FALSE);
echo field_input('PORT', $device['PORT'], lang('nut_port'), FALSE);
echo field_input('VENDORID', $device['VENDORID'], lang('nut_vendorid'), FALSE);
echo field_input('PRODUCTID', $device['PRODUCTID'], lang('nut_productid'), FALSE);
echo field_input('SERIAL', $device['SERIAL'], lang('nut_serial'), FALSE);
echo field_input('DESC', $device['DESC'], lang('base_description'), FALSE);

if ($preview !== '') {
    echo field_view(
        lang('nut_config_preview'),
        '<pre style="white-space: pre-wrap; margin: 0; font-family: monospace;">' . htmlspecialchars($preview) . '</pre>'
    );
}

echo field_button_set($buttons);

echo form_footer();
echo form_close();
