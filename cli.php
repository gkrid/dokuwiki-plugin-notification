<?php

use dokuwiki\Extension\CLIPlugin;
use splitbrain\phpcli\Exception;
use splitbrain\phpcli\Options;

class cli_plugin_notification extends CLIPlugin
{
    /** @var helper_plugin_notification_db */
    protected $db_helper;

    /** @var helper_plugin_notification_cron */
    protected $cron_helper;

    /**
     * Initialize helper plugin
     */
    public function __construct()
    {
        parent::__construct();
        $this->db_helper = plugin_load('helper', 'notification_db');
        $this->cron_helper = plugin_load('helper', 'notification_cron');
    }

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     * @throws Exception
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Bulk notification dispatcher');
        $options->registerCommand('send', 'Send all due notifications');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    protected function main(Options $options)
    {
        $cmd = $options->getCmd();
        switch ($cmd) {
            case 'send':
                $this->sendNotifications();
                break;
            default:
                $this->error('No command provided');
                exit(1);
        }
    }

    /**
     * Check and send notifications
     */
    protected function sendNotifications()
    {
        $sqlite = $this->db_helper->getDB();

        // get users from DB
        $sql = 'SELECT user FROM cron_check';
        $result = $sqlite->query($sql);
        $rows = $sqlite->res2arr($result);
        if (!is_array($rows)) {
            $this->info('Exiting: no users to notify found.');
            return;
        }
        // gather new notifications per user
        foreach ($rows as $row) {
            $user = $row['user'];

            // update timestamp of cron check
            $sqlite->query('UPDATE cron_check SET timestamp=? WHERE user=?', date('c'), $user);

            $notification_data = $this->cron_helper->getNotificationData($user);

            //no notifications - nothing to send
            if (empty($notification_data['notifications'])) {
                $this->info('No notifications at all for user ' . $user);
                continue;
            }

            $new_notifications = $this->cron_helper->getNewNotifications($user, $notification_data);
            //  send email
            // no notifications left - nothing to send
            if (!$new_notifications) {
                $this->info('No new notifications for user ' . $user);
                continue;
            }

            list($text, $html) = $this->cron_helper->composeEmail($new_notifications);
            if ($this->cron_helper->sendMail($user, $text, $html)) {
                $this->cron_helper->storeSentNotifications($user, $new_notifications);
                $this->info('Sent notification to ' . $user);
            } else {
                $this->error('Failed sending notification to ' . $user);
            }
        }
    }
}
