<?php

/**
 * NUT USB devices controller.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Devices extends ClearOS_Controller
{
    /**
     * USB devices list for adding a new UPS.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $data['devices'] = $this->nut->get_usb_devices();
            $data['configured_devices'] = $this->nut->get_configured_devices();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $this->page->view_form('nut/devices', $data, lang('nut_add_ups'));
    }

    /**
     * Opens add dialog with suggested settings for a detected USB device.
     *
     * @param string $vendorid  USB vendor ID
     * @param string $productid USB product ID
     * @param string $bus       USB bus number
     * @param string $devnum    USB device number
     *
     * @return view
     */

    function add($vendorid = NULL, $productid = NULL, $bus = '', $devnum = '')
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        // Keep this method tolerant: older views used URI segments with
        // bus/device numbers, while newer views use only vendor/product IDs.
        // Also accept GET/POST fields for safer future form-based buttons.
        if ($vendorid === NULL)
            $vendorid = $this->input->post('vendorid') ? $this->input->post('vendorid') : $this->input->get('vendorid');
        if ($productid === NULL)
            $productid = $this->input->post('productid') ? $this->input->post('productid') : $this->input->get('productid');
        if ($bus === '')
            $bus = $this->input->post('bus') ? $this->input->post('bus') : $this->input->get('bus');
        if ($devnum === '')
            $devnum = $this->input->post('device') ? $this->input->post('device') : $this->input->get('device');

        try {
            if ($vendorid === NULL || $productid === NULL || $vendorid === '' || $productid === '')
                throw new Exception(lang('nut_usb_id_invalid'));

            $device = $this->nut->get_usb_device_suggestion($vendorid, $productid, $bus, $devnum);

            $data['settings'] = $this->nut->get_settings();
            $data['device'] = $device;
            $data['driver_options'] = $this->nut->get_driver_options();
            $data['is_new'] = TRUE;
            $data['form_action'] = 'nut/devices/create';
            $data['preview'] = $this->nut->get_ups_conf_preview($device);

            $this->page->view_form('nut/settings', $data, lang('nut_add_ups'));
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Creates a UPS device from the add dialog, then applies NUT config.
     *
     * @return view/redirect
     */

    function create()
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        $this->_set_validation_rules();

        $device = array(
            'UPS_NAME' => $this->input->post('UPS_NAME'),
            'DRIVER' => $this->input->post('DRIVER'),
            'PORT' => $this->input->post('PORT'),
            'VENDORID' => $this->input->post('VENDORID'),
            'PRODUCTID' => $this->input->post('PRODUCTID'),
            'SERIAL' => $this->input->post('SERIAL'),
            'DESC' => $this->input->post('DESC'),
        );

        if ($this->input->post('submit') && $this->form_validation->run()) {
            try {
                $this->nut->create_device($device);
                $this->nut->apply_configuration();
                $this->page->set_status_added();
                redirect('/nut');
                return;
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        $preview = '';
        try {
            $preview = $this->nut->get_ups_conf_preview($device);
        } catch (Exception $e) {
            // Keep the validation errors in the form instead of replacing them
            // with a generic exception page.
            $preview = '';
        }

        $data['settings'] = $this->nut->get_settings();
        $data['device'] = $device;
        $data['driver_options'] = $this->nut->get_driver_options();
        $data['is_new'] = TRUE;
        $data['form_action'] = 'nut/devices/create';
        $data['preview'] = $preview;

        $this->page->view_form('nut/settings', $data, lang('nut_add_ups'));
    }

    /**
     * Backward-compatible alias.
     */

    function select($vendorid, $productid)
    {
        $this->add($vendorid, $productid);
    }

    /**
     * Backward-compatible alias: add, apply, then return summary.
     */

    function configure($vendorid, $productid)
    {
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $this->nut->add_usb_device($vendorid, $productid);
            $this->nut->apply_configuration();
            $this->page->set_status_updated();
            redirect('/nut');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Sets validation rules shared by the add dialog.
     *
     * @return void
     */

    function _set_validation_rules()
    {
        $this->form_validation->set_policy('UPS_NAME', 'nut/NUT', 'validate_ups_name', TRUE);
        $this->form_validation->set_policy('DRIVER', 'nut/NUT', 'validate_driver', TRUE);
        $this->form_validation->set_policy('PORT', 'nut/NUT', 'validate_port', TRUE);
        $this->form_validation->set_policy('VENDORID', 'nut/NUT', 'validate_usb_id', FALSE);
        $this->form_validation->set_policy('PRODUCTID', 'nut/NUT', 'validate_usb_id', FALSE);
        $this->form_validation->set_policy('SERIAL', 'nut/NUT', 'validate_optional_token', FALSE);
        $this->form_validation->set_policy('DESC', 'nut/NUT', 'validate_description', TRUE);
    }
}
