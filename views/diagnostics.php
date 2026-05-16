<?php

/**
 * NUT diagnostics view.
 *
 * @category   apps
 * @package    nut
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('nut');

if (! isset($diagnostics))
    $diagnostics = array();

if (! function_exists('nut_diag_escape')) {
    function nut_diag_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('nut_diag_output_block')) {
    function nut_diag_output_block($command)
    {
        $output = isset($command['output']) && is_array($command['output']) ? $command['output'] : array();
        $text = trim(implode("\n", $output));

        if ($text === '')
            $text = '-';

        echo '<div class="panel panel-default" style="margin-bottom: 12px;">';
        echo '<div class="panel-heading"><strong>' . nut_diag_escape($command['title']) . '</strong>';
        echo ' <span class="text-muted">exit=' . nut_diag_escape(isset($command['exit_code']) ? $command['exit_code'] : '-') . '</span>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="text-muted" style="margin-bottom: 6px; font-family: monospace;">' . nut_diag_escape($command['command']) . '</div>';
        echo '<pre style="white-space: pre-wrap; word-break: break-word; margin-bottom: 0;">' . nut_diag_escape($text) . '</pre>';
        echo '</div>';
        echo '</div>';
    }
}

echo infobox_highlight(
    lang('nut_diagnostics'),
    lang('nut_diagnostics_help')
);

foreach ($diagnostics as $section) {
    $title = isset($section['title']) ? $section['title'] : '';
    $type = isset($section['type']) ? $section['type'] : 'table';

    if ($type === 'table') {
        echo summary_table(
            $title,
            array(),
            isset($section['headers']) ? $section['headers'] : array(),
            isset($section['items']) ? $section['items'] : array(),
            array('no_action' => TRUE)
        );
    } else if ($type === 'commands') {
        echo form_header($title);

        if (isset($section['commands']) && is_array($section['commands'])) {
            foreach ($section['commands'] as $command)
                nut_diag_output_block($command);
        }

        echo form_footer();
    }
}

echo field_button_set(array(anchor_custom('/app/nut', lang('nut_return_to_summary'))));
