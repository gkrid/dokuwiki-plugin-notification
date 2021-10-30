<?php
/**
 * DokuWiki Plugin watchcycle (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

class helper_plugin_notification_cron extends DokuWiki_Plugin
{
    /** @var helper_plugin_sqlite */
    protected $sqlite;

    public function __construct()
    {
        /** @var \helper_plugin_notification_db $db_helper */
        $db_helper = plugin_load('helper', 'notification_db');
        $this->sqlite = $db_helper->getDB();
    }

    public function addUsersToCron()
    {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $res = $this->sqlite->query('SELECT user from cron_check');
        $ourUsers = $this->sqlite->res2arr($res);

        $ourUsers = array_map(function ($item) {
            return $item['user'];
        }, $ourUsers);

        $allUsers = array_keys($auth->retrieveUsers());

        $newUsers = array_diff($allUsers, $ourUsers);

        if (!is_array($newUsers) || empty($newUsers)) return;

        foreach ($newUsers as $user) {
            $this->sqlite->storeEntry('cron_check',
                ['user' => $user, 'timestamp' => date('c', 0)]);
        }
    }
}
