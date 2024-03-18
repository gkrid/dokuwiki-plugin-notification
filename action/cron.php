<?php

/**
 * DokuWiki Plugin notification (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <it@rid.pl>
 */

class action_plugin_notification_cron extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'handle_indexer_tasks_run');
    }

    /**
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_indexer_tasks_run(Doku_Event $event, $param)
    {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        /** @var \helper_plugin_notification_db $db_helper */
        $db_helper = plugin_load('helper', 'notification_db');
        $sqlite = $db_helper->getDB();

        // insert new users first
        /** @var \helper_plugin_notification_cron $cron_helper */
        $cron_helper = plugin_load('helper', 'notification_cron');
        $cron_helper->addUsersToCron();

        //get the oldest check
        $res = $sqlite->query('SELECT user, MIN(timestamp) FROM cron_check');
        $user = $sqlite->res2single($res);
        //no user to send notifications to
        if (!$user) return;

        //update user last check
        $sqlite->query('UPDATE cron_check SET timestamp=? WHERE user=?',  date('c'), $user);

        // get new notifications from plugins
        $notification_data = $cron_helper->getNotificationData($user);

        //no notifications - nothing to send
        if (empty($notification_data['notifications'])) return;

        $new_notifications = $cron_helper->getNewNotifications($user, $notification_data);

        // no notifications left - nothing to send
        if (!$new_notifications) return;

        list($text, $html) = $cron_helper->composeEmail($new_notifications);
        if ($cron_helper->sendMail($user, $text, $html)) {
            //mark notifications as sent
            $cron_helper->storeSentNotifications($user, $new_notifications);
        }

        $event->stopPropagation();
        $event->preventDefault();
    }
}
