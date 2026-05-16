<?php

/**
 * NUT summary view.
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
    $settings = array();
if (! isset($mode_options))
    $mode_options = array('standalone' => 'standalone');
if (! isset($devices))
    $devices = array();
if (! isset($statuses))
    $statuses = array();
if (! isset($upsd_user_role_options))
    $upsd_user_role_options = array('upsmon_primary' => 'upsmon primary');
if (! isset($refresh_interval))
    $refresh_interval = 15;

if (! function_exists('nut_html_escape')) {
    function nut_html_escape($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('nut_format_status_value')) {
    function nut_format_status_value($key, $value)
    {
        if ($value === NULL || $value === '')
            $value = '-';

        if ($key === 'ups.status' && $value !== '-')
            $value = nut_format_status_with_description($value);
        if (preg_match('/^battery\.charge(\.low|\.warning)?$/', $key) && $value !== '-')
            $value .= ' %';
        if ($key === 'battery.runtime' && is_numeric($value))
            $value = round($value / 60, 1) . ' ' . lang('nut_minutes');
        if ($key === 'battery.temperature' && $value !== '-')
            $value .= ' °C';
        if ($key === 'ups.load' && $value !== '-')
            $value .= ' %';
        if (preg_match('/voltage$/', $key) && $value !== '-')
            $value .= ' V';

        return $value;
    }
}

if (! function_exists('nut_get_status_emoji')) {
    function nut_get_status_emoji($status_text)
    {
        $status_text = strtoupper(trim((string) $status_text));

        if ($status_text === '')
            return '⚪';
        if (strpos($status_text, 'FSD') !== FALSE)
            return '🚨';
        if (strpos($status_text, 'OB') !== FALSE && strpos($status_text, 'LB') !== FALSE)
            return '🚨🪫';
        if (strpos($status_text, 'LB') !== FALSE)
            return '🪫';
        if (strpos($status_text, 'ALARM') !== FALSE)
            return '🚨';
        if (strpos($status_text, 'RB') !== FALSE)
            return '🧯';
        if (strpos($status_text, 'BYPASS') !== FALSE)
            return '↔️';
        if (strpos($status_text, 'OB') !== FALSE)
            return '🔋';
        if (strpos($status_text, 'DISCHRG') !== FALSE)
            return '📉';
        if (strpos($status_text, 'CHRG') !== FALSE)
            return '⚡';
        if (strpos($status_text, 'TRIM') !== FALSE || strpos($status_text, 'BOOST') !== FALSE)
            return '🟠';
        if (strpos($status_text, 'WAIT') !== FALSE)
            return '⏳';
        if (strpos($status_text, 'OFF') !== FALSE)
            return '⚫';
        if (strpos($status_text, 'OL') !== FALSE)
            return '🔌';

        return '⚪';
    }
}


if (! function_exists('nut_get_status_description')) {
    function nut_get_status_description($status_text)
    {
        $status_text = strtoupper(trim((string) $status_text));

        if ($status_text === '')
            return '';

        if (strpos($status_text, 'OB') !== FALSE && strpos($status_text, 'LB') !== FALSE)
            return lang('nut_status_description_ob_lb');

        $map = array(
            'OL' => lang('nut_status_description_ol'),
            'OB' => lang('nut_status_description_ob'),
            'LB' => lang('nut_status_description_lb'),
            'CHRG' => lang('nut_status_description_chrg'),
            'DISCHRG' => lang('nut_status_description_dischrg'),
            'RB' => lang('nut_status_description_rb'),
            'BYPASS' => lang('nut_status_description_bypass'),
            'TRIM' => lang('nut_status_description_trim'),
            'BOOST' => lang('nut_status_description_boost'),
            'WAIT' => lang('nut_status_description_wait'),
            'FSD' => lang('nut_status_description_fsd'),
            'OFF' => lang('nut_status_description_off'),
            'ALARM' => lang('nut_status_description_alarm'),
            'CAL' => lang('nut_status_description_cal'),
            'OVER' => lang('nut_status_description_over'),
        );

        $descriptions = array();
        foreach (preg_split('/\s+/', $status_text) as $token) {
            if (isset($map[$token]) && ! isset($descriptions[$map[$token]]))
                $descriptions[$map[$token]] = TRUE;
        }

        return implode(', ', array_keys($descriptions));
    }
}

if (! function_exists('nut_format_status_with_description')) {
    function nut_format_status_with_description($status_text)
    {
        $formatted = nut_get_status_emoji($status_text) . ' ' . $status_text;
        $description = nut_get_status_description($status_text);

        if ($description !== '')
            $formatted .= ' — ' . $description;

        return $formatted;
    }
}

if (! function_exists('nut_format_summary_status')) {
    function nut_format_summary_status($status)
    {
        if (! $status || empty($status['available'])) {
            if ($status && isset($status['message']))
                return '⚠️ ' . $status['message'];
            return '⚪ ' . lang('nut_no_status');
        }

        $details = $status['details'];
        $status_code = isset($details['ups.status']) ? $details['ups.status'] : lang('base_ok');
        $status_text = nut_get_status_emoji($status_code) . ' ' . $status_code;

        if (isset($details['battery.charge']))
            $status_text .= ' / ' . $details['battery.charge'] . '%';

        return $status_text;
    }
}

if (! function_exists('nut_format_upsd_users_for_view')) {
    function nut_format_upsd_users_for_view($users, $role_options)
    {
        $lines = array();

        if (! is_array($users))
            $users = array();

        foreach ($users as $user) {
            $username = isset($user['USERNAME']) ? $user['USERNAME'] : '';
            $role = isset($user['ROLE']) ? $user['ROLE'] : 'readonly';

            if ($username === '')
                continue;

            $role_label = isset($role_options[$role]) ? $role_options[$role] : $role;
            $lines[] = nut_html_escape($username) . ' — ' . nut_html_escape($role_label) . ' / ••••••••';
        }

        if (count($lines) === 0)
            return '-';

        return implode('<br>', $lines);
    }
}


if (! function_exists('nut_format_upsmon_settings_for_view')) {
    function nut_format_upsmon_settings_for_view($settings)
    {
        $defaults = array(
            'MINSUPPLIES' => '1',
            'POLLFREQ' => '5',
            'POLLFREQALERT' => '5',
            'DEADTIME' => '15',
            'FINALDELAY' => '5',
        );

        if (! is_array($settings))
            $settings = array();

        foreach ($defaults as $key => $value) {
            if (! isset($settings[$key]) || $settings[$key] === '')
                $settings[$key] = $value;
        }

        $lines = array();
        $lines[] = lang('nut_upsmon_minsupplies') . ': ' . nut_html_escape($settings['MINSUPPLIES']);
        $lines[] = lang('nut_upsmon_pollfreq') . ': ' . nut_html_escape($settings['POLLFREQ']) . ' ' . lang('nut_upsmon_seconds_suffix');
        $lines[] = lang('nut_upsmon_pollfreqalert') . ': ' . nut_html_escape($settings['POLLFREQALERT']) . ' ' . lang('nut_upsmon_seconds_suffix');
        $lines[] = lang('nut_upsmon_deadtime') . ': ' . nut_html_escape($settings['DEADTIME']) . ' ' . lang('nut_upsmon_seconds_suffix');
        $lines[] = lang('nut_upsmon_finaldelay') . ': ' . nut_html_escape($settings['FINALDELAY']) . ' ' . lang('nut_upsmon_seconds_suffix');

        return implode('<br>', $lines);
    }
}

$status_map = array(
    'ups.status' => lang('nut_status'),
    'battery.charge' => lang('nut_battery_charge'),
    'battery.charge.low' => lang('nut_battery_charge_low'),
    'battery.charge.warning' => lang('nut_battery_charge_warning'),
    'battery.runtime' => lang('nut_battery_runtime'),
    'battery.temperature' => lang('nut_battery_temperature'),
    'battery.date' => lang('nut_battery_date'),
    'ups.load' => lang('nut_load'),
    'input.voltage' => lang('nut_input_voltage'),
    'output.voltage' => lang('nut_output_voltage'),
    'device.mfr' => lang('nut_manufacturer'),
    'device.model' => lang('nut_model'),
    'device.serial' => lang('nut_serial'),
);

///////////////////////////////////////////////////////////////////////////////
// Warnings
///////////////////////////////////////////////////////////////////////////////

if (isset($warnings) && count($warnings)) {
    echo infobox_warning(
        lang('base_warning'),
        '<ul><li>' . implode('</li><li>', $warnings) . '</li></ul>'
    );
}

///////////////////////////////////////////////////////////////////////////////
// ClearOS daemon sidebar integration
///////////////////////////////////////////////////////////////////////////////

// ClearOS daemon.js.php uses these hidden fields to update the standard
// right-side app summary card rows: Status and Action.  The server controller
// below intentionally manages the whole NUT stack, not only nut-server.service.
echo "<input id='os_app_name' value='nut' type='hidden'>
";
echo "<input id='os_daemon_name' value='nut-stack' type='hidden'>
";
echo "<input id='os_daemon_status_lock' value='off' type='hidden'>
";

///////////////////////////////////////////////////////////////////////////////
// Settings: nut.conf MODE
///////////////////////////////////////////////////////////////////////////////

$current_mode = isset($settings['MODE']) ? $settings['MODE'] : 'standalone';
$current_mode_display = isset($mode_options[$current_mode]) ? $mode_options[$current_mode] : $current_mode;
$current_listeners = isset($settings['UPSD_LISTENERS']) && is_array($settings['UPSD_LISTENERS']) ? $settings['UPSD_LISTENERS'] : array();
if (count($current_listeners) === 0)
    $current_listeners[] = array(
        'ADDRESS' => isset($settings['UPSD_LISTEN_ADDRESS']) ? $settings['UPSD_LISTEN_ADDRESS'] : '127.0.0.1',
        'PORT' => isset($settings['UPSD_LISTEN_PORT']) ? $settings['UPSD_LISTEN_PORT'] : '3493',
    );

$current_listener_lines = array();
foreach ($current_listeners as $listener) {
    $address = isset($listener['ADDRESS']) ? $listener['ADDRESS'] : '';
    $port = isset($listener['PORT']) ? $listener['PORT'] : '3493';
    if ($address !== '')
        $current_listener_lines[] = nut_html_escape($address . ':' . $port);
}
$current_listeners_display = count($current_listener_lines) ? implode('<br>', $current_listener_lines) : '-';
$allow_no_device = (isset($settings['ALLOW_NO_DEVICE']) && $settings['ALLOW_NO_DEVICE'] === '1') ? '1' : '0';
$allow_not_all_listeners = (isset($settings['ALLOW_NOT_ALL_LISTENERS']) && $settings['ALLOW_NOT_ALL_LISTENERS'] === '1') ? '1' : '0';
$debug_min = isset($settings['DEBUG_MIN']) ? $settings['DEBUG_MIN'] : '0';
$debug_min_display = ($debug_min === '0') ? lang('base_disabled') : $debug_min;
$upsmon_settings = isset($settings['UPSMON_SETTINGS']) && is_array($settings['UPSMON_SETTINGS']) ? $settings['UPSMON_SETTINGS'] : array();
$current_users = isset($settings['UPSD_USERS']) && is_array($settings['UPSD_USERS']) ? $settings['UPSD_USERS'] : array();
if (count($current_users) === 0)
    $current_users[] = array(
        'USERNAME' => isset($settings['UPSMON_USER']) ? $settings['UPSMON_USER'] : 'upsmon-local',
        'PASSWORD' => isset($settings['UPSMON_PASSWORD']) ? $settings['UPSMON_PASSWORD'] : '',
        'ROLE' => 'upsmon_primary',
    );

echo form_open('nut/settings/mode/edit');
echo form_header(lang('base_settings'));
echo field_view(lang('nut_mode'), nut_html_escape($current_mode_display));
echo field_view(lang('nut_allow_no_device'), ($allow_no_device === '1') ? lang('base_enabled') : lang('base_disabled'));
echo field_view(lang('nut_allow_not_all_listeners'), ($allow_not_all_listeners === '1') ? lang('base_enabled') : lang('base_disabled'));
echo field_view(lang('nut_debug_min'), $debug_min_display);
echo field_view(lang('nut_upsd_listeners'), $current_listeners_display);
echo field_button_set(array(
    anchor_edit('/app/nut/settings/mode/edit'),
    '<button type="button" class="btn btn-primary" onclick="window.location.href=\'/app/nut/event_log/index\'; return false;">' . nut_html_escape(lang('nut_event_log')) . '</button>',
    '<button type="button" class="btn btn-primary" onclick="window.location.href=\'/app/nut/diagnostics/index\'; return false;">' . nut_html_escape(lang('nut_diagnostics')) . '</button>'
));
echo form_footer();
echo form_close();

///////////////////////////////////////////////////////////////////////////////
// Live refresh status line
///////////////////////////////////////////////////////////////////////////////

echo '<div id="nut-live-status-bar" data-refresh-interval="' . intval($refresh_interval) . '" style="margin: 0 0 10px 0;">';
echo '<span>' . lang('nut_auto_refresh') . ': <strong>' . intval($refresh_interval) . ' ' . lang('nut_seconds') . '</strong></span>';
echo ' &middot; ';
echo '<span>' . lang('nut_last_update') . ': <span id="nut-last-refresh">--:--:--</span></span>';
echo ' <span id="nut-live-refresh-state"></span>';
echo '</div>';

///////////////////////////////////////////////////////////////////////////////
// Configured UPS list
///////////////////////////////////////////////////////////////////////////////

$buttons = array(
    anchor_add('/app/nut/devices/index'),
);

$headers = array(
    lang('nut_ups_name'),
    lang('base_description'),
    lang('nut_status'),
);

$items = array();

foreach ($devices as $device) {
    $ups_name = $device['UPS_NAME'];
    $ups_name_safe = nut_html_escape($ups_name);
    $status = isset($statuses[$ups_name]) ? $statuses[$ups_name] : NULL;
    $status_text = nut_format_summary_status($status);
    $status_text = '<span class="nut-live-summary-status" data-nut-ups="' . $ups_name_safe . '" style="white-space: pre-line;">' . nut_html_escape($status_text) . '</span>';

    $items[] = array(
        'anchors' => button_set(array(
            anchor_edit('/app/nut/settings/edit/' . $ups_name),
            anchor_delete('/app/nut/settings/delete/' . $ups_name),
        )),
        'details' => array(
            $ups_name_safe,
            nut_html_escape($device['DESC']),
            $status_text,
        ),
    );
}

echo summary_table(
    lang('nut_configured_ups_devices'),
    $buttons,
    $headers,
    $items
);

///////////////////////////////////////////////////////////////////////////////
// Detailed status for every configured UPS
///////////////////////////////////////////////////////////////////////////////

if (count($devices) > 0) {
    foreach ($devices as $device) {
        $ups_name = $device['UPS_NAME'];
        $ups_name_safe = nut_html_escape($ups_name);
        $status_items = array();
        $status = isset($statuses[$ups_name]) ? $statuses[$ups_name] : NULL;
        $details = ($status && ! empty($status['available']) && isset($status['details'])) ? $status['details'] : array();
        $message = ($status && isset($status['message'])) ? $status['message'] : lang('nut_no_status');

        foreach ($status_map as $key => $label) {
            $value = isset($details[$key]) ? $details[$key] : '-';
            $value = nut_format_status_value($key, $value);
            $status_items[] = array(
                'details' => array(
                    $label,
                    '<span class="nut-live-field" data-nut-ups="' . $ups_name_safe . '" data-nut-key="' . nut_html_escape($key) . '">' . nut_html_escape($value) . '</span>'
                )
            );
        }

        $status_items[] = array(
            'details' => array(
                lang('nut_message'),
                '<span class="nut-live-message" data-nut-ups="' . $ups_name_safe . '" style="white-space: pre-line;">' . nut_html_escape($message) . '</span>'
            )
        );

        echo summary_table(
            lang('nut_runtime_status') . ': ' . $ups_name_safe,
            array(),
            array(lang('nut_parameter'), lang('nut_value')),
            $status_items,
            array('no_action' => TRUE)
        );
    }
}
