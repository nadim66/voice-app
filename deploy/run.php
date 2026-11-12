<?php

// Retired cron entry point. Uploading this file disables earlier cron installers.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
}
