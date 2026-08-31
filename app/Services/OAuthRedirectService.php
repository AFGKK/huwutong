<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 社交登录 OAuth 授权跳转（无需 Socialite）
 *
 * 凭据优先级：SiteSetting > .env（config/services.php / config/oauth.php）
 */
class OAuthRedirectService
{
    public const SUPPORTED = ['google', 'github', 'wechat', 'qq'];

    public function isConfigured(string $provider): bool
    {
        $cfg = $this->credentials($provider);

        return ! empty($cfg['client_id']) && ! empty($cfg['client_secret']);
    }

    /**
     * @return array{authorize_url: string, state: string}
     */
    public function buildAuthorizeUrl(string $provider, string $intent = 'login', ?string $returnTo = null, ?int $userId = null): array
    {
        if (! in_array($provider, self::SUPPORTED, true)) {
            throw new RuntimeException(__("app.oauth_redirect.msg_cfedb52e"));
        }

        if (! $this->isConfigured($provider)) {
            throw new RuntimeException(__("app.oauth_redirect.msg_c6f186b0"));
        }

        $state = Str::random(40);
        Cache::put($this->stateKey($state), [
            'provider' => $provider,
            'intent' => in_array($intent, ['login', 'bind'], true) ? $intent : 'login',
            'return_to' => $this->sanitizeReturnTo($returnTo),
            'user_id' => $userId,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        $cfg = $this->credentials($provider);
        $redirectUri = $this->callbackUrl($provider);

        $params = match ($provider) {
            'google' => [
                'client_id' => $cfg['client_id'],
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'access_type' => 'online',
                'include_granted_scopes' => 'true',
                'state' => $state,
                'prompt' => 'select_account',
            ],
            'github' => [
                'client_id' => $cfg['client_id'],
                'redirect_uri' => $redirectUri,
                'scope' => 'read:user user:email',
                'state' => $state,
                'allow_signup' => 'true',
            ],
            'wechat' => [
                'appid' => $cfg['client_id'],
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'snsapi_login',
                'state' => $state,
            ],
            'qq' => [
                'client_id' => $cfg['client_id'],
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'get_user_info',
                'state' => $state,
            ],
            default => throw new RuntimeException(__("app.oauth_redirect.msg_0613337d")),
        };

        $base = match ($provider) {
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'github' => 'https://github.com/login/oauth/authorize',
            'wechat' => 'https://open.weixin.qq.com/connect/qrconnect',
            'qq' => 'https://graph.qq.com/oauth2.0/authorize',
default => throw new RuntimeException(__("app.oauth_redirect.unsupported_provider", ['provider' => $provider])),
        };

        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url = $base.'?'.$qs;
        if ($provider === 'wechat') {
            $url .= '#wechat_redirect';
        }

        return ['authorize_url' => $url, 'state' => $state];
    }

    /**
     * @return array{provider: string, provider_id: string, email: ?string, name: ?string, avatar: ?string, intent: string, return_to: ?string, user_id: ?int}
     */
    public function handleCallback(string $provider, string $code, string $state): array
    {
        $payload = Cache::pull($this->stateKey($state));
        if (! $payload || ($payload['provider'] ?? null) !== $provider) {
            throw new RuntimeException(__("app.oauth_redirect.msg_b54159d7"));
        }

        $profile = match ($provider) {
            'google' => $this->exchangeGoogle($code, $provider),
            'github' => $this->exchangeGithub($code, $provider),
            'wechat' => $this->exchangeWechat($code, $provider),
            'qq' => $this->exchangeQq($code, $provider),
default => throw new RuntimeException(__("app.oauth_redirect.unsupported_provider", ['provider' => $provider])),
        };

        return array_merge($profile, [
            'intent' => $payload['intent'] ?? 'login',
            'return_to' => $payload['return_to'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
        ]);
    }

    public function callbackUrl(string $provider): string
    {
        return rtrim(config('app.url'), '/').'/api/oauth/callback/'.$provider;
    }

    protected function credentials(string $provider): array
    {
        $idKey = "oauth_{$provider}_client_id";
        $secretKey = "oauth_{$provider}_client_secret";

        // 与后台 OAuth 配置页字段对齐（微信/QQ 用 app_id；QQ 密钥字段为 app_key）
        $altId = site_setting("oauth_{$provider}_app_id", '')
            ?: site_setting("oauth_{$provider}_appid", '');

        $clientId = (string) (site_setting($idKey, '') ?: $altId ?: config("services.oauth.{$provider}.client_id", ''));
        $clientSecret = (string) (site_setting($secretKey, '')
            ?: site_setting("oauth_{$provider}_app_secret", '')
            ?: site_setting("oauth_{$provider}_secret", '')
            ?: site_setting("oauth_{$provider}_app_key", '')
            ?: config("services.oauth.{$provider}.client_secret", ''));

        return [
            'client_id' => trim($clientId),
            'client_secret' => trim($clientSecret),
        ];
    }

    protected function stateKey(string $state): string
    {
        return 'oauth:state:'.$state;
    }

    protected function sanitizeReturnTo(?string $returnTo): ?string
    {
        if (! $returnTo) {
            return null;
        }
        // 仅允许站内相对路径，防止开放重定向
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return null;
        }

        return $returnTo;
    }

    protected function exchangeGoogle(string $code, string $provider): array
    {
        $cfg = $this->credentials($provider);
        $tokenRes = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => $this->callbackUrl($provider),
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenRes->successful()) {
            throw new RuntimeException(__("app.oauth_redirect.msg_b4b4439d").$tokenRes->body());
        }

        $accessToken = $tokenRes->json('access_token');
        $userRes = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if (! $userRes->successful()) {
            throw new RuntimeException(__("app.oauth_redirect.msg_e1a817d3"));
        }

        $u = $userRes->json();

        return [
            'provider' => 'google',
            'provider_id' => (string) ($u['sub'] ?? ''),
            'email' => $u['email'] ?? null,
            'name' => $u['name'] ?? ($u['email'] ?? 'Google User'),
            'avatar' => $u['picture'] ?? null,
        ];
    }

    protected function exchangeGithub(string $code, string $provider): array
    {
        $cfg = $this->credentials($provider);
        $tokenRes = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'code' => $code,
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri' => $this->callbackUrl($provider),
            ]);

