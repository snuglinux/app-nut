<?php

/**
 * NUT diagnostics controller.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Diagnostics extends ClearOS_Controller
{
    /**
     * Read-only diagnostics page.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $data['diagnostics'] = $this->nut->get_diagnostics();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $this->page->view_form('nut/diagnostics', $data, lang('nut_diagnostics'));
    }
}
