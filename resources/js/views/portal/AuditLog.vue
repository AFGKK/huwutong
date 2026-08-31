<template>
    <div class="portal-audit-log">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.audit_title') }}</h2>
                <p class="text-muted">{{ $t('portal.audit_subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-select v-model="actionFilter" :placeholder="$t('portal.action_type')" clearable size="small" style="width: 140px" @change="fetchLogs">
                    <el-option :label="$t('portal.all')" value="" />
                    <el-option :label="$t('portal.act_device_activation')" value="device_activation" />
                    <el-option :label="$t('portal.act_device_deactivation')" value="device_deactivation" />
                    <el-option :label="$t('portal.act_login')" value="login" />
                    <el-option :label="$t('portal.act_password_change')" value="password_change" />
                    <el-option :label="$t('portal.act_settings_change')" value="settings_change" />
                    <el-option :label="$t('portal.act_payment')" value="payment" />
                </el-select>
                <el-button @click="fetchLogs" :icon="Refresh" :loading="loading">{{ $t('portal.refresh') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.total_actions') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #0f172a">{{ stats.this_week || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.week_actions') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.today || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.today_actions') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #e6a23c">{{ licenseActions }}</div>
                        <div class="mini-label">{{ $t('portal.license_related') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-table :data="logs" v-loading="loading" stripe>
                <el-table-column :label="$t('portal.time')" width="170" prop="created_at" />
                <el-table-column :label="$t('portal.action_type')" width="140">
                    <template #default="{ row }">
                        <el-tag :type="actionTypeTag(row.action)" size="small">
                            {{ actionLabel(row.action) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.description')" min-width="260">
                    <template #default="{ row }">
                        <span>{{ row.description || row.action_label || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.ip_address')" width="140">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.ip_address || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.device_browser')" min-width="160">
                    <template #default="{ row }">
                        <span class="small-text">{{ row.user_agent ? truncateUA(row.user_agent) : '-' }}</span>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchLogs"
                    @size-change="fetchLogs"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import apiClient from '@/api/client';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const logs = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const actionFilter = ref('');

const stats = ref({ total: 0, this_week: 0, today: 0 });
const licenseActions = computed(() => {
    return logs.value.filter(l =>
        l.action?.includes('license') || l.action?.includes('device')
    ).length || 0;
});

function actionMeta(action) {
    const map = {
        device_activation: { type: 'success', label: t('portal.act_device_activation') },
        device_deactivation: { type: 'warning', label: t('portal.act_device_deactivation') },
        login: { type: 'primary', label: t('portal.act_login') },
        login_failed: { type: 'danger', label: t('portal.act_login_failed') },
        logout: { type: 'info', label: t('portal.act_logout') },
        password_change: { type: 'warning', label: t('portal.act_password_change') },
        settings_change: { type: 'info', label: t('portal.act_settings_change') },
        payment_success: { type: 'success', label: t('portal.act_payment_success') },
        payment_failed: { type: 'danger', label: t('portal.act_payment_failed') },
        invoice_view: { type: 'info', label: t('portal.act_invoice_view') },
        license_view: { type: 'info', label: t('portal.act_license_view') },
        api_key_create: { type: 'warning', label: t('portal.act_api_key_create') },
        api_key_revoke: { type: 'danger', label: t('portal.act_api_key_revoke') },
    };
    return map[action] || { type: 'info', label: action || '-' };
}

function actionTypeTag(action) {
    return actionMeta(action).type;
}

function actionLabel(action) {
    return actionMeta(action).label;
}

function truncateUA(ua) {
    if (!ua) return '-';
    return ua.length > 40 ? ua.substring(0, 40) + '...' : ua;
}

async function fetchLogs() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
        };
        if (actionFilter.value) {
            params.action = actionFilter.value;
        }
        const { data: res } = await apiClient.get('/audit-logs', { params });
        logs.value = res.data?.data || [];
        total.value = res.data?.total || 0;

        const { data: statsRes } = await apiClient.get('/audit-logs/stats');
        stats.value = statsRes.data || { total: 0, this_week: 0, today: 0 };
    } catch {
        ElMessage.error(t('portal.audit_load_failed'));
    } finally {
        loading.value = false;
    }
}

onMounted(fetchLogs);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0 0 4px; }

.text-muted {
    color: #909399;
    font-size: 14px;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.mb-4 { margin-bottom: 16px; }

.mini-stat {
    text-align: center;
    padding: 8px 0;
}

.mini-value {
    font-size: 26px;
    font-weight: 700;
    color: #303133;
}

.mini-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.small-text { font-size: 11px; }
</style>
