<?php

return [
    'features' => [
        // Toggle these to enable/disable bulk role notifications
        'role_broadcast'   => (bool) env('NOTIFY_ENABLE_ROLE_BROADCAST', false),
        'admins_broadcast' => (bool) env('NOTIFY_ENABLE_ADMINS_BROADCAST', false),
    ],
];
