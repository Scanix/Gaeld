<?php

return [
    'managed' => (bool) env('GAELD_MANAGED_INSTALLATION', false),
    'scheduler_heartbeat' => env('GAELD_SCHEDULER_HEARTBEAT', '/run/gaeld/scheduler'),
];
