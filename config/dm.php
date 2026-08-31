<?php

return [
    'text_max_length' => (int) env('DM_TEXT_MAX_LENGTH', 2000),
    'image_max_count' => (int) env('DM_IMAGE_MAX_COUNT', 9),
    'sticker_max_count' => (int) env('DM_STICKER_MAX_COUNT', 1),
    'retention_days' => (int) env('DM_RETENTION_DAYS', 180),
];
