<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wrap index creation in try-catch to handle duplicate index errors
        $addIndex = function (string $table, array|string $columns, string $name) {
            try {
                Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                    if (is_string($columns)) {
                        $t->index($columns, $name);
                    } else {
                        $t->index($columns, $name);
                    }
                });
            } catch (\Throwable $e) {
                // Ignore duplicate index errors
            }
        };

        $addIndex('conversation_messages', ['conversation_id', 'created_at'], 'idx_conv_created');
        $addIndex('conversation_messages', ['conversation_id', 'is_pinned'], 'idx_conv_pinned');
        $addIndex('conversation_participants', ['user_id', 'deleted_at'], 'idx_user_deleted');
        $addIndex('user_conversations', 'type', 'idx_type');
        $addIndex('user_conversations', 'last_message_at', 'idx_last_message_at');
        $addIndex('orders', 'status', 'idx_status');
        $addIndex('orders', 'deleted_at', 'idx_deleted_at');
        $addIndex('order_items', 'order_id', 'idx_order_id');
        $addIndex('order_items', 'product_id', 'idx_product_id');
        $addIndex('products', 'is_active', 'idx_is_active');
        $addIndex('products', 'is_featured', 'idx_is_featured');
        $addIndex('licenses', 'customer_id', 'idx_customer_id');
        $addIndex('licenses', 'expires_at', 'idx_expires_at');
        $addIndex('license_activations', 'fingerprint', 'idx_fingerprint');
        $addIndex('agents', 'user_id', 'idx_agent_user_id');
        $addIndex('agents', 'parent_agent_id', 'idx_parent_agent_id');
        $addIndex('affiliate_creatives', ['campaign_id', 'is_active'], 'idx_campaign_active');
        $addIndex('customers', 'tenant_id', 'idx_customer_tenant_id');
        $addIndex('customers', 'user_id', 'idx_customer_user_id');
        $addIndex('subscriptions', 'customer_id', 'idx_sub_customer_id');
        $addIndex('subscriptions', 'ends_at', 'idx_ends_at');
    }

    public function down(): void
    {
        $drop = function (string $table, string $index) {
            try { Schema::table($table, fn(Blueprint $t) => $t->dropIndex($index)); } catch (\Throwable $e) {}
        };

        $drop('conversation_messages', 'idx_conv_created');
        $drop('conversation_messages', 'idx_conv_pinned');
        $drop('conversation_participants', 'idx_user_deleted');
        $drop('user_conversations', 'idx_type');
        $drop('user_conversations', 'idx_last_message_at');
        $drop('orders', 'idx_status');
        $drop('orders', 'idx_deleted_at');
        $drop('order_items', 'idx_order_id');
        $drop('order_items', 'idx_product_id');
        $drop('products', 'idx_is_active');
        $drop('products', 'idx_is_featured');
        $drop('licenses', 'idx_customer_id');
        $drop('licenses', 'idx_expires_at');
        $drop('license_activations', 'idx_fingerprint');
        $drop('agents', 'idx_agent_user_id');
        $drop('agents', 'idx_parent_agent_id');
        $drop('affiliate_creatives', 'idx_campaign_active');
        $drop('customers', 'idx_customer_tenant_id');
        $drop('customers', 'idx_customer_user_id');
        $drop('subscriptions', 'idx_sub_customer_id');
        $drop('subscriptions', 'idx_ends_at');
    }
};
