<?php

/**
 * NUT general settings view.
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
    $settings = array(
        'MODE' => 'standalone',
        'UPSD_LISTENERS' => array(array('ADDRESS' => '127.0.0.1', 'PORT' => '3493')),
        'ALLOW_NO_DEVICE' => '0',
        'ALLOW_NOT_ALL_LISTENERS' => '0',
        'DEBUG_MIN' => '0',
        'UPSMON_SETTINGS' => array(
            'MINSUPPLIES' => '1',
            'SHUTDOWNCMD' => '/sbin/shutdown -h +0',
            'POLLFREQ' => '5',
            'POLLFREQALERT' => '5',
            'HOSTSYNC' => '15',
            'DEADTIME' => '15',
            'POWERDOWNFLAG' => '/etc/killpower',
            'FINALDELAY' => '5',
        ),
        'EVENT_LOG_SETTINGS' => array(
            'ENABLED' => '0',
            'LOG_FILE' => '/var/clearos/nut/events.log',
            'RETENTION_DAYS' => '30',
            'MAX_SIZE_MB' => '5',
            'SYSLOG_ENABLED' => '1',
            'EVENTS' => array('ONLINE', 'ONBATT', 'LOWBATT', 'FSD', 'SHUTDOWN', 'COMMOK', 'COMMBAD', 'NOCOMM', 'REPLBATT'),
        ),
        'UPSD_USERS' => array(array('USERNAME' => 'upsmon-local', 'PASSWORD' => '', 'ROLE' => 'upsmon_primary')),
    );
if (! isset($mode_options))
    $mode_options = array('standalone' => 'standalone');
if (! isset($listen_address_options))
    $listen_address_options = array('127.0.0.1' => '127.0.0.1 — localhost');
if (! isset($debug_min_options))
    $debug_min_options = array('0' => lang('base_disabled'));
if (! isset($upsd_user_role_options))
    $upsd_user_role_options = array('upsmon_primary' => 'upsmon primary');
if (! isset($event_log_event_options))
    $event_log_event_options = array(
        'ONLINE' => 'ONLINE',
        'ONBATT' => 'ONBATT',
        'LOWBATT' => 'LOWBATT',
        'FSD' => 'FSD',
        'SHUTDOWN' => 'SHUTDOWN',
        'COMMOK' => 'COMMOK',
        'COMMBAD' => 'COMMBAD',
        'NOCOMM' => 'NOCOMM',
        'REPLBATT' => 'REPLBATT',
    );
if (! isset($form_type))
    $form_type = 'view';

$current_mode = isset($settings['MODE']) ? $settings['MODE'] : 'standalone';
$current_mode_display = isset($mode_options[$current_mode]) ? $mode_options[$current_mode] : $current_mode;
$current_listeners = isset($settings['UPSD_LISTENERS']) && is_array($settings['UPSD_LISTENERS']) ? $settings['UPSD_LISTENERS'] : array();
if (count($current_listeners) === 0)
    $current_listeners[] = array('ADDRESS' => '127.0.0.1', 'PORT' => '3493');

$current_users = isset($settings['UPSD_USERS']) && is_array($settings['UPSD_USERS']) ? $settings['UPSD_USERS'] : array();
if (count($current_users) === 0)
    $current_users[] = array('USERNAME' => isset($settings['UPSMON_USER']) ? $settings['UPSMON_USER'] : 'upsmon-local', 'PASSWORD' => isset($settings['UPSMON_PASSWORD']) ? $settings['UPSMON_PASSWORD'] : '', 'ROLE' => 'upsmon_primary');

$allow_no_device = (isset($settings['ALLOW_NO_DEVICE']) && $settings['ALLOW_NO_DEVICE'] === '1') ? '1' : '0';
$allow_not_all_listeners = (isset($settings['ALLOW_NOT_ALL_LISTENERS']) && $settings['ALLOW_NOT_ALL_LISTENERS'] === '1') ? '1' : '0';
$debug_min = isset($settings['DEBUG_MIN']) ? $settings['DEBUG_MIN'] : '0';
if (! isset($debug_min_options[$debug_min]))
    $debug_min = '0';

$upsmon_settings = isset($settings['UPSMON_SETTINGS']) && is_array($settings['UPSMON_SETTINGS']) ? $settings['UPSMON_SETTINGS'] : array();
$upsmon_defaults = array(
    'MINSUPPLIES' => '1',
    'SHUTDOWNCMD' => '/sbin/shutdown -h +0',
    'POLLFREQ' => '5',
    'POLLFREQALERT' => '5',
    'HOSTSYNC' => '15',
    'DEADTIME' => '15',
    'POWERDOWNFLAG' => '/etc/killpower',
    'FINALDELAY' => '5',
);
foreach ($upsmon_defaults as $key => $value) {
    if (! isset($upsmon_settings[$key]) || $upsmon_settings[$key] === '')
        $upsmon_settings[$key] = $value;
}

$event_log_settings = isset($settings['EVENT_LOG_SETTINGS']) && is_array($settings['EVENT_LOG_SETTINGS']) ? $settings['EVENT_LOG_SETTINGS'] : array();
$event_log_defaults = array(
    'ENABLED' => '0',
    'LOG_FILE' => '/var/clearos/nut/events.log',
    'RETENTION_DAYS' => '30',
    'MAX_SIZE_MB' => '5',
    'SYSLOG_ENABLED' => '1',
    'EVENTS' => array('ONLINE', 'ONBATT', 'LOWBATT', 'FSD', 'SHUTDOWN', 'COMMOK', 'COMMBAD', 'NOCOMM', 'REPLBATT'),
);
foreach ($event_log_defaults as $key => $value) {
    if (! isset($event_log_settings[$key]) || $event_log_settings[$key] === '')
        $event_log_settings[$key] = $value;
}
if (! is_array($event_log_settings['EVENTS']))
    $event_log_settings['EVENTS'] = preg_split('/\s*,\s*/', $event_log_settings['EVENTS']);

