<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 门户品牌配置表 ───
        Schema::create('portal_branding_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('locale', 10)->default('zh-CN')->comment('语言');
            $table->string('brand_name', 200)->nullable()->comment('品牌名称');
            $table->string('brand_slogan', 500)->nullable()->comment('品牌标语');
            $table->string('logo_url', 500)->nullable()->comment('Logo URL');
            $table->string('favicon_url', 500)->nullable()->comment('Favicon URL');
            $table->string('primary_color', 20)->default('#409eff')->comment('主色调');
            $table->string('secondary_color', 20)->default('#67c23a')->comment('辅助色');
            $table->string('background_color', 20)->default('#f5f7fa')->comment('背景色');
            $table->string('text_color', 20)->default('#303133')->comment('文字颜色');
            $table->string('link_color', 20)->default('#409eff')->comment('链接颜色');
            $table->string('header_bg_color', 20)->default('#ffffff')->comment('顶部背景色');
            $table->string('sidebar_bg_color', 20)->default('#304156')->comment('侧边栏背景色');
            $table->string('sidebar_text_color', 20)->default('#bfcbd9')->comment('侧边栏文字颜色');
            $table->string('button_radius', 10)->default('4px')->comment('按钮圆角');
            $table->string('font_family', 200)->nullable()->comment('字体');
            $table->text('custom_css')->nullable()->comment('自定义CSS');
            $table->text('header_html')->nullable()->comment('顶部自定义HTML');
            $table->text('footer_html')->nullable()->comment('底部自定义HTML');
            $table->string('login_page_title', 200)->nullable()->comment('登录页标题');
            $table->string('login_page_subtitle', 500)->nullable()->comment('登录页副标题');
            $table->string('login_bg_image', 500)->nullable()->comment('登录页背景图');
            $table->string('footer_text', 500)->nullable()->comment('底部版权文字');
            $table->json('links')->nullable()->comment('自定义链接');
            $table->json('social_links')->nullable()->comment('社交链接');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false)->comment('是否为默认配置');
            $table->timestamps();

            $table->unique(['tenant_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_branding_configs');
    }
};
