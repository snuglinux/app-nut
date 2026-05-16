<?php

/**
 * NUT aggregate daemon controller.
 *
 * This controller follows the ClearOS daemon/sidebar pattern used by apps like
 * ssh_server, but it manages the whole NUT stack instead of a single daemon:
 *
 *   nut-driver@UPS.service -> nut-server.service -> nut-monitor.service
 *
 * The standard ClearOS base/daemon.js.php calls:
 *   /app/nut/server/status/<name>
 *   /app/nut/server/start/<name>
 *   /app/nut/server/stop/<name>
 *
 * The <name> is intentionally ignored here; it is only present for compatibility
 * with the standard ClearOS JavaScript helper.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Server extends ClearOS_Controller
{
    /**
     * Hidden fields for ClearOS daemon sidebar integration.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');

        $data['daemon_name'] = 'nut-stack';
        $data['app_name'] = 'nut';

        $options['javascript'] = array(clearos_app_htdocs('base') . '/daemon.js.php');

        $this->page->view_form('base/daemon', $data, lang('base_server_status'), $options);
    }

    /**
     * Aggregate NUT stack status.
     *
     * @param string $daemon_name ignored, ClearOS daemon.js compatibility only
     *
     * @return JSON
     */

    function status($daemon_name = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            $status = $this->nut->get_service_status_summary();
            echo json_encode(array('status' => $status['status']));
        } catch (Exception $e) {
            echo json_encode(array('status' => 'dead'));
        }
    }

    /**
     * Start/enable the aggregate NUT stack.
     *
     * @param string $daemon_name ignored, ClearOS daemon.js compatibility only
     *
     * @return JSON
     */

    function start($daemon_name = NULL)
    {
        $this->_run_action(TRUE);
    }

    /**
     * Stop/disable the aggregate NUT stack.
     *
     * @param string $daemon_name ignored, ClearOS daemon.js compatibility only
     *
     * @return JSON
     */

    function stop($daemon_name = NULL)
    {
        $this->_run_action(FALSE);
    }

    /**
     * Run start/stop action.
     *
     * @param boolean $start start service stack
     *
     * @return JSON
     */

    protected function _run_action($start)
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        $this->lang->load('base');
        $this->lang->load('nut');
        $this->load->library('nut/NUT');

        try {
            if ($start)
                $this->nut->start_service_stack();
            else
                $this->nut->stop_service_stack();

            echo json_encode('ok');
        } catch (Exception $e) {
            echo json_encode('error');
        }
    }
}
