<?php

/**
 * NUT monitor daemon controller.
 *
 * @category   apps
 * @package    nut
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

require clearos_app_base('base') . '/controllers/daemon.php';

class Monitor extends Daemon
{
    function __construct()
    {
        parent::__construct('nut-monitor', 'nut');
    }
}
