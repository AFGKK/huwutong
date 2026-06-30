<template>
    <div class="cache-invalidation-page">
        <div class="page-header">
            <div>
                <h2>SDK 缓存失效主动推送</h2>
                <p class="text-muted">通过 WebSocket/SSE 主动通知 SDK 清除缓存 · 推送失败降级为心跳拉取 · 批量合并 · 多通道</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button type="primary" @click="showPushForm = true" :icon="Promotion">手动推送</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">待推送</div><div class="metric-value warning">{{ stats.total_pending }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">已推送</div><div class="metric-value success">{{ stats.total_published }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">失败</div><div class="metric-value danger">{{ stats.total_failed }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">Webhook 数</div><div class="metric-value">{{ stats.webhook_count }}</div></el-card></el-col>
        </el-row>

        <!-- 按类型/渠道分布 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span><el-icon><Histogram /></el-icon> 按类型分布</span></template>
                    <div class="dist-chart">
                        <div v-for="(count, type) in stats.by_type" :key="type" class="dist-row">
                            <span class="dist-label">{{ typeLabels[type] || type }}</span>
                            <el-progress :percentage="calcPct(count, stats)" :stroke-width="16">
                                <span class="dist-count">{{ count }}</span>
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-if="!Object.keys(stats.by_type || {}).length" description="暂无数据" />
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header><span><el-icon><Connection /></el-icon> 按通道分布</span></template>
                    <div class="dist-chart">
                        <div v-for="(count, ch) in stats.by_channel" :key="ch" class="dist-row">
                            <span class="dist-label">{{ channelLabels[ch] || ch }}</span>
                            <el-progress :percentage="calcPct(count, stats)" :stroke-width="16">
                                <span class="dist-count">{{ count }}</span>
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-if="!Object.keys(stats.by_channel || {}).length" description="暂无数据" />
                </el-card>
            </el-col>
        </el-row>

        <!-- Webhook 配置 -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Link /></el-icon> Webhook 配置</span>
                    <el-button size="small" type="primary" @click="showWebhookForm = true">+ 新增</el-button>
                </div>
            </template>
            <el-table :data="webhooks" stripe v-loading="whLoading" size="small">
                <el-table-column prop="url" label="URL" min-width="300" show-overflow-tooltip />
                <el-table-column label="订阅类型" min-width="200">
                    <template #default="{row}">
                        <el-tag v-for="t in (row.subscribed_types || [])" :key="t" size="small" style="margin:2px">{{ typeLabels[t] || t }}</el-tag>
                        <span v-if="!row.subscribed_types?.length" class="text-muted">全部</span>
                    </template>
                </el-table-column>
                <el-table-column label="活跃" width="70">
                    <template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{row}">
                        <el-popconfirm title="确认删除?" @confirm="deleteWebhook(row)">
                            <template #reference><el-button size="small" type="danger">删除</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!webhooks.length && !whLoading" description="暂无 Webhook 配置" />
        </el-card>

        <!-- 手动推送对话框 -->
        <el-dialog v-model="showPushForm" title="手动触发缓存失效推送" width="500px">
            <el-form :model="pushForm" label-width="120px">
                <el-form-item label="类型" :rules="[{required:true}]">
                    <el-select v-model="pushForm.type" style="width:100%">
                        <el-option v-for="(label, key) in typeLabels" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="失效 Key"><el-input v-model="pushForm.invalidation_key" placeholder="例如: license:123" /></el-form-item>
                <el-form-item label="租户 ID"><el-input-number v-model="pushForm.tenant_id" :min="0" style="width:100%" placeholder="留空为当前租户" /></el-form-item>
                <el-form-item label="上下文"><el-input v-model="pushForm.context_text" type="textarea" :rows="3" placeholder='{"reason": "管理员手动触发"}' /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPushForm = false">取消</el-button>
                <el-button type="primary" @click="submitPush" :loading="pushLoading">推送</el-button>
            </template>
        </el-dialog>

        <!-- 新增 Webhook 对话框 -->
        <el-dialog v-model="showWebhookForm" title="新增 Webhook 配置" width="500px">
            <el-form :model="whForm" label-width="100px">
                <el-form-item label="URL" :rules="[{required:true}]"><el-input v-model="whForm.url" placeholder="https://your-server.com/cache/invalidation" /></el-form-item>
                <el-form-item label="密钥"><el-input v-model="whForm.secret" placeholder="HMAC 签名密钥" /></el-form-item>
                <el-form-item label="订阅类型"><el-checkbox-group v-model="whForm.subscribed_types">
                    <el-checkbox v-for="(label, key) in typeLabels" :key="key" :label="key">{{ label }}</el-checkbox>
                </el-checkbox-group></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showWebhookForm = false">取消</el-button>
                <el-button type="primary" @click="submitWebhook" :loading="whSaving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Promotion, Histogram, Connection, Link, CircleCheck } from '@element-plus/icons-vue';
import cacheInvalidationApi from '@/api/cacheInvalidation';

const loading = ref(false);
const whLoading = ref(false);
const pushLoading = ref(false);
const whSaving = ref(false);
const showPushForm = ref(false);
const showWebhookForm = ref(false);

const stats = reactive({ total_pending: 0, total_published: 0, total_failed: 0, webhook_count: 0, by_type: {}, by_channel: {} });
const webhooks = ref([]);
const pushForm = reactive({ type: 'license_status', invalidation_key: '', tenant_id: 0, context_text: '' });
const whForm = reactive({ url: '', secret: '', subscribed_types: [] });

const typeLabels = {
    license_status: 'License 状态变更',
    feature_flag: 'Feature Flag 变更',
    product_config: '产品配置变更',
    heartbeat: '心跳检查',
};
const channelLabels = { reverb: 'WebSocket', webhook: 'Webhook', sse: 'SSE' };

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadStats(), loadWebhooks()]); } finally { loading.value = false; }
}
async function loadStats() {
    try { const r = await cacheInvalidationApi.stats(); Object.assign(stats, r.data?.data || {}); } catch {}
}
async function loadWebhooks() {
    whLoading.value = true;
    try { const r = await cacheInvalidationApi.listWebhooks(); webhooks.value = r.data?.data || []; } finally { whLoading.value = false; }
}

