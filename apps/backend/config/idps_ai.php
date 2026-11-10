<?php

return [
    'url' => env('AI_BASE_URL', 'http://127.0.0.1:8001'),
    'key' => env('IDPS_AI_SERVICE_KEY'),
    'timeout' => (int) env('AI_REQUEST_TIMEOUT_SECONDS', 180),
];
