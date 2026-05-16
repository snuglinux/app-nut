<?php

/**
 * Network UPS Tools (NUT) ClearOS integration.
 *
 * Minimal local USB UPS version with configurable upsd LISTEN address/port,
 * no firewall changes, no Zabbix integration.
 *
 * @category   apps
 * @package    nut
 * @subpackage libraries
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 */

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\nut;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('nut');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\base\Daemon as Daemon;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Daemon');
clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File');
clearos_load_library('base/Shell');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

class NUT extends Daemon
{
    ///////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////

    const FILE_APP_CONFIG = '/etc/clearos/nut.conf';
    const DIR_NUT_CONFIG = '/etc/ups';
    const DIR_BACKUP = '/var/clearos/nut/backup';

    const FILE_NUT_CONF = '/etc/ups/nut.conf';
    const FILE_UPS_CONF = '/etc/ups/ups.conf';
    const FILE_UPSD_CONF = '/etc/ups/upsd.conf';
    const FILE_UPSD_USERS = '/etc/ups/upsd.users';
    const FILE_UPSMON_CONF = '/etc/ups/upsmon.conf';
    const FILE_EVENT_LOG = '/var/clearos/nut/events.log';
    const COMMAND_NOTIFY = '/usr/sbin/app-nut-notify';

    const COMMAND_LSUSB = '/usr/bin/lsusb';
    const COMMAND_UPSC = '/bin/upsc';
    const COMMAND_UPSC_ALT = '/usr/bin/upsc';

    ///////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////

    protected $is_loaded = FALSE;
    protected $settings = array();

