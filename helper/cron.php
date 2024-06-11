<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Extension\Event;

/**
 * DokuWiki Plugin notification (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */
class helper_plugin_notification_cron extends Plugin
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

        if (!is_array($newUsers) || $newUsers === []) return;

        foreach ($newUsers as $user) {
            $this->sqlite->storeEntry(
                'cron_check',
                ['user' => $user, 'timestamp' => date('c', 0)]
            );
        }
    }

    /**
     * Gather notification data from plugins
     *
     * @param string $user
     * @return array
     */
    public function getNotificationData($user)
    {
        $plugins = [];
        $event = new Event('PLUGIN_NOTIFICATION_REGISTER_SOURCE', $plugins);
        $event->trigger();

        $notifications_data = [
            'plugins' => $plugins,
            'user' => $user,
            'notifications' => []
        ];
        $event = new Event('PLUGIN_NOTIFICATION_GATHER', $notifications_data);
        $event->trigger();

        if (!empty($notifications_data['notifications'])) {
            $notifications = $notifications_data['notifications'];

            // get only notifications that have ids
            $notifications_data['notifications'] = array_filter($notifications, function ($notification) {
                return array_key_exists('id', $notification);
            });
        }

        return $notifications_data;
    }

    /**
     * Prune old (already sent) notifications and return only new ones
     *
     * @param string $user
     * @param array $notification_data
     * @return array
     */
    public function getNewNotifications($user, $notification_data)
    {
        /** @var \helper_plugin_notification_db $db_helper */
        $db_helper = plugin_load('helper', 'notification_db');
        $sqlite = $db_helper->getDB();

        $notifications = $notification_data['notifications'];
        $plugins = $notification_data['plugins'];

        //get the notifications that have been sent already
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

        // keep only notifications not yet sent
        $new_notifications = [];
        foreach ($notifications as $notification) {
            $plugin = $notification['plugin'];
            $id = $notification['id'];
            if (!isset($sent_notifications_by_plugin[$plugin][$id])) {
                $new_notifications[] = $notification;
            }
        }

        return $new_notifications;
    }

    /**
     * Create text and HTML components of email message
     *
     * @param array $new_notifications
     * @return string[]
     */
    public function composeEmail($new_notifications)
    {
        $html = '<p>' . $this->getLang('mail content') . '</p>';
        $html .= '<ul>';
        $text = $this->getLang('mail content') . "\n\n";

        usort($new_notifications, function ($a, $b) {
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
            $text .= $date . ' ' . strip_tags($content) . "\n";
        }
        $html .= '</ul>';

        return [$text, $html];
    }

    /**
     * Send notification email to the given user
     *
     * @param string $user
     * @param string $text
     * @param string $html
     * @return bool true if email was sent successfully
     */
    public function sendMail($user, $text, $html)
    {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $mail = new Mailer();
        $userinfo = $auth->getUserData($user, false);
        $mail->to($userinfo['name'] . ' <' . $userinfo['mail'] . '>');
        $mail->subject($this->getLang('mail subject'));
        $mail->setBody($text, null, null, $html);
        return $mail->send();
    }

    /**
     * Store info about sent notifications
     *
     * @param string $user
     * @param array $notifications
     */
    public function storeSentNotifications($user, $notifications)
    {
        /** @var \helper_plugin_notification_db $db_helper */
        $db_helper = plugin_load('helper', 'notification_db');
        $sqlite = $db_helper->getDB();

        foreach ($notifications as $notification) {
            $plugin = $notification['plugin'];
            $id = $notification['id'];
            $sqlite->storeEntry(
                'notification',
                ['plugin' => $plugin, 'notification_id' => $id, 'user' => $user, 'sent' => date('c')]
            );
        }
    }
}