function calcPct(count, stats) {
    const total = (stats.total_pending || 0) + (stats.total_published || 0) + (stats.total_failed || 0);
    return total > 0 ? Math.round((count / total) * 100) : 0;
}

async function submitPush() {
    pushLoading.value = true;
    try {
        const data = {
            type: pushForm.type,
            invalidation_key: pushForm.invalidation_key || `${pushForm.type}:manual-${Date.now()}`,
            tenant_id: pushForm.tenant_id || undefined,
            context: pushForm.context_text ? JSON.parse(pushForm.context_text) : { reason: '管理员手动触发' },
        };
        await cacheInvalidationApi.invalidate(data);
        ElMessage.success('推送已触发'); showPushForm.value = false; loadStats();
    } catch { ElMessage.error('推送失败'); } finally { pushLoading.value = false; }
}

async function submitWebhook() {
    whSaving.value = true;
    try {
        await cacheInvalidationApi.storeWebhook(whForm);
        ElMessage.success('已创建'); showWebhookForm.value = false; loadWebhooks();
    } catch { ElMessage.error('创建失败'); } finally { whSaving.value = false; }
}

async function deleteWebhook(row) {
    await cacheInvalidationApi.destroyWebhook(row.id);
    ElMessage.success('已删除'); loadWebhooks();
}
</script>

<style scoped>
.cache-invalidation-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.dist-chart { padding: 8px 0; }
.dist-row { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.dist-label { min-width: 130px; font-size: 13px; }
.dist-count { font-size: 12px; font-weight: 600; }
</style>
