<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL 表数量过多时 migrate:fresh 会触发 max_locks_per_transaction，
 * 首次运行仅执行 migrate（CI/本地可预先 migrate --seed）。
 */
trait RefreshDatabase
{
    use CanConfigureMigrationCommands;

    public function refreshDatabase(): void
    {
        $this->beforeRefreshingDatabase();

        if ($this->usingInMemoryDatabase()) {
            $this->restoreInMemoryDatabase();
        }

        $this->refreshTestDatabase();

        $this->afterRefreshingDatabase();
    }

    protected function usingInMemoryDatabase(): bool
    {
        $default = config('database.default');

        return config("database.connections.$default.database") === ':memory:';
    }

    protected function restoreInMemoryDatabase(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            if (isset(RefreshDatabaseState::$inMemoryConnections[$name])) {
                $database->connection($name)->setPdo(RefreshDatabaseState::$inMemoryConnections[$name]);
            }
        }
    }

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            if (config('database.default') === 'pgsql') {
                $this->purgePersistedTestData();
                $this->artisan('migrate', ['--force' => true]);
            } else {
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * 清空测试库运行期/seed 残留数据，避免与 RefreshDatabase 事务外数据冲突。
     */
    protected function purgePersistedTestData(): void
    {
        $connection = DB::connection();

        if (! $this->testDatabaseNeedsPurge($connection)) {
            return;
        }

        $except = ['migrations'];

        $tables = collect($connection->select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        ))->pluck('tablename')->reject(fn (string $table) => in_array($table, $except, true));

        $connection->statement("SET session_replication_role = 'replica'");

        foreach ($tables as $table) {
            try {
                $connection->statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
            } catch (\Throwable) {
                // 单表失败不阻断整套测试启动
            }
        }

        $connection->statement("SET session_replication_role = 'origin'");
    }

    protected function testDatabaseNeedsPurge(\Illuminate\Database\Connection $connection): bool
    {
        foreach (['pages', 'portal_branding_configs', 'languages', 'products', 'logs'] as $table) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $count = (int) $connection->table($table)->count();
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    public function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        $connections = $this->connectionsToTransact();

        $this->app->instance('db.transactions', $transactionsManager = new DatabaseTransactionsManager($connections));

        foreach ($connections as $name) {
            $connection = $database->connection($name);

            $connection->setTransactionManager($transactionsManager);

            if ($this->usingInMemoryDatabase()) {
                RefreshDatabaseState::$inMemoryConnections[$name] ??= $connection->getPdo();
            }

            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();

                if ($connection->getPdo() && ! $connection->getPdo()->inTransaction()) {
                    if (config('database.default') !== 'pgsql') {
                        RefreshDatabaseState::$migrated = false;
                    }
                }

                $connection->rollBack();
                $connection->setEventDispatcher($dispatcher);
            }
        });
    }

    protected function connectionsToTransact(): array
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact
            : [null];
    }

    protected function beforeRefreshingDatabase(): void
    {
        //
    }

    protected function afterRefreshingDatabase(): void
    {
        //
    }
}
