<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$tables = [
    'withdrawal_channels' => function (Blueprint $t) {
        $t->id();
        $t->string('name', 100);
        $t->string('slug', 50)->unique();
        $t->string('type', 30)->default('bank'); // bank/alipay/wechat/crypto
        $t->json('config')->nullable();
        $t->decimal('min_amount', 12, 2)->default(0);
        $t->decimal('max_amount', 12, 2)->nullable();
        $t->decimal('fee_rate', 5, 4)->default(0);
        $t->decimal('fee_fixed', 12, 2)->default(0);
        $t->boolean('is_active')->default(true);
        $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();
    },
    'commission_records' => function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
        $t->string('type', 30)->index(); // referral/sale/bonus
        $t->decimal('amount', 12, 2);
        $t->decimal('balance_before', 12, 2)->default(0);
        $t->decimal('balance_after', 12, 2)->default(0);
        $t->string('status', 20)->default('pending'); // pending/approved/frozen/paid
        $t->text('note')->nullable();
        $t->timestamps();
    },
    'affiliate_commissions' => function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
        $t->decimal('amount', 12, 2);
        $t->decimal('rate', 5, 4);
        $t->string('status', 20)->default('pending');
        $t->timestamp('settled_at')->nullable();
        $t->timestamps();
    },
];

foreach ($tables as $table => $closure) {
    if (!Schema::hasTable($table)) {
        Schema::create($table, $closure);
        echo "Created: $table\n";
    } else {
        echo "Exists: $table\n";
    }
}
