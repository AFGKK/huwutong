<?php

namespace App\Services\BiExport;

interface BiConnectorInterface
{
    public function test(): bool;
    public function export(string $tableName, array $data): array;
}