    ///////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        parent::__construct('nut-server');
    }

    /**
     * Returns app settings.
     *
     * @return array settings
     * @throws Engine_Exception
     */

    public function get_settings()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $first = $this->_get_first_device($settings['DEVICES']);

        // Compatibility fields for older views/controllers and easy display.
        $settings['UPS_NAME'] = $first ? $first['UPS_NAME'] : $this->get_default_ups_name();
        $settings['DRIVER'] = $first ? $first['DRIVER'] : 'usbhid-ups';
        $settings['PORT'] = $first ? $first['PORT'] : 'auto';
        $settings['VENDORID'] = $first ? $first['VENDORID'] : '';
        $settings['PRODUCTID'] = $first ? $first['PRODUCTID'] : '';
        $settings['SERIAL'] = $first ? $first['SERIAL'] : '';
        $settings['DESC'] = $first ? $first['DESC'] : 'USB UPS';

        return $settings;
    }

    /**
     * Returns one setting.
     *
     * @param string $key setting key
     *
     * @return string value
     * @throws Engine_Exception
     */

    public function get_setting($key)
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = $this->get_settings();

        return isset($settings[$key]) ? $settings[$key] : '';
    }

    /**
     * Returns NUT MODE options for /etc/ups/nut.conf.
     *
     * @return array mode options
     */

    public function get_mode_options()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'none' => lang('nut_mode_none'),
            'standalone' => lang('nut_mode_standalone'),
            'netserver' => lang('nut_mode_netserver'),
            'netclient' => lang('nut_mode_netclient'),
        );
    }

    /**
     * Returns DEBUG_MIN options for upsd.conf.
     *
     * @return array debug level options
     */

    public function get_debug_min_options()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            '0' => lang('base_disabled'),
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
        );
    }


    /**
     * Returns upsd.users role presets managed by app-nut.
     *
     * @return array role options
     */

    public function get_upsd_user_role_options()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'upsmon_primary' => lang('nut_upsd_role_upsmon_primary'),
            'upsmon_secondary' => lang('nut_upsd_role_upsmon_secondary'),
            'admin' => lang('nut_upsd_role_admin'),
            'instcmd_all' => lang('nut_upsd_role_instcmd_all'),
            'set' => lang('nut_upsd_role_set'),
            'fsd' => lang('nut_upsd_role_fsd'),
            'readonly' => lang('nut_upsd_role_readonly'),
        );
    }


    /**
     * Returns NUT event log notification options.
     *
     * @return array event options
     */

    public function get_event_log_event_options()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'ONLINE' => lang('nut_event_online'),
            'ONBATT' => lang('nut_event_onbatt'),
            'LOWBATT' => lang('nut_event_lowbatt'),
            'FSD' => lang('nut_event_fsd'),
            'SHUTDOWN' => lang('nut_event_shutdown'),
            'COMMOK' => lang('nut_event_commok'),
            'COMMBAD' => lang('nut_event_commbad'),
            'NOCOMM' => lang('nut_event_nocomm'),
            'REPLBATT' => lang('nut_event_replbatt'),
        );
    }


    /**
     * Returns recent NUT event log entries.
     *
     * The event log stores stable event codes and runtime values only.
     * Human-readable messages are formatted here through ClearOS language files.
     *
     * @param int $limit maximum number of entries
     *
     * @return array event log entries
     */

    public function get_event_log_entries($limit = 200)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $event_log_settings = $this->_get_event_log_settings($this->settings);
        $file = $event_log_settings['LOG_FILE'];

        if (! preg_match('/^\/var\/clearos\/nut\//', $file))
            $file = self::FILE_EVENT_LOG;

        $limit = intval($limit);
        if ($limit < 1)
            $limit = 200;
        if ($limit > 1000)
            $limit = 1000;

        $contents = $this->_read_full_file($file);
        if ($contents === '')
            return array();

        $lines = preg_split('/\r?\n/', trim($contents));
        $lines = array_reverse($lines);
        $entries = array();

        foreach ($lines as $line) {
            if (trim($line) === '')
                continue;

            $entry = json_decode($line, TRUE);
            if (! is_array($entry))
                continue;

            $entry = $this->_normalize_event_log_entry($entry);
            $entry['message_display'] = $this->_format_event_log_message($entry);
            $entry['runtime_display'] = $this->_format_event_log_runtime($entry);

            $entries[] = $entry;

            if (count($entries) >= $limit)
                break;
        }

        return $entries;
    }


    /**
     * Returns local IPv4 addresses that can be used in upsd.conf LISTEN.
     *
     * This is read-only and intentionally does not use sudo.  It reads the
     * system interface list with ip(8) and always includes 127.0.0.1.
     *
     * @return array address options
     */

    public function get_upsd_listen_address_options()
    {
        clearos_profile(__METHOD__, __LINE__);

        $options = array(
            '127.0.0.1' => '127.0.0.1 — localhost',
        );

        $commands = array(
            '/sbin/ip -o -4 addr show scope global 2>/dev/null',
            '/usr/sbin/ip -o -4 addr show scope global 2>/dev/null',
            '/bin/ip -o -4 addr show scope global 2>/dev/null',
            '/usr/bin/ip -o -4 addr show scope global 2>/dev/null',
        );

        foreach ($commands as $command) {
            $output = array();
            $exit_code = 1;
            exec($command, $output, $exit_code);

            if ($exit_code !== 0 || count($output) === 0)
                continue;

            foreach ($output as $line) {
                $matches = array();
                if (! preg_match('/^\d+:\s+([^\s]+)\s+inet\s+([0-9.]+)\/(\d+)/', $line, $matches))
                    continue;

                $interface = preg_replace('/@.*$/', '', $matches[1]);
                $address = $matches[2];
                $prefix = $matches[3];

                if ($address === '127.0.0.1')
                    continue;
                if (preg_match('/^169\.254\./', $address))
                    continue;

                $options[$address] = $address . '/' . $prefix . ' — ' . $interface;
            }

            break;
        }

        return $options;
    }

    /**
     * Sets NUT MODE in app settings and /etc/ups/nut.conf.
     *
     * @param string  $mode          NUT mode
     * @param boolean $apply_runtime apply service state for the selected mode
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function set_mode($mode, $apply_runtime = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->set_general_settings(array('MODE' => $mode), $apply_runtime);
    }

    /**
     * Sets general NUT settings managed by app-nut.
     *
     * @param array   $values        settings to update
     * @param boolean $apply_runtime apply service state when configuration is active
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function set_general_settings($values, $apply_runtime = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;

        $mode = isset($values['MODE']) ? $values['MODE'] : $settings['MODE'];
        $listeners = isset($values['UPSD_LISTENERS']) ? $values['UPSD_LISTENERS'] : $this->_get_upsd_listeners($settings);
        $allow_no_device = isset($values['ALLOW_NO_DEVICE']) ? $values['ALLOW_NO_DEVICE'] : $settings['ALLOW_NO_DEVICE'];
        $allow_not_all_listeners = isset($values['ALLOW_NOT_ALL_LISTENERS']) ? $values['ALLOW_NOT_ALL_LISTENERS'] : $settings['ALLOW_NOT_ALL_LISTENERS'];
        $debug_min = isset($values['DEBUG_MIN']) ? $values['DEBUG_MIN'] : $settings['DEBUG_MIN'];
        $upsd_users = isset($values['UPSD_USERS']) ? $values['UPSD_USERS'] : $this->_get_upsd_users($settings);
        $upsmon_settings = isset($values['UPSMON_SETTINGS']) ? $values['UPSMON_SETTINGS'] : $this->_get_upsmon_settings($settings);
        $event_log_settings = isset($values['EVENT_LOG_SETTINGS']) ? $values['EVENT_LOG_SETTINGS'] : $this->_get_event_log_settings($settings);

        Validation_Exception::is_valid($this->validate_mode($mode));
        Validation_Exception::is_valid($this->validate_upsd_listeners($listeners));
        Validation_Exception::is_valid($this->validate_boolean_setting($allow_no_device));
        Validation_Exception::is_valid($this->validate_boolean_setting($allow_not_all_listeners));
        Validation_Exception::is_valid($this->validate_debug_min($debug_min));
        Validation_Exception::is_valid($this->validate_upsd_users($upsd_users));
        Validation_Exception::is_valid($this->validate_upsmon_settings($upsmon_settings));
        Validation_Exception::is_valid($this->validate_event_log_settings($event_log_settings));

        $listeners = $this->_normalize_upsd_listeners($listeners);
        $upsd_users = $this->_normalize_upsd_users($upsd_users);
        $upsmon_settings = $this->_normalize_upsmon_settings($upsmon_settings);
        $event_log_settings = $this->_normalize_event_log_settings($event_log_settings);
        $primary_upsmon_user = $this->_get_primary_upsmon_user($upsd_users);
        $allow_no_device = $this->_normalize_boolean_setting($allow_no_device);
        $allow_not_all_listeners = $this->_normalize_boolean_setting($allow_not_all_listeners);
        $debug_min = $this->_normalize_debug_min($debug_min);

        $settings['MODE'] = $mode;
        $settings['UPSD_LISTENERS'] = $listeners;
        $settings['ALLOW_NO_DEVICE'] = $allow_no_device;
        $settings['ALLOW_NOT_ALL_LISTENERS'] = $allow_not_all_listeners;
        $settings['DEBUG_MIN'] = $debug_min;
        $settings['UPSD_USERS'] = $upsd_users;
        $settings['UPSMON_SETTINGS'] = $upsmon_settings;
        $settings['EVENT_LOG_SETTINGS'] = $event_log_settings;
        $settings['UPSMON_USER'] = $primary_upsmon_user['USERNAME'];
        $settings['UPSMON_PASSWORD'] = $primary_upsmon_user['PASSWORD'];
        $primary_listener = $this->_get_primary_upsd_listener($settings);
        $settings['UPSD_LISTEN_ADDRESS'] = $primary_listener['ADDRESS'];
        $settings['UPSD_LISTEN_PORT'] = $primary_listener['PORT'];
        $this->_save_settings($settings);

        $this->_ensure_directories();
        $this->_backup_config_files();
        $this->_set_nut_mode($mode);
        $this->_write_upsd_conf($settings);
        if (count($settings['DEVICES']) > 0) {
            $this->_write_upsd_users($settings);
            $this->_write_upsmon_conf($settings, $settings['DEVICES']);
        }
        $this->_fix_nut_config_permissions();

        // Do not start a local NUT stack on a new install that has no applied
        // UPS yet. If NUT is already enabled, changing LISTEN/PORT must be
        // applied immediately so upsd/upsmon use the new endpoint.
        if ($apply_runtime && ($settings['ENABLED'] === '1' || $mode === 'none'))
            $this->_apply_services_for_mode($mode, $settings['DEVICES']);
    }

    /**
     * Returns configured UPS devices.
     *
     * @return array UPS devices
     * @throws Engine_Exception
     */

    public function get_configured_devices()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        return $this->settings['DEVICES'];
    }

    /**
     * Returns a configured UPS device.
     *
     * @param string $ups_name UPS name
     *
     * @return array device
     * @throws Engine_Exception
     */

    public function get_configured_device($ups_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_ups_name($ups_name));

        $devices = $this->get_configured_devices();
        foreach ($devices as $device) {
            if ($device['UPS_NAME'] === $ups_name)
                return $device;
        }

        throw new Engine_Exception(lang('nut_ups_device_not_found') . ': ' . $ups_name);
    }

    /**
     * Adds a USB UPS from detected system devices.
     *
     * @param string $vendorid  USB vendor ID
     * @param string $productid USB product ID
     * @param string $bus       USB bus number
     * @param string $devnum    USB device number
     *
     * @return string created UPS name
     * @throws Engine_Exception, Validation_Exception
     */

    public function add_usb_device($vendorid, $productid, $bus = '', $devnum = '')
    {
        clearos_profile(__METHOD__, __LINE__);

        $device = $this->get_usb_device_suggestion($vendorid, $productid, $bus, $devnum);

        return $this->create_device($device);
    }

    /**
     * Returns suggested UPS settings for a detected USB device without saving.
     *
     * @param string $vendorid  USB vendor ID
     * @param string $productid USB product ID
     * @param string $bus       USB bus number
     * @param string $devnum    USB device number
     *
     * @return array suggested UPS device settings
     * @throws Engine_Exception, Validation_Exception
     */

    public function get_usb_device_suggestion($vendorid, $productid, $bus = '', $devnum = '')
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_usb_id($vendorid, TRUE));
        Validation_Exception::is_valid($this->validate_usb_id($productid, TRUE));

        $vendorid = strtolower($vendorid);
        $productid = strtolower($productid);
        $selected = NULL;
        $devices = $this->get_usb_devices();

        foreach ($devices as $device) {
            if ($device['vendorid'] !== $vendorid || $device['productid'] !== $productid)
                continue;

            if ($bus !== '' && $devnum !== '') {
                if ($device['bus'] === $bus && $device['device'] === $devnum) {
                    $selected = $device;
                    break;
                }
            } else {
                $selected = $device;
                break;
            }
        }

        if ($selected === NULL) {
            $profile = $this->get_usb_profile($vendorid, $productid, '');
            $selected = array(
                'vendorid' => $vendorid,
                'productid' => $productid,
                'driver' => $profile['driver'],
                'desc' => $profile['desc'],
                'label' => '',
                'bus' => $bus,
                'device' => $devnum,
            );
        }

        if (! $this->is_loaded)
            $this->_load_settings();

        $ups_name = $this->_make_unique_ups_name($this->get_default_ups_name(), $this->settings['DEVICES']);

        return $this->_normalize_device(array(
            'UPS_NAME' => $ups_name,
            'DRIVER' => $selected['driver'],
            'PORT' => 'auto',
            'VENDORID' => $selected['vendorid'],
            'PRODUCTID' => $selected['productid'],
            'SERIAL' => '',
            'DESC' => $selected['desc'],
        ));
    }

    /**
     * Creates a UPS device from form settings and saves it to app config.
     *
     * @param array $device UPS device settings
     *
     * @return string created UPS name
     * @throws Engine_Exception, Validation_Exception
     */

    public function create_device($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $device = $this->_normalize_device($device);
        $this->_validate_device($device);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;

        foreach ($settings['DEVICES'] as $existing) {
            if ($existing['UPS_NAME'] === $device['UPS_NAME'])
                throw new Engine_Exception(lang('nut_ups_name_exists') . ': ' . $device['UPS_NAME']);
        }

        $settings['DEVICES'][] = $device;
        if (empty($settings['UPSMON_PASSWORD']))
            $settings['UPSMON_PASSWORD'] = $this->_generate_password();

        $this->_save_settings($settings);

        return $device['UPS_NAME'];
    }

    /**
     * Returns a preview of the ups.conf block for one UPS device.
     *
     * @param array $device UPS device settings
     *
     * @return string ups.conf preview
     * @throws Validation_Exception
     */

    public function get_ups_conf_preview($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $device = $this->_normalize_device($device);
        $this->_validate_device($device);

        return trim($this->_build_ups_conf_block(array($device)));
    }

    /**
     * Updates a configured UPS device.
     *
     * @param string $original_name original UPS name
     * @param array  $device        new device settings
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function update_device($original_name, $device)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_ups_name($original_name));

        $device = $this->_normalize_device($device);
        $this->_validate_device($device);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $updated = array();
        $found = FALSE;

        foreach ($settings['DEVICES'] as $existing) {
            if ($existing['UPS_NAME'] !== $original_name && $existing['UPS_NAME'] === $device['UPS_NAME'])
                throw new Engine_Exception(lang('nut_ups_name_exists') . ': ' . $device['UPS_NAME']);

            if ($existing['UPS_NAME'] === $original_name) {
                $updated[] = $device;
                $found = TRUE;
            } else {
                $updated[] = $existing;
            }
        }

        if (! $found)
            throw new Engine_Exception(lang('nut_ups_device_not_found') . ': ' . $original_name);

        if (empty($settings['UPSMON_PASSWORD']))
            $settings['UPSMON_PASSWORD'] = $this->_generate_password();

        $settings['DEVICES'] = $updated;
        $this->_save_settings($settings);
    }

    /**
     * Deletes configured UPS device from app configuration.
     *
     * @param string $ups_name UPS name
     *
     * @return integer remaining device count
     * @throws Engine_Exception, Validation_Exception
     */

    public function delete_device($ups_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_ups_name($ups_name));

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $updated = array();
        $found = FALSE;

        foreach ($settings['DEVICES'] as $device) {
            if ($device['UPS_NAME'] === $ups_name) {
                $found = TRUE;
                continue;
            }

            $updated[] = $device;
        }

        if (! $found)
            throw new Engine_Exception(lang('nut_ups_device_not_found') . ': ' . $ups_name);

        if (count($updated) === 0)
            $settings['ENABLED'] = '0';

        $settings['DEVICES'] = $updated;
        $this->_save_settings($settings);

        return count($updated);
    }

    /**
     * Legacy helper: sets first USB UPS settings.
     *
     * @param array $settings settings
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function set_usb_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        if (count($this->settings['DEVICES']) === 0) {
            $settings = $this->_normalize_device($settings);
            $this->_validate_device($settings);
            $this->settings['DEVICES'][] = $settings;
            $this->_save_settings($this->settings);
        } else {
            $first = $this->settings['DEVICES'][0]['UPS_NAME'];
            $this->update_device($first, $settings);
        }
    }

    /**
     * Applies configured USB UPS devices to NUT.
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function apply_configuration()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $devices = $settings['DEVICES'];

        if (count($devices) === 0)
            throw new Engine_Exception(lang('nut_no_configured_ups_devices'));

        foreach ($devices as $device)
            $this->_validate_device($device);

        if (empty($settings['UPSMON_PASSWORD']))
            $settings['UPSMON_PASSWORD'] = $this->_generate_password();
        if (empty($settings['UPSMON_USER']))
            $settings['UPSMON_USER'] = 'upsmon-local';

        $mode = isset($settings['MODE']) ? $settings['MODE'] : 'standalone';
        Validation_Exception::is_valid($this->validate_mode($mode));

        $settings['ENABLED'] = '1';
        $settings['MODE'] = $mode;
        $this->_save_settings($settings);

        $this->_ensure_directories();
        $this->_backup_config_files();

        $this->_set_nut_mode($mode);
        $this->_write_ups_conf($devices);
        $this->_write_upsd_conf($settings);
        $this->_write_upsd_users($settings);
        $this->_write_upsmon_conf($settings, $devices);
        $this->_fix_nut_config_permissions();

        $this->_apply_services_for_mode($mode, $devices);
    }

    /**
     * Removes app-nut managed UPS blocks after deleting all devices.
     *
     * @return void
     * @throws Engine_Exception
     */

    public function disable_configuration()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $devices = $settings['DEVICES'];

        $settings['ENABLED'] = '0';
        $settings['DEVICES'] = array();
        $this->_save_settings($settings);

        $this->_ensure_directories();
        $this->_backup_config_files();

        // Stop while the existing NUT device sections are still present in
        // ups.conf.  nut-driver@UPS_NAME.service ExecStop uses the NUT
        // enumerator and needs the section name to exist.
        $this->_disable_and_stop_services($devices);

        $this->_remove_managed_block(self::FILE_UPS_CONF, 'app-nut usb-ups', 0640);
        $this->_remove_managed_block(self::FILE_UPSD_USERS, 'app-nut upsmon-user', 0640);
        $this->_remove_managed_block(self::FILE_UPSD_USERS, 'app-nut upsd-users', 0640);
        $this->_remove_managed_block(self::FILE_UPSMON_CONF, 'app-nut local-monitor', 0640);
        // Keep the managed LISTEN block consistent with current settings.
        $this->_write_upsd_conf($settings);
        $this->_fix_nut_config_permissions();
    }

    /**
     * Returns UPS runtime status from upsc.
     *
     * @param string $ups_name optional UPS name
     *
     * @return array status details
     */

    public function get_status($ups_name = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = $this->get_settings();
        $devices = $this->get_configured_devices();

        if (count($devices) === 0) {
            return array(
                'available' => FALSE,
                'message' => lang('nut_no_configured_ups_devices'),
                'details' => array(),
            );
        }

        if ($ups_name === NULL)
            $ups_name = $devices[0]['UPS_NAME'];

        if (! isset($settings['ENABLED']) || $settings['ENABLED'] !== '1') {
            return array(
                'available' => FALSE,
                'message' => lang('nut_status_not_configured'),
                'details' => array(),
            );
        }

        $command = $this->_get_upsc_command();

        if ($command === NULL) {
            return array(
                'available' => FALSE,
                'message' => lang('nut_upsc_not_found'),
                'details' => array(),
            );
        }

        $target = escapeshellarg($this->_get_upsc_target($ups_name, $settings));
        $output = array();
        $exit_code = 1;
        exec($command . ' ' . $target . ' 2>&1', $output, $exit_code);

        if ($exit_code !== 0) {
            $message = implode("\n", $output);
            if (preg_match('/No matching HID UPS found/i', $message))
                $message .= "\n" . lang('nut_hint_no_matching_hid');

            return array(
                'available' => FALSE,
                'message' => $message,
                'details' => array(),
            );
        }

        $details = array();
        foreach ($output as $line) {
            $matches = array();
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches))
                $details[$matches[1]] = $matches[2];
        }

        return array(
            'available' => TRUE,
            'message' => lang('base_ok'),
            'details' => $details,
        );
    }

    /**
     * Returns runtime status for all configured devices.
     *
     * @return array statuses keyed by UPS name
     */

    public function get_device_statuses()
    {
        clearos_profile(__METHOD__, __LINE__);

        $statuses = array();
        $devices = $this->get_configured_devices();

        foreach ($devices as $device)
            $statuses[$device['UPS_NAME']] = $this->get_status($device['UPS_NAME']);

        return $statuses;
    }

    /**
     * Detects USB devices and suggests NUT settings.
     *
     * @return array devices
     */

    public function get_usb_devices()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! is_executable(self::COMMAND_LSUSB))
            return array();

        $output = array();
        $exit_code = 1;
        exec(self::COMMAND_LSUSB . ' 2>/dev/null', $output, $exit_code);

        if ($exit_code !== 0)
            return array();

        $devices = array();
        $configured = $this->_get_configured_usb_index();

        foreach ($output as $line) {
            $matches = array();
            if (! preg_match('/^Bus\s+(\d+)\s+Device\s+(\d+):\s+ID\s+([0-9a-fA-F]{4}):([0-9a-fA-F]{4})\s*(.*)$/', $line, $matches))
                continue;

            $vendorid = strtolower($matches[3]);
            $productid = strtolower($matches[4]);
            $label = trim($matches[5]);

            if ($this->_is_ignored_usb_device($vendorid, $productid, $label))
                continue;

            $profile = $this->get_usb_profile($vendorid, $productid, $label);
            $usb_key = $vendorid . ':' . $productid;

            $devices[] = array(
                'bus' => $matches[1],
                'device' => $matches[2],
                'vendorid' => $vendorid,
                'productid' => $productid,
                'usb_id' => $usb_key,
                'label' => $label,
                'driver' => $profile['driver'],
                'desc' => $profile['desc'],
                'known' => $profile['known'],
                'configured' => isset($configured[$usb_key]) ? implode(', ', $configured[$usb_key]) : '',
            );
        }

        return $devices;
    }

    /**
     * Returns driver profiles.
     *
     * @return array drivers
     */

    public function get_driver_options()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'usbhid-ups' => 'usbhid-ups - generic USB HID UPS',
            'powercom' => 'powercom - Powercom/Trust/Advice protocol UPS',
            'nutdrv_qx' => 'nutdrv_qx - Q* / Megatec USB UPS',
            'blazer_usb' => 'blazer_usb - older Megatec/Q1 USB UPS',
            'richcomm_usb' => 'richcomm_usb - Richcomm USB UPS',
            'tripplite_usb' => 'tripplite_usb - older Tripp Lite USB UPS',
        );
    }

    /**
     * Returns TRUE for USB devices that should not be shown as candidate UPS.
     *
     * @param string $vendorid  USB vendor ID
     * @param string $productid USB product ID
     * @param string $label     lsusb label
     *
     * @return boolean TRUE if the USB device should be hidden
     */

    protected function _is_ignored_usb_device($vendorid, $productid, $label)
    {
        clearos_profile(__METHOD__, __LINE__);

        $vendorid = strtolower($vendorid);
        $productid = strtolower($productid);
        $label_lc = strtolower($label);

        // Hide Linux kernel USB root hubs. They are system controller hubs,
        // not physical UPS devices, and they confuse the add-device list.
        if ($vendorid === '1d6b') {
            if (in_array($productid, array('0001', '0002', '0003')))
                return TRUE;

            if (preg_match('/root hub/', $label_lc))
                return TRUE;
        }

        return FALSE;
    }

    /**
     * Returns default UPS name.
     *
     * @return string default UPS name
     */

    public function get_default_ups_name()
    {
        clearos_profile(__METHOD__, __LINE__);

        $host = trim(php_uname('n'));
        $host = preg_replace('/\..*/', '', $host);
        $host = preg_replace('/[^A-Za-z0-9_-]/', '-', $host);
        $host = trim($host, '-_');

        if (empty($host))
            $host = 'localhost';

        return 'ups-' . strtolower($host);
    }

    /**
     * Returns known USB profile.
     *
     * @param string $vendorid  USB vendor ID
     * @param string $productid USB product ID
     * @param string $label     lsusb label
     *
     * @return array profile
     */

    public function get_usb_profile($vendorid, $productid = '', $label = '')
    {
        clearos_profile(__METHOD__, __LINE__);

        $vendorid = strtolower($vendorid);
        $label_lc = strtolower($label);

        if ($vendorid === '0d9f' || preg_match('/powercom/', $label_lc)) {
            return array(
                'driver' => 'usbhid-ups',
                'desc' => 'POWERCOM USB UPS',
                'known' => TRUE,
            );
        }

        if ($vendorid === '051d' || preg_match('/american power conversion|\bapc\b/', $label_lc)) {
            return array(
                'driver' => 'usbhid-ups',
                'desc' => 'APC USB UPS',
                'known' => TRUE,
            );
        }

        if ($vendorid === '0463' || preg_match('/eaton|mge/', $label_lc)) {
            return array(
                'driver' => 'usbhid-ups',
                'desc' => 'Eaton/MGE USB UPS',
                'known' => TRUE,
            );
        }

        if ($vendorid === '0764' || preg_match('/cyberpower/', $label_lc)) {
            return array(
                'driver' => 'usbhid-ups',
                'desc' => 'CyberPower USB UPS',
                'known' => TRUE,
            );
        }

        if ($vendorid === '09ae' || preg_match('/tripp lite|tripplite/', $label_lc)) {
            return array(
                'driver' => 'usbhid-ups',
                'desc' => 'Tripp Lite USB UPS',
                'known' => TRUE,
            );
        }

        if ($vendorid === '0665') {
            return array(
                'driver' => 'nutdrv_qx',
                'desc' => 'Qx/Megatec compatible USB UPS',
                'known' => TRUE,
            );
        }

        return array(
            'driver' => 'usbhid-ups',
            'desc' => empty($label) ? 'USB UPS' : $this->_clean_description($label),
            'known' => FALSE,
        );
    }

    /**
     * Legacy helper: selects first USB device.
     *
     * @param string $vendorid  vendor ID
     * @param string $productid product ID
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function select_usb_device($vendorid, $productid)
    {
        clearos_profile(__METHOD__, __LINE__);

        $ups_name = $this->add_usb_device($vendorid, $productid);
        $this->get_configured_device($ups_name);
    }

    /**
     * Returns configuration warnings.
     *
     * @return array warnings
     */

    public function get_warnings()
    {
        clearos_profile(__METHOD__, __LINE__);

        $warnings = array();
        $settings = $this->get_settings();
        $devices = $this->get_configured_devices();

        if (count($devices) === 0)
            $warnings[] = lang('nut_warning_no_devices');
        else if ($settings['ENABLED'] !== '1')
            $warnings[] = lang('nut_warning_not_applied');

        $unmanaged_listen = $this->_get_unmanaged_listen_lines();
        if (count($unmanaged_listen) > 0)
            $warnings[] = lang('nut_warning_unmanaged_listen') . ': ' . implode(', ', $unmanaged_listen);

        return $warnings;
    }

    /**
     * Returns aggregate NUT service status for the overview page.
     *
     * @return array status information
     */

    public function get_service_status_summary()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $devices = $this->_normalize_devices($settings['DEVICES']);
        $mode = isset($settings['MODE']) ? $settings['MODE'] : 'standalone';

        if (count($devices) === 0) {
            return array(
                'status' => 'no_entries',
                'message' => lang('nut_no_configured_ups_devices'),
                'enabled' => FALSE,
            );
        }

        if ($mode === 'none') {
            return array(
                'status' => 'stopped',
                'message' => lang('base_stopped'),
                'enabled' => FALSE,
            );
        }

        $server_active = $this->_is_systemd_active('nut-server.service');
        $monitor_active = $this->_is_systemd_active('nut-monitor.service');
        $driver_active = TRUE;

        foreach ($devices as $device) {
            if (! $this->_is_systemd_active($this->_get_driver_unit($device['UPS_NAME']))) {
                $driver_active = FALSE;
                break;
            }
        }

        if ($mode === 'netclient') {
            $status = $monitor_active ? 'running' : 'stopped';
        } else if ($driver_active && $server_active && $monitor_active) {
            $status = 'running';
        } else if (! $driver_active && ! $server_active && ! $monitor_active) {
            $status = 'stopped';
        } else {
            $status = 'dead';
        }

        $enabled = $this->_is_systemd_enabled('nut-server.service') || $this->_is_systemd_enabled('nut-monitor.service');

        return array(
            'status' => $status,
            'message' => $this->_format_service_status_message($status),
            'enabled' => $enabled,
        );
    }

    /**
     * Enables and starts the configured NUT stack.
     *
     * @return void
     */

    public function start_service_stack()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->apply_configuration();
    }

    /**
     * Stops and disables the configured NUT stack.
     *
     * @return void
     */

    public function stop_service_stack()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $this->_disable_and_stop_nut_services($settings['DEVICES']);
    }

    /**
     * Returns read-only NUT diagnostics.
     *
     * This method does not change configuration, permissions, firewall rules,
     * Zabbix settings or service state.  It only reads current files, service
     * status and NUT runtime output.
     *
     * @return array diagnostics sections
     */

    public function get_diagnostics()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $settings = $this->settings;
        $devices = $this->_normalize_devices($settings['DEVICES']);
        $sections = array();

        $sections[] = array(
            'title' => lang('nut_diagnostics_overview'),
            'type' => 'table',
            'headers' => array(lang('nut_parameter'), lang('nut_value')),
            'items' => $this->_get_diagnostic_overview_items($settings, $devices),
        );

        $sections[] = array(
            'title' => lang('nut_diagnostics_services'),
            'type' => 'table',
            'headers' => array(lang('nut_service_unit'), lang('nut_status'), lang('nut_enabled'), lang('nut_result')),
            'items' => $this->_get_diagnostic_service_items($devices),
        );

        $sections[] = array(
            'title' => lang('nut_diagnostics_runtime'),
            'type' => 'commands',
            'commands' => $this->_get_diagnostic_runtime_commands($devices),
        );

        $sections[] = array(
            'title' => lang('nut_diagnostics_config_files'),
            'type' => 'table',
            'headers' => array(lang('base_file'), lang('nut_status'), lang('nut_permissions')),
            'items' => $this->_get_diagnostic_file_items(),
        );

        $sections[] = array(
            'title' => lang('nut_diagnostics_recent_logs'),
            'type' => 'commands',
            'commands' => $this->_get_diagnostic_log_commands($devices),
        );

        return $sections;
    }


    protected function _get_diagnostic_overview_items($settings, $devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $service_status = $this->get_service_status_summary();
        $listeners = $this->_get_upsd_listeners($settings);
        $listener_lines = array();

        foreach ($listeners as $listener)
            $listener_lines[] = $listener['ADDRESS'] . ':' . $listener['PORT'];

        return array(
            array('details' => array(lang('nut_mode'), isset($settings['MODE']) ? $settings['MODE'] : 'standalone')),
            array('details' => array(lang('nut_configured_ups_devices'), count($devices))),
            array('details' => array(lang('nut_status'), $service_status['message'])),
            array('details' => array(lang('nut_upsd_listeners'), count($listener_lines) ? implode("\n", $listener_lines) : '-')),
            array('details' => array(lang('nut_allow_no_device'), (isset($settings['ALLOW_NO_DEVICE']) && $settings['ALLOW_NO_DEVICE'] === '1') ? lang('base_enabled') : lang('base_disabled'))),
            array('details' => array(lang('nut_allow_not_all_listeners'), (isset($settings['ALLOW_NOT_ALL_LISTENERS']) && $settings['ALLOW_NOT_ALL_LISTENERS'] === '1') ? lang('base_enabled') : lang('base_disabled'))),
            array('details' => array(lang('nut_debug_min'), isset($settings['DEBUG_MIN']) && $settings['DEBUG_MIN'] !== '0' ? $settings['DEBUG_MIN'] : lang('base_disabled'))),
        );
    }

    protected function _get_diagnostic_service_items($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $units = array();

        foreach ($devices as $device)
            $units[] = $this->_get_driver_unit($device['UPS_NAME']);

        $units[] = 'nut-driver.target';
        $units[] = 'nut-server.service';
        $units[] = 'nut-monitor.service';

        $items = array();

        foreach ($units as $unit) {
            $show = $this->_get_systemctl_show_properties($unit);
            $items[] = array(
                'details' => array(
                    $unit,
                    isset($show['ActiveState']) ? $show['ActiveState'] . (isset($show['SubState']) && $show['SubState'] !== '' ? ' / ' . $show['SubState'] : '') : '-',
                    isset($show['UnitFileState']) ? $show['UnitFileState'] : '-',
                    isset($show['Result']) ? $show['Result'] : '-',
                ),
            );
        }

        return $items;
    }

    protected function _get_diagnostic_runtime_commands($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $commands = array();

        $commands[] = $this->_get_diagnostic_socket_status($settings);

        $upsc = $this->_get_upsc_command();

        if ($upsc === NULL) {
            $commands[] = array(
                'title' => 'upsc',
                'command' => 'upsc',
                'exit_code' => 127,
                'output' => array(lang('nut_upsc_not_found')),
            );
        } else {
            $commands[] = $this->_run_diagnostic_command('upsc -l', $upsc, '-l ' . escapeshellarg($this->_get_upsc_host($this->settings)));

            foreach ($devices as $device) {
                $commands[] = $this->_run_diagnostic_command(
                    'upsc ' . $device['UPS_NAME'],
                    $upsc,
                    escapeshellarg($this->_get_upsc_target($device['UPS_NAME'], $this->settings))
                );
            }
        }

        return $commands;
    }

    protected function _get_diagnostic_socket_status($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        $ss = $this->_get_command_path(array('/usr/sbin/ss', '/usr/bin/ss', '/bin/ss'));
        $listeners = $this->_get_upsd_listeners($settings);
        $ports = array();

        foreach ($listeners as $listener) {
            $port = isset($listener['PORT']) ? trim($listener['PORT']) : '3493';
            if ($this->validate_upsd_listen_port($port) === NULL)
                $ports[$port] = TRUE;
        }

        if (count($ports) === 0)
            $ports['3493'] = TRUE;

        $ports = array_keys($ports);

        $result = $this->_run_diagnostic_command(
            lang('nut_diagnostics_socket_status') . ' (' . implode(', ', $ports) . ')',
            $ss,
            '-lnt'
        );

        $result['command'] = trim(($ss === NULL ? 'ss' : $ss) . ' -lnt | ' . lang('nut_diagnostics_selected_ports_only'));

        if ($result['exit_code'] !== 0)
            return $result;

        $filtered = array();

        foreach ($result['output'] as $line) {
            if (preg_match('/^(State|Netid)\s+/i', $line)) {
                $filtered[] = $line;
                continue;
            }

            foreach ($ports as $port) {
                if (preg_match('/:' . preg_quote($port, '/') . '\b/', $line)) {
                    $filtered[] = $line;
                    break;
                }
            }
        }

        if (count($filtered) === 0)
            $filtered[] = lang('nut_diagnostics_no_selected_ports') . ': ' . implode(', ', $ports);

        $result['output'] = $filtered;

        return $result;
    }

    protected function _get_diagnostic_file_items()
    {
        clearos_profile(__METHOD__, __LINE__);

        $files = array(
            self::DIR_NUT_CONFIG,
            self::FILE_NUT_CONF,
            self::FILE_UPS_CONF,
            self::FILE_UPSD_CONF,
            self::FILE_UPSD_USERS,
            self::FILE_UPSMON_CONF,
            self::FILE_APP_CONFIG,
            '/run/nut',
            '/var/run/nut',
            '/etc/tmpfiles.d/nut-run.conf',
        );

        $items = array();

        foreach ($files as $file) {
            if (! file_exists($file)) {
                $items[] = array('details' => array($file, lang('base_missing'), '-'));
                continue;
            }

            $owner = function_exists('posix_getpwuid') ? @posix_getpwuid(@fileowner($file)) : NULL;
            $group = function_exists('posix_getgrgid') ? @posix_getgrgid(@filegroup($file)) : NULL;
            $owner_name = is_array($owner) && isset($owner['name']) ? $owner['name'] : @fileowner($file);
            $group_name = is_array($group) && isset($group['name']) ? $group['name'] : @filegroup($file);
            $mode = substr(sprintf('%o', @fileperms($file)), -4);

            $items[] = array(
                'details' => array(
                    $file,
                    is_dir($file) ? lang('base_directory') : lang('base_file'),
                    $owner_name . ':' . $group_name . ' ' . $mode,
                ),
            );
        }

        return $items;
    }

    protected function _get_diagnostic_config_previews()
    {
        clearos_profile(__METHOD__, __LINE__);

        $files = array(
            self::FILE_NUT_CONF,
            self::FILE_UPS_CONF,
            self::FILE_UPSD_CONF,
            self::FILE_UPSD_USERS,
            self::FILE_UPSMON_CONF,
        );

        $commands = array();

        foreach ($files as $file) {
            $contents = $this->_read_full_file($file);

            if ($contents === '') {
                $commands[] = array(
                    'title' => $file,
                    'command' => $file,
                    'exit_code' => 1,
                    'output' => array(lang('base_file_not_found')),
                );
                continue;
            }

            $commands[] = array(
                'title' => $file,
                'command' => $file,
                'exit_code' => 0,
                'output' => $this->_mask_sensitive_lines($this->_filter_diagnostic_config_lines($file, $contents)),
            );
        }

        return $commands;
    }

    protected function _get_diagnostic_log_commands($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Do not call journalctl directly from Webconfig.  On ClearOS it can
        // trigger sudo without tty/askpass unless the standard log viewer app
        // grants access.  systemctl status is read-only, already used by
        // standard ClearOS service pages, and includes the recent unit messages
        // that are useful for NUT diagnostics.
        $systemctl = $this->_get_command_path(array('/usr/bin/systemctl', '/bin/systemctl'));
        $commands = array();

        if ($systemctl === NULL) {
            $commands[] = array(
                'title' => 'systemctl',
                'command' => 'systemctl',
                'exit_code' => 127,
                'output' => array('systemctl not found'),
            );
            return $commands;
        }

        foreach ($devices as $device) {
            $unit = $this->_get_driver_unit($device['UPS_NAME']);
            $commands[] = $this->_run_diagnostic_command('systemctl status ' . $unit, $systemctl, 'status ' . escapeshellarg($unit) . ' --no-pager -l');
        }

        $commands[] = $this->_run_diagnostic_command('systemctl status nut-server.service', $systemctl, 'status nut-server.service --no-pager -l');
        $commands[] = $this->_run_diagnostic_command('systemctl status nut-monitor.service', $systemctl, 'status nut-monitor.service --no-pager -l');

        return $commands;
    }

    protected function _get_systemctl_show_properties($unit)
    {
        clearos_profile(__METHOD__, __LINE__);

        $systemctl = $this->_get_command_path(array('/usr/bin/systemctl', '/bin/systemctl'));

        if ($systemctl === NULL)
            return array();

        if (! preg_match('/^[A-Za-z0-9@_.\-]+\.service$/', $unit) && $unit !== 'nut-driver.target')
            return array();

        $args = 'show ' . escapeshellarg($unit) . ' -p ActiveState -p SubState -p Result -p UnitFileState --no-pager';
        $result = $this->_run_diagnostic_command('systemctl show ' . $unit, $systemctl, $args);
        $properties = array();

        foreach ($result['output'] as $line) {
            $matches = array();
            if (preg_match('/^([^=]+)=(.*)$/', $line, $matches))
                $properties[$matches[1]] = $matches[2];
        }

        return $properties;
    }

    protected function _run_diagnostic_command($title, $command, $args = '')
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($command === NULL || ! file_exists($command)) {
            return array(
                'title' => $title,
                'command' => trim($title),
                'exit_code' => 127,
                'output' => array(($command === NULL ? $title : $command) . ' not found'),
            );
        }

        $output = array();
        $exit_code = 1;
        exec($command . ($args !== '' ? ' ' . $args : '') . ' 2>&1', $output, $exit_code);

        return array(
            'title' => $title,
            'command' => trim($command . ' ' . $args),
            'exit_code' => $exit_code,
            'output' => $this->_mask_sensitive_lines($output),
        );
    }

    protected function _get_command_path($candidates)
    {
        clearos_profile(__METHOD__, __LINE__);

        foreach ($candidates as $candidate) {
            if (is_executable($candidate))
                return $candidate;
        }

        return NULL;
    }

    protected function _filter_diagnostic_config_lines($file, $contents)
    {
        clearos_profile(__METHOD__, __LINE__);

        $patterns = array(
            self::FILE_NUT_CONF => '/^\s*(MODE)\s*=/i',
            self::FILE_UPS_CONF => '/^\s*(\[|driver\s*=|port\s*=|vendorid\s*=|productid\s*=|serial\s*=|desc\s*=|maxstartdelay\s*=)/i',
            self::FILE_UPSD_CONF => '/^\s*(LISTEN|ALLOW_NO_DEVICE|ALLOW_NOT_ALL_LISTENERS|MAXAGE|DEBUG_MIN|STATEPATH|MAXCONN)\b/i',
            self::FILE_UPSD_USERS => '/^\s*(\[|password\s*=|upsmon\s+|actions\s*=|instcmds\s*=)/i',
            self::FILE_UPSMON_CONF => '/^\s*(MONITOR|MINSUPPLIES|SHUTDOWNCMD|NOTIFYCMD|POLLFREQ|POLLFREQALERT|HOSTSYNC|DEADTIME|POWERDOWNFLAG|FINALDELAY|NOTIFYMSG|NOTIFYFLAG)\b/i',
        );

        $pattern = isset($patterns[$file]) ? $patterns[$file] : '/.*/';
        $result = array();

        foreach (preg_split('/\r?\n/', trim($contents)) as $line) {
            if (preg_match($pattern, $line))
                $result[] = $line;
        }

        if (count($result) === 0)
            $result[] = lang('nut_diagnostics_no_relevant_lines');

        return $result;
    }

    protected function _mask_sensitive_lines($lines)
    {
        clearos_profile(__METHOD__, __LINE__);

        $masked = array();

        foreach ($lines as $line) {
            $line = preg_replace('/(\bpassword\s*=\s*).*/i', '$1********', $line);
            $line = preg_replace('/^(MONITOR\s+\S+\s+\S+\s+\S+\s+)\S+(\s+\S+)/i', '$1********$2', $line);
            $line = preg_replace('/^(UPSMON_PASSWORD=).*/i', '$1"********"', $line);
            $line = preg_replace('/^(UPSD_USER_[0-9]+_PASSWORD=).*/i', '$1"********"', $line);
            $masked[] = $line;
        }

        return $masked;
    }

    ///////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N
    ///////////////////////////////////////////////////////////////////////////

    public function validate_mode($mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! in_array($mode, array('none', 'standalone', 'netserver', 'netclient')))
            return lang('nut_mode_invalid');
    }

    public function validate_ups_name($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $name))
            return lang('nut_ups_name_invalid');
    }

    public function validate_driver($driver)
    {
        clearos_profile(__METHOD__, __LINE__);

        $drivers = $this->get_driver_options();

        if (! array_key_exists($driver, $drivers))
            return lang('nut_driver_invalid');
    }

    public function validate_port($port)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^[A-Za-z0-9_\.\/: -]{1,128}$/', $port))
            return lang('nut_port_invalid');
    }

    public function validate_upsd_listen_address($address)
    {
        clearos_profile(__METHOD__, __LINE__);

        $address = trim($address);

        // Supports IPv4 addresses, hostnames and 0.0.0.0. Keep it strict:
        // no spaces, no shell characters, no LISTEN snippets.
        if (! preg_match('/^[A-Za-z0-9_.-]{1,255}$/', $address))
            return lang('nut_upsd_listen_address_invalid');
    }

    public function validate_upsd_listen_port($port)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^[0-9]{1,5}$/', $port))
            return lang('nut_upsd_listen_port_invalid');

        $port = intval($port);
        if ($port < 1 || $port > 65535)
            return lang('nut_upsd_listen_port_invalid');
    }

    public function validate_upsd_listeners($listeners)
    {
        clearos_profile(__METHOD__, __LINE__);

        $normalized = $this->_normalize_upsd_listeners($listeners, FALSE);

        if (count($normalized) === 0)
            return lang('nut_upsd_listeners_invalid');

        $seen = array();

        foreach ($normalized as $listener) {
            $address_error = $this->validate_upsd_listen_address($listener['ADDRESS']);
            if ($address_error !== NULL)
                return $address_error;

            $port_error = $this->validate_upsd_listen_port($listener['PORT']);
            if ($port_error !== NULL)
                return $port_error;

            $key = strtolower($listener['ADDRESS']) . ':' . $listener['PORT'];
            if (isset($seen[$key]))
                return lang('nut_upsd_listeners_duplicate');

            $seen[$key] = TRUE;
        }
    }

    public function validate_boolean_setting($value)
    {
        clearos_profile(__METHOD__, __LINE__);

        $value = $this->_normalize_boolean_setting($value);

        if ($value !== '0' && $value !== '1')
            return lang('nut_boolean_invalid');
    }

    /**
     * Validates upsd DEBUG_MIN.
     *
     * @param string $value debug level
     *
     * @return string error message if invalid
     */

    public function validate_debug_min($value)
    {
        clearos_profile(__METHOD__, __LINE__);

        $value = trim((string) $value);

        if (! preg_match('/^[0-5]$/', $value))
            return lang('nut_debug_min_invalid');
    }


    /**
     * Validates managed upsmon.conf settings.
     *
     * @param array $settings upsmon settings
     *
     * @return string error message if invalid
     */

    public function validate_upsmon_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! is_array($settings))
            return lang('nut_upsmon_settings_invalid');

        $defaults = $this->_get_upsmon_default_settings();
        foreach ($defaults as $key => $default) {
            if (! isset($settings[$key]))
                $settings[$key] = $default;
            $settings[$key] = trim((string) $settings[$key]);
        }

        $integer_ranges = array(
            'MINSUPPLIES' => array(1, 99),
            'POLLFREQ' => array(1, 86400),
            'POLLFREQALERT' => array(1, 86400),
            'HOSTSYNC' => array(0, 86400),
            'DEADTIME' => array(1, 86400),
            'FINALDELAY' => array(0, 86400),
        );

        foreach ($integer_ranges as $key => $range) {
            if (! preg_match('/^[0-9]+$/', $settings[$key]))
                return lang('nut_upsmon_integer_invalid') . ': ' . $key;

            $value = intval($settings[$key]);
            if ($value < $range[0] || $value > $range[1])
                return lang('nut_upsmon_integer_invalid') . ': ' . $key;
        }

        if (! preg_match('/^[A-Za-z0-9_\/.:\-\s+"\'=]+$/', $settings['SHUTDOWNCMD']))
            return lang('nut_upsmon_command_invalid');

        if (! preg_match('/^\/[A-Za-z0-9_\/.:-]+$/', $settings['POWERDOWNFLAG']))
            return lang('nut_upsmon_path_invalid');
    }

    /**
     * Validates event log settings.
     *
     * @param array $settings event log settings
     *
     * @return string error message if invalid
     */

    public function validate_event_log_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! is_array($settings))
            return lang('nut_event_log_settings_invalid');

        $settings = $this->_normalize_event_log_settings($settings);
        $events = $settings['EVENTS'];
        $allowed = $this->get_event_log_event_options();

        if ($settings['ENABLED'] !== '0' && $settings['ENABLED'] !== '1')
            return lang('nut_boolean_invalid');
        if ($settings['SYSLOG_ENABLED'] !== '0' && $settings['SYSLOG_ENABLED'] !== '1')
            return lang('nut_boolean_invalid');

        if (! preg_match('/^\/[A-Za-z0-9_\/.:-]+$/', $settings['LOG_FILE']))
            return lang('nut_event_log_file_invalid');

        if (strpos($settings['LOG_FILE'], '/var/clearos/nut/') !== 0)
            return lang('nut_event_log_file_invalid');

        if (! preg_match('/^[0-9]+$/', $settings['RETENTION_DAYS']) || intval($settings['RETENTION_DAYS']) < 1 || intval($settings['RETENTION_DAYS']) > 3650)
            return lang('nut_event_log_retention_invalid');

        if (! preg_match('/^[0-9]+$/', $settings['MAX_SIZE_MB']) || intval($settings['MAX_SIZE_MB']) < 1 || intval($settings['MAX_SIZE_MB']) > 1024)
            return lang('nut_event_log_size_invalid');

        if (! is_array($events) || count($events) === 0)
            return lang('nut_event_log_events_invalid');

        foreach ($events as $event) {
            if (! isset($allowed[$event]))
                return lang('nut_event_log_events_invalid') . ': ' . $event;
        }
    }

    /**
     * Validates a managed upsd user name.
     *
     * @param string $user upsd user
     *
     * @return string error message if invalid
     */

    public function validate_upsd_user($user)
    {
        clearos_profile(__METHOD__, __LINE__);

        $user = trim((string) $user);

        if (! preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $user))
            return lang('nut_upsd_user_invalid');
    }

    /**
     * Validates a managed upsd user password.
     *
     * @param string $password upsd password
     *
     * @return string error message if invalid
     */

    public function validate_upsd_password($password)
    {
        clearos_profile(__METHOD__, __LINE__);

        $password = trim((string) $password);

        if (! preg_match('/^[A-Za-z0-9_.:-]{8,128}$/', $password))
            return lang('nut_upsd_password_invalid');
    }

    /**
     * Validates a managed upsd user role.
     *
     * @param string $role upsd role
     *
     * @return string error message if invalid
     */

    public function validate_upsd_user_role($role)
    {
        clearos_profile(__METHOD__, __LINE__);

        $roles = $this->get_upsd_user_role_options();

        if (! isset($roles[$role]))
            return lang('nut_upsd_role_invalid');
    }

    /**
     * Validates managed upsd.users rows.
     *
     * @param array $users managed upsd users
     *
     * @return string error message if invalid
     */

    public function validate_upsd_users($users)
    {
        clearos_profile(__METHOD__, __LINE__);

        $users = $this->_normalize_upsd_users($users, FALSE);

        if (count($users) === 0)
            return lang('nut_upsd_users_invalid');

        $seen = array();
        $has_primary = FALSE;

        foreach ($users as $user) {
            $user_error = $this->validate_upsd_user($user['USERNAME']);
            if ($user_error !== NULL)
                return $user_error;

            $password_error = $this->validate_upsd_password($user['PASSWORD']);
            if ($password_error !== NULL)
                return $password_error;

            $role_error = $this->validate_upsd_user_role($user['ROLE']);
            if ($role_error !== NULL)
                return $role_error;

            $key = strtolower($user['USERNAME']);
            if (isset($seen[$key]))
                return lang('nut_upsd_users_duplicate');

            $seen[$key] = TRUE;

            if ($user['ROLE'] === 'upsmon_primary')
                $has_primary = TRUE;
        }

        if (! $has_primary)
            return lang('nut_upsd_users_primary_required');
    }

    public function validate_usb_id($id, $required = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($id === '' && ! $required)
            return;

        if (! preg_match('/^[0-9a-fA-F]{4}$/', $id))
            return lang('nut_usb_id_invalid');
    }

    public function validate_optional_token($token)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($token === '')
            return;

        if (! preg_match('/^[A-Za-z0-9_\.:-]{1,128}$/', $token))
            return lang('nut_token_invalid');
    }

    public function validate_description($description)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (strlen($description) > 128)
            return lang('nut_description_invalid');

        if (preg_match('/[\r\n\[\]]/', $description))
            return lang('nut_description_invalid');
    }

    ///////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////

    protected function _load_settings()
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = array(
            'ENABLED' => '0',
            'MODE' => 'standalone',
            'UPSD_LISTEN_ADDRESS' => '127.0.0.1',
            'UPSD_LISTEN_PORT' => '3493',
            'UPSD_LISTENERS' => array(
                array('ADDRESS' => '127.0.0.1', 'PORT' => '3493'),
            ),
            'ALLOW_NO_DEVICE' => '0',
            'ALLOW_NOT_ALL_LISTENERS' => '0',
            'DEBUG_MIN' => '0',
            'UPSMON_SETTINGS' => $this->_get_upsmon_default_settings(),
            'EVENT_LOG_SETTINGS' => $this->_get_event_log_default_settings(),
            'UPSD_USERS' => array(
                array('USERNAME' => 'upsmon-local', 'PASSWORD' => '', 'ROLE' => 'upsmon_primary'),
            ),
            'UPSMON_USER' => 'upsmon-local',
            'UPSMON_PASSWORD' => '',
            'DEVICES' => array(),
        );

        if (! file_exists(self::FILE_APP_CONFIG)) {
            $this->settings = $settings;
            $this->_save_settings($this->settings);
            $this->is_loaded = TRUE;
            return;
        }

        try {
            $file = new File(self::FILE_APP_CONFIG, TRUE);
            $lines = $file->get_contents_as_array();
        } catch (\Exception $e) {
            throw new Engine_Exception(lang('base_file_parse_error') . ': ' . self::FILE_APP_CONFIG);
        }

        $raw = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^#/', $line))
                continue;

            $matches = array();
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                $value = preg_replace('/^"(.*)"$/', '$1', $value);
                $value = str_replace('\\"', '"', $value);
                $raw[$key] = $value;
            }
        }

        foreach (array('ENABLED', 'MODE', 'UPSD_LISTEN_ADDRESS', 'UPSD_LISTEN_PORT', 'ALLOW_NO_DEVICE', 'ALLOW_NOT_ALL_LISTENERS', 'DEBUG_MIN', 'UPSMON_USER', 'UPSMON_PASSWORD', 'UPSMON_MINSUPPLIES', 'UPSMON_SHUTDOWNCMD', 'UPSMON_POLLFREQ', 'UPSMON_POLLFREQALERT', 'UPSMON_HOSTSYNC', 'UPSMON_DEADTIME', 'UPSMON_POWERDOWNFLAG', 'UPSMON_FINALDELAY', 'EVENT_LOG_ENABLED', 'EVENT_LOG_FILE', 'EVENT_LOG_RETENTION_DAYS', 'EVENT_LOG_MAX_SIZE_MB', 'EVENT_LOG_SYSLOG_ENABLED', 'EVENT_LOG_EVENTS') as $key) {
            if (isset($raw[$key]))
                $settings[$key] = $raw[$key];
        }

        $settings['UPSD_LISTENERS'] = array();
        $listen_count = isset($raw['UPSD_LISTEN_COUNT']) ? intval($raw['UPSD_LISTEN_COUNT']) : 0;
        if ($listen_count > 0) {
            for ($i = 1; $i <= $listen_count; $i++) {
                $prefix = 'UPSD_LISTEN_' . $i . '_';
                if (empty($raw[$prefix . 'ADDRESS']) && empty($raw[$prefix . 'PORT']))
                    continue;

                $settings['UPSD_LISTENERS'][] = array(
                    'ADDRESS' => isset($raw[$prefix . 'ADDRESS']) ? $raw[$prefix . 'ADDRESS'] : '127.0.0.1',
                    'PORT' => isset($raw[$prefix . 'PORT']) ? $raw[$prefix . 'PORT'] : '3493',
                );
            }
        }

        if (count($settings['UPSD_LISTENERS']) === 0) {
            $settings['UPSD_LISTENERS'][] = array(
                'ADDRESS' => isset($settings['UPSD_LISTEN_ADDRESS']) ? $settings['UPSD_LISTEN_ADDRESS'] : '127.0.0.1',
                'PORT' => isset($settings['UPSD_LISTEN_PORT']) ? $settings['UPSD_LISTEN_PORT'] : '3493',
            );
        }

        $settings['UPSD_LISTENERS'] = $this->_normalize_upsd_listeners($settings['UPSD_LISTENERS']);
        $settings['ALLOW_NO_DEVICE'] = $this->_normalize_boolean_setting($settings['ALLOW_NO_DEVICE']);
        $settings['ALLOW_NOT_ALL_LISTENERS'] = $this->_normalize_boolean_setting($settings['ALLOW_NOT_ALL_LISTENERS']);
        $settings['DEBUG_MIN'] = $this->_normalize_debug_min($settings['DEBUG_MIN']);
        $settings['UPSMON_SETTINGS'] = $this->_normalize_upsmon_settings($settings);
        $settings['EVENT_LOG_SETTINGS'] = $this->_normalize_event_log_settings($settings);

        $settings['UPSD_USERS'] = array();
        $user_count = isset($raw['UPSD_USER_COUNT']) ? intval($raw['UPSD_USER_COUNT']) : 0;
        if ($user_count > 0) {
            for ($i = 1; $i <= $user_count; $i++) {
                $prefix = 'UPSD_USER_' . $i . '_';
                if (empty($raw[$prefix . 'USERNAME']))
                    continue;

                $settings['UPSD_USERS'][] = array(
                    'USERNAME' => $raw[$prefix . 'USERNAME'],
                    'PASSWORD' => isset($raw[$prefix . 'PASSWORD']) ? $raw[$prefix . 'PASSWORD'] : '',
                    'ROLE' => isset($raw[$prefix . 'ROLE']) ? $raw[$prefix . 'ROLE'] : 'readonly',
                );
            }
        }

        if (count($settings['UPSD_USERS']) === 0) {
            $settings['UPSD_USERS'][] = array(
                'USERNAME' => isset($settings['UPSMON_USER']) ? $settings['UPSMON_USER'] : 'upsmon-local',
                'PASSWORD' => isset($settings['UPSMON_PASSWORD']) ? $settings['UPSMON_PASSWORD'] : '',
                'ROLE' => 'upsmon_primary',
            );
        }

        $settings['UPSD_USERS'] = $this->_normalize_upsd_users($settings['UPSD_USERS']);
        $primary_upsmon_user = $this->_get_primary_upsmon_user($settings['UPSD_USERS']);
        $settings['UPSMON_USER'] = $primary_upsmon_user['USERNAME'];
        $settings['UPSMON_PASSWORD'] = $primary_upsmon_user['PASSWORD'];

        $primary_listener = $this->_get_primary_upsd_listener($settings);
        $settings['UPSD_LISTEN_ADDRESS'] = $primary_listener['ADDRESS'];
        $settings['UPSD_LISTEN_PORT'] = $primary_listener['PORT'];

        $count = isset($raw['UPS_COUNT']) ? intval($raw['UPS_COUNT']) : 0;
        if ($count > 0) {
            for ($i = 1; $i <= $count; $i++) {
                $prefix = 'UPS_' . $i . '_';
                if (empty($raw[$prefix . 'NAME']))
                    continue;

                $settings['DEVICES'][] = $this->_normalize_device(array(
                    'UPS_NAME' => $raw[$prefix . 'NAME'],
                    'DRIVER' => isset($raw[$prefix . 'DRIVER']) ? $raw[$prefix . 'DRIVER'] : 'usbhid-ups',
                    'PORT' => isset($raw[$prefix . 'PORT']) ? $raw[$prefix . 'PORT'] : 'auto',
                    'VENDORID' => isset($raw[$prefix . 'VENDORID']) ? $raw[$prefix . 'VENDORID'] : '',
                    'PRODUCTID' => isset($raw[$prefix . 'PRODUCTID']) ? $raw[$prefix . 'PRODUCTID'] : '',
                    'SERIAL' => isset($raw[$prefix . 'SERIAL']) ? $raw[$prefix . 'SERIAL'] : '',
                    'DESC' => isset($raw[$prefix . 'DESC']) ? $raw[$prefix . 'DESC'] : 'USB UPS',
                ));
            }
        } else if (! empty($raw['UPS_NAME']) && (! empty($raw['VENDORID']) || ! empty($raw['PRODUCTID']) || (isset($raw['ENABLED']) && $raw['ENABLED'] === '1'))) {
            // Migration from app-nut <= 0.1.3 single-device format.
            $settings['DEVICES'][] = $this->_normalize_device(array(
                'UPS_NAME' => $raw['UPS_NAME'],
                'DRIVER' => isset($raw['DRIVER']) ? $raw['DRIVER'] : 'usbhid-ups',
                'PORT' => isset($raw['PORT']) ? $raw['PORT'] : 'auto',
                'VENDORID' => isset($raw['VENDORID']) ? $raw['VENDORID'] : '',
                'PRODUCTID' => isset($raw['PRODUCTID']) ? $raw['PRODUCTID'] : '',
                'SERIAL' => isset($raw['SERIAL']) ? $raw['SERIAL'] : '',
                'DESC' => isset($raw['DESC']) ? $raw['DESC'] : 'USB UPS',
            ));
        }

        $this->settings = $settings;
        $this->is_loaded = TRUE;
    }

    protected function _save_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_ensure_directories();

        if (! isset($settings['DEVICES']) || ! is_array($settings['DEVICES']))
            $settings['DEVICES'] = array();

        $contents = "# ClearOS app-nut settings\n";
        $contents .= "# Managed by Webconfig. Do not store unrelated NUT settings here.\n\n";
        $contents .= $this->_quote_setting('ENABLED', isset($settings['ENABLED']) ? $settings['ENABLED'] : '0');
        $contents .= $this->_quote_setting('MODE', isset($settings['MODE']) ? $settings['MODE'] : 'standalone');

        $settings['UPSD_LISTENERS'] = $this->_normalize_upsd_listeners($this->_get_upsd_listeners($settings));
        $primary_listener = $this->_get_primary_upsd_listener($settings);

        // Compatibility fields for app-nut <= 0.1.31.
        $contents .= $this->_quote_setting('UPSD_LISTEN_ADDRESS', $primary_listener['ADDRESS']);
        $contents .= $this->_quote_setting('UPSD_LISTEN_PORT', $primary_listener['PORT']);
        $contents .= $this->_quote_setting('UPSD_LISTEN_COUNT', count($settings['UPSD_LISTENERS']));
        $contents .= $this->_quote_setting('ALLOW_NO_DEVICE', $this->_normalize_boolean_setting(isset($settings['ALLOW_NO_DEVICE']) ? $settings['ALLOW_NO_DEVICE'] : '0'));
        $contents .= $this->_quote_setting('ALLOW_NOT_ALL_LISTENERS', $this->_normalize_boolean_setting(isset($settings['ALLOW_NOT_ALL_LISTENERS']) ? $settings['ALLOW_NOT_ALL_LISTENERS'] : '0'));
        $contents .= $this->_quote_setting('DEBUG_MIN', $this->_normalize_debug_min(isset($settings['DEBUG_MIN']) ? $settings['DEBUG_MIN'] : '0'));

        $upsmon_settings = $this->_normalize_upsmon_settings(isset($settings['UPSMON_SETTINGS']) ? $settings['UPSMON_SETTINGS'] : $settings);
        foreach ($upsmon_settings as $key => $value)
            $contents .= $this->_quote_setting('UPSMON_' . $key, $value);

        $event_log_settings = $this->_normalize_event_log_settings(isset($settings['EVENT_LOG_SETTINGS']) ? $settings['EVENT_LOG_SETTINGS'] : $settings);
        $contents .= $this->_quote_setting('EVENT_LOG_ENABLED', $event_log_settings['ENABLED']);
        $contents .= $this->_quote_setting('EVENT_LOG_FILE', $event_log_settings['LOG_FILE']);
        $contents .= $this->_quote_setting('EVENT_LOG_RETENTION_DAYS', $event_log_settings['RETENTION_DAYS']);
        $contents .= $this->_quote_setting('EVENT_LOG_MAX_SIZE_MB', $event_log_settings['MAX_SIZE_MB']);
        $contents .= $this->_quote_setting('EVENT_LOG_SYSLOG_ENABLED', $event_log_settings['SYSLOG_ENABLED']);
        $contents .= $this->_quote_setting('EVENT_LOG_EVENTS', implode(',', $event_log_settings['EVENTS']));

        $listen_index = 1;
        foreach ($settings['UPSD_LISTENERS'] as $listener) {
            $prefix = 'UPSD_LISTEN_' . $listen_index . '_';
            $contents .= $this->_quote_setting($prefix . 'ADDRESS', $listener['ADDRESS']);
            $contents .= $this->_quote_setting($prefix . 'PORT', $listener['PORT']);
            $listen_index++;
        }

        $settings['UPSD_USERS'] = $this->_normalize_upsd_users($this->_get_upsd_users($settings));
        $primary_upsmon_user = $this->_get_primary_upsmon_user($settings['UPSD_USERS']);

        // Compatibility fields for app-nut <= 0.1.38.
        $contents .= $this->_quote_setting('UPSMON_USER', $primary_upsmon_user['USERNAME']);
        $contents .= $this->_quote_setting('UPSMON_PASSWORD', $primary_upsmon_user['PASSWORD']);
        $contents .= $this->_quote_setting('UPSD_USER_COUNT', count($settings['UPSD_USERS']));

        $user_index = 1;
        foreach ($settings['UPSD_USERS'] as $user) {
            $prefix = 'UPSD_USER_' . $user_index . '_';
            $contents .= $this->_quote_setting($prefix . 'USERNAME', $user['USERNAME']);
            $contents .= $this->_quote_setting($prefix . 'PASSWORD', $user['PASSWORD']);
            $contents .= $this->_quote_setting($prefix . 'ROLE', $user['ROLE']);
            $user_index++;
        }

        $contents .= $this->_quote_setting('UPS_COUNT', count($settings['DEVICES']));
        $contents .= "\n";

        $index = 1;
        foreach ($settings['DEVICES'] as $device) {
            $device = $this->_normalize_device($device);
            $prefix = 'UPS_' . $index . '_';
            $contents .= '# UPS device ' . $index . "\n";
            $contents .= $this->_quote_setting($prefix . 'NAME', $device['UPS_NAME']);
            $contents .= $this->_quote_setting($prefix . 'DRIVER', $device['DRIVER']);
            $contents .= $this->_quote_setting($prefix . 'PORT', $device['PORT']);
            $contents .= $this->_quote_setting($prefix . 'VENDORID', $device['VENDORID']);
            $contents .= $this->_quote_setting($prefix . 'PRODUCTID', $device['PRODUCTID']);
            $contents .= $this->_quote_setting($prefix . 'SERIAL', $device['SERIAL']);
            $contents .= $this->_quote_setting($prefix . 'DESC', $device['DESC']);
            $contents .= "\n";
            $index++;
        }

        $first = $this->_get_first_device($settings['DEVICES']);
        $contents .= "# Compatibility fields for app-nut <= 0.1.3\n";
        $contents .= $this->_quote_setting('UPS_NAME', $first ? $first['UPS_NAME'] : '');
        $contents .= $this->_quote_setting('DRIVER', $first ? $first['DRIVER'] : 'usbhid-ups');
        $contents .= $this->_quote_setting('PORT', $first ? $first['PORT'] : 'auto');
        $contents .= $this->_quote_setting('VENDORID', $first ? $first['VENDORID'] : '');
        $contents .= $this->_quote_setting('PRODUCTID', $first ? $first['PRODUCTID'] : '');
        $contents .= $this->_quote_setting('SERIAL', $first ? $first['SERIAL'] : '');
        $contents .= $this->_quote_setting('DESC', $first ? $first['DESC'] : 'USB UPS');

        $this->_write_full_file(self::FILE_APP_CONFIG, $contents, '0600');

        $this->settings = $settings;
        $this->is_loaded = TRUE;
    }

    protected function _quote_setting($key, $value)
    {
        clearos_profile(__METHOD__, __LINE__);

        $value = str_replace('"', '\\"', $value);
        return $key . '="' . $value . '"' . "\n";
    }

    protected function _normalize_device($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'UPS_NAME' => isset($device['UPS_NAME']) ? trim($device['UPS_NAME']) : $this->get_default_ups_name(),
            'DRIVER' => isset($device['DRIVER']) ? trim($device['DRIVER']) : 'usbhid-ups',
            'PORT' => isset($device['PORT']) ? trim($device['PORT']) : 'auto',
            'VENDORID' => isset($device['VENDORID']) ? strtolower(trim($device['VENDORID'])) : '',
            'PRODUCTID' => isset($device['PRODUCTID']) ? strtolower(trim($device['PRODUCTID'])) : '',
            'SERIAL' => isset($device['SERIAL']) ? trim($device['SERIAL']) : '',
            'DESC' => isset($device['DESC']) ? $this->_clean_description($device['DESC']) : 'USB UPS',
        );
    }

    protected function _validate_device($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_ups_name($device['UPS_NAME']));
        Validation_Exception::is_valid($this->validate_driver($device['DRIVER']));
        Validation_Exception::is_valid($this->validate_port($device['PORT']));
        Validation_Exception::is_valid($this->validate_usb_id($device['VENDORID'], FALSE));
        Validation_Exception::is_valid($this->validate_usb_id($device['PRODUCTID'], FALSE));
        Validation_Exception::is_valid($this->validate_optional_token($device['SERIAL']));
        Validation_Exception::is_valid($this->validate_description($device['DESC']));
    }

    protected function _clean_description($description)
    {
        clearos_profile(__METHOD__, __LINE__);

        $description = trim(preg_replace('/[\r\n\[\]]+/', ' ', $description));
        if ($description === '')
            $description = 'USB UPS';
        if (strlen($description) > 128)
            $description = substr($description, 0, 128);

        return $description;
    }

    protected function _get_first_device($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (is_array($devices) && count($devices) > 0)
            return $devices[0];

        return NULL;
    }

    protected function _make_unique_ups_name($base, $devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $existing = array();
        foreach ($devices as $device)
            $existing[$device['UPS_NAME']] = TRUE;

        if (! isset($existing[$base]))
            return $base;

        for ($i = 2; $i < 100; $i++) {
            $candidate = $base . '-' . $i;
            if (! isset($existing[$candidate]))
                return $candidate;
        }

        return $base . '-' . time();
    }

    protected function _get_configured_usb_index()
    {
        clearos_profile(__METHOD__, __LINE__);

        $index = array();
        if (! $this->is_loaded)
            $this->_load_settings();

        foreach ($this->settings['DEVICES'] as $device) {
            if ($device['VENDORID'] === '' || $device['PRODUCTID'] === '')
                continue;

            $key = strtolower($device['VENDORID']) . ':' . strtolower($device['PRODUCTID']);
            if (! isset($index[$key]))
                $index[$key] = array();
            $index[$key][] = $device['UPS_NAME'];
        }

        return $index;
    }

    protected function _ensure_directories()
    {
        clearos_profile(__METHOD__, __LINE__);

        // These directories and permissions are package-install/upgrade work.
        // Do not mkdir/chown/chmod them from Webconfig: otherwise ClearOS Shell
        // falls back to sudo and fails without TTY/askpass.
        foreach (array(self::DIR_NUT_CONFIG, '/var/clearos/nut', self::DIR_BACKUP) as $directory) {
            if (! file_exists($directory))
                throw new Engine_Exception(lang('base_directory_not_found') . ': ' . $directory . '. Run /usr/clearos/apps/nut/deploy/upgrade');
        }
    }

    protected function _backup_config_files()
    {
        clearos_profile(__METHOD__, __LINE__);

        $timestamp = date('Ymd-His');
        $files = array(
            self::FILE_NUT_CONF,
            self::FILE_UPS_CONF,
            self::FILE_UPSD_CONF,
            self::FILE_UPSD_USERS,
            self::FILE_UPSMON_CONF,
        );

        foreach ($files as $file) {
            if (! file_exists($file))
                continue;

            $target = self::DIR_BACKUP . '/' . basename($file) . '.' . $timestamp;
            try {
                $source = new File($file, TRUE);
                $source->copy_to($target);
            } catch (\Exception $e) {
                throw new Engine_Exception(lang('base_file_backup_failed') . ': ' . $file);
            }
        }
    }

    protected function _set_nut_mode($mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $contents = $this->_read_full_file(self::FILE_NUT_CONF);

        if (preg_match('/^MODE=/m', $contents))
            $contents = preg_replace('/^MODE=.*/m', 'MODE=' . $mode, $contents);
        else
            $contents .= (strlen($contents) && substr($contents, -1) !== "\n" ? "\n" : '') . 'MODE=' . $mode . "\n";

        $this->_write_file(self::FILE_NUT_CONF, $contents, 0640);
    }

    protected function _write_ups_conf($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        // If the same UPS was configured manually before app-nut was installed,
        // remove the unmanaged duplicate section first.  NUT does not like two
        // sections with the same [ups-name].  A backup is already created before
        // this method is called from apply_configuration().
        $this->_remove_conflicting_unmanaged_ups_sections(self::FILE_UPS_CONF, $devices, 0640);

        $block = $this->_build_ups_conf_block($devices);

        $this->_replace_managed_block(self::FILE_UPS_CONF, 'app-nut usb-ups', $block, 0640);
    }

    protected function _build_ups_conf_block($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $block = '';
        foreach ($devices as $device) {
            $device = $this->_normalize_device($device);
            $block .= '[' . $device['UPS_NAME'] . "]\n";
            $block .= '    driver = ' . $device['DRIVER'] . "\n";
            $block .= '    port = ' . $device['PORT'] . "\n";
            $block .= '    desc = ' . $device['DESC'] . "\n";

            if (! empty($device['VENDORID']))
                $block .= '    vendorid = ' . strtolower($device['VENDORID']) . "\n";
            if (! empty($device['PRODUCTID']) && $this->_should_write_productid($device))
                $block .= '    productid = ' . strtolower($device['PRODUCTID']) . "\n";
            if (! empty($device['SERIAL']))
                $block .= '    serial = ' . $device['SERIAL'] . "\n";

            $block .= "\n";
        }

        return $block;
    }

    protected function _should_write_productid($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Powercom 0d9f:0004 is commonly configured with usbhid-ups using
        // only vendorid. This also matches the original working script.
        // Keeping PRODUCTID in the app database is useful for the UI, but
        // omitting it from ups.conf makes matching less fragile on older NUT
        // releases such as 2.7.x.
        if (isset($device['DRIVER']) && $device['DRIVER'] === 'usbhid-ups' &&
            isset($device['VENDORID']) && strtolower($device['VENDORID']) === '0d9f')
            return FALSE;

        return TRUE;
    }

    protected function _normalize_boolean_setting($value)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($value === TRUE || $value === 1 || $value === '1' || $value === 'on' || $value === 'yes' || $value === 'true' || $value === 'enabled')
            return '1';

        return '0';
    }

    protected function _normalize_debug_min($value)
    {
        clearos_profile(__METHOD__, __LINE__);

        $value = trim((string) $value);

        if (! preg_match('/^[0-5]$/', $value))
            return '0';

        return $value;
    }


    protected function _get_upsmon_default_settings()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'MINSUPPLIES' => '1',
            'SHUTDOWNCMD' => '/sbin/shutdown -h +0',
            'POLLFREQ' => '5',
            'POLLFREQALERT' => '5',
            'HOSTSYNC' => '15',
            'DEADTIME' => '15',
            'POWERDOWNFLAG' => '/etc/killpower',
            'FINALDELAY' => '5',
        );
    }

    protected function _normalize_upsmon_integer($value, $default)
    {
        clearos_profile(__METHOD__, __LINE__);

        $value = trim((string) $value);

        if (! preg_match('/^[0-9]+$/', $value))
            return $default;

        return (string) intval($value);
    }

    protected function _normalize_upsmon_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        $defaults = $this->_get_upsmon_default_settings();
        $normalized = $defaults;

        if (! is_array($settings))
            $settings = array();

        foreach ($defaults as $key => $default) {
            $value = NULL;

            if (isset($settings[$key]))
                $value = $settings[$key];
            else if (isset($settings['UPSMON_' . $key]))
                $value = $settings['UPSMON_' . $key];

            if ($value === NULL)
                continue;

            if (in_array($key, array('MINSUPPLIES', 'POLLFREQ', 'POLLFREQALERT', 'HOSTSYNC', 'DEADTIME', 'FINALDELAY')))
                $normalized[$key] = $this->_normalize_upsmon_integer($value, $default);
            else
                $normalized[$key] = trim((string) $value);
        }

        if ($normalized['SHUTDOWNCMD'] === '')
            $normalized['SHUTDOWNCMD'] = $defaults['SHUTDOWNCMD'];
        if ($normalized['POWERDOWNFLAG'] === '')
            $normalized['POWERDOWNFLAG'] = $defaults['POWERDOWNFLAG'];

        return $normalized;
    }

    protected function _get_upsmon_settings($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        if (isset($settings['UPSMON_SETTINGS']) && is_array($settings['UPSMON_SETTINGS']))
            return $this->_normalize_upsmon_settings($settings['UPSMON_SETTINGS']);

        return $this->_normalize_upsmon_settings($settings);
    }

    protected function _get_event_log_default_settings()
    {
        clearos_profile(__METHOD__, __LINE__);

        return array(
            'ENABLED' => '0',
            'LOG_FILE' => self::FILE_EVENT_LOG,
            'RETENTION_DAYS' => '30',
            'MAX_SIZE_MB' => '5',
            'SYSLOG_ENABLED' => '1',
            'EVENTS' => array('ONLINE', 'ONBATT', 'LOWBATT', 'FSD', 'SHUTDOWN', 'COMMOK', 'COMMBAD', 'NOCOMM', 'REPLBATT'),
        );
    }

    protected function _normalize_event_log_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        $defaults = $this->_get_event_log_default_settings();
        $normalized = $defaults;

        if (! is_array($settings))
            $settings = array();

        $normalized['ENABLED'] = $this->_normalize_boolean_setting(isset($settings['ENABLED']) ? $settings['ENABLED'] : (isset($settings['EVENT_LOG_ENABLED']) ? $settings['EVENT_LOG_ENABLED'] : $defaults['ENABLED']));
        $normalized['LOG_FILE'] = trim((string) (isset($settings['LOG_FILE']) ? $settings['LOG_FILE'] : (isset($settings['EVENT_LOG_FILE']) ? $settings['EVENT_LOG_FILE'] : $defaults['LOG_FILE'])));
        $normalized['RETENTION_DAYS'] = $this->_normalize_upsmon_integer(isset($settings['RETENTION_DAYS']) ? $settings['RETENTION_DAYS'] : (isset($settings['EVENT_LOG_RETENTION_DAYS']) ? $settings['EVENT_LOG_RETENTION_DAYS'] : $defaults['RETENTION_DAYS']), $defaults['RETENTION_DAYS']);
        $normalized['MAX_SIZE_MB'] = $this->_normalize_upsmon_integer(isset($settings['MAX_SIZE_MB']) ? $settings['MAX_SIZE_MB'] : (isset($settings['EVENT_LOG_MAX_SIZE_MB']) ? $settings['EVENT_LOG_MAX_SIZE_MB'] : $defaults['MAX_SIZE_MB']), $defaults['MAX_SIZE_MB']);
        $normalized['SYSLOG_ENABLED'] = $this->_normalize_boolean_setting(isset($settings['SYSLOG_ENABLED']) ? $settings['SYSLOG_ENABLED'] : (isset($settings['EVENT_LOG_SYSLOG_ENABLED']) ? $settings['EVENT_LOG_SYSLOG_ENABLED'] : $defaults['SYSLOG_ENABLED']));

        $events = isset($settings['EVENTS']) ? $settings['EVENTS'] : (isset($settings['EVENT_LOG_EVENTS']) ? $settings['EVENT_LOG_EVENTS'] : $defaults['EVENTS']);
        if (is_string($events))
            $events = preg_split('/\s*,\s*/', trim($events));
        if (! is_array($events))
            $events = $defaults['EVENTS'];

        $allowed = $this->get_event_log_event_options();
        $normalized['EVENTS'] = array();
        foreach ($events as $event) {
            $event = strtoupper(trim((string) $event));
            if ($event !== '' && isset($allowed[$event]) && ! in_array($event, $normalized['EVENTS']))
                $normalized['EVENTS'][] = $event;
        }

        if (count($normalized['EVENTS']) === 0)
            $normalized['EVENTS'] = $defaults['EVENTS'];

        if ($normalized['LOG_FILE'] === '')
            $normalized['LOG_FILE'] = $defaults['LOG_FILE'];

        return $normalized;
    }

    protected function _get_event_log_settings($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        if (isset($settings['EVENT_LOG_SETTINGS']) && is_array($settings['EVENT_LOG_SETTINGS']))
            return $this->_normalize_event_log_settings($settings['EVENT_LOG_SETTINGS']);

        return $this->_normalize_event_log_settings($settings);
    }

    protected function _quote_upsmon_value($value)
    {
        clearos_profile(__METHOD__, __LINE__);

        return str_replace(array('\\', '"'), array('\\\\', '\\"'), $value);
    }

    protected function _normalize_upsd_listeners($listeners, $use_default = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        $normalized = array();

        if (! is_array($listeners))
            $listeners = array();

        foreach ($listeners as $listener) {
            if (! is_array($listener))
                continue;

            $address = isset($listener['ADDRESS']) ? trim($listener['ADDRESS']) : '';
            $port = isset($listener['PORT']) ? trim($listener['PORT']) : '';

            if ($address === '' && $port === '')
                continue;

            if ($port === '')
                $port = '3493';

            $normalized[] = array(
                'ADDRESS' => $address,
                'PORT' => $port,
            );
        }

        if ($use_default && count($normalized) === 0)
            $normalized[] = array('ADDRESS' => '127.0.0.1', 'PORT' => '3493');

        return $normalized;
    }

    protected function _get_upsd_listeners($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        if (isset($settings['UPSD_LISTENERS']) && is_array($settings['UPSD_LISTENERS']))
            return $this->_normalize_upsd_listeners($settings['UPSD_LISTENERS']);

        return $this->_normalize_upsd_listeners(array(
            array(
                'ADDRESS' => isset($settings['UPSD_LISTEN_ADDRESS']) ? $settings['UPSD_LISTEN_ADDRESS'] : '127.0.0.1',
                'PORT' => isset($settings['UPSD_LISTEN_PORT']) ? $settings['UPSD_LISTEN_PORT'] : '3493',
            ),
        ));
    }

    protected function _get_primary_upsd_listener($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        $listeners = $this->_get_upsd_listeners($settings);

        foreach ($listeners as $listener) {
            $address = strtolower($listener['ADDRESS']);
            if ($address === '127.0.0.1' || $address === 'localhost' || $address === '::1')
                return $listener;
        }

        return $listeners[0];
    }

    protected function _normalize_upsd_user_role($role)
    {
        clearos_profile(__METHOD__, __LINE__);

        $role = trim((string) $role);

        // Migration from older NUT wording used by many installations.
        if ($role === 'upsmon_master')
            $role = 'upsmon_primary';
        if ($role === 'upsmon_slave')
            $role = 'upsmon_secondary';

        $roles = $this->get_upsd_user_role_options();

        if (! isset($roles[$role]))
            return 'readonly';

        return $role;
    }

    protected function _normalize_upsd_users($users, $use_default = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        $normalized = array();

        if (! is_array($users))
            $users = array();

        foreach ($users as $user) {
            if (! is_array($user))
                continue;

            $username = isset($user['USERNAME']) ? trim($user['USERNAME']) : '';
            $password = isset($user['PASSWORD']) ? trim($user['PASSWORD']) : '';
            $role = isset($user['ROLE']) ? $this->_normalize_upsd_user_role($user['ROLE']) : 'readonly';

            if ($username === '' && $password === '')
                continue;

            if ($username === '')
                $username = 'upsmon-local';
            if ($password === '')
                $password = $this->_generate_password();

            $normalized[] = array(
                'USERNAME' => $username,
                'PASSWORD' => $password,
                'ROLE' => $role,
            );
        }

        if ($use_default && count($normalized) === 0)
            $normalized[] = array('USERNAME' => 'upsmon-local', 'PASSWORD' => $this->_generate_password(), 'ROLE' => 'upsmon_primary');

        $has_primary = FALSE;
        foreach ($normalized as $user) {
            if ($user['ROLE'] === 'upsmon_primary') {
                $has_primary = TRUE;
                break;
            }
        }

        if ($use_default && ! $has_primary)
            $normalized[] = array('USERNAME' => 'upsmon-local', 'PASSWORD' => $this->_generate_password(), 'ROLE' => 'upsmon_primary');

        return $normalized;
    }

    protected function _get_upsd_users($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        if (isset($settings['UPSD_USERS']) && is_array($settings['UPSD_USERS']))
            return $this->_normalize_upsd_users($settings['UPSD_USERS']);

        return $this->_normalize_upsd_users(array(
            array(
                'USERNAME' => isset($settings['UPSMON_USER']) ? $settings['UPSMON_USER'] : 'upsmon-local',
                'PASSWORD' => isset($settings['UPSMON_PASSWORD']) ? $settings['UPSMON_PASSWORD'] : '',
                'ROLE' => 'upsmon_primary',
            ),
        ));
    }

    protected function _get_primary_upsmon_user($users)
    {
        clearos_profile(__METHOD__, __LINE__);

        $users = $this->_normalize_upsd_users($users);

        foreach ($users as $user) {
            if ($user['ROLE'] === 'upsmon_primary')
                return $user;
        }

        return $users[0];
    }

    protected function _get_upsd_user_directives($role)
    {
        clearos_profile(__METHOD__, __LINE__);

        $role = $this->_normalize_upsd_user_role($role);

        switch ($role) {
            case 'upsmon_primary':
                return "    upsmon primary\n";

            case 'upsmon_secondary':
                return "    upsmon secondary\n";

            case 'admin':
                return "    actions = set\n    actions = fsd\n    instcmds = all\n";

            case 'instcmd_all':
                return "    instcmds = all\n";

            case 'set':
                return "    actions = set\n";

            case 'fsd':
                return "    actions = fsd\n";

            case 'readonly':
            default:
                return '';
        }
    }


    protected function _write_upsd_conf($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        $listeners = $this->_get_upsd_listeners($settings);
        Validation_Exception::is_valid($this->validate_upsd_listeners($listeners));

        // Keep only the LISTEN lines managed by app-nut. Old manual LISTEN
        // lines can make upsd bind to unexpected addresses. A backup is made
        // before applying configuration.
        $this->_remove_unmanaged_listen_lines(self::FILE_UPSD_CONF, 0640);
        $this->_remove_managed_block(self::FILE_UPSD_CONF, 'app-nut local-listen', 0640);

        $block = '';
        foreach ($listeners as $listener)
            $block .= 'LISTEN ' . $listener['ADDRESS'] . ' ' . $listener['PORT'] . "\n";

        if ($this->_normalize_boolean_setting(isset($settings['ALLOW_NO_DEVICE']) ? $settings['ALLOW_NO_DEVICE'] : '0') === '1')
            $block .= "ALLOW_NO_DEVICE true\n";

        if ($this->_normalize_boolean_setting(isset($settings['ALLOW_NOT_ALL_LISTENERS']) ? $settings['ALLOW_NOT_ALL_LISTENERS'] : '0') === '1')
            $block .= "ALLOW_NOT_ALL_LISTENERS true\n";

        $debug_min = $this->_normalize_debug_min(isset($settings['DEBUG_MIN']) ? $settings['DEBUG_MIN'] : '0');
        if ($debug_min !== '0')
            $block .= 'DEBUG_MIN ' . $debug_min . "\n";

        $this->_replace_managed_block(self::FILE_UPSD_CONF, 'app-nut upsd-listen', $block, 0640);
    }

    protected function _write_upsd_users($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        $users = $this->_normalize_upsd_users($this->_get_upsd_users($settings));

        $block = '';
        foreach ($users as $user) {
            $block .= '[' . $user['USERNAME'] . "]\n";
            $block .= '    password = ' . $user['PASSWORD'] . "\n";
            $block .= $this->_get_upsd_user_directives($user['ROLE']);
            $block .= "\n";
        }

        $this->_remove_managed_block(self::FILE_UPSD_USERS, 'app-nut upsmon-user', 0640);
        $this->_replace_managed_block(self::FILE_UPSD_USERS, 'app-nut upsd-users', $block, 0640);
    }


    protected function _write_upsmon_conf($settings, $devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        // First app-nut release is local USB only.  Old manual MONITOR lines
        // left by previous scripts can point to non-existing UPS names and make
        // nut-monitor fail.  A backup is created before this method runs, so we
        // keep upsmon.conf consistent with the visible Webconfig device list.
        $this->_remove_unmanaged_monitor_lines(self::FILE_UPSMON_CONF, 0640);

        $primary_upsmon_user = $this->_get_primary_upsmon_user($this->_get_upsd_users($settings));

        $upsmon_settings = $this->_get_upsmon_settings($settings);

        $block = '';
        foreach ($devices as $device) {
            $target = $this->_get_upsc_target($device['UPS_NAME'], $settings);
            $block .= 'MONITOR ' . $target . ' 1 ' . $primary_upsmon_user['USERNAME'] . ' ' . $primary_upsmon_user['PASSWORD'] . " primary\n";
        }

        $block .= 'MINSUPPLIES ' . $upsmon_settings['MINSUPPLIES'] . "\n";
        $block .= 'SHUTDOWNCMD "' . $this->_quote_upsmon_value($upsmon_settings['SHUTDOWNCMD']) . '"' . "\n";
        $block .= 'POLLFREQ ' . $upsmon_settings['POLLFREQ'] . "\n";
        $block .= 'POLLFREQALERT ' . $upsmon_settings['POLLFREQALERT'] . "\n";
        $block .= 'HOSTSYNC ' . $upsmon_settings['HOSTSYNC'] . "\n";
        $block .= 'DEADTIME ' . $upsmon_settings['DEADTIME'] . "\n";
        $block .= 'POWERDOWNFLAG ' . $upsmon_settings['POWERDOWNFLAG'] . "\n";
        $block .= 'FINALDELAY ' . $upsmon_settings['FINALDELAY'] . "\n";

        $event_log_settings = $this->_get_event_log_settings($settings);
        if ($event_log_settings['ENABLED'] === '1') {
            $block .= "\n";
            $block .= 'NOTIFYCMD ' . self::COMMAND_NOTIFY . "\n";
            foreach ($event_log_settings['EVENTS'] as $event) {
                $block .= 'NOTIFYMSG ' . $event . ' "' . $this->_quote_upsmon_value($this->_get_notify_message($event)) . '"' . "\n";
                $block .= 'NOTIFYFLAG ' . $event . ' ' . ($event_log_settings['SYSLOG_ENABLED'] === '1' ? 'SYSLOG+EXEC' : 'EXEC') . "\n";
            }
        }

        $this->_replace_managed_block(self::FILE_UPSMON_CONF, 'app-nut local-monitor', $block, 0640);
    }


    protected function _normalize_event_log_entry($entry)
    {
        clearos_profile(__METHOD__, __LINE__);

        $event = isset($entry['event']) ? strtoupper((string) $entry['event']) : 'UNKNOWN';

        $normalized = array(
            'time' => isset($entry['time']) ? (string) $entry['time'] : '',
            'event' => $event,
            'level' => isset($entry['level']) ? (string) $entry['level'] : 'info',
            'ups' => isset($entry['ups']) ? (string) $entry['ups'] : '',
            'message_key' => isset($entry['message_key']) ? (string) $entry['message_key'] : '',
            'ups_status' => isset($entry['ups_status']) ? (string) $entry['ups_status'] : '',
            'battery_charge' => isset($entry['battery_charge']) ? (string) $entry['battery_charge'] : '',
            'battery_runtime' => isset($entry['battery_runtime']) ? (string) $entry['battery_runtime'] : '',
            'ups_load' => isset($entry['ups_load']) ? (string) $entry['ups_load'] : '',
            'input_voltage' => isset($entry['input_voltage']) ? (string) $entry['input_voltage'] : '',
            'output_voltage' => isset($entry['output_voltage']) ? (string) $entry['output_voltage'] : '',
            'battery_temperature' => isset($entry['battery_temperature']) ? (string) $entry['battery_temperature'] : '',
        );

        if ($normalized['message_key'] === '')
            $normalized['message_key'] = 'nut_event_message_' . strtolower(preg_replace('/[^A-Z0-9_]/', '_', $event));

        return $normalized;
    }

    protected function _format_event_log_message($entry)
    {
        clearos_profile(__METHOD__, __LINE__);

        $event = isset($entry['event']) ? $entry['event'] : 'UNKNOWN';
        $ups = isset($entry['ups']) && $entry['ups'] !== '' ? $entry['ups'] : '-';
        $key = 'nut_event_message_' . strtolower(preg_replace('/[^A-Z0-9_]/', '_', $event));

        $message = lang($key);

        if ($message === $key || $message === '')
            return sprintf(lang('nut_event_message_unknown'), $ups, $event);

        return sprintf($message, $ups);
    }

    protected function _format_event_log_runtime($entry)
    {
        clearos_profile(__METHOD__, __LINE__);

        $parts = array();

        if (! empty($entry['ups_status']))
            $parts[] = lang('nut_status') . ': ' . $entry['ups_status'];
        if ($entry['battery_charge'] !== '')
            $parts[] = lang('nut_battery_charge') . ': ' . $entry['battery_charge'] . '%';
        if ($entry['battery_runtime'] !== '' && is_numeric($entry['battery_runtime']))
            $parts[] = lang('nut_battery_runtime') . ': ' . round($entry['battery_runtime'] / 60, 1) . ' ' . lang('nut_minutes');
        if ($entry['ups_load'] !== '')
            $parts[] = lang('nut_load') . ': ' . $entry['ups_load'] . '%';
        if ($entry['input_voltage'] !== '')
            $parts[] = lang('nut_input_voltage') . ': ' . $entry['input_voltage'] . ' V';
        if ($entry['output_voltage'] !== '')
            $parts[] = lang('nut_output_voltage') . ': ' . $entry['output_voltage'] . ' V';
        if ($entry['battery_temperature'] !== '')
            $parts[] = lang('nut_battery_temperature') . ': ' . $entry['battery_temperature'] . ' °C';

        return implode('\n', $parts);
    }

    protected function _get_notify_message($event)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Keep upsmon.conf and the raw event log language-neutral.  The
        // Webconfig UI translates event codes according to the user's language.
        return 'APP_NUT_EVENT ' . $event . ' %s';
    }


    protected function _remove_conflicting_unmanaged_ups_sections($file, $devices, $mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $names = array();
        foreach ($devices as $device)
            $names[$device['UPS_NAME']] = TRUE;

        if (count($names) === 0)
            return;

        $contents = $this->_read_full_file($file);
        if ($contents === '')
            return;

        $lines = preg_split('/\r?\n/', rtrim($contents, "\r\n"));
        $result = array();
        $inside_managed = FALSE;
        $changed = FALSE;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '# BEGIN app-nut usb-ups') {
                $inside_managed = TRUE;
                $result[] = $line;
                continue;
            }

            if ($trimmed === '# END app-nut usb-ups') {
                $inside_managed = FALSE;
                $result[] = $line;
                continue;
            }

            $matches = array();
            if (! $inside_managed && preg_match('/^\[([A-Za-z0-9][A-Za-z0-9_-]{0,63})\]\s*$/', $trimmed, $matches) && isset($names[$matches[1]])) {
                $changed = TRUE;

                // Skip this unmanaged UPS section until the next section header
                // or until the managed app-nut block starts.
                while (($i + 1) < count($lines)) {
                    $next = trim($lines[$i + 1]);
                    if (preg_match('/^\[[^\]]+\]\s*$/', $next) || $next === '# BEGIN app-nut usb-ups')
                        break;
                    $i++;
                }
                continue;
            }

            $result[] = $line;
        }

        if ($changed)
            $this->_write_file($file, rtrim(implode("\n", $result), "\n") . "\n", $mode);
    }

    protected function _remove_unmanaged_listen_lines($file, $mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $contents = $this->_read_full_file($file);
        if ($contents === '')
            return;

        $lines = preg_split('/\r?\n/', rtrim($contents, "\r\n"));
        $result = array();
        $inside_managed = FALSE;
        $changed = FALSE;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '# BEGIN app-nut local-listen') {
                $inside_managed = TRUE;
                $result[] = $line;
                continue;
            }

            if ($trimmed === '# END app-nut local-listen') {
                $inside_managed = FALSE;
                $result[] = $line;
                continue;
            }

            if (! $inside_managed && preg_match('/^LISTEN\s+/i', $trimmed)) {
                $changed = TRUE;
                continue;
            }

            $result[] = $line;
        }

        if ($changed)
            $this->_write_file($file, rtrim(implode("\n", $result), "\n") . "\n", $mode);
    }

    protected function _remove_unmanaged_monitor_lines($file, $mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $contents = $this->_read_full_file($file);
        if ($contents === '')
            return;

        $lines = preg_split('/\r?\n/', rtrim($contents, "\r\n"));
        $result = array();
        $inside_managed = FALSE;
        $changed = FALSE;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '# BEGIN app-nut local-monitor') {
                $inside_managed = TRUE;
                $result[] = $line;
                continue;
            }

            if ($trimmed === '# END app-nut local-monitor') {
                $inside_managed = FALSE;
                $result[] = $line;
                continue;
            }

            if (! $inside_managed && preg_match('/^MONITOR\s+/i', $trimmed)) {
                $changed = TRUE;
                continue;
            }

            $result[] = $line;
        }

        if ($changed)
            $this->_write_file($file, rtrim(implode("\n", $result), "\n") . "\n", $mode);
    }

    protected function _replace_managed_block($file, $name, $block, $mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $contents = $this->_read_full_file($file);

        $begin = '# BEGIN ' . $name;
        $end = '# END ' . $name;
        $managed = $begin . "\n" . rtrim($block) . "\n" . $end . "\n";
        $pattern = '/^' . preg_quote($begin, '/') . '$.*?^' . preg_quote($end, '/') . '$\s*/ms';

        if (preg_match($pattern, $contents))
            $contents = preg_replace($pattern, $managed, $contents);
        else
            $contents .= (strlen($contents) && substr($contents, -1) !== "\n" ? "\n" : '') . "\n" . $managed;

        $this->_write_file($file, $contents, $mode);
    }

    protected function _remove_managed_block($file, $name, $mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $contents = $this->_read_full_file($file);
        $begin = '# BEGIN ' . $name;
        $end = '# END ' . $name;
        $pattern = '/^' . preg_quote($begin, '/') . '$.*?^' . preg_quote($end, '/') . '$\s*/ms';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, '', $contents);
            $this->_write_file($file, $contents, $mode);
        }
    }

    protected function _write_file($file, $contents, $mode)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_write_full_file($file, $contents, sprintf('%04o', $mode));
    }

    protected function _read_full_file($file)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $target = new File($file, TRUE);
            if (! $target->exists())
                return '';

            $lines = $target->get_contents_as_array();
            return implode("\n", $lines) . (count($lines) ? "\n" : '');
        } catch (\Exception $e) {
            return '';
        }
    }


    protected function _is_nut_runtime_config_file($file)
    {
        clearos_profile(__METHOD__, __LINE__);

        $runtime_files = array(
            self::FILE_NUT_CONF,
            self::FILE_UPS_CONF,
            self::FILE_UPSD_CONF,
            self::FILE_UPSD_USERS,
            self::FILE_UPSMON_CONF,
        );

        return in_array($file, $runtime_files);
    }

    protected function _fix_nut_config_permissions($fix_files = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Permissions for /etc/ups and existing NUT config files are set by
        // deploy/install and deploy/upgrade.  Do not call a sudo/helper from
        // Webconfig just to fix permissions: if the package install did not
        // prepare permissions, the package is incomplete.  Files written by
        // this library are chowned/chmoded in _write_full_file().
        return;
    }

    protected function _write_full_file($file, $contents, $mode = '0644')
    {
        clearos_profile(__METHOD__, __LINE__);

        $target = new File($file, TRUE);

        try {
            if (! $target->exists())
                $target->create('root', 'root', $mode);

            $tempfile = tempnam(defined('CLEAROS_TEMP_DIR') ? CLEAROS_TEMP_DIR : sys_get_temp_dir(), 'app-nut-');
            if ($tempfile === FALSE)
                throw new Engine_Exception(lang('base_file_write_error') . ': ' . $file);

            if (file_put_contents($tempfile, $contents) === FALSE) {
                @unlink($tempfile);
                throw new Engine_Exception(lang('base_file_write_error') . ': ' . $file);
            }

            $target->replace($tempfile);
            $target->chmod($mode);

            if ($this->_is_nut_runtime_config_file($file))
                $target->chown('root', 'nut');
            else
                $target->chown('root', 'root');
        } catch (\Exception $e) {
            throw new Engine_Exception(lang('base_file_write_error') . ': ' . $file);
        }
    }

    protected function _mkdir_p($directory, $mode = '0755')
    {
        clearos_profile(__METHOD__, __LINE__);

        if (is_dir($directory))
            return;

        try {
            $shell = new Shell();
            $exit_code = $shell->execute('/bin/mkdir', '-p ' . escapeshellarg($directory), TRUE);
            if ($exit_code !== 0)
                throw new Engine_Exception(lang('base_directory_create_failed') . ': ' . $directory);

            $shell->execute('/bin/chmod', $mode . ' ' . escapeshellarg($directory), TRUE);
            if ($directory === self::DIR_NUT_CONFIG)
                $shell->execute('/bin/chown', 'root:nut ' . escapeshellarg($directory), TRUE);
            else
                $shell->execute('/bin/chown', 'root:root ' . escapeshellarg($directory), TRUE);
        } catch (\Exception $e) {
            throw new Engine_Exception(lang('base_directory_create_failed') . ': ' . $directory);
        }
    }

    protected function _apply_services_for_mode($mode, $devices = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_mode($mode));

        switch ($mode) {
            case 'standalone':
            case 'netserver':
                $this->_enable_and_restart_services($devices);
                break;

            case 'netclient':
                // netclient mode is for systems that only run upsmon. app-nut
                // currently generates local USB MONITOR lines, so this is mostly
                // useful when the admin edits upsmon.conf manually. Keep service
                // handling conservative and do not start local drivers or upsd.
                $this->_stop_nut_stack($devices);
                $this->_run_shell('/usr/bin/systemctl', 'enable nut-monitor.service', TRUE);
                $this->_run_shell('/usr/bin/systemctl', 'start nut-monitor.service', TRUE);
                break;

            case 'none':
                $this->_disable_and_stop_nut_services($devices);
                break;
        }
    }

    protected function _disable_and_stop_nut_services($devices = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($devices === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $devices = $this->settings['DEVICES'];
        }

        $this->_stop_nut_stack($devices);

        foreach ($this->_normalize_devices($devices) as $device)
            $this->_run_shell('/usr/bin/systemctl', 'disable ' . $this->_get_driver_unit($device['UPS_NAME']), TRUE);

        $this->_run_shell('/usr/bin/systemctl', 'disable nut-driver.target', TRUE);
        $this->_run_shell('/usr/bin/systemctl', 'disable nut-server.service', TRUE);
        $this->_run_shell('/usr/bin/systemctl', 'disable nut-monitor.service', TRUE);
    }

    protected function _enable_and_restart_services($devices = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($devices === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $devices = $this->settings['DEVICES'];
        }

        // Verified on ClearOS/NUT 2.8: nut-server.service does not start the
        // UPS driver.  The driver must be controlled through the real NUT
        // systemd instance unit nut-driver@UPS_NAME.service.  Do not call
        // /usr/sbin/upsdrvctl directly from Webconfig.
        $this->_enable_nut_services($devices);
        $this->_stop_nut_stack($devices);
        $this->_prepare_runtime_directory();
        $this->_start_nut_drivers($devices);
        $this->_wait_for_driver_sockets($devices);
        $this->_start_nut_server();
        $this->_wait_for_upsc_devices($devices);
        $this->_start_nut_monitor();
    }

    protected function _disable_and_stop_services($devices = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_stop_nut_stack($devices);
    }

    protected function _prepare_runtime_directory()
    {
        clearos_profile(__METHOD__, __LINE__);

        $commands = array(
            array('/bin/mkdir', '-p /run/nut /var/run/nut'),
            array('/bin/chown', 'nut:nut /run/nut /var/run/nut'),
            array('/bin/chmod', '0770 /run/nut /var/run/nut'),
        );

        foreach ($commands as $command) {
            $result = $this->_run_shell($command[0], $command[1], TRUE);
            if ($result['exit_code'] !== 0)
                throw new Engine_Exception(lang('base_directory_create_failed') . ': /run/nut' . "\n" . $this->_format_command_error($result));
        }
    }

    protected function _enable_nut_services($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Best effort.  Do not block configuration apply on enablement.
        $this->_run_shell('/usr/bin/systemctl', 'daemon-reload', TRUE);
        $this->_run_shell('/usr/bin/systemctl', 'reset-failed nut-driver.target nut-server.service nut-monitor.service', TRUE);

        foreach ($this->_normalize_devices($devices) as $device) {
            $unit = $this->_get_driver_unit($device['UPS_NAME']);
            $this->_run_shell('/usr/bin/systemctl', 'reset-failed ' . $unit, TRUE);
            $this->_run_shell('/usr/bin/systemctl', 'enable ' . $unit, TRUE);
        }

        $this->_run_shell('/usr/bin/systemctl', 'enable nut-driver.target', TRUE);
        $this->_run_shell('/usr/bin/systemctl', 'enable nut-server.service', TRUE);
        $this->_run_shell('/usr/bin/systemctl', 'enable nut-monitor.service', TRUE);
    }

    protected function _stop_nut_stack($devices = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($devices === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $devices = $this->settings['DEVICES'];
        }

        // Best effort stop before applying the verified start order.
        // Stop monitor/server first so they do not keep using stale driver state.
        $this->_run_shell('/usr/bin/systemctl', 'stop nut-monitor.service', TRUE);
        $this->_run_shell('/usr/bin/systemctl', 'stop nut-server.service', TRUE);
        $this->_run_shell('/sbin/service', 'nut-monitor stop', TRUE);
        $this->_run_shell('/sbin/service', 'nut-server stop', TRUE);

        foreach ($this->_normalize_devices($devices) as $device)
            $this->_run_shell('/usr/bin/systemctl', 'stop ' . $this->_get_driver_unit($device['UPS_NAME']), TRUE);

        // The target has PartOf= relationships with driver instances.  Stopping it
        // is harmless when inactive and helps clean up any enabled/running driver
        // instance that belongs to the NUT target.
        $this->_run_shell('/usr/bin/systemctl', 'stop nut-driver.target', TRUE);
    }

    protected function _start_nut_drivers($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        foreach ($this->_normalize_devices($devices) as $device) {
            $unit = $this->_get_driver_unit($device['UPS_NAME']);
            $result = $this->_run_shell('/usr/bin/systemctl', 'start ' . $unit, TRUE);

            if ($result['exit_code'] !== 0) {
                $status = $this->_run_shell('/usr/bin/systemctl', 'status ' . $unit . ' --no-pager -l', TRUE);
                throw new Engine_Exception(lang('nut_driver_start_failed') . ': ' . $unit . "\n" . $this->_format_command_error($status));
            }
        }
    }

    protected function _normalize_devices($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $normalized = array();

        if (! is_array($devices))
            return $normalized;

        foreach ($devices as $device) {
            $device = $this->_normalize_device($device);
            if ($device['UPS_NAME'] === '')
                continue;
            $normalized[] = $device;
        }

        return $normalized;
    }

    protected function _get_driver_unit($ups_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        // UPS names are validated with /^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.
        // Keep this method strict because the value is passed as a systemctl unit.
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $ups_name))
            throw new Engine_Exception(lang('nut_ups_name_invalid') . ': ' . $ups_name);

        return 'nut-driver@' . $ups_name . '.service';
    }

    protected function _wait_for_driver_sockets($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $missing = array();

        foreach ($devices as $device) {
            $device = $this->_normalize_device($device);
            $socket1 = '/run/nut/' . $device['DRIVER'] . '-' . $device['UPS_NAME'];
            $socket2 = '/var/run/nut/' . $device['DRIVER'] . '-' . $device['UPS_NAME'];
            $found = FALSE;

            for ($i = 0; $i < 10; $i++) {
                if ($this->_path_exists_superuser($socket1) || $this->_path_exists_superuser($socket2)) {
                    $found = TRUE;
                    break;
                }
                sleep(1);
            }

            if (! $found)
                $missing[] = $socket1;
        }

        if (count($missing) > 0)
            throw new Engine_Exception(lang('nut_driver_socket_missing') . ': ' . implode(', ', $missing));
    }

    protected function _start_nut_server()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_run_shell('/usr/bin/systemctl', 'start nut-server.service', TRUE);

        if (! $this->_is_upsc_port_listening())
            $this->_run_shell('/sbin/service', 'nut-server start', TRUE);

        for ($i = 0; $i < 10; $i++) {
            if ($this->_is_upsc_port_listening())
                return;
            sleep(1);
        }

        $status = $this->_run_shell('/usr/bin/systemctl', 'status nut-server.service --no-pager -l', TRUE);
        throw new Engine_Exception(lang('nut_server_start_failed') . ': ' . $this->_format_command_error($status));
    }

    protected function _wait_for_upsc_devices($devices)
    {
        clearos_profile(__METHOD__, __LINE__);

        $command = $this->_get_upsc_command();
        if ($command === NULL)
            throw new Engine_Exception(lang('nut_upsc_not_found'));

        $failed = array();

        foreach ($devices as $device) {
            $target = escapeshellarg($this->_get_upsc_target($device['UPS_NAME']));
            $ok = FALSE;
            $last_output = array();

            for ($i = 0; $i < 20; $i++) {
                $output = array();
                $exit_code = 1;
                exec($command . ' ' . $target . ' 2>&1', $output, $exit_code);
                $last_output = $output;

                if ($exit_code === 0) {
                    $ok = TRUE;
                    break;
                }

                usleep(250000);
            }

            if (! $ok)
                $failed[] = $device['UPS_NAME'] . ': ' . implode(' ', $last_output);
        }

        if (count($failed) > 0)
            throw new Engine_Exception(lang('nut_upsc_check_failed') . ': ' . implode('; ', $failed));
    }

    protected function _start_nut_monitor()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Start upsmon only after upsd/upsc is confirmed.  Do not fail the whole
        // apply action if nut-monitor has a local systemd wrapper problem.
        $this->_run_shell('/usr/bin/systemctl', 'start nut-monitor.service', TRUE);

        if (! $this->_is_process_running('upsmon'))
            $this->_run_shell('/sbin/service', 'nut-monitor start', TRUE);
    }

    protected function _path_exists_superuser($path)
    {
        clearos_profile(__METHOD__, __LINE__);

        $result = $this->_run_shell('/bin/ls', escapeshellarg($path), TRUE);
        return ($result['exit_code'] === 0);
    }

    protected function _get_upsc_host($settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        $listener = $this->_get_primary_upsd_listener($settings);
        $address = isset($listener['ADDRESS']) ? trim($listener['ADDRESS']) : '127.0.0.1';

        // 0.0.0.0 is a bind address, not a useful client target.
        if ($address === '0.0.0.0')
            return 'localhost';

        return $address;
    }

    protected function _get_upsc_target($ups_name, $settings = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($settings === NULL) {
            if (! $this->is_loaded)
                $this->_load_settings();
            $settings = $this->settings;
        }

        $host = $this->_get_upsc_host($settings);
        $listener = $this->_get_primary_upsd_listener($settings);
        $port = isset($listener['PORT']) ? $listener['PORT'] : '3493';

        if ($port === '3493')
            return $ups_name . '@' . $host;

        return $ups_name . '@' . $host . ':' . $port;
    }

    protected function _is_upsc_port_listening()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        $listeners = $this->_get_upsd_listeners($this->settings);

        foreach ($listeners as $listener) {
            $port = isset($listener['PORT']) ? $listener['PORT'] : '3493';
            if ($this->validate_upsd_listen_port($port) !== NULL)
                continue;

            $needle = escapeshellarg(':' . $port);
            $output = array();
            $exit_code = 1;
            exec('/usr/sbin/ss -lnt 2>/dev/null | /bin/grep -q ' . $needle, $output, $exit_code);
            if ($exit_code === 0)
                return TRUE;

            exec('/usr/bin/ss -lnt 2>/dev/null | /bin/grep -q ' . $needle, $output, $exit_code);
            if ($exit_code === 0)
                return TRUE;
        }

        return FALSE;
    }

    protected function _is_process_running($process)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $process))
            return FALSE;

        $output = array();
        $exit_code = 1;
        exec('/usr/bin/pgrep -x ' . escapeshellarg($process) . ' >/dev/null 2>&1', $output, $exit_code);
        if ($exit_code === 0)
            return TRUE;

        exec('/bin/pgrep -x ' . escapeshellarg($process) . ' >/dev/null 2>&1', $output, $exit_code);
        return ($exit_code === 0);
    }

    protected function _is_systemd_active($unit)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^[A-Za-z0-9@_.\-]+\.service$/', $unit) && $unit !== 'nut-driver.target')
            return FALSE;

        $output = array();
        $exit_code = 1;
        exec('/usr/bin/systemctl is-active --quiet ' . escapeshellarg($unit) . ' 2>/dev/null', $output, $exit_code);
        if ($exit_code === 0)
            return TRUE;

        exec('/bin/systemctl is-active --quiet ' . escapeshellarg($unit) . ' 2>/dev/null', $output, $exit_code);
        return ($exit_code === 0);
    }

    protected function _is_systemd_enabled($unit)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^[A-Za-z0-9@_.\-]+\.service$/', $unit) && $unit !== 'nut-driver.target')
            return FALSE;

        $output = array();
        $exit_code = 1;
        exec('/usr/bin/systemctl is-enabled --quiet ' . escapeshellarg($unit) . ' 2>/dev/null', $output, $exit_code);
        if ($exit_code === 0)
            return TRUE;

        exec('/bin/systemctl is-enabled --quiet ' . escapeshellarg($unit) . ' 2>/dev/null', $output, $exit_code);
        return ($exit_code === 0);
    }

    protected function _format_service_status_message($status)
    {
        clearos_profile(__METHOD__, __LINE__);

        switch ($status) {
            case 'running':
                return lang('base_running');
            case 'stopped':
                return lang('base_stopped');
            case 'dead':
                return lang('base_dead');
            case 'no_entries':
                return lang('base_no_entries');
            default:
                return $status;
        }
    }

    protected function _run_shell($command, $args = '', $ignore_errors = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! file_exists($command)) {
            return array(
                'exit_code' => 127,
                'output' => array($command . ' not found'),
            );
        }

        $shell = new Shell();
        $options = array('validate_exit_code' => FALSE);
        $exit_code = $shell->execute($command, $args, TRUE, $options);
        $output = $shell->get_output();

        if (! is_array($output))
            $output = array($output);

        if (! $ignore_errors && $exit_code !== 0)
            throw new Engine_Exception($this->_format_command_error(array('exit_code' => $exit_code, 'output' => $output)));

        return array(
            'exit_code' => $exit_code,
            'output' => $output,
        );
    }

    protected function _format_command_error($result)
    {
        clearos_profile(__METHOD__, __LINE__);

        $output = isset($result['output']) && is_array($result['output']) ? $result['output'] : array();
        $text = trim(implode("\n", $output));

        if ($text === '')
            $text = 'exit=' . (isset($result['exit_code']) ? $result['exit_code'] : 'unknown');

        return $text;
    }

    protected function _get_upsc_command()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (is_executable(self::COMMAND_UPSC))
            return self::COMMAND_UPSC;
        if (is_executable(self::COMMAND_UPSC_ALT))
            return self::COMMAND_UPSC_ALT;

        return NULL;
    }

    protected function _generate_password()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(18);
            if ($bytes !== FALSE)
                return substr(strtr(base64_encode($bytes), '+/', 'Aa'), 0, 24);
        }

        return substr(md5(uniqid('', TRUE) . mt_rand()), 0, 24);
    }

    protected function _get_unmanaged_listen_lines()
    {
        clearos_profile(__METHOD__, __LINE__);

        $contents = $this->_read_full_file(self::FILE_UPSD_CONF);
        if ($contents === '')
            return array();

        $lines = explode("\n", rtrim($contents));

        $inside_managed = FALSE;
        $unmanaged = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '# BEGIN app-nut local-listen' || $trimmed === '# BEGIN app-nut upsd-listen') {
                $inside_managed = TRUE;
                continue;
            }

            if ($trimmed === '# END app-nut local-listen' || $trimmed === '# END app-nut upsd-listen') {
                $inside_managed = FALSE;
                continue;
            }

            if ($inside_managed)
                continue;

            if (preg_match('/^LISTEN\s+/i', $trimmed))
                $unmanaged[] = $trimmed;
        }

        return $unmanaged;
    }
}