function nut_mode_escape($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function nut_format_listeners_for_view($listeners)
{
    $lines = array();

    foreach ($listeners as $listener) {
        $address = isset($listener['ADDRESS']) ? $listener['ADDRESS'] : '';
        $port = isset($listener['PORT']) ? $listener['PORT'] : '3493';
        if ($address === '')
            continue;
        $lines[] = nut_mode_escape($address . ':' . $port);
    }

    if (count($lines) === 0)
        return '-';

    return implode('<br>', $lines);
}

function nut_format_users_for_view($users, $role_options)
{
    $lines = array();

    foreach ($users as $user) {
        $username = isset($user['USERNAME']) ? $user['USERNAME'] : '';
        $role = isset($user['ROLE']) ? $user['ROLE'] : 'readonly';
        if ($username === '')
            continue;
        $role_label = isset($role_options[$role]) ? $role_options[$role] : $role;
        $lines[] = nut_mode_escape($username) . ' — ' . nut_mode_escape($role_label) . ' / ••••••••';
    }

    if (count($lines) === 0)
        return '-';

    return implode('<br>', $lines);
}


function nut_format_upsmon_settings_for_view($settings)
{
    $lines = array();
    $lines[] = lang('nut_upsmon_minsupplies') . ': ' . nut_mode_escape($settings['MINSUPPLIES']);
    $lines[] = lang('nut_upsmon_pollfreq') . ': ' . nut_mode_escape($settings['POLLFREQ']) . ' ' . lang('nut_upsmon_seconds_suffix');
    $lines[] = lang('nut_upsmon_pollfreqalert') . ': ' . nut_mode_escape($settings['POLLFREQALERT']) . ' ' . lang('nut_upsmon_seconds_suffix');
    $lines[] = lang('nut_upsmon_deadtime') . ': ' . nut_mode_escape($settings['DEADTIME']) . ' ' . lang('nut_upsmon_seconds_suffix');
    $lines[] = lang('nut_upsmon_finaldelay') . ': ' . nut_mode_escape($settings['FINALDELAY']) . ' ' . lang('nut_upsmon_seconds_suffix');

    return implode('<br>', $lines);
}

function nut_format_event_log_settings_for_view($settings, $event_options)
{
    if (! is_array($settings))
        return '-';

    $enabled = (isset($settings['ENABLED']) && $settings['ENABLED'] === '1') ? lang('base_enabled') : lang('base_disabled');
    $events = isset($settings['EVENTS']) && is_array($settings['EVENTS']) ? $settings['EVENTS'] : array();
    $event_labels = array();

    foreach ($events as $event) {
        if (isset($event_options[$event]))
            $event_labels[] = $event_options[$event];
        else
            $event_labels[] = $event;
    }

    $lines = array();
    $lines[] = lang('base_status') . ': ' . $enabled;
    $lines[] = lang('nut_event_log_file') . ': ' . nut_mode_escape(isset($settings['LOG_FILE']) ? $settings['LOG_FILE'] : '/var/clearos/nut/events.log');
    $lines[] = lang('nut_event_log_retention_days') . ': ' . nut_mode_escape(isset($settings['RETENTION_DAYS']) ? $settings['RETENTION_DAYS'] : '30') . ' ' . lang('nut_days');
    $lines[] = lang('nut_event_log_max_size_mb') . ': ' . nut_mode_escape(isset($settings['MAX_SIZE_MB']) ? $settings['MAX_SIZE_MB'] : '5') . ' MB';
    $lines[] = lang('nut_event_log_syslog') . ': ' . ((isset($settings['SYSLOG_ENABLED']) && $settings['SYSLOG_ENABLED'] === '1') ? lang('base_enabled') : lang('base_disabled'));
    $lines[] = lang('nut_event_log_events') . ': ' . nut_mode_escape(count($event_labels) ? implode(', ', $event_labels) : '-');

    return implode('<br>', $lines);
}

function nut_render_listen_address_select($name, $selected, $options)
{
    if (! isset($options[$selected]) && $selected !== '')
        $options[$selected] = $selected;

    $html = '<select name="' . nut_mode_escape($name) . '" class="form-control nut-listen-address">';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . nut_mode_escape($value) . '"';
        if ($value === $selected)
            $html .= ' selected="selected"';
        $html .= '>' . nut_mode_escape($label) . '</option>';
    }
    $html .= '</select>';

    return $html;
}

