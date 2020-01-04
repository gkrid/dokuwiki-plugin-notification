<?php
/**
 * DokuWiki Plugin bez (Action Component)
 *
 */

// must be run within Dokuwiki

if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_bez_migration
 *
 * Handle migrations that need more than just SQL
 */
class action_plugin_notification_migration extends DokuWiki_Action_Plugin
{
    /**
     * @inheritDoc
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PLUGIN_SQLITE_DATABASE_UPGRADE', 'AFTER', $this, 'handle_migrations');
    }

    /**
     * Call our custom migrations when defined
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_migrations(Doku_Event $event, $param)
    {
        if ($event->data['sqlite']->getAdapter()->getDbname() !== 'notification') {
            return;
        }
        $to = $event->data['to'];

        if (is_callable([$this, "migration$to"])) {
            $event->result = call_user_func([$this, "migration$to"], $event->data);
        }
    }

    protected function migration1($data)
    {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        /** @var \helper_plugin_notification_db $db_helper */
        $db_helper = plugin_load('helper', 'notification_db');
        $sqlite = $db_helper->getDB();

        foreach (array_keys($auth->retrieveUsers()) as $user) {
            $sqlite->storeEntry('cron_check',
                ['user' => $user, 'timestamp' => date('c', 0)]);
        }
    }
}
