<?php

/**
 * Javascript helper for app-nut.
 *
 * @category   apps
 * @package    nut
 * @subpackage javascript
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

clearos_load_language('nut');
clearos_load_language('base');

header('Content-Type: application/x-javascript');

?>
var nut_lang_updating = '<?php echo lang('nut_updating') ?>';
var nut_lang_update_failed = '<?php echo lang('nut_update_failed') ?>';
var nut_refresh_timer = null;
var nut_refresh_interval = 15000;

$(document).ready(function() {
    var status_bar = $('#nut-live-status-bar');

    if (status_bar.length == 0)
        return;

    var configured_interval = parseInt(status_bar.attr('data-refresh-interval'), 10);

    if (! isNaN(configured_interval) && configured_interval > 0)
        nut_refresh_interval = configured_interval * 1000;

    nut_update_last_refresh_time();
    nut_schedule_refresh();
});

function nut_format_two_digits(value) {
    value = parseInt(value, 10);

    if (isNaN(value))
        value = 0;

    return (value < 10 ? '0' : '') + value;
}

function nut_get_browser_time() {
    var now = new Date();

    return nut_format_two_digits(now.getHours()) + ':' +
        nut_format_two_digits(now.getMinutes()) + ':' +
        nut_format_two_digits(now.getSeconds());
}

function nut_update_last_refresh_time() {
    $('#nut-last-refresh').text(nut_get_browser_time());
}

function nut_schedule_refresh() {
    if (nut_refresh_timer != null)
        window.clearTimeout(nut_refresh_timer);

    nut_refresh_timer = window.setTimeout(function() {
        nut_refresh_status();
    }, nut_refresh_interval);
}

function nut_refresh_status() {
    $('#nut-live-refresh-state').text(nut_lang_updating);

    $.ajax({
        type: 'GET',
        dataType: 'json',
        cache: false,
        url: '/app/nut/status',
        data: '',
        success: function(data) {
            if (data.code != 0) {
                var message = data.errmsg ? data.errmsg : nut_lang_update_failed;
                $('#nut-live-refresh-state').text(' - ' + message);
                nut_schedule_refresh();
                return;
            }

            $.each(data.statuses, function(ups_name, status) {
                nut_update_summary_status(ups_name, status.summary);
                nut_update_message(ups_name, status.message);

                $.each(status.fields, function(key, value) {
                    nut_update_field(ups_name, key, value);
                });
            });

            // Use the browser's local time here.  The ClearOS/PHP process can
            // run in UTC or another timezone, while the UI must show the user's
            // current local time.
            nut_update_last_refresh_time();

            $('#nut-live-refresh-state').text('');
            nut_schedule_refresh();
        },
        error: function(xhr, text, err) {
            $('#nut-live-refresh-state').text(' - ' + nut_lang_update_failed);
            nut_schedule_refresh();
        }
    });
}

function nut_update_summary_status(ups_name, value) {
    $('.nut-live-summary-status').each(function() {
        if ($(this).attr('data-nut-ups') == ups_name)
            $(this).text(value);
    });
}

function nut_update_message(ups_name, value) {
    $('.nut-live-message').each(function() {
        if ($(this).attr('data-nut-ups') == ups_name)
            $(this).text(value);
    });
}

function nut_update_field(ups_name, key, value) {
    $('.nut-live-field').each(function() {
        if ($(this).attr('data-nut-ups') == ups_name && $(this).attr('data-nut-key') == key)
            $(this).text(value);
    });
}



// Settings form helpers for /app/nut/settings/mode/edit.
// Keep this in the external JS file because ClearOS pages can be refreshed via
// the framework and inline scripts in views are fragile.
$(document).ready(function() {
    nut_init_settings_form();
});

function nut_init_settings_form() {
    if ($('#nut-listeners-table').length == 0 && $('#nut-upsd-users-table').length == 0)
        return;

    $(document).off('click.nutSettings', '#nut-add-listener');
    $(document).on('click.nutSettings', '#nut-add-listener', function(e) {
        e.preventDefault();
        nut_add_listener_row();
    });

    $(document).off('click.nutSettings', '.nut-remove-listener');
    $(document).on('click.nutSettings', '.nut-remove-listener', function(e) {
        e.preventDefault();
        nut_remove_listener_row($(this));
    });

    $(document).off('click.nutSettings', '#nut-add-upsd-user');
    $(document).on('click.nutSettings', '#nut-add-upsd-user', function(e) {
        e.preventDefault();
        nut_add_upsd_user_row();
    });

    $(document).off('click.nutSettings', '.nut-remove-upsd-user');
    $(document).on('click.nutSettings', '.nut-remove-upsd-user', function(e) {
        e.preventDefault();
        nut_remove_upsd_user_row($(this));
    });
}

function nut_add_listener_row() {
    var table = $('#nut-listeners-table');
    var source = table.find('tbody tr:first');

    if (source.length == 0)
        return;

    var row = source.clone(false);

    row.find('select[name="UPSD_LISTEN_ADDRESS[]"]').prop('selectedIndex', 0);
    row.find('input[name="UPSD_LISTEN_PORT[]"]').val('3493');
    row.find('button').prop('disabled', false);

    table.find('tbody').append(row);
}

function nut_remove_listener_row(button) {
    var table = $('#nut-listeners-table');
    var rows = table.find('tbody tr');
    var row = button.closest('tr');

    if (rows.length > 1) {
        row.remove();
        return;
    }

    row.find('select[name="UPSD_LISTEN_ADDRESS[]"]').prop('selectedIndex', 0);
    row.find('input[name="UPSD_LISTEN_PORT[]"]').val('3493');
}

function nut_add_upsd_user_row() {
    var table = $('#nut-upsd-users-table');
    var source = table.find('tbody tr:first');

    if (source.length == 0)
        return;

    var row = source.clone(false);

    row.find('input[name="UPSD_USER_USERNAME[]"]').val('');
    row.find('input[name="UPSD_USER_PASSWORD[]"]').val('');
    row.find('select[name="UPSD_USER_ROLE[]"]').prop('selectedIndex', 0);
    row.find('button').prop('disabled', false);

    table.find('tbody').append(row);
}

function nut_remove_upsd_user_row(button) {
    var table = $('#nut-upsd-users-table');
    var rows = table.find('tbody tr');
    var row = button.closest('tr');

    if (rows.length > 1) {
        row.remove();
        return;
    }

    row.find('input[name="UPSD_USER_USERNAME[]"]').val('');
    row.find('input[name="UPSD_USER_PASSWORD[]"]').val('');
    row.find('select[name="UPSD_USER_ROLE[]"]').prop('selectedIndex', 0);
}

// vim: ts=4 syntax=javascript