function nut_render_role_select($name, $selected, $options)
{
    if (! isset($options[$selected]) && $selected !== '')
        $selected = 'readonly';

    $html = '<select name="' . nut_mode_escape($name) . '" class="form-control nut-upsd-user-role">';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . nut_mode_escape($value) . '"';
        if ($value === $selected)
            $html .= ' selected="selected"';
        $html .= '>' . nut_mode_escape($label) . '</option>';
    }
    $html .= '</select>';

    return $html;
}

if ($form_type === 'edit') {
    $read_only = FALSE;
    $buttons = array(
        form_submit_update('submit'),
        anchor_cancel('/app/nut')
    );
} else {
    $read_only = TRUE;
    $buttons = array(
        anchor_edit('/app/nut/settings/mode/edit')
    );
}

echo form_open('nut/settings/mode/edit');
echo form_header(lang('base_settings'));

if ($read_only) {
    echo field_view(lang('nut_mode'), nut_mode_escape($current_mode_display));
    echo field_view(lang('nut_allow_no_device'), ($allow_no_device === '1') ? lang('base_enabled') : lang('base_disabled'));
    echo field_view(lang('nut_allow_not_all_listeners'), ($allow_not_all_listeners === '1') ? lang('base_enabled') : lang('base_disabled'));
    echo field_view(lang('nut_debug_min'), nut_mode_escape($debug_min_options[$debug_min]));
    echo field_view(lang('nut_upsmon_settings'), nut_format_upsmon_settings_for_view($upsmon_settings));
    echo field_view(lang('nut_event_log_settings'), nut_format_event_log_settings_for_view($event_log_settings, $event_log_event_options));
    echo field_view(lang('nut_upsd_users'), nut_format_users_for_view($current_users, $upsd_user_role_options));
    echo field_view(lang('nut_upsd_listeners'), nut_format_listeners_for_view($current_listeners));
} else {
    echo field_dropdown('MODE', $mode_options, $current_mode, lang('nut_mode'), FALSE);
    echo field_toggle_enable_disable('ALLOW_NO_DEVICE', ($allow_no_device === '1'), lang('nut_allow_no_device'));
    echo field_toggle_enable_disable('ALLOW_NOT_ALL_LISTENERS', ($allow_not_all_listeners === '1'), lang('nut_allow_not_all_listeners'));
    echo field_dropdown('DEBUG_MIN', $debug_min_options, $debug_min, lang('nut_debug_min'), FALSE);

    echo infobox_highlight(
        lang('base_information'),
        lang('nut_upsmon_settings_help')
    );

    echo fieldset_header(lang('nut_upsmon_settings'));
    echo field_input('UPSMON_MINSUPPLIES', $upsmon_settings['MINSUPPLIES'], lang('nut_upsmon_minsupplies'), FALSE);
    echo field_input('UPSMON_SHUTDOWNCMD', $upsmon_settings['SHUTDOWNCMD'], lang('nut_upsmon_shutdowncmd'), FALSE);
    echo field_input('UPSMON_POLLFREQ', $upsmon_settings['POLLFREQ'], lang('nut_upsmon_pollfreq') . ' (' . lang('nut_upsmon_seconds_suffix') . ')', FALSE);
    echo field_input('UPSMON_POLLFREQALERT', $upsmon_settings['POLLFREQALERT'], lang('nut_upsmon_pollfreqalert') . ' (' . lang('nut_upsmon_seconds_suffix') . ')', FALSE);
    echo field_input('UPSMON_HOSTSYNC', $upsmon_settings['HOSTSYNC'], lang('nut_upsmon_hostsync') . ' (' . lang('nut_upsmon_seconds_suffix') . ')', FALSE);
    echo field_input('UPSMON_DEADTIME', $upsmon_settings['DEADTIME'], lang('nut_upsmon_deadtime') . ' (' . lang('nut_upsmon_seconds_suffix') . ')', FALSE);
    echo field_input('UPSMON_POWERDOWNFLAG', $upsmon_settings['POWERDOWNFLAG'], lang('nut_upsmon_powerdownflag'), FALSE);
    echo field_input('UPSMON_FINALDELAY', $upsmon_settings['FINALDELAY'], lang('nut_upsmon_finaldelay') . ' (' . lang('nut_upsmon_seconds_suffix') . ')', FALSE);
    echo fieldset_footer();

    echo infobox_highlight(
        lang('base_information'),
        lang('nut_event_log_settings_help')
    );

    echo fieldset_header(lang('nut_event_log_settings'));
    echo field_toggle_enable_disable('EVENT_LOG_ENABLED', (isset($event_log_settings['ENABLED']) && $event_log_settings['ENABLED'] === '1'), lang('nut_event_log_enable'));
    echo field_input('EVENT_LOG_FILE', isset($event_log_settings['LOG_FILE']) ? $event_log_settings['LOG_FILE'] : '/var/clearos/nut/events.log', lang('nut_event_log_file'), FALSE);
    echo field_input('EVENT_LOG_RETENTION_DAYS', isset($event_log_settings['RETENTION_DAYS']) ? $event_log_settings['RETENTION_DAYS'] : '30', lang('nut_event_log_retention_days'), FALSE);
    echo field_input('EVENT_LOG_MAX_SIZE_MB', isset($event_log_settings['MAX_SIZE_MB']) ? $event_log_settings['MAX_SIZE_MB'] : '5', lang('nut_event_log_max_size_mb'), FALSE);
    echo field_toggle_enable_disable('EVENT_LOG_SYSLOG_ENABLED', (isset($event_log_settings['SYSLOG_ENABLED']) && $event_log_settings['SYSLOG_ENABLED'] === '1'), lang('nut_event_log_syslog'));

    echo '<div class="theme-field theme-field-text">';
    echo '<label>' . nut_mode_escape(lang('nut_event_log_events')) . '</label>';
    echo '<div class="theme-field-input">';
    echo '<table class="table" style="margin-bottom: 0;">';
    foreach ($event_log_event_options as $event => $label) {
        $checked = in_array($event, $event_log_settings['EVENTS']) ? ' checked="checked"' : '';
        echo '<tr>';
        echo '<td style="width: 30px;"><input type="checkbox" name="EVENT_LOG_EVENTS[]" value="' . nut_mode_escape($event) . '"' . $checked . ' /></td>';
        echo '<td><strong>' . nut_mode_escape($event) . '</strong> — ' . nut_mode_escape($label) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div></div>';
    echo fieldset_footer();

    echo infobox_highlight(
        lang('base_information'),
        lang('nut_upsd_users_help')
    );

    echo fieldset_header(lang('nut_upsd_users'));
    echo '<div class="theme-field theme-field-text">';
    echo '<label>&nbsp;</label>';
    echo '<div class="theme-field-input">';
    echo '<table id="nut-upsd-users-table" class="table" style="margin-bottom: 8px;">';
    echo '<thead><tr>';
    echo '<th>' . nut_mode_escape(lang('nut_upsd_user')) . '</th>';
    echo '<th>' . nut_mode_escape(lang('nut_upsd_password')) . '</th>';
    echo '<th style="width: 230px;">' . nut_mode_escape(lang('nut_upsd_permissions')) . '</th>';
    echo '<th style="width: 90px;">' . nut_mode_escape(lang('base_delete')) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($current_users as $user) {
        $username = isset($user['USERNAME']) ? $user['USERNAME'] : '';
        $password = isset($user['PASSWORD']) ? $user['PASSWORD'] : '';
        $role = isset($user['ROLE']) ? $user['ROLE'] : 'readonly';
        echo '<tr>';
        echo '<td><input type="text" name="UPSD_USER_USERNAME[]" value="' . nut_mode_escape($username) . '" class="form-control" /></td>';
        echo '<td><input type="password" name="UPSD_USER_PASSWORD[]" value="' . nut_mode_escape($password) . '" class="form-control" /></td>';
        echo '<td>' . nut_render_role_select('UPSD_USER_ROLE[]', $role, $upsd_user_role_options) . '</td>';
        echo '<td><button type="button" class="btn btn-default btn-sm nut-remove-upsd-user">' . nut_mode_escape(lang('base_delete')) . '</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="button" id="nut-add-upsd-user" class="btn btn-primary">' . nut_mode_escape(lang('nut_add_upsd_user')) . '</button>';
    echo '</div></div>';
    echo fieldset_footer();

    echo infobox_highlight(
        lang('base_information'),
        lang('nut_upsd_listeners_help')
    );

    echo fieldset_header(lang('nut_upsd_listeners'));
    echo '<div class="theme-field theme-field-text">';
    echo '<label>&nbsp;</label>';
    echo '<div class="theme-field-input">';
    echo '<table id="nut-listeners-table" class="table" style="margin-bottom: 8px;">';
    echo '<thead><tr>';
    echo '<th>' . nut_mode_escape(lang('nut_upsd_listen_address')) . '</th>';
    echo '<th style="width: 140px;">' . nut_mode_escape(lang('nut_upsd_listen_port')) . '</th>';
    echo '<th style="width: 90px;">' . nut_mode_escape(lang('base_delete')) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($current_listeners as $listener) {
        $address = isset($listener['ADDRESS']) ? $listener['ADDRESS'] : '';
        $port = isset($listener['PORT']) ? $listener['PORT'] : '3493';
        echo '<tr>';
        echo '<td>' . nut_render_listen_address_select('UPSD_LISTEN_ADDRESS[]', $address, $listen_address_options) . '</td>';
        echo '<td><input type="text" name="UPSD_LISTEN_PORT[]" value="' . nut_mode_escape($port) . '" class="form-control" /></td>';
        echo '<td><button type="button" class="btn btn-default btn-sm nut-remove-listener">' . nut_mode_escape(lang('base_delete')) . '</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="button" id="nut-add-listener" class="btn btn-primary">' . nut_mode_escape(lang('nut_add_listener')) . '</button>';
    echo '</div></div>';
    echo fieldset_footer();

}

echo field_button_set($buttons);

echo form_footer();
echo form_close();
