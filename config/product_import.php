<?php

return [
    'disk' => env('PRODUCT_IMPORT_DISK', 'local'),
    'max_upload_kb' => (int) env('PRODUCT_IMPORT_MAX_UPLOAD_KB', 102400),
];
