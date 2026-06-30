<?php

// M3-84 商品评论/评分系统配置

return [
    'review' => [
        'review_required' => true,
        'allow_anonymous' => false,
        'max_reviews_per_product_per_user' => 1,
        'rating_range' => [1, 5],
        'allow_images' => true,
        'max_images_per_review' => 9,
        'max_description_length' => 2000,
        'auto_approve_trusted_users' => false,
    ],

    'sorting' => [
        'default_sort' => 'created_at',
        'allowed_sorts' => ['created_at', 'rating', 'helpful'],
    ],

    'merchant' => [
        'allow_reply' => true,
        'reply_within_days' => 30,
        'max_replies_per_review' => 1,
    ],
];
