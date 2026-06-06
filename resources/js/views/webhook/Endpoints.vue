<template>
    <div class="webhook-endpoints-page">
        <div class="page-header">
            <div>
                <h2>Webhook 端点管理</h2>
                <p class="header-desc">配置 Webhook 端点，订阅 License 事件推送</p>
            </div>
            <el-button type="primary" @click="showCreateDialog">
                <el-icon><Plus /></el-icon>新建端点
            </el-button>
        </div>

        <el-alert
            title="Webhook 端点接收推送事件通知。支持签名验证（HMAC-SHA256）、自动重试（1s/5s/30s/5m/30m/2h）、熔断保护（连续 10 次失败自动暂停）。"
            type="info" show-icon :closable="false" class="alert-bar"
        />

        <el-table :data="endpoints" v-loading="loading" stripe>
            <el-table-column label="名称" min-width="150" prop="name" />
            <el-table-column label="URL" min-width="250" prop="url">
                <template #default="{ row }">
                    <code class="url-text">{{ row.url }}</code>
                </template>
            </el-table-column>
            <el-table-column label="事件" min-width="180">
                <template #default="{ row }">
                    <el-tag
                        v-for="evt in (row.events || [])" :key="evt"
                        size="small"
                        class="mr-1"
                    >{{ evt === '*' ? '全部' : evt }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.is_paused" type="warning" size="small">已暂停</el-tag>
                    <el-tag v-else-if="row.is_active" type="success" size="small">运行中</el-tag>
                    <el-tag v-else type="info" size="small">已停用</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="事件数" width="80" prop="events_count" align="center" />
            <el-table-column label="操作" width="260" fixed="right">
                <template #default="{ row }">
                    <el-button text size="small" type="primary" @click="showEditDialog(row)">编辑</el-button>
                    <el-button
                        text size="small"
                        :type="row.is_paused ? 'success' : 'warning'"
                        :loading="togglingId === row.id"
                        @click="handleTogglePause(row)"
                    >
                        {{ row.is_paused ? '恢复' : '暂停' }}
                    </el-button>
                    <el-button
                        text size="small" type="info"
                        :loading="testingId === row.id"
                        @click="handleTest(row)"
                    >测试</el-button>
                    <el-popconfirm
                        title="确定停用该端点？"
                        @confirm="handleDelete(row)"
                    >
                        <template #reference>
                            <el-button text size="small" type="danger">删除</el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>

        <!-- 创建/编辑对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? '编辑 Webhook 端点' : '新建 Webhook 端点'"
            width="640px"
        >
            <el-form :model="form" label-position="top" size="small" ref="formRef">
                <el-form-item label="名称" prop="name" :rules="[{ required: true, message: '请输入名称' }]">
                    <el-input v-model="form.name" placeholder="如：生产环境推送" />
                </el-form-item>
                <el-form-item label="目标 URL" prop="url" :rules="[{ required: true, message: '请输入 URL' }]">
                    <el-input v-model="form.url" placeholder="https://api.example.com/webhooks/hwt" />
                </el-form-item>
                <el-form-item label="订阅事件" prop="events" :rules="[{ required: true, message: '请选择至少一个事件' }]">
                    <el-select v-model="form.events" multiple filterable style="width: 100%">
                        <el-option
                            v-for="t in eventTypeOptions"
                            :key="t.value"
                            :label="t.label"
                            :value="t.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="签名密钥（留空自动生成）">
                    <el-input v-model="form.secret" type="password" show-password placeholder="至少 16 位字符，留空自动生成" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import webhookEndpointApi from '@/api/webhookEndpoint';

const loading = ref(false);
const saving = ref(false);
const endpoints = ref([]);
const eventTypeOptions = ref([]);
const dialogVisible = ref(false);
const isEditing = ref(false);
const togglingId = ref(null);
const testingId = ref(null);
const formRef = ref(null);

const form = reactive({
    id: null,
    name: '',
    url: '',
    events: [],
    secret: '',
});

async function loadEndpoints() {
    loading.value = true;
    try {
        const { data: res } = await webhookEndpointApi.list();
        if (res.success) endpoints.value = res.data?.data || [];
    } finally {
        loading.value = false;
    }
}

async function loadEventTypes() {
    try {
        const { data: res } = await webhookEndpointApi.eventTypes();
        if (res.success) eventTypeOptions.value = res.data || [];
    } catch {
        // ignore
    }
}

function resetForm() {
    form.id = null;
    form.name = '';
    form.url = '';
    form.events = [];
    form.secret = '';
}

function showCreateDialog() {
    isEditing.value = false;
    resetForm();
    dialogVisible.value = true;
}

function showEditDialog(endpoint) {
    isEditing.value = true;
    form.id = endpoint.id;
    form.name = endpoint.name;
    form.url = endpoint.url;
    form.events = endpoint.events || [];
    form.secret = '';
    dialogVisible.value = true;
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const payload = {
            name: form.name,
            url: form.url,
            events: form.events,
        };
        if (form.secret) payload.secret = form.secret;

        let res;
        if (isEditing.value) {
            res = await webhookEndpointApi.update(form.id, payload);
        } else {
            res = await webhookEndpointApi.create(payload);
        }

        if (res.data.success) {
            ElMessage.success(isEditing.value ? '端点已更新' : '端点创建成功');
            dialogVisible.value = false;
            await loadEndpoints();
        }
    } catch {
        ElMessage.error('保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleTogglePause(endpoint) {
    togglingId.value = endpoint.id;
    try {
        const { data: res } = await webhookEndpointApi.togglePause(endpoint.id);
        if (res.success) {
            ElMessage.success(res.data?.is_paused ? '端点已暂停' : '端点已恢复');
            await loadEndpoints();
        }
    } catch {
        ElMessage.error('操作失败');
    } finally {
        togglingId.value = null;
    }
}

async function handleTest(endpoint) {
    testingId.value = endpoint.id;
    try {
        const { data: res } = await webhookEndpointApi.test(endpoint.id);
        if (res.success && res.data) {
            const result = res.data;
            if (result.success) {
                ElMessage.success(`连接成功！状态码: ${result.status_code}，延迟: ${result.latency_ms}ms`);
            } else {
                ElMessage.warning(`连接失败: ${result.error}（${result.latency_ms}ms）`);
            }
        }
    } catch {
        ElMessage.error('测试请求失败');
    } finally {
        testingId.value = null;
    }
}

async function handleDelete(endpoint) {
    try {
        const { data: res } = await webhookEndpointApi.destroy(endpoint.id);
        if (res.success) {
            ElMessage.success('端点已停用');
            endpoints.value = endpoints.value.filter(e => e.id !== endpoint.id);
        }
    } catch {
        ElMessage.error('删除失败');
    }
}

onMounted(() => {
    loadEndpoints();
    loadEventTypes();
});
</script>

<style scoped>
.webhook-endpoints-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 16px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.header-desc {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.alert-bar { margin-bottom: 16px; }
.url-text {
    font-size: 12px;
    word-break: break-all;
}
.mr-1 { margin-right: 4px; }
</style>
