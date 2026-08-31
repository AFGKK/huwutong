<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('app.mail.verify_code.title') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5;">
<div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px;">
    <h2 style="margin-top: 0; color: #1a1a1a;">{{ __('app.mail.verify_code.heading') }}</h2>
    <p style="color: #666; line-height: 1.6;">{{ __('app.mail.verify_code.body', ['action' => $action]) }}</p>
    <div style="text-align: center; margin: 32px 0;">
        <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #0f172a; background: #f1f5f9; padding: 12px 24px; border-radius: 4px;">{{ $code }}</span>
    </div>
    <p style="color: #999; font-size: 13px;">{{ __('app.mail.verify_code.expires', ['minutes' => $expiresIn]) }}</p>
    <p style="color: #999; font-size: 13px;">{{ __('app.mail.verify_code.ignore') }}</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="color: #999; font-size: 12px;">{{ __('app.mail.verify_code.auto', ['app' => config('app.name')]) }}</p>
</div>
</body>
</html>
