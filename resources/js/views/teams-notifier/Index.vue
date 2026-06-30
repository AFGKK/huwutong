<template>
    <div class="teams-notifier-container">
        <el-page-header :content="'Microsoft Teams 通知集成'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="配置 Teams Webhook 后，可将 License 激活成功、系统异常告警、过期提醒自动推送至 Teams 频道（Adaptive Cards 格式）。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dash.stats.active }}</div>
                    <div class="stat-label">活跃 Webhook</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dash.stats.today_success }}</div>
                    <div class="stat-label">今日成功</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dash.stats.today_failed }}</div>
                    <div class="stat-label">今日失败</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dash.stats.total }}</div>
                    <div class="stat-label">总计配置</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ dash.stats.today_total }}</div>
                    <div class="stat-label">今日发送</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane label="Webhook 配置" name="webhooks">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>Teams Webhook 列表</span>
                            <el-button size="small" type="primary" @click="openCreateDialog">新建 Webhook</el-button>
                        </el-space>
                    </template>
                    <el-table :data="webhooks" stripe v-loading="loading">
                        <el-table-column prop="name" label="频道名称" width="150" />
                        <el-table-column prop="webhook_url" label="Webhook URL" min-width="300" show-overflow-tooltip />
                        <el-table-column label="通知类型" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ typeLabel(row.notification_type) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="最后发送" width="160">
                            <template #default="{ row }">{{ row.last_sent_at || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="160">
                            <template #default="{ row }">{{ row.created_at }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="300" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="editWebhook(row)">编辑</el-button>
                                <el-button size="small" type="success" @click="handleTest(row)">测试</el-button>
                                <el-button size="small" type="primary" @click="handleSendTest(row)">发测试</el-button>
                                <el-popconfirm title="确认删除?" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- 手动发送区域 -->
                <el-card class="manual-send-card">
                    <template #header><span>手动发送</span></template>
                    <el-row :gutter="20">
                        <el-col :span="12">
                            <el-card shadow="never" class="manual-item">
                                <template #header><el-space><span>发送激活通知</span><el-tag size="small">activation</el-tag></el-space></template>
                                <el-form size="small" label-position="top">
                                    <el-form-item label="License Key"><el-input v-model="activationForm.license_key" placeholder="LIC-XXXX" /></el-form-item>
                                    <el-form-item label="产品名称"><el-input v-model="activationForm.product_name" placeholder="标准版" /></el-form-item>
                                    <el-form-item label="客户名称"><el-input v-model="activationForm.customer_name" placeholder="张三" /></el-form-item>
                                    <el-button type="primary" size="small" :loading="sendingActivation" @click="handleSendActivation">发送</el-button>
                                </el-form>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never" class="manual-item">
                                <template #header><el-space><span>发送告警通知</span><el-tag size="small">alert</el-tag></el-space></template>
                                <el-form size="small" label-position="top">
                                    <el-form-item label="标题"><el-input v-model="alertForm.title" placeholder="系统异常告警" /></el-form-item>
                                    <el-form-item label="消息内容"><el-input v-model="alertForm.message" type="textarea" :rows="2" placeholder="详细描述" /></el-form-item>
                                    <el-form-item label="严重程度">
                                        <el-select v-model="alertForm.severity" style="width:120px">
                                            <el-option label="信息" value="info" />
                                            <el-option label="警告" value="warning" />
                                            <el-option label="严重" value="critical" />
                                        </el-select>
                                    </el-form-item>
                                    <el-button type="danger" size="small" :loading="sendingAlert" @click="handleSendAlert">发送</el-button>
                                </el-form>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="发送日志" name="logs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>通知发送日志</span>
                            <el-select v-model="logStatusFilter" placeholder="状态" clearable size="small" style="width:120px" @change="loadLogs">
                                <el-option label="全部" value="" />
                                <el-option label="成功" value="success" />
                                <el-option label="失败" value="failed" />
                            </el-select>
                            <el-select v-model="logTypeFilter" placeholder="类型" clearable size="small" style="width:140px" @change="loadLogs">
                                <el-option label="全部" value="" />
                                <el-option label="激活通知" value="activation" />
                                <el-option label="告警通知" value="alert" />
                                <el-option label="过期提醒" value="expiry" />
                                <el-option label="测试" value="test" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="logs" stripe v-loading="logsLoading">
                        <el-table-column prop="created_at" label="时间" width="160" />
                        <el-table-column label="类型" width="90">
                            <template #default="{ row }">{{ typeLabel(row.notification_type) }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status === 'success' ? '成功' : '失败' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="title" label="标题" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="message" label="消息" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="http_status" label="HTTP" width="70" />
                        <el-table-column prop="error_message" label="错误" min-width="200" show-overflow-tooltip />
                    </el-table>
                    <el-pagination v-if="logTotal > logPerPage" v-model:current-page="logPage" :page-size="logPerPage" :total="logTotal" layout="prev, pager, next" @current-change="loadLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 新建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑 Webhook' : '新建 Teams Webhook'" width="550px">
            <el-form label-position="top" size="small" :model="form">
                <el-form-item label="频道名称" required>
                    <el-input v-model="form.name" placeholder="如：销售团队告警频道" />
                </el-form-item>
                <el-form-item label="Webhook URL" required>
                    <el-input v-model="form.webhook_url" placeholder="https://your-org.webhook.office.com/webhookb2/..." />
                    <div style="font-size:12px;color:#909399;margin-top:4px">
                        在 Teams 频道 → 连接器 → Incoming Webhook 获取 URL
                    </div>
                </el-form-item>
                <el-form-item label="通知类型" required>
                    <el-select v-model="form.notification_type" style="width:100%">
                        <el-option v-for="nt in notificationTypes" :key="nt.key" :label="nt.label" :value="nt.key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="form.description" type="textarea" :rows="2" placeholder="可选" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveWebhook">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import teamsNotifier from '@/api/teamsNotifier';

