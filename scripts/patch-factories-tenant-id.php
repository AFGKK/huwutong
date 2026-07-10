<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'database/factories/DeployEnvironmentFactory.php',
    'database/factories/DeployJobFactory.php',
    'database/factories/DeployReleaseFactory.php',
    'database/factories/MarketingCampaignFactory.php',
    'database/factories/ChurnInterventionFactory.php',
    'database/factories/CustomerLifecycleStageFactory.php',
    'database/factories/HeatmapLayerFactory.php',
    'database/factories/RenewalReminderLogFactory.php',
    'database/factories/RenewalReminderTemplateFactory.php',
    'database/factories/SlaCompensationFactory.php',
    'database/factories/TaxComplianceDocumentFactory.php',
    'database/factories/TaxComplianceReportFactory.php',
    'database/factories/TaxComplianceRuleFactory.php',
    'database/factories/VasSubscriptionFactory.php',
];

foreach ($files as $relative) {
    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        continue;
    }

    $content = file_get_contents($path);

    if (! str_contains($content, 'use App\\Models\\Tenant;') && str_contains($content, "'tenant_id' => 1")) {
        $content = preg_replace(
            '/(namespace Database\\\\Factories;\r?\n\r?\n)/',
            "$1use App\\Models\\Tenant;\n",
            $content,
            1
        );
    }

    if (! str_contains($content, 'use App\\Models\\User;') && str_contains($content, "'created_by' => 1")) {
        $content = preg_replace(
            '/(namespace Database\\\\Factories;\r?\n\r?\n)/',
            "$1use App\\Models\\User;\n",
            $content,
            1
        );
    }

    $content = str_replace("'tenant_id' => 1,", "'tenant_id' => Tenant::factory(),", $content);
    $content = str_replace("'created_by' => 1,", "'created_by' => User::factory(),", $content);

    file_put_contents($path, $content);
    echo "Patched {$relative}\n";
}

echo "Done.\n";
