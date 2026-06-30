# HWT License PHP SDK Demo

> 互物通授权系统 PHP 集成示例 — 支持 Laravel / 原生 PHP

## 安装

```bash
composer install
```

## 用法

```bash
# 设置环境变量（可选）
export HWT_API_KEY=sk_test_xxx
export HWT_HOST=https://api.huwutong.com

# 激活 License
php examples/activate.php HWT-XXXX-XXXX

# 验证 License
php examples/validate.php HWT-XXXX-XXXX
```

## Laravel 集成

在 Laravel 中集成 License 验证中间件：

```php
// app/Providers/AppServiceProvider.php
use Huwutong\Demo\HWTClient;

public function register()
{
    $this->app->singleton(HWTClient::class, function ($app) {
        return new HWTClient(
            config('services.huwutong.api_key'),
            config('services.huwutong.host')
        );
    });
}
```

## API 参考

所有方法返回标准格式：`{ success, data/message }`

| 方法 | 参数 | 说明 |
|------|------|------|
| `activate(key, deviceInfo)` | string, array | 激活 License |
| `validate(key, fingerprint)` | string, string|null | 验证 License |
| `checkFeature(key, feature)` | string, string | 检查 Feature |
| `getLicenseInfo(key)` | string | 查询信息 |
| `heartbeat(key, fingerprint)` | string, string | 心跳上报 |
