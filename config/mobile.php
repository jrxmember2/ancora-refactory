<?php

return [
    'token_ttl_days' => (int) env('MOBILE_API_TOKEN_TTL_DAYS', 30),
    'app_version' => (string) config('ancora_version.current.version', '1.0.0'),
];
