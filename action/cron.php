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
        //no user to sent notifications
        if (!$user) return;

        //update user last check
        $sqlite->query('UPDATE cron_check SET timestamp=? WHERE user=?',  date('c'), $user);

        $plugins = [];
        trigger_event('PLUGIN_NOTIFICATION_REGISTER_SOURCE', $plugins);
        $notifications_data = [
            'plugins' => $plugins,
            'user' => $user,
            'notifications' => []
        ];
        trigger_event('PLUGIN_NOTIFICATION_GATHER', $notifications_data);

        $notifications = $notifications_data['notifications'];
        //no notifications - nothing to sent
        if (!$notifications) return;

        //get only notifications that has id
        $notifications = array_filter($notifications, function ($notification) {
            return array_key_exists('id', $notification);
        });
        //no notifications - nothing to sent
        if (!$notifications) return;

        //get the notifications that has been sent already
        $res = $sqlite->query('SELECT plugin, notification_id FROM notification WHERE user=?', $user);
        $sent_notifications = $sqlite->res2arr($res);
        $sent_notifications_by_plugin = [];
        foreach ($plugins as $plugin) {
            $sent_notifications_by_plugin[$plugin] = [];
        }
        foreach ($sent_notifications as $sent_notification) {
            $plugin = $sent_notification['plugin'];
            $id = $sent_notification['notification_id'];
            $sent_notifications_by_plugin[$plugin][$id] = true;
        }

        $new_notifications = [];
        foreach ($notifications as $notification) {
            $plugin = $notification['plugin'];
            $id = $notification['id'];
            if (!isset($sent_notifications_by_plugin[$plugin][$id])) {
                $new_notifications[] = $notification;
            }
        }

        //no notifications - nothing to sent
        if (!$new_notifications) return;

        $html = '<p>' . $this->getLang('mail content');
        $html .= '<ul>';
        $text = $this->getLang('mail content') . "\n\n";

        usort($new_notifications, function($a, $b) {
            if ($a['timestamp'] == $b['timestamp']) {
                return 0;
            }
            return ($a['timestamp'] > $b['timestamp']) ? -1 : 1;
        });

        foreach ($new_notifications as $notification) {
            $content = $notification['full'];
            $timestamp = $notification['timestamp'];

            $date = strftime('%d.%m %H:%M', $timestamp);

            $html .= "<li class=\"level1\"><div class=\"li\">$date $content</div></li>";
            $text .= $date . ' ' . strip_tags($content). "\n";
        }
        $html .= '</ul></p>';

        $mail = new Mailer();
        $userinfo = $auth->getUserData($user, $requireGroups = false);
        $mail->to($userinfo['name'].' <'.$userinfo['mail'].'>');
        $mail->subject($this->getLang('mail subject'));
        $mail->setBody($text, null, null, $html);
        $mail->send();

        //mark notifications as sent
        foreach ($new_notifications as $notification) {
            $plugin = $notification['plugin'];
            $id = $notification['id'];
            $sqlite->storeEntry('notification',
                ['plugin' => $plugin, 'notification_id' => $id, 'user' => $user, 'sent' => date('c')]);
        }

        $event->stopPropagation();
        $event->preventDefault();
    }
}