        if (! $tokenRes->successful() || empty($tokenRes->json('access_token'))) {
            throw new RuntimeException(__("app.oauth_redirect.msg_ef5f2c13").$tokenRes->body());
        }

        $accessToken = $tokenRes->json('access_token');
        $userRes = Http::withToken($accessToken)
            ->accept('application/vnd.github+json')
            ->withHeaders(['User-Agent' => 'Huwutong-OAuth'])
            ->get('https://api.github.com/user');

        if (! $userRes->successful()) {
            throw new RuntimeException(__("app.oauth_redirect.msg_c27b3ead"));
        }

        $u = $userRes->json();
        $email = $u['email'] ?? null;
        if (! $email) {
            $emailsRes = Http::withToken($accessToken)
                ->accept('application/vnd.github+json')
                ->withHeaders(['User-Agent' => 'Huwutong-OAuth'])
                ->get('https://api.github.com/user/emails');
            if ($emailsRes->successful()) {
                foreach ($emailsRes->json() as $row) {
                    if (! empty($row['primary']) && ! empty($row['email'])) {
                        $email = $row['email'];
                        break;
                    }
                }
            }
        }

        return [
            'provider' => 'github',
            'provider_id' => (string) ($u['id'] ?? ''),
            'email' => $email,
            'name' => $u['name'] ?? ($u['login'] ?? 'GitHub User'),
            'avatar' => $u['avatar_url'] ?? null,
        ];
    }

    protected function exchangeWechat(string $code, string $provider): array
    {
        $cfg = $this->credentials($provider);
        $tokenRes = Http::get('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'appid' => $cfg['client_id'],
            'secret' => $cfg['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        $token = $tokenRes->json();
        if (empty($token['access_token']) || empty($token['openid'])) {
            throw new RuntimeException(__("app.oauth_redirect.msg_fe9c802f").$tokenRes->body());
        }

        $userRes = Http::get('https://api.weixin.qq.com/sns/userinfo', [
            'access_token' => $token['access_token'],
            'openid' => $token['openid'],
        ]);
        $u = $userRes->json();

        return [
            'provider' => 'wechat',
            'provider_id' => (string) ($token['unionid'] ?? $token['openid']),
            'email' => null,
            'name' => $u['nickname'] ?? '微信用户',
            'avatar' => $u['headimgurl'] ?? null,
        ];
    }

    protected function exchangeQq(string $code, string $provider): array
    {
        $cfg = $this->credentials($provider);
        $tokenRes = Http::get('https://graph.qq.com/oauth2.0/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->callbackUrl($provider),
            'fmt' => 'json',
        ]);
        $token = $tokenRes->json();
        if (empty($token['access_token'])) {
            throw new RuntimeException(__("app.oauth_redirect.msg_5d3137fe").$tokenRes->body());
        }

        $meRes = Http::get('https://graph.qq.com/oauth2.0/me', [
            'access_token' => $token['access_token'],
            'fmt' => 'json',
        ]);
        $me = $meRes->json();
        $openid = $me['openid'] ?? null;
        if (! $openid) {
            throw new RuntimeException(__("app.oauth_redirect.msg_9d799ac1"));
        }

        $userRes = Http::get('https://graph.qq.com/user/get_user_info', [
            'access_token' => $token['access_token'],
            'oauth_consumer_key' => $cfg['client_id'],
            'openid' => $openid,
        ]);
        $u = $userRes->json();

        return [
            'provider' => 'qq',
            'provider_id' => (string) $openid,
            'email' => null,
            'name' => $u['nickname'] ?? 'QQ用户',
            'avatar' => $u['figureurl_qq_2'] ?? ($u['figureurl_qq_1'] ?? null),
        ];
    }
}
