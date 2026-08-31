<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * 模板列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmailTemplate::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($request->filled('filter.locale')) {
            $query->where('locale', $request->input('filter.locale'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->orderBy('code')->paginate($perPage));
    }

    /**
     * 模板详情
     */
    public function show(int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);
        return ApiResponse::success($template);
    }

    /**
     * 创建模板
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:email_templates,code',
            'name' => 'required|string|max:200',
            'subject' => 'required|string|max:500',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'locale' => 'sometimes|string|max:10',
            'variables' => 'nullable|array',
            'status' => 'sometimes|in:draft,published',
        ]);

        $template = EmailTemplate::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'body_text' => $validated['body_text'] ?? null,
            'locale' => $validated['locale'] ?? 'zh-CN',
            'variables' => $validated['variables'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        return ApiResponse::created($template, __('app.api.email_tpl.created'));
    }

    /**
     * 更新模板
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'subject' => 'sometimes|string|max:500',
            'body_html' => 'sometimes|string',
            'body_text' => 'nullable|string',
            'locale' => 'sometimes|string|max:10',
            'variables' => 'nullable|array',
            'status' => 'sometimes|in:draft,published',
        ]);

        $template->update($validated);

        return ApiResponse::success($template->fresh(), __('app.api.email_tpl.updated'));
    }

    /**
     * 删除模板
     */
    public function destroy(int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);
        $template->delete();
        return ApiResponse::success(null, __('app.api.email_tpl.deleted'));
    }

    /**
     * 预览模板（渲染变量后）
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
        ]);

        $template = new EmailTemplate([
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'body_text' => $validated['body_text'] ?? null,
        ]);

        $testData = [
            'site_name' => config('app.name'),
            'customer_name' => __('app.api.email_tpl.sample_customer'),
            'customer_email' => 'test@example.com',
            'license_key' => 'HWT-XXXX-XXXX-XXXX',
            'license_status' => 'active',
            'product_name' => __('app.api.email_tpl.sample_product'),
            'expires_at' => now()->addYear()->format('Y-m-d'),
            'days_remaining' => '365',
            'user_name' => __('app.api.email_tpl.sample_customer'),
            'user_email' => 'test@example.com',
            'login_url' => url('/login'),
            'reset_url' => url('/password/reset'),
            'amount' => '¥999.00',
            'payment_date' => now()->format('Y-m-d'),
        ];

        $rendered = $template->render($testData);

        return ApiResponse::success([
            'subject' => $rendered['subject'],
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'test_data' => $testData,
        ]);
    }

    /**
     * 获取默认模板列表（用于初始化）
     */
    public function defaults(): JsonResponse
    {
        $defaults = self::getDefaultTemplates();
        return ApiResponse::success($defaults);
    }

    /**
     * 初始化默认模板（批量创建）
     */
    public function initDefaults(): JsonResponse
    {
        $created = [];
        foreach (self::getDefaultTemplates() as $tmpl) {
            $t = EmailTemplate::firstOrCreate(
                ['code' => $tmpl['code'], 'locale' => $tmpl['locale']],
                $tmpl
            );
            if ($t->wasRecentlyCreated) {
                $created[] = $t->code;
            }
        }

        return ApiResponse::success(['created' => $created], __('app.api.email_tpl.defaults_init'));
    }

    /**
     * 获取可用的变量列表
     */
    public function variables(): JsonResponse
    {
        return ApiResponse::success(EmailTemplate::availableVariables());
    }

    /**
     * 定义默认模板
     */
    public static function getDefaultTemplates(): array
    {
        return [
            [
                'code' => 'license_activated',
                'name' => 'License 激活成功',
                'subject' => '【{{site_name}}】License 激活成功 - {{product_name}}',
                'body_html' => '<h2>License 激活通知</h2>
<p>尊敬的 {{customer_name}}：</p>
<p>您的 <strong>{{product_name}}</strong> 产品 License 已成功激活！</p>
<ul>
<li>License Key：<code>{{license_key}}</code></li>
<li>状态：{{license_status}}</li>
<li>过期时间：{{expires_at}}</li>
</ul>
<p>如有任何问题，请随时联系我们。</p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['customer_name', 'product_name', 'license_key', 'license_status', 'expires_at'],
                'status' => 'published',
            ],
            [
                'code' => 'license_expiring',
                'name' => 'License 即将过期',
                'subject' => '【{{site_name}}】License 即将过期 - 剩余 {{days_remaining}} 天',
                'body_html' => '<h2>License 过期提醒</h2>
<p>尊敬的 {{customer_name}}：</p>
<p>您的 <strong>{{product_name}}</strong> License 将于 <strong>{{expires_at}}</strong> 过期，剩余 <strong>{{days_remaining}}</strong> 天。</p>
<ul>
<li>License Key：<code>{{license_key}}</code></li>
<li>过期时间：{{expires_at}}</li>
</ul>
<p>为避免服务中断，请及时续费。</p>
<p><a href="{{site_url}}/licenses/{{license_key}}/renew">立即续费</a></p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['customer_name', 'product_name', 'license_key', 'expires_at', 'days_remaining'],
                'status' => 'published',
            ],
            [
                'code' => 'license_suspended',
                'name' => 'License 已暂停',
                'subject' => '【{{site_name}}】License 已暂停 - {{product_name}}',
                'body_html' => '<h2>License 暂停通知</h2>
<p>尊敬的 {{customer_name}}：</p>
<p>您的 <strong>{{product_name}}</strong> License 已被暂停。</p>
<ul>
<li>License Key：<code>{{license_key}}</code></li>
<li>当前状态：{{license_status}}</li>
</ul>
<p>如需恢复使用，请联系我们的客服团队。</p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['customer_name', 'product_name', 'license_key', 'license_status'],
                'status' => 'published',
            ],
            [
                'code' => 'license_revoked',
                'name' => 'License 已吊销',
                'subject' => '【{{site_name}}】License 已吊销 - {{product_name}}',
                'body_html' => '<h2>License 吊销通知</h2>
<p>尊敬的 {{customer_name}}：</p>
<p>您的 <strong>{{product_name}}</strong> License 已被吊销。</p>
<ul>
<li>License Key：<code>{{license_key}}</code></li>
<li>状态：已吊销</li>
</ul>
<p>如有疑问，请联系我们的客服团队了解详情。</p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['customer_name', 'product_name', 'license_key'],
                'status' => 'published',
            ],
            [
                'code' => 'trial_started',
                'name' => '试用开始',
                'subject' => '【{{site_name}}】试用开始 - {{product_name}}',
                'body_html' => '<h2>试用通知</h2>
<p>尊敬的 {{customer_name}}：</p>
<p>您已成功开始 <strong>{{product_name}}</strong> 试用！</p>
<ul>
<li>License Key：<code>{{license_key}}</code></li>
<li>试用到期：{{expires_at}}</li>
</ul>
<p>试用期间您可以体验所有功能。到期前我们会提醒您。</p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['customer_name', 'product_name', 'license_key', 'expires_at'],
                'status' => 'published',
            ],
            [
                'code' => 'password_reset',
                'name' => '密码重置',
                'subject' => '【{{site_name}}】密码重置请求',
                'body_html' => '<h2>密码重置</h2>
<p>{{user_name}}，您好：</p>
<p>我们收到了您的密码重置请求。</p>
<p><a href="{{reset_url}}" style="display:inline-block;padding:10px 24px;background:#0f172a;color:#fff;text-decoration:none;border-radius:4px;">重置密码</a></p>
<p>如果按钮无法点击，请复制以下链接到浏览器：</p>
<p><code>{{reset_url}}</code></p>
<p>此链接有效期为 60 分钟。</p>
<p>如果您没有请求重置密码，请忽略此邮件。</p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['user_name', 'reset_url'],
                'status' => 'published',
            ],
            [
                'code' => 'welcome',
                'name' => '新用户欢迎',
                'subject' => '欢迎加入 {{site_name}}！',
                'body_html' => '<h2>欢迎加入！</h2>
<p>{{user_name}}，您好：</p>
<p>感谢您注册 {{site_name}}！</p>
<p>您的账号已创建成功，您可以：</p>
<ul>
<li>创建和管理 License</li>
<li>查看产品和订阅</li>
<li>管理设备绑定</li>
</ul>
<p><a href="{{login_url}}" style="display:inline-block;padding:10px 24px;background:#0f172a;color:#fff;text-decoration:none;border-radius:4px;">立即登录</a></p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['user_name', 'login_url'],
                'status' => 'published',
            ],
            [
                'code' => 'invoice_created',
                'name' => '发票已生成',
                'subject' => '【{{site_name}}】发票 {{invoice_no}} 已生成',
                'body_html' => '<h2>发票通知</h2>
<p>尊敬的 {{customer_name}}：</p>
<p>您的发票已生成：</p>
<ul>
<li>发票号：{{invoice_no}}</li>
<li>金额：{{amount}} {{currency}}</li>
<li>日期：{{payment_date}}</li>
</ul>
<p>您可以在后台下载 PDF 格式的发票。</p>
<p>{{site_name}} 团队</p>',
                'locale' => 'zh-CN',
                'variables' => ['customer_name', 'invoice_no', 'amount', 'currency', 'payment_date'],
                'status' => 'published',
            ],
            // 英文版
            [
                'code' => 'license_activated_en',
                'name' => 'License Activated',
                'subject' => '[{{site_name}}] License Activated - {{product_name}}',
                'body_html' => '<h2>License Activation</h2>
<p>Dear {{customer_name}},</p>
<p>Your <strong>{{product_name}}</strong> license has been successfully activated!</p>
<ul>
<li>License Key: <code>{{license_key}}</code></li>
<li>Status: {{license_status}}</li>
<li>Expires: {{expires_at}}</li>
</ul>
<p>If you have any questions, please contact us.</p>
<p>{{site_name}} Team</p>',
                'locale' => 'en',
                'variables' => ['customer_name', 'product_name', 'license_key', 'license_status', 'expires_at'],
                'status' => 'published',
            ],
            [
                'code' => 'license_expiring_en',
                'name' => 'License Expiring Soon',
                'subject' => '[{{site_name}}] License Expiring - {{days_remaining}} Days Left',
                'body_html' => '<h2>License Expiry Reminder</h2>
<p>Dear {{customer_name}},</p>
<p>Your <strong>{{product_name}}</strong> license will expire on <strong>{{expires_at}}</strong> ({{days_remaining}} days remaining).</p>
<ul>
<li>License Key: <code>{{license_key}}</code></li>
<li>Expires: {{expires_at}}</li>
</ul>
<p>Please renew to avoid service interruption.</p>
<p><a href="{{site_url}}/licenses/{{license_key}}/renew">Renew Now</a></p>
<p>{{site_name}} Team</p>',
                'locale' => 'en',
                'variables' => ['customer_name', 'product_name', 'license_key', 'expires_at', 'days_remaining'],
                'status' => 'published',
            ],
            [
                'code' => 'welcome_en',
                'name' => 'Welcome New User',
                'subject' => 'Welcome to {{site_name}}!',
                'body_html' => '<h2>Welcome!</h2>
<p>Dear {{user_name}},</p>
<p>Thank you for registering with {{site_name}}!</p>
<p>Your account has been created. You can now:</p>
<ul>
<li>Create and manage Licenses</li>
<li>View products and subscriptions</li>
<li>Manage device bindings</li>
</ul>
<p><a href="{{login_url}}" style="display:inline-block;padding:10px 24px;background:#0f172a;color:#fff;text-decoration:none;border-radius:4px;">Login Now</a></p>
<p>{{site_name}} Team</p>',
                'locale' => 'en',
                'variables' => ['user_name', 'login_url'],
                'status' => 'published',
            ],
        ];
    }
}
