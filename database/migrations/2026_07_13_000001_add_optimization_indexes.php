<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 添加基于查询频率分析的关键性缺失索引。
     *
     * 优先级原则：
     * 1. 高频查询列（tenant_id、status、created_at）之间缺少的复合索引
     * 2. WHERE + ORDER BY 模式（如 tenant_id 过滤后按 created_at 排序）
     * 3. 多条件过滤（如 status + created_at 组合过滤）
     */
    public function up(): void
    {
        $safeIndex = function (string $table, array|string $columns, string $name) {
            try {
                if (!Schema::hasTable($table)) {
                    return;
                }
                // 对于复合索引，检查所有列是否都存在
                $cols = is_array($columns) ? $columns : [$columns];
                foreach ($cols as $col) {
                    if (!Schema::hasColumn($table, $col)) {
                        return;
                    }
                }
                Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                    $t->index($columns, $name);
                });
            } catch (\Throwable $e) {
                // 安全忽略：索引已存在或表结构不匹配
            }
        };

        // ═══════════════════════════════════════════════
        // 1. licenses
        // ═══════════════════════════════════════════════
        $safeIndex('licenses', ['tenant_id', 'created_at'], 'idx_lic_tenant_created');
        $safeIndex('licenses', ['tenant_id', 'expires_at'], 'idx_lic_tenant_expires');
        $safeIndex('licenses', ['tenant_id', 'status', 'created_at'], 'idx_lic_tenant_status_created');
        $safeIndex('licenses', ['tenant_id', 'deleted_at'], 'idx_lic_tenant_deleted');

        // ═══════════════════════════════════════════════
        // 2. devices
        // ═══════════════════════════════════════════════
        $safeIndex('devices', ['tenant_id', 'trust_score'], 'idx_dev_tenant_trust');
        $safeIndex('devices', ['tenant_id', 'is_vm'], 'idx_dev_tenant_vm');
        $safeIndex('devices', ['tenant_id', 'is_blacklisted'], 'idx_dev_tenant_blacklist');
        $safeIndex('devices', ['license_id', 'created_at'], 'idx_dev_lic_created');
        $safeIndex('devices', ['tenant_id', 'created_at'], 'idx_dev_tenant_created');

        // ═══════════════════════════════════════════════
        // 3. license_activations
        // ═══════════════════════════════════════════════
        $safeIndex('license_activations', ['license_id', 'created_at'], 'idx_la_lic_created');
        $safeIndex('license_activations', ['fingerprint', 'created_at'], 'idx_la_fp_created');

        // ═══════════════════════════════════════════════
        // 4. orders
        // ═══════════════════════════════════════════════
        $safeIndex('orders', ['tenant_id', 'created_at'], 'idx_ord_tenant_created');
        $safeIndex('orders', ['user_id', 'status'], 'idx_ord_user_status');
        $safeIndex('orders', ['tenant_id', 'status', 'created_at'], 'idx_ord_tenant_status_created');
        $safeIndex('orders', ['customer_id', 'status'], 'idx_ord_customer_status');

        // ═══════════════════════════════════════════════
        // 5. order_items
        // ═══════════════════════════════════════════════
        $safeIndex('order_items', ['product_id', 'created_at'], 'idx_oi_product_created');

        // ═══════════════════════════════════════════════
        // 6. subscriptions
        // ═══════════════════════════════════════════════
        $safeIndex('subscriptions', ['customer_id', 'created_at'], 'idx_sub_customer_created');
        $safeIndex('subscriptions', ['tenant_id', 'status'], 'idx_sub_tenant_status');

        // ═══════════════════════════════════════════════
        // 7. invoices
        // ═══════════════════════════════════════════════
        $safeIndex('invoices', ['tenant_id', 'status'], 'idx_inv_tenant_status');
        $safeIndex('invoices', ['customer_id', 'created_at'], 'idx_inv_customer_created');
        $safeIndex('invoices', ['status', 'due_date'], 'idx_inv_status_duedate');

        // ═══════════════════════════════════════════════
        // 8. commissions
        // ═══════════════════════════════════════════════
        $safeIndex('commissions', ['order_id'], 'idx_comm_order');
        $safeIndex('commissions', ['status', 'created_at'], 'idx_comm_status_created');
        $safeIndex('commissions', ['earnings_account_id', 'created_at'], 'idx_comm_account_created');

        // ═══════════════════════════════════════════════
        // 9. blog_posts
        // ═══════════════════════════════════════════════
        $safeIndex('blog_posts', ['author_id', 'is_published'], 'idx_bp_author_published');
        $safeIndex('blog_posts', ['category_id', 'is_published'], 'idx_bp_cat_published');
        $safeIndex('blog_posts', ['is_featured', 'is_published'], 'idx_bp_featured_published');

        // ═══════════════════════════════════════════════
        // 10. notifications
        // ═══════════════════════════════════════════════
        $safeIndex('notifications', ['notifiable_id', 'notifiable_type', 'read_at'], 'idx_notif_read');
        $safeIndex('notifications', ['notifiable_id', 'notifiable_type', 'created_at'], 'idx_notif_created');

        // ═══════════════════════════════════════════════
        // 11. user_conversations
        // ═══════════════════════════════════════════════
        $safeIndex('user_conversations', ['user_id', 'last_message_at'], 'idx_uc_user_lastmsg');
        $safeIndex('user_conversations', ['user_id', 'type', 'last_message_at'], 'idx_uc_user_type_lastmsg');

        // ═══════════════════════════════════════════════
        // 12. conversation_participants
        // ═══════════════════════════════════════════════
        $safeIndex('conversation_participants', ['conversation_id', 'user_id'], 'idx_cp_conv_user');

        // ═══════════════════════════════════════════════
        // 13. product_skus
        // ═══════════════════════════════════════════════
        $safeIndex('product_skus', ['is_active', 'price'], 'idx_ps_active_price');

        // ═══════════════════════════════════════════════
        // 14. products
        // ═══════════════════════════════════════════════
        $safeIndex('products', ['category_id', 'is_active'], 'idx_prod_cat_active');

        // ═══════════════════════════════════════════════
        // 15. product_reviews
        // ═══════════════════════════════════════════════
        $safeIndex('product_reviews', ['product_id', 'status', 'created_at'], 'idx_pr_product_status_created');

        // ═══════════════════════════════════════════════
        // 16. earnings_accounts
        // ═══════════════════════════════════════════════
        $safeIndex('earnings_accounts', ['user_id', 'created_at'], 'idx_ea_user_created');

        // ═══════════════════════════════════════════════
        // 17. cart_items
        // ═══════════════════════════════════════════════
        $safeIndex('cart_items', ['cart_id', 'created_at'], 'idx_ci_cart_created');
        $safeIndex('cart_items', ['user_id', 'created_at'], 'idx_ci_user_created');

        // ═══════════════════════════════════════════════
        // 18. customers
        // ═══════════════════════════════════════════════
        $safeIndex('customers', ['tenant_id', 'email'], 'idx_cust_tenant_email');
    }

    public function down(): void
    {
        $safeDrop = function (string $table, string $index) {
            try {
                if (Schema::hasTable($table)) {
                    Schema::table($table, fn(Blueprint $t) => $t->dropIndex($index));
                }
            } catch (\Throwable $e) {
                // 忽略索引不存在的错误
            }
        };

        // licenses
        $safeDrop('licenses', 'idx_lic_tenant_created');
        $safeDrop('licenses', 'idx_lic_tenant_expires');
        $safeDrop('licenses', 'idx_lic_tenant_status_created');
        $safeDrop('licenses', 'idx_lic_tenant_deleted');

        // devices
        $safeDrop('devices', 'idx_dev_tenant_trust');
        $safeDrop('devices', 'idx_dev_tenant_vm');
        $safeDrop('devices', 'idx_dev_tenant_blacklist');
        $safeDrop('devices', 'idx_dev_lic_created');
        $safeDrop('devices', 'idx_dev_tenant_created');

        // license_activations
        $safeDrop('license_activations', 'idx_la_lic_created');
        $safeDrop('license_activations', 'idx_la_fp_created');

        // orders
        $safeDrop('orders', 'idx_ord_tenant_created');
        $safeDrop('orders', 'idx_ord_user_status');
        $safeDrop('orders', 'idx_ord_tenant_status_created');
        $safeDrop('orders', 'idx_ord_customer_status');

        // order_items
        $safeDrop('order_items', 'idx_oi_product_created');

        // subscriptions
        $safeDrop('subscriptions', 'idx_sub_customer_created');
        $safeDrop('subscriptions', 'idx_sub_tenant_status');

        // invoices
        $safeDrop('invoices', 'idx_inv_tenant_status');
        $safeDrop('invoices', 'idx_inv_customer_created');
        $safeDrop('invoices', 'idx_inv_status_duedate');

        // commissions
        $safeDrop('commissions', 'idx_comm_order');
        $safeDrop('commissions', 'idx_comm_status_created');
        $safeDrop('commissions', 'idx_comm_account_created');

        // blog_posts
        $safeDrop('blog_posts', 'idx_bp_author_published');
        $safeDrop('blog_posts', 'idx_bp_cat_published');
        $safeDrop('blog_posts', 'idx_bp_featured_published');

        // notifications
        $safeDrop('notifications', 'idx_notif_read');
        $safeDrop('notifications', 'idx_notif_created');

        // user_conversations
        $safeDrop('user_conversations', 'idx_uc_user_lastmsg');
        $safeDrop('user_conversations', 'idx_uc_user_type_lastmsg');

        // conversation_participants
        $safeDrop('conversation_participants', 'idx_cp_conv_user');

        // product_skus
        $safeDrop('product_skus', 'idx_ps_active_price');

        // products
        $safeDrop('products', 'idx_prod_cat_active');

        // product_reviews
        $safeDrop('product_reviews', 'idx_pr_product_status_created');

        // earnings_accounts
        $safeDrop('earnings_accounts', 'idx_ea_user_created');

        // cart_items
        $safeDrop('cart_items', 'idx_ci_cart_created');
        $safeDrop('cart_items', 'idx_ci_user_created');

        // customers
        $safeDrop('customers', 'idx_cust_tenant_email');
    }
};
