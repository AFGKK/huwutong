<?php

namespace App\Http\Middleware;

use App\Models\SlowQueryLog;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 慢查询监控中间件 (M2-118)
 *
 * 在请求生命周期中监听并记录慢查询 SQL。
 * 替换或增强 app/Http/Middleware/ApmMiddleware.php 的 DB 监听逻辑。
 */
class SlowQueryMiddleware
{
    protected array $capturedQueries = [];

    protected bool $enabled;

    protected float $thresholdMs;

    protected int $sampleRate;

    protected array $ignorePatterns = [];

    public function __construct()
    {
        $this->enabled = config('slow-query.enabled', true);
        $this->thresholdMs = (float) config('slow-query.slow_threshold_ms', 200);
        $this->sampleRate = (int) config('slow-query.sample_rate', 1);
        $this->ignorePatterns = config('slow-query.ignore_patterns', []);
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->enabled) {
            return $next($request);
        }

        // 采样
        if ($this->sampleRate > 1 && random_int(1, $this->sampleRate) !== 1) {
            return $next($request);
        }

        DB::listen(function (QueryExecuted $query) use ($request) {
            $durationMs = $query->time; // Laravel 返回毫秒
            if ($durationMs < $this->thresholdMs) {
                return;
            }

            $sql = $query->sql;
            $bindings = $query->bindings;

            // 替换绑定参数
            foreach ($bindings as $binding) {
                if (is_string($binding)) {
                    $sql = Str::replaceFirst('?', "'{$binding}'", $sql);
                } elseif (is_null($binding)) {
                    $sql = Str::replaceFirst('?', 'NULL', $sql);
                } else {
                    $sql = Str::replaceFirst('?', (string) $binding, $sql);
                }
            }

            // 忽略匹配的模式
            foreach ($this->ignorePatterns as $pattern) {
                $likePattern = str_replace('%', '.*', preg_quote($pattern, '/'));
                if (preg_match("/^{$likePattern}/i", $sql)) {
                    return;
                }
            }

            $sqlType = strtoupper(Str::before(trim($sql), ' '));
            $sqlHash = md5($sql);

            // 统计调用栈
            $stackTrace = '';
            if (config('app.debug', false)) {
                $stackTrace = (new \Exception)->getTraceAsString();
            }

            $routeName = $request->route()?->getName();
            $requestPath = $request->path();
            $requestMethod = $request->method();

            $this->capturedQueries[] = [
                'sql_hash' => $sqlHash,
                'sql_text' => Str::limit($sql, 2000),
                'sql_type' => in_array($sqlType, ['SELECT', 'INSERT', 'UPDATE', 'DELETE']) ? $sqlType : 'OTHER',
                'database_name' => $query->connection->getDatabaseName(),
                'table_name' => $this->extractTableName($sql, $sqlType),
                'duration_ms' => round($durationMs, 2),
                'rows_examined' => 0,
                'rows_sent' => 0,
                'lock_time_ms' => 0,
                'stack_trace' => $stackTrace,
                'route_name' => $routeName,
                'request_path' => $requestPath,
                'request_method' => $requestMethod,
                'occurred_at' => now(),
            ];
        });

        $response = $next($request);

        // 批量写入
        if (!empty($this->capturedQueries)) {
            try {
                $chunks = array_chunk($this->capturedQueries, 50);
                foreach ($chunks as $chunk) {
                    SlowQueryLog::insert($chunk);
                }
            } catch (\Throwable $e) {
                Log::warning('慢查询日志写入失败: ' . $e->getMessage());
            }
        }

        return $response;
    }

    protected function extractTableName(string $sql, string $sqlType): ?string
    {
        if ($sqlType === 'SELECT' && preg_match('/\bFROM\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if (in_array($sqlType, ['INSERT', 'REPLACE']) && preg_match('/\b(?:INTO|TABLE)\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if ($sqlType === 'UPDATE' && preg_match('/\bUPDATE\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if ($sqlType === 'DELETE' && preg_match('/\bFROM\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        return null;
    }
}
