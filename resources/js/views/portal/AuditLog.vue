<template>
    <div class="portal-audit-log">
        <div class="page-header">
            <div>
                <h2>操作审计日志</h2>
                <p class="text-muted">查看您的账户下所有操作记录，了解谁在何时做了什么。</p>
            </div>
            <div class="header-actions">
                <el-select v-model="actionFilter" placeholder="操作类型" clearable size="small" style="width: 140px" @change="fetchLogs">
                    <el-option label="全部" value="" />
                    <el-option label="激活设备" value="device_activation" />
                    <el-option label="解绑设备" value="device_deactivation" />
                    <el-option label="登录" value="login" />
                    <el-option label="修改密码" value="password_change" />
                    <el-option label="修改设置" value="settings_change" />
                    <el-option label="支付操作" value="payment" />
                </el-select>
                <el-button @click="fetchLogs" :icon="Refresh" :loading="loading">刷新</el-button>
            </div>
        </div>

        <!-- 统计摘要 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total || 0 }}</div>
                        <div class="mini-label">总操作次数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #409eff">{{ stats.this_week || 0 }}</div>
                        <div class="mini-label">本周操作</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.today || 0 }}</div>
                        <div class="mini-label">今日操作</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #e6a23c">{{ licenseActions }}</div>
                        <div class="mini-label">License 相关</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 日志列表 -->
        <el-card shadow="never">
            <el-table :data="logs" v-loading="loading" stripe>
                <el-table-column label="时间" width="170" prop="created_at" />
                <el-table-column label="操作类型" width="140">
                    <template #default="{ row }">
                        <el-tag :type="actionTypeTag(row.action)" size="small">
                            {{ actionLabel(row.action) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="描述" min-width="260">
                    <template #default="{ row }">
                        <span>{{ row.description || row.action_label || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="IP 地址" width="140">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.ip_address || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="设备/浏览器" min-width="160">
                    <template #default="{ row }">
                        <span class="small-text">{{ row.user_agent ? truncateUA(row.user_agent) : '-' }}</span>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
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
import apiClient from '@/api/client';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';

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

const ACTION_LABELS = {
    device_activation: { type: 'success', label: '激活设备' },
    device_deactivation: { type: 'warning', label: '解绑设备' },
    login: { type: 'primary', label: '登录' },
    login_failed: { type: 'danger', label: '登录失败' },
    logout: { type: 'info', label: '登出' },
    password_change: { type: 'warning', label: '修改密码' },
    settings_change: { type: 'info', label: '修改设置' },
    payment_success: { type: 'success', label: '支付成功' },
    payment_failed: { type: 'danger', label: '支付失败' },
    invoice_view: { type: 'info', label: '查看发票' },
    license_view: { type: 'info', label: '查看 License' },
    api_key_create: { type: 'warning', label: '创建 API Key' },
    api_key_revoke: { type: 'danger', label: '撤销 API Key' },
};

function actionTypeTag(action) {
    return ACTION_LABELS[action]?.type || 'info';
}

function actionLabel(action) {
    return ACTION_LABELS[action]?.label || action || '-';
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

        // Stats
        const { data: statsRes } = await apiClient.get('/audit-logs/stats');
        stats.value = statsRes.data || { total: 0, this_week: 0, today: 0 };
    } catch {
        ElMessage.error('获取审计日志失败');
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
