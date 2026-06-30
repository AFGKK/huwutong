<?php

// M3-90 商品收藏夹/心愿单配置

return [
    'wishlist' => [
        'max_items_per_user' => 200,
        'allow_public_sharing' => true,
        'allow_multiple_lists' => true,
        'max_lists_per_user' => 10,
    ],

    'notification' => [
        'on_price_drop' => true,
        'on_back_in_stock' => true,
        'on_promotion' => true,
        'channels' => ['database', 'mail'],
    ],

    'analytics' => [
        'track_add_events' => true,
        'track_remove_events' => true,
        'track_share_events' => true,
    ],
];
