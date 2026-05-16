<?php

/**
 * NUT main controller.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class NUT extends ClearOS_Controller
{
    const STATUS_REFRESH_INTERVAL = 15;

    /**
     * Main summary.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $data['settings'] = $this->nut->get_settings();
            $data['mode_options'] = $this->nut->get_mode_options();
            $data['upsd_user_role_options'] = $this->nut->get_upsd_user_role_options();
            $data['devices'] = $this->nut->get_configured_devices();
            $data['statuses'] = $this->nut->get_device_statuses();
            $data['warnings'] = $this->nut->get_warnings();
            $data['refresh_interval'] = self::STATUS_REFRESH_INTERVAL;
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $options['javascript'] = array(
            clearos_app_htdocs('base') . '/daemon.js.php',
            clearos_app_htdocs('nut') . '/nut.js.php'
        );

        $this->page->view_form('nut/summary', $data, lang('nut_app_name'), $options);
    }

    /**
     * Runtime status for dynamic summary refresh.
     *
     * This endpoint is read-only.  It only calls upsc through the NUT library
     * and does not apply configuration, change permissions or restart services.
     *
     * @return JSON
     */

    function status()
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $devices = $this->nut->get_configured_devices();
            $statuses = $this->nut->get_device_statuses();

            $payload = array();

            foreach ($devices as $device) {
                $ups_name = $device['UPS_NAME'];
                $status = isset($statuses[$ups_name]) ? $statuses[$ups_name] : NULL;

                $payload[$ups_name] = array(
                    'available' => ($status && ! empty($status['available'])) ? TRUE : FALSE,
                    'summary' => $this->_format_summary_status($status),
                    'message' => $this->_format_status_message($status),
                    'fields' => $this->_format_status_fields($status),
                );
            }

            echo json_encode(array(
                'code' => 0,
                'interval' => self::STATUS_REFRESH_INTERVAL,
                'refreshed_at_epoch' => time(),
                'statuses' => $payload,
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'code' => clearos_exception_code($e),
                'errmsg' => clearos_exception_message($e),
            ));
        }
    }

    /**
     * Add button fallback.
     *
     * Some Webconfig themes submit toolbar buttons back to the current app
     * summary.  This method gives /app/nut/add a simple and safe target.
     *
     * @return redirect
     */

    function add()
    {
        redirect('/nut/devices/index');
    }

    /**
     * Returns status fields shown in the summary view.
     *
     * @return array field map
     */

    protected function _get_status_map()
    {
        return array(
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
    }

    /**
     * Formats compact UPS status for the configured UPS list.
     *
     * @param array $status status data
     *
     * @return string formatted status
     */

    protected function _get_status_emoji($status_text)
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


    protected function _get_status_description($status_text)
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

    protected function _format_status_with_description($status_text)
    {
        $formatted = $this->_get_status_emoji($status_text) . ' ' . $status_text;
        $description = $this->_get_status_description($status_text);

        if ($description !== '')
            $formatted .= ' — ' . $description;

        return $formatted;
    }

    protected function _format_summary_status($status)
    {
        if (! $status || empty($status['available']))
            return '⚠️ ' . $this->_format_status_message($status);

        $details = $status['details'];
        $status_code = isset($details['ups.status']) ? $details['ups.status'] : lang('base_ok');
        $status_text = $this->_get_status_emoji($status_code) . ' ' . $status_code;

        if (isset($details['battery.charge']))
            $status_text .= ' / ' . $details['battery.charge'] . '%';

        return $status_text;
    }

    /**
     * Formats status message.
     *
     * @param array $status status data
     *
     * @return string formatted message
     */

    protected function _format_status_message($status)
    {
        if ($status && ! empty($status['available']))
            return isset($status['message']) ? $status['message'] : lang('base_ok');

        if ($status && isset($status['message']))
            return $status['message'];

        return lang('nut_no_status');
    }

    /**
     * Formats detailed runtime fields for JSON/UI refresh.
     *
     * @param array $status status data
     *
     * @return array formatted fields
     */

    protected function _format_status_fields($status)
    {
        $fields = array();
        $details = ($status && ! empty($status['available']) && isset($status['details'])) ? $status['details'] : array();

        foreach ($this->_get_status_map() as $key => $label) {
            $value = isset($details[$key]) ? $details[$key] : '-';
            $fields[$key] = $this->_format_status_value($key, $value);
        }

        return $fields;
    }

    /**
     * Formats one UPS runtime value.
     *
     * @param string $key   upsc key
     * @param string $value upsc value
     *
     * @return string formatted value
     */

    protected function _format_status_value($key, $value)
    {
        if ($value === NULL || $value === '')
            $value = '-';

        if ($key === 'ups.status' && $value !== '-')
            $value = $this->_format_status_with_description($value);
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
