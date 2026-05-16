<?php

/**
 * NUT event log view.
 *
 * @category   apps
 * @package    nut
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('nut');

if (! isset($entries))
    $entries = array();

if (! function_exists('nut_events_escape')) {
    function nut_events_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('nut_events_format_level')) {
    function nut_events_format_level($level)
    {
        $level = strtolower((string) $level);

        switch ($level) {
            case 'critical':
                return '🚨 ' . lang('nut_event_level_critical');
            case 'warning':
                return '⚠️ ' . lang('nut_event_level_warning');
            case 'info':
            default:
                return 'ℹ️ ' . lang('nut_event_level_info');
        }
    }
}

$headers = array(
    lang('base_date'),
    lang('nut_ups_name'),
    lang('nut_event'),
    lang('nut_event_level'),
    lang('nut_message'),
    lang('nut_runtime_data'),
);

$items = array();

foreach ($entries as $entry) {
    $runtime = isset($entry['runtime_display']) ? $entry['runtime_display'] : '';
    $runtime = $runtime === '' ? '-' : nl2br(nut_events_escape($runtime));

    $items[] = array(
        'details' => array(
            nut_events_escape(isset($entry['time']) ? $entry['time'] : ''),
            nut_events_escape(isset($entry['ups']) ? $entry['ups'] : ''),
            nut_events_escape(isset($entry['event']) ? $entry['event'] : ''),
            nut_events_escape(nut_events_format_level(isset($entry['level']) ? $entry['level'] : 'info')),
            nut_events_escape(isset($entry['message_display']) ? $entry['message_display'] : ''),
            $runtime,
        ),
    );
}

if (count($items) === 0) {
    echo infobox_warning(
        lang('nut_event_log'),
        lang('nut_event_log_empty')
    );
}

echo summary_table(
    lang('nut_event_log'),
    array(anchor_custom('/app/nut', lang('nut_return_to_summary'))),
    $headers,
    $items,
    array('no_action' => TRUE)
);
