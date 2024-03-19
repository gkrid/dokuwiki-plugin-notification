<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 * Class action_plugin_notification_migration
 *
 * Handle migrations that need more than just SQL
 */
class action_plugin_notification_migration extends ActionPlugin
{
    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_SQLITE_DATABASE_UPGRADE', 'AFTER', $this, 'handleMigrations');
    }

    /**
     * Call our custom migrations when defined
     *
     * @param Event $event
     * @param $param
     */
    public function handleMigrations(Event $event, $param)
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
            $sqlite->storeEntry(
                'cron_check',
                ['user' => $user, 'timestamp' => date('c', 0)]
            );
        }
    }
}
