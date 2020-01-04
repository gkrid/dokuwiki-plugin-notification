CREATE TABLE notification (
    plugin TEXT NOT NULL,
    notification_id TEXT NOT NULL,
    user TEXT NOT NULL,
    sent TEXT NOT NULL,
    PRIMARY KEY (plugin, notification_id, user)
);

CREATE TABLE cron_check (
    user TEXT NOT NULL,
    timestamp TEXT NOT NULL
);
