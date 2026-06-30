<?php

namespace Database\Seeders;

use App\Models\ChatFaq;
use Illuminate\Database\Seeder;

class ChatFaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['question' => '如何下载此产品？', 'icon' => '📥', 'sort_order' => 1],
            ['question' => '价格是多少？', 'icon' => '💰', 'sort_order' => 2],
            ['question' => '如何续费或升级？', 'icon' => '🔄', 'sort_order' => 3],
            ['question' => '有哪些功能和特性？', 'icon' => '✨', 'sort_order' => 4],
            ['question' => '购买后如何激活？', 'icon' => '🔑', 'sort_order' => 5],
            ['question' => '支持退款吗？', 'icon' => '💵', 'sort_order' => 6],
            ['question' => '如何联系人工客服？', 'icon' => '👤', 'sort_order' => 7],
        ];

        foreach ($faqs as $faq) {
            ChatFaq::create($faq);
        }
    }
}
