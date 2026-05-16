<?php

/**
 * NUT event log controller.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Event_Log extends ClearOS_Controller
{
    /**
     * Event log page.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $data['entries'] = $this->nut->get_event_log_entries(200);
            $data['settings'] = $this->nut->get_settings();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $this->page->view_form('nut/events', $data, lang('nut_event_log'));
    }
}
