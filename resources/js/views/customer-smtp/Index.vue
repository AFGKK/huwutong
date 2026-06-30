<template>
    <div class="customer-smtp-container">
        <el-page-header :content="'客户 SMTP 配置 & 降级'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="配置自定义 SMTP 发信（QQ/163/Gmail/Outlook/企业微信/阿里云），支持主备自动切换降级；主SMTP失败自动切备用，全部失败切回系统默认。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dash.stats.total }}</div>
                    <div class="stat-label">SMTP 配置数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dash.stats.active }}</div>
                    <div class="stat-label">活跃</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dash.stats.failed }}</div>
                    <div class="stat-label">故障</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ dash.stats.primary?.host || '系统默认' }}</div>
                    <div class="stat-label">当前主 SMTP</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 降级配置卡片 -->
        <el-card class="fallback-card">
            <template #header><el-space><span>降级配置</span><el-tag type="warning">M2-84</el-tag></el-space></template>
            <el-descriptions :column="4" border size="small">
                <el-descriptions-item label="失败阈值">{{ dash.fallback_config?.failure_threshold }} 次</el-descriptions-item>
                <el-descriptions-item label="恢复间隔">{{ dash.fallback_config?.recovery_interval }} 分钟</el-descriptions-item>
                <el-descriptions-item label="系统默认">{{ dash.system_default?.host }}:{{ dash.system_default?.port }}</el-descriptions-item>
                <el-descriptions-item label="告警邮箱">{{ dash.fallback_config?.alert_email }}</el-descriptions-item>
            </el-descriptions>
            <div class="fallback-flow">
                <strong>降级链路：</strong>
                <el-tag type="success">主 SMTP</el-tag> →
                <el-tag type="warning">备用 SMTP</el-tag> →
                <el-tag type="info">系统默认 SMTP</el-tag> →
                <el-tag type="danger">告警通知</el-tag>
            </div>
        </el-card>

        <el-tabs v-model="activeTab">
            <el-tab-pane label="SMTP 配置" name="configs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>SMTP 配置列表</span>
                            <el-button size="small" type="primary" @click="openCreateDialog">新建配置</el-button>
                            <el-button size="small" @click="handleRecover">恢复检查</el-button>
                        </el-space>
                    </template>
                    <el-table :data="configs" stripe v-loading="loading">
                        <el-table-column label="提供商" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ providers[row.provider]?.name || row.provider }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="host" label="主机" width="180" />
                        <el-table-column prop="port" label="端口" width="70" />
                        <el-table-column prop="encryption" label="加密" width="70" />
                        <el-table-column prop="from_address" label="发件人" width="200" />
                        <el-table-column label="主" width="60" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.is_primary" type="success" size="small">主</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                    {{ row.status === 'active' ? '正常' : '故障' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="320" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="editConfig(row)">编辑</el-button>
                                <el-button size="small" type="success" @click="handleTest(row)">测试</el-button>
                                <el-button v-if="!row.is_primary" size="small" type="primary" @click="handleSetPrimary(row)">设为主</el-button>
                                <el-popconfirm title="确认删除?" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="发送日志" name="logs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>SMTP 发送 & 降级日志</span>
                            <el-select v-model="logEventFilter" placeholder="事件类型" clearable size="small" style="width:140px" @change="loadLogs">
                                <el-option label="全部" value="" />
                                <el-option label="发送" value="send" />
                                <el-option label="测试" value="test" />
                                <el-option label="降级" value="failover" />
                                <el-option label="恢复" value="recovery" />
                                <el-option label="告警" value="alert" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="logs" stripe v-loading="logsLoading">
                        <el-table-column prop="created_at" label="时间" width="160" />
                        <el-table-column label="事件" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.event_type === 'alert' ? 'danger' : row.event_type === 'failover' ? 'warning' : row.event_type === 'recovery' ? 'success' : 'info'" size="small">
                                    {{ row.event_type === 'failover' ? '降级' : row.event_type === 'recovery' ? '恢复' : row.event_type === 'alert' ? '告警' : row.event_type === 'test' ? '测试' : '发送' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status === 'success' ? '成功' : '失败' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="from_address" label="发件人" width="180" />
                        <el-table-column prop="to_address" label="收件人" width="180" />
                        <el-table-column prop="subject" label="主题" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="error_message" label="错误" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="fallback_action" label="降级动作" width="140">
                            <template #default="{ row }">
                                <el-tag v-if="row.fallback_action" size="small" type="warning">{{ row.fallback_action === 'switch_to_backup' ? '切备用' : row.fallback_action === 'use_system_default' ? '系统默认' : '告警' }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="logTotal > logPerPage" v-model:current-page="logPage" :page-size="logPerPage" :total="logTotal" layout="prev, pager, next" @current-change="loadLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 新建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑 SMTP 配置' : '新建 SMTP 配置'" width="600px">
            <el-form label-position="top" size="small" :model="form">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="SMTP 提供商" required>
                            <el-select v-model="form.provider" style="width:100%" @change="applyProviderTemplate">
                                <el-option v-for="(p, key) in providers" :key="key" :label="p.name" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="配置名称">
                            <el-input v-model="form.name" placeholder="如：公司主邮箱" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="SMTP 主机" required>
                            <el-input v-model="form.host" placeholder="smtp.example.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label="端口" required>
                            <el-input-number v-model="form.port" :min="1" :max="65535" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label="加密方式">
                            <el-select v-model="form.encryption" style="width:100%">
                                <el-option label="无" value="" />
                                <el-option label="SSL" value="ssl" />
                                <el-option label="TLS" value="tls" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="用户名">
                            <el-input v-model="form.username" placeholder="邮箱地址或用户名" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="密码">
                            <el-input v-model="form.password" type="password" show-password placeholder="SMTP 授权码或密码" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="发件人地址">
                            <el-input v-model="form.from_address" placeholder="noreply@yourdomain.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="发件人名称">
                            <el-input v-model="form.from_name" placeholder="默认：互物通" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item>
                    <el-checkbox v-model="form.is_primary">设为主 SMTP</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveConfig">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import customerSmtp from '@/api/customerSmtp';

const activeTab = ref('configs');
const loading = ref(false);
const logsLoading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const logEventFilter = ref('');

const dash = reactive({ stats: { total: 0, active: 0, failed: 0, primary: null }, providers: {}, fallback_config: {}, system_default: {} });
const providers = reactive({});
const configs = ref([]);
const logs = ref([]);

const form = reactive({
    provider: 'custom', name: '', host: '', port: 587, encryption: 'tls',
    username: '', password: '', from_address: '', from_name: '', is_primary: false,
});

const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);

async function loadProviders() {
    try {
        const res = await customerSmtp.providers();
        Object.assign(providers, res.data.data);
    } catch (e) { console.error(e); }
}

async function loadDashboard() {
    try {
        const res = await customerSmtp.dashboard();
        Object.assign(dash, res.data.data);
    } catch (e) { console.error(e); }
}

async function loadConfigs() {
    loading.value = true;
    try {
        const res = await customerSmtp.list();
        configs.value = res.data.data || [];
    } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function loadLogs(page) {
    logsLoading.value = true;
    try {
        const params = { page: page || logPage.value, per_page: logPerPage.value };
        if (logEventFilter.value) params.event_type = logEventFilter.value;
        const res = await customerSmtp.logs(params);
        logs.value = res.data.data.items || [];
        logTotal.value = res.data.data.total;
        logPage.value = res.data.data.page;
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

function applyProviderTemplate(key) {
    const p = providers[key];
    if (p && key !== 'custom') {
        form.host = p.host;
        form.port = p.port;
        form.encryption = p.encryption;
    }
}

function openCreateDialog() {
    isEditing.value = false; editingId.value = null;
    form.provider = 'custom'; form.name = ''; form.host = ''; form.port = 587;
    form.encryption = 'tls'; form.username = ''; form.password = '';
    form.from_address = ''; form.from_name = ''; form.is_primary = false;
    dialogVisible.value = true;
}

function editConfig(row) {
    isEditing.value = true; editingId.value = row.id;
    form.provider = row.provider; form.name = row.name || ''; form.host = row.host;
    form.port = row.port; form.encryption = row.encryption || 'tls';
    form.username = row.username || ''; form.password = '';
    form.from_address = row.from_address || ''; form.from_name = row.from_name || '';
    form.is_primary = row.is_primary;
    dialogVisible.value = true;
}

async function saveConfig() {
    if (!form.host || !form.port) { ElMessage.warning('请填写主机和端口'); return; }
    saving.value = true;
    try {
        const data = { ...form };
        if (!data.password) delete data.password;
        if (isEditing.value) {
            await customerSmtp.update(editingId.value, data);
            ElMessage.success('已更新');
        } else {
            await customerSmtp.create(data);
            ElMessage.success('已创建');
        }
        dialogVisible.value = false;
        loadConfigs();
    } catch (e) { console.error(e); } finally { saving.value = false; }
}

async function handleTest(row) {
    try {
        const res = await customerSmtp.test(row.id);
        if (res.data.success) {
            ElMessage.success('SMTP 连接成功');
        } else {
            ElMessage.error(res.data.message || '连接失败');
        }
    } catch (e) { console.error(e); }
}

async function handleSetPrimary(row) {
    try {
        await customerSmtp.setPrimary(row.id);
        ElMessage.success('已设为主 SMTP');
        loadConfigs();
    } catch (e) { console.error(e); }
}

async function handleDelete(row) {
    try { await customerSmtp.destroy(row.id); ElMessage.success('已删除'); loadConfigs(); } catch (e) { console.error(e); }
}

async function handleRecover() {
    try { const res = await customerSmtp.recover(); ElMessage.success(`恢复检查完成: ${res.data.data.recovered.length} 个已恢复`); loadConfigs(); } catch (e) { console.error(e); }
}

onMounted(() => { loadProviders(); loadDashboard(); loadConfigs(); });
</script>

<style scoped>
.customer-smtp-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; overflow: hidden; text-overflow: ellipsis; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
.fallback-card { margin-bottom: 16px; }
.fallback-flow { margin-top: 12px; padding: 8px 12px; background: #f5f7fa; border-radius: 4px; display: flex; align-items: center; gap: 8px; font-size: 13px; }
</style>
