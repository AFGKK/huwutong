<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Fix pricing_plans table - add columns the model/seeder expects
Schema::table('pricing_plans', function (Blueprint $t) {
    if (!Schema::hasColumn('pricing_plans', 'price_monthly')) {
        $t->decimal('price_monthly', 12, 2)->default(0)->after('description');
        echo "Added price_monthly\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'price_quarterly')) {
        $t->decimal('price_quarterly', 12, 2)->default(0)->after('price_monthly');
        echo "Added price_quarterly\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'price_semi_annually')) {
        $t->decimal('price_semi_annually', 12, 2)->default(0)->after('price_quarterly');
        echo "Added price_semi_annually\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'price_yearly')) {
        $t->decimal('price_yearly', 12, 2)->default(0)->after('price_semi_annually');
        echo "Added price_yearly\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'currency')) {
        $t->string('currency', 3)->default('CNY')->after('price_yearly');
        echo "Added currency\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'trial_days')) {
        $t->unsignedSmallInteger('trial_days')->default(0)->after('currency');
        echo "Added trial_days\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'is_public')) {
        $t->boolean('is_public')->default(true)->after('is_active');
        echo "Added is_public\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'badge')) {
        $t->string('badge', 50)->nullable()->after('is_public');
        echo "Added badge\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'features')) {
        $t->json('features')->nullable()->after('badge');
        echo "Added features\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'limits')) {
        $t->json('limits')->nullable()->after('features');
        echo "Added limits\n";
    }
    if (!Schema::hasColumn('pricing_plans', 'metadata')) {
        $t->json('metadata')->nullable()->after('limits');
        echo "Added metadata\n";
    }
});

// Also fix existing columns to have defaults
\Illuminate\Support\Facades\DB::statement("ALTER TABLE pricing_plans MODIFY COLUMN billing_period VARCHAR(30) NOT NULL DEFAULT 'monthly'");
\Illuminate\Support\Facades\DB::statement("ALTER TABLE pricing_plans MODIFY COLUMN pricing_model VARCHAR(30) NOT NULL DEFAULT 'fixed'");

echo "\nPricing plans columns updated. Now seeding data...\n";

// Now run the seeder
$seeder = new \Database\Seeders\PricingPlanSeeder();
$seeder->run();

echo "PricingPlanSeeder completed.\n";

// Verify
$count = \Illuminate\Support\Facades\DB::table('pricing_plans')->count();
echo "pricing_plans count: $count\n";
$count2 = \Illuminate\Support\Facades\DB::table('coupons')->count();
echo "coupons count: $count2\n";
