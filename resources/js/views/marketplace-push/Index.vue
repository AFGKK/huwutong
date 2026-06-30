<template>
    <div class="push-page">
        <div class="page-header">
            <div>
                <h2>市场推送管理</h2>
                <p class="text-muted">创建和管理应用市场推送活动</p>
            </div>
            <el-button type="primary" @click="openCreateDialog"><el-icon><Plus /></el-icon> 新建推送</el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4"><el-card shadow="never"><div class="stat-value">{{ stats.total_campaigns || 0 }}</div><div class="stat-label">总活动</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value primary">{{ stats.total_sent || 0 }}</div><div class="stat-label">已发送</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value success">{{ stats.total_read || 0 }}</div><div class="stat-label">已阅读</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value warning">{{ stats.scheduled_count || 0 }}</div><div class="stat-label">待发送</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value">{{ stats.draft_count || 0 }}</div><div class="stat-label">草稿</div></el-card></el-col>
        </el-row>

        <el-card shadow="never">
            <div class="toolbar">
                <el-select v-model="filter.status" clearable placeholder="状态" style="width:130px" @change="loadCampaigns">
                    <el-option label="全部" value="" />
                    <el-option label="草稿" value="draft" />
                    <el-option label="定时" value="scheduled" />
                    <el-option label="发送中" value="sending" />
                    <el-option label="已发送" value="sent" />
                    <el-option label="已取消" value="cancelled" />
                </el-select>
            </div>

            <el-table :data="campaigns" v-loading="loading" stripe>
                <el-table-column label="标题" prop="title" min-width="200" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }"><el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="目标" width="120">
                    <template #default="{ row }">{{ targetLabel(row.target_type) }}</template>
                </el-table-column>
                <el-table-column label="发送/阅读" width="120">
                    <template #default="{ row }">{{ row.sent_count }}/{{ row.read_count }}</template>
                </el-table-column>
                <el-table-column label="目标数" width="80" prop="target_count" align="center" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="160">
                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" text size="small" type="primary" @click="openEditDialog(row)">编辑</el-button>
                        <el-button v-if="row.status === 'draft'" text size="small" type="success" @click="handleSend(row)">发送</el-button>
                        <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" text size="small" type="danger" @click="handleCancel(row)">取消</el-button>
                        <el-button v-if="row.status === 'sent'" text size="small" @click="showDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'draft'" text size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建/编辑 Dialog -->
        <el-dialog v-model="dialogVisible" :title="editingId ? '编辑推送' : '新建推送'" width="580px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
                <el-form-item label="标题" prop="title">
                    <el-input v-model="form.title" maxlength="200" placeholder="推送标题" />
                </el-form-item>
                <el-form-item label="内容" prop="content">
                    <el-input v-model="form.content" type="textarea" :rows="4" maxlength="2000" placeholder="推送内容" show-word-limit />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="类型" prop="type">
                            <el-select v-model="form.type" style="width:100%">
                                <el-option label="营销推广" value="marketing" />
                                <el-option label="版本更新" value="update" />
                                <el-option label="促销活动" value="promo" />
                                <el-option label="系统通知" value="info" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="目标用户" prop="target_type">
                            <el-select v-model="form.target_type" style="width:100%" @change="onTargetChange">
                                <el-option label="全部用户" value="all" />
                                <el-option label="已安装某应用" value="installed_app" />
                                <el-option label="某分类用户" value="category" />
                                <el-option label="指定应用用户" value="specific_app" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item v-if="showAppSelector" label="选择应用" prop="target_app_id">
                    <el-select v-model="form.target_app_id" filterable style="width:100%">
                        <el-option v-for="a in availableApps" :key="a.id" :label="a.name" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.target_type === 'category'" label="选择分类" prop="target_category">
                    <el-select v-model="form.target_category" style="width:100%">
                        <el-option v-for="(label, val) in categoryMap" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="链接类型">
                            <el-select v-model="form.link_type" clearable placeholder="无链接" style="width:100%">
                                <el-option label="应用" value="app" />
                                <el-option label="URL" value="url" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item v-if="form.link_type" :label="form.link_type === 'app' ? '应用 ID' : '链接地址'">
                            <el-input v-model="form.link_value" :placeholder="form.link_type === 'app' ? '输入应用 ID' : 'https://...'" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="定时发送">
                    <el-date-picker v-model="form.scheduled_at" type="datetime" placeholder="立即发送" style="width:100%" :disabled-date="d => d < new Date()" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">{{ editingId ? '保存' : '创建' }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="detailVisible" title="推送详情" width="500px">
            <div v-if="detail">
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item label="标题">{{ detail.title }}</el-descriptions-item>
                    <el-descriptions-item label="内容">{{ detail.content }}</el-descriptions-item>
                    <el-descriptions-item label="类型"><el-tag :type="typeTag(detail.type)" size="small">{{ typeLabel(detail.type) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item label="目标">{{ targetLabel(detail.target_type) }}</el-descriptions-item>
                    <el-descriptions-item label="状态"><el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item label="目标数">{{ detail.target_count }}</el-descriptions-item>
                    <el-descriptions-item label="已发送">{{ detail.sent_count }}</el-descriptions-item>
                    <el-descriptions-item label="已阅读">{{ detail.read_count }}</el-descriptions-item>
                    <el-descriptions-item label="发送时间">{{ fmtDate(detail.sent_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="完成时间">{{ fmtDate(detail.completed_at) || '-' }}</el-descriptions-item>
                </el-descriptions>
            </div>
            <template #footer><el-button @click="detailVisible = false">关闭</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/marketplacePush';
import appApi from '@/api/openPlatform';

const loading = ref(false);
const campaigns = ref([]);
const stats = ref({});
const filter = reactive({ status: '' });
const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref(null);
const formRef = ref(null);
const detailVisible = ref(false);
const detail = ref(null);
const availableApps = ref([]);

const showAppSelector = computed(() =>
    ['installed_app', 'specific_app'].includes(form.target_type)
);

const categoryMap = {
    integration: '集成扩展', automation: '自动化', analytics: '数据分析',
    security: '安全合规', billing: '计费财务', other: '其他',
};

const form = reactive({
    title: '', content: '', type: 'marketing', target_type: 'all',
    target_app_id: null, target_category: null,
    link_type: '', link_value: '', scheduled_at: null,
});

const formRules = {
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
    content: [{ required: true, message: '请输入内容', trigger: 'blur' }],
    type: [{ required: true, message: '请选择类型', trigger: 'change' }],
    target_type: [{ required: true, message: '请选择目标用户', trigger: 'change' }],
};

function onTargetChange() {
    form.target_app_id = null;
    form.target_category = null;
    if (showAppSelector.value) loadAvailableApps();
}

async function loadAvailableApps() {
    try { const { data: r } = await appApi.apps({ per_page: 100 }); availableApps.value = r.data || []; } catch {}
}

async function loadCampaigns() {
    loading.value = true;
    try {
        const params = { per_page: 50 };
        if (filter.status) params.status = filter.status;
        const { data: r } = await api.campaigns(params);
        campaigns.value = r.data || [];
    } catch { campaigns.value = []; }
    finally { loading.value = false; }
}

async function loadStats() {
    try { const { data: r } = await api.stats(); if (r.success) stats.value = r.data; } catch {}
}

function openCreateDialog() {
    editingId.value = null;
    form.title = ''; form.content = ''; form.type = 'marketing';
    form.target_type = 'all'; form.target_app_id = null;
    form.target_category = null; form.link_type = '';
    form.link_value = ''; form.scheduled_at = null;
    dialogVisible.value = true;
}

function openEditDialog(row) {
    editingId.value = row.id;
    Object.assign(form, {
        title: row.title, content: row.content, type: row.type,
        target_type: row.target_type, target_app_id: row.target_app_id,
        target_category: row.target_category, link_type: row.link_type || '',
        link_value: row.link_value || '', scheduled_at: row.scheduled_at || null,
    });
    dialogVisible.value = true;
    if (showAppSelector.value) loadAvailableApps();
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;
    submitting.value = true;
    try {
        const payload = { ...form, link_type: form.link_type || null, link_value: form.link_value || null, scheduled_at: form.scheduled_at || null };
        if (editingId.value) {
            await api.campaignUpdate(editingId.value, payload);
            ElMessage.success('已更新');
        } else {
            await api.campaignCreate(payload);
            ElMessage.success('已创建');
        }
        dialogVisible.value = false;
        loadCampaigns();
        loadStats();
    } catch {} finally { submitting.value = false; }
}

async function handleSend(row) {
    try {
        await ElMessageBox.confirm(`确认发送推送「${row.title}」给 ${row.target_count} 名用户？`, '确认发送');
        await api.campaignSend(row.id);
        ElMessage.success('推送已发送');
        loadCampaigns();
        loadStats();
    } catch {}
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(`确认取消推送「${row.title}」？`, '确认取消', { type: 'warning' });
        await api.campaignCancel(row.id);
        ElMessage.success('已取消');
        loadCampaigns();
    } catch {}
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确认删除「${row.title}」？`, '确认删除', { type: 'warning' });
        await api.campaignDelete(row.id);
        ElMessage.success('已删除');
        loadCampaigns();
    } catch {}
}

function showDetail(row) {
    detail.value = row;
    detailVisible.value = true;
}

function typeTag(t) { return { marketing: '', update: 'primary', promo: 'warning', info: 'info' }[t] || ''; }
function typeLabel(t) { return { marketing: '营销', update: '更新', promo: '促销', info: '通知' }[t] || t; }
function targetLabel(t) { return { all: '全部用户', installed_app: '安装应用用户', category: '分类用户', specific_app: '指定应用用户' }[t] || t; }
function statusTag(s) { return { draft: 'info', scheduled: 'warning', sending: 'warning', sent: 'success', cancelled: 'danger' }[s] || ''; }
function statusLabel(s) { return { draft: '草稿', scheduled: '定时', sending: '发送中', sent: '已发送', cancelled: '已取消' }[s] || s; }
function fmtDate(d) { if (!d) return '-'; return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }

onMounted(() => { loadCampaigns(); loadStats(); });
</script>

<style scoped>
.push-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.stat-value { font-size: 22px; font-weight: 600; color: #303133; }
.stat-value.primary { color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
