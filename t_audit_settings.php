<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Read seeder file
$seederCode = file_get_contents(__DIR__ . '/database/seeders/SiteSettingsSeeder.php');
preg_match_all("/\['group' => '(\w+)', 'key' => '(\w+)', 'value' => '([^']*)', 'type' => '(\w+)', 'description' => '([^']*)'/", $seederCode, $matches, PREG_SET_ORDER);

echo "=== Seeder 期望的设置 ===\n";
$expected = [];
foreach ($matches as $m) {
    $expected[$m[2]] = ['group' => $m[1], 'key' => $m[2], 'value' => $m[3], 'type' => $m[4], 'description' => $m[5]];
}

echo "Total expected: " . count($expected) . "\n\n";

// Check what's in DB
$dbSettings = \Illuminate\Support\Facades\DB::table('site_settings')->get()->keyBy('key');

echo "=== 缺失的设置项 ===\n";
$missing = [];
foreach ($expected as $key => $exp) {
    if (!isset($dbSettings[$key])) {
        $missing[$key] = $exp;
    }
}

if (empty($missing)) {
    echo "✅ 全部 " . count($expected) . " 项设置都已恢复！\n";
} else {
    foreach ($missing as $key => $m) {
        echo "❌ {$key} ({$m['group']}) - {$m['description']}\n";
    }
    echo "共缺失 " . count($missing) . " 项\n";
}

echo "\n=== 按分组统计 ===\n";
$groupStats = [];
foreach ($expected as $k => $v) {
    $g = $v['group'];
    if (!isset($groupStats[$g])) $groupStats[$g] = ['expected' => 0, 'actual' => 0];
    $groupStats[$g]['expected']++;
    if (isset($dbSettings[$k])) $groupStats[$g]['actual']++;
}
foreach ($groupStats as $g => $s) {
    $status = $s['expected'] == $s['actual'] ? '✅' : '⚠️';
    echo "$status {$g}: {$s['actual']}/{$s['expected']} 项\n";
}

echo "\n=== 数据库中额外的设置项（不在 Seeder 中）===\n";
foreach ($dbSettings as $key => $db) {
    if (!isset($expected[$key])) {
        echo "  ➕ {$key} ({$db->group})\n";
    }
}
