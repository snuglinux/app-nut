<?php

/**
 * NUT settings controller.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Settings extends ClearOS_Controller
{
    /**
     * Default view.
     *
     * @return redirect
     */

    function index()
    {
        redirect('/nut');
    }

    /**
     * Edit UPS device settings.
     *
     * @param string $ups_name UPS name
     *
     * @return view
     */

    function edit($ups_name = NULL)
    {
        $this->_view_edit($ups_name);
    }

    /**
     * Apply NUT config.
     *
     * @return redirect
     */

    function apply()
    {
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $this->nut->apply_configuration();
            $this->page->set_status_updated();
            redirect('/nut');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Updates /etc/ups/nut.conf MODE.
     *
     * @return redirect
     */

    function mode($form_type = 'view')
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        if ($form_type !== 'edit')
            $form_type = 'view';

        $this->form_validation->set_policy('MODE', 'nut/NUT', 'validate_mode', TRUE);

        $form_ok = $this->form_validation->run();

        if ($this->input->post('submit') && $form_ok) {
            try {
                $listen_addresses = $this->input->post('UPSD_LISTEN_ADDRESS');
                $listen_ports = $this->input->post('UPSD_LISTEN_PORT');

                if (! is_array($listen_addresses))
                    $listen_addresses = array($listen_addresses);
                if (! is_array($listen_ports))
                    $listen_ports = array($listen_ports);

                $listeners = array();
                $count = max(count($listen_addresses), count($listen_ports));
                for ($i = 0; $i < $count; $i++) {
                    $listeners[] = array(
                        'ADDRESS' => isset($listen_addresses[$i]) ? $listen_addresses[$i] : '',
                        'PORT' => isset($listen_ports[$i]) ? $listen_ports[$i] : '',
                    );
                }

                $upsd_usernames = $this->input->post('UPSD_USER_USERNAME');
                $upsd_passwords = $this->input->post('UPSD_USER_PASSWORD');
                $upsd_roles = $this->input->post('UPSD_USER_ROLE');

                if (! is_array($upsd_usernames))
                    $upsd_usernames = array($upsd_usernames);
                if (! is_array($upsd_passwords))
                    $upsd_passwords = array($upsd_passwords);
                if (! is_array($upsd_roles))
                    $upsd_roles = array($upsd_roles);

                $upsd_users = array();
                $user_count = max(count($upsd_usernames), count($upsd_passwords), count($upsd_roles));
                for ($i = 0; $i < $user_count; $i++) {
                    $upsd_users[] = array(
                        'USERNAME' => isset($upsd_usernames[$i]) ? $upsd_usernames[$i] : '',
                        'PASSWORD' => isset($upsd_passwords[$i]) ? $upsd_passwords[$i] : '',
                        'ROLE' => isset($upsd_roles[$i]) ? $upsd_roles[$i] : '',
                    );
                }

                $event_log_events = $this->input->post('EVENT_LOG_EVENTS');
                if (! is_array($event_log_events))
                    $event_log_events = array();

                $this->nut->set_general_settings(array(
                    'MODE' => $this->input->post('MODE'),
                    'UPSD_LISTENERS' => $listeners,
                    'ALLOW_NO_DEVICE' => $this->input->post('ALLOW_NO_DEVICE') ? '1' : '0',
                    'ALLOW_NOT_ALL_LISTENERS' => $this->input->post('ALLOW_NOT_ALL_LISTENERS') ? '1' : '0',
                    'DEBUG_MIN' => $this->input->post('DEBUG_MIN'),
                    'UPSD_USERS' => $upsd_users,
                    'UPSMON_SETTINGS' => array(
                        'MINSUPPLIES' => $this->input->post('UPSMON_MINSUPPLIES'),
                        'SHUTDOWNCMD' => $this->input->post('UPSMON_SHUTDOWNCMD'),
                        'POLLFREQ' => $this->input->post('UPSMON_POLLFREQ'),
                        'POLLFREQALERT' => $this->input->post('UPSMON_POLLFREQALERT'),
                        'HOSTSYNC' => $this->input->post('UPSMON_HOSTSYNC'),
                        'DEADTIME' => $this->input->post('UPSMON_DEADTIME'),
                        'POWERDOWNFLAG' => $this->input->post('UPSMON_POWERDOWNFLAG'),
                        'FINALDELAY' => $this->input->post('UPSMON_FINALDELAY'),
                    ),
                    'EVENT_LOG_SETTINGS' => array(
                        'ENABLED' => $this->input->post('EVENT_LOG_ENABLED') ? '1' : '0',
                        'LOG_FILE' => $this->input->post('EVENT_LOG_FILE'),
                        'RETENTION_DAYS' => $this->input->post('EVENT_LOG_RETENTION_DAYS'),
                        'MAX_SIZE_MB' => $this->input->post('EVENT_LOG_MAX_SIZE_MB'),
                        'SYSLOG_ENABLED' => $this->input->post('EVENT_LOG_SYSLOG_ENABLED') ? '1' : '0',
                        'EVENTS' => $event_log_events,
                    ),
                ));
                $this->page->set_status_updated();
                redirect('/nut');
                return;
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        try {
            $data['form_type'] = $form_type;
            $data['settings'] = $this->nut->get_settings();
            $data['mode_options'] = $this->nut->get_mode_options();
            $data['listen_address_options'] = $this->nut->get_upsd_listen_address_options();
            $data['debug_min_options'] = $this->nut->get_debug_min_options();
            $data['upsd_user_role_options'] = $this->nut->get_upsd_user_role_options();
            $data['event_log_event_options'] = $this->nut->get_event_log_event_options();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $options['javascript'] = array(
            clearos_app_htdocs('nut') . '/nut.js.php'
        );

        $this->page->view_form('nut/mode', $data, lang('base_settings'), $options);
    }

    /**
     * Confirm delete.
     *
     * @param string $ups_name UPS name
     *
     * @return view
     */

    function delete($ups_name)
    {
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $device = $this->nut->get_configured_device($ups_name);
            $confirm_uri = '/app/nut/settings/destroy/' . $ups_name;
            $cancel_uri = '/app/nut';
            $items = array(
                lang('nut_ups_name') . ': ' . $device['UPS_NAME'] . '<br></li><li>' .
                lang('nut_usb_id') . ': ' . $device['VENDORID'] . ':' . $device['PRODUCTID']
            );

            $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Delete UPS device.
     *
     * @param string $ups_name UPS name
     *
     * @return redirect
     */

    function destroy($ups_name)
    {
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $was_enabled = ($this->nut->get_setting('ENABLED') === '1');
            $remaining = $this->nut->delete_device($ups_name);

            // Keep /etc/ups/*.conf consistent with the visible list.
            if ($was_enabled) {
                if ($remaining > 0)
                    $this->nut->apply_configuration();
                else
                    $this->nut->disable_configuration();
            }

            $this->page->set_status_deleted();
            redirect('/nut');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Common edit form.
     *
     * @param string $ups_name UPS name
     *
     * @return view
     */

    function _view_edit($ups_name)
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        $this->form_validation->set_policy('UPS_NAME', 'nut/NUT', 'validate_ups_name', TRUE);
        $this->form_validation->set_policy('DRIVER', 'nut/NUT', 'validate_driver', TRUE);
        $this->form_validation->set_policy('PORT', 'nut/NUT', 'validate_port', TRUE);
        $this->form_validation->set_policy('VENDORID', 'nut/NUT', 'validate_usb_id', FALSE);
        $this->form_validation->set_policy('PRODUCTID', 'nut/NUT', 'validate_usb_id', FALSE);
        $this->form_validation->set_policy('SERIAL', 'nut/NUT', 'validate_optional_token', FALSE);
        $this->form_validation->set_policy('DESC', 'nut/NUT', 'validate_description', TRUE);

        $form_ok = $this->form_validation->run();

        if (($this->input->post('submit') || $this->input->post('save_and_apply')) && $form_ok) {
            try {
                $original_name = $this->input->post('ORIGINAL_UPS_NAME');
                $device = array(
                    'UPS_NAME' => $this->input->post('UPS_NAME'),
                    'DRIVER' => $this->input->post('DRIVER'),
                    'PORT' => $this->input->post('PORT'),
                    'VENDORID' => $this->input->post('VENDORID'),
                    'PRODUCTID' => $this->input->post('PRODUCTID'),
                    'SERIAL' => $this->input->post('SERIAL'),
                    'DESC' => $this->input->post('DESC'),
                );

                $this->nut->update_device($original_name, $device);
                $this->nut->apply_configuration();

                $this->page->set_status_updated();
                redirect('/nut');
                return;
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        try {
            $devices = $this->nut->get_configured_devices();
            if ($ups_name === NULL && count($devices) > 0)
                $ups_name = $devices[0]['UPS_NAME'];

            if ($ups_name === NULL) {
                redirect('/nut/devices');
                return;
            }

            $data['settings'] = $this->nut->get_settings();
            $data['device'] = $this->nut->get_configured_device($ups_name);
            $data['mode_options'] = $this->nut->get_mode_options();
            $data['driver_options'] = $this->nut->get_driver_options();
            $data['is_new'] = FALSE;
            $data['form_action'] = 'nut/settings/edit/' . $data['device']['UPS_NAME'];
            $data['preview'] = $this->nut->get_ups_conf_preview($data['device']);
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $this->page->view_form('nut/settings', $data, lang('nut_edit_ups'));
    }
}