const activeTab = ref('webhooks');
const loading = ref(false);
const logsLoading = ref(false);
const saving = ref(false);
const sendingActivation = ref(false);
const sendingAlert = ref(false);
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const logStatusFilter = ref('');
const logTypeFilter = ref('');

const dash = reactive({ stats: { total: 0, active: 0, today_total: 0, today_success: 0, today_failed: 0 } });
const webhooks = ref([]);
const logs = ref([]);
const notificationTypes = ref([]);

const form = reactive({
    name: '', webhook_url: '', notification_type: 'all', is_active: true, description: '',
});

const activationForm = reactive({ license_key: '', product_name: '', customer_name: '' });
const alertForm = reactive({ title: '', message: '', severity: 'warning' });

const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);

function typeLabel(type) {
    const map = { all: '全部', activation: '激活通知', alert: '告警通知', expiry: '过期提醒', test: '测试' };
    return map[type] || type;
}

async function loadConfig() {
    try { const res = await teamsNotifier.config(); notificationTypes.value = res.data.data.notification_types; } catch (e) { console.error(e); }
}

async function loadDashboard() {
    try { const res = await teamsNotifier.dashboard(); Object.assign(dash, res.data.data); } catch (e) { console.error(e); }
}

async function loadWebhooks() {
    loading.value = true;
    try { const res = await teamsNotifier.list(); webhooks.value = res.data.data || []; } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function loadLogs(page) {
    logsLoading.value = true;
    try {
        const params = { page: page || logPage.value, per_page: logPerPage.value };
        if (logStatusFilter.value) params.status = logStatusFilter.value;
        if (logTypeFilter.value) params.notification_type = logTypeFilter.value;
        const res = await teamsNotifier.logs(params);
        logs.value = res.data.data.items || [];
        logTotal.value = res.data.data.total;
        logPage.value = res.data.data.page;
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

function openCreateDialog() {
    isEditing.value = false; editingId.value = null;
    form.name = ''; form.webhook_url = ''; form.notification_type = 'all';
    form.is_active = true; form.description = '';
    dialogVisible.value = true;
}

function editWebhook(row) {
    isEditing.value = true; editingId.value = row.id;
    form.name = row.name; form.webhook_url = row.webhook_url;
    form.notification_type = row.notification_type; form.is_active = row.is_active;
    form.description = row.description || '';
    dialogVisible.value = true;
}

async function saveWebhook() {
    if (!form.name || !form.webhook_url) { ElMessage.warning('请填写频道名称和 Webhook URL'); return; }
    saving.value = true;
    try {
        const data = { ...form };
        if (isEditing.value) {
            await teamsNotifier.update(editingId.value, data);
            ElMessage.success('已更新');
        } else {
            await teamsNotifier.create(data);
            ElMessage.success('已创建');
        }
        dialogVisible.value = false;
        loadWebhooks();
    } catch (e) { console.error(e); } finally { saving.value = false; }
}

async function handleTest(row) {
    try {
        const res = await teamsNotifier.test(row.id);
        ElMessage.success(res.data.message || '连接测试成功');
    } catch (e) { console.error(e); }
}

async function handleSendTest(row) {
    try {
        await teamsNotifier.sendTest(row.id);
        ElMessage.success('测试消息已发送到 Teams');
    } catch (e) { console.error(e); }
}

async function handleDelete(row) {
    try { await teamsNotifier.destroy(row.id); ElMessage.success('已删除'); loadWebhooks(); } catch (e) { console.error(e); }
}

async function handleSendActivation() {
    if (!activationForm.license_key || !activationForm.product_name || !activationForm.customer_name) {
        ElMessage.warning('请填写完整信息'); return;
    }
    sendingActivation.value = true;
    try {
        const res = await teamsNotifier.sendActivation({ ...activationForm });
        ElMessage.success(`已发送 (成功 ${res.data.data.sent} / 失败 ${res.data.data.failed})`);
    } catch (e) { console.error(e); } finally { sendingActivation.value = false; }
}

async function handleSendAlert() {
    if (!alertForm.title || !alertForm.message) { ElMessage.warning('请填写标题和消息'); return; }
    sendingAlert.value = true;
    try {
        const res = await teamsNotifier.sendAlert({ ...alertForm });
        ElMessage.success(`已发送 (成功 ${res.data.data.sent} / 失败 ${res.data.data.failed})`);
    } catch (e) { console.error(e); } finally { sendingAlert.value = false; }
}

onMounted(() => { loadConfig(); loadDashboard(); loadWebhooks(); });
</script>

<style scoped>
.teams-notifier-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
.manual-send-card { margin-top: 16px; }
.manual-item { border: 1px solid #ebeef5; }
</style>
