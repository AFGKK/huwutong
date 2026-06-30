<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ─── 只读从库连接 (M2-23 读写分离) ───
        'mysql_replica' => [
            'driver' => 'mysql',
            'url' => env('DB_REPLICA_URL'),
            'host' => env('DB_REPLICA_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_REPLICA_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_REPLICA_DATABASE', env('DB_DATABASE', 'laravel')),
            'username' => env('DB_REPLICA_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_REPLICA_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_REPLICA_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_uca1400_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
            // Redis Sentinel 高可用配置
            'replication' => env('REDIS_REPLICATION', 'sentinel'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_CLUSTER', 'redis') !== false && env('REDIS_CLUSTER') !== 'false' && env('REDIS_CLUSTER') !== ''
                ? explode(',', env('REDIS_HOST', '127.0.0.1'))
                : env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            // Sentinel 模式：需要配置 service 名称
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'read_timeout' => env('REDIS_READ_TIMEOUT', 60),
            // 连接池配置
            'persistent' => env('REDIS_PERSISTENT', false),
            'persistent_id' => env('REDIS_PERSISTENT_ID', null),
            'timeout' => env('REDIS_TIMEOUT', 5.0),
            'retry_interval' => env('REDIS_RETRY_INTERVAL', 10),
            'retry_limit' => env('REDIS_RETRY_LIMIT', 3),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_CLUSTER', 'redis') !== false && env('REDIS_CLUSTER') !== 'false' && env('REDIS_CLUSTER') !== ''
                ? explode(',', env('REDIS_HOST', '127.0.0.1'))
                : env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'read_timeout' => env('REDIS_READ_TIMEOUT', 60),
            'persistent' => env('REDIS_PERSISTENT', false),
            'timeout' => env('REDIS_TIMEOUT', 5.0),
            'retry_interval' => env('REDIS_RETRY_INTERVAL', 10),
            'retry_limit' => env('REDIS_RETRY_LIMIT', 3),
        ],

        // 可选的 Sentinel 专用连接（读写分离）
        'sentinel' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_REPLICATION', 'sentinel') !== false && env('REDIS_REPLICATION') !== 'false' && env('REDIS_REPLICATION') !== ''
                ? explode(',', env('REDIS_SENTINEL_HOST', env('REDIS_HOST', '127.0.0.1')))
                : env('REDIS_SENTINEL_HOST', env('REDIS_HOST', '127.0.0.1')),
            'port' => env('REDIS_SENTINEL_PORT', '26379'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_SENTINEL_PASSWORD', env('REDIS_PASSWORD')),
            'database' => env('REDIS_DB', '0'),
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'read_timeout' => env('REDIS_READ_TIMEOUT', 60),
            'persistent' => env('REDIS_PERSISTENT', false),
            'timeout' => env('REDIS_TIMEOUT', 5.0),
        ],

    ],

];
