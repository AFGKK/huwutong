<template>
    <div class="public-key-page">
        <div class="page-header">
            <div>
                <h2>离线公钥版本管理</h2>
                <p class="text-muted">公钥版本管理 · 密钥轮换 · CRL 吊销列表 · 兼容窗口期 · CDN 分发</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button type="primary" @click="showCreateForm = true" :icon="Plus">新建密钥</el-button>
                <el-button @click="testSigningDialog" :icon="CircleCheck">签名测试</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">密钥版本</div><div class="metric-value">{{ stats.total_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">活跃版本</div><div class="metric-value success">{{ stats.active_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">兼容窗口</div><div class="metric-value warning">{{ stats.compat_mode_versions || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">吊销</div><div class="metric-value danger">{{ stats.revoked_versions }}</div></el-card></el-col>
        </el-row>

        <!-- 轮换提醒 -->
        <el-alert v-if="rotationAlert" :title="rotationAlert" type="warning" show-icon :closable="true" class="mb-4" />

        <!-- 版本列表 -->
        <el-card shadow="hover">
            <template #header><span><el-icon><Key /></el-icon> 公钥版本列表</span></template>
            <el-table :data="versions" stripe v-loading="listLoading" size="small">
                <el-table-column prop="key_version" label="版本" width="70" />
                <el-table-column prop="algorithm" label="算法" width="90" />
                <el-table-column prop="public_key_preview" label="公钥" min-width="200" show-overflow-tooltip>
                    <template #default="{row}"><code style="font-size:11px">{{ row.public_key?.substring(0, 40) }}...</code></template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{row}">
                        <el-tag v-if="row.is_active && !row.is_revoked" type="success" size="small">活跃</el-tag>
                        <el-tag v-else-if="row.is_revoked" type="danger" size="small">已吊销</el-tag>
                        <el-tag v-else-if="row.is_compat_mode" type="warning" size="small">兼容期</el-tag>
                        <el-tag v-else type="info" size="small">过期</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="兼容窗口" width="90">
                    <template #default="{row}">
                        <el-tag v-if="row.is_compat_mode" type="warning" size="small">是</el-tag>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column label="激活时间" width="150"><template #default="{row}">{{ fmtTime(row.activated_at) }}</template></el-table-column>
                <el-table-column label="过期时间" width="150"><template #default="{row}">{{ fmtTime(row.expires_at) }}</template></el-table-column>
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{row}">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button v-if="!row.is_revoked && !row.is_active" size="small" type="warning" @click="showRevokeDialog(row)">吊销</el-button>
                        <el-button v-if="row.is_revoked" size="small" disabled>已吊销</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!versions.length && !listLoading" description="暂无密钥版本" />
        </el-card>

        <!-- 创建对话框 -->
        <el-dialog v-model="showCreateForm" title="新建公钥版本" width="500px">
            <el-form :model="createForm" label-width="120px">
                <el-form-item label="算法" :rules="[{required:true}]">
                    <el-select v-model="createForm.algorithm" style="width:100%">
                        <el-option label="Ed25519" value="ED25519" />
                        <el-option label="RSA 2048" value="RSA2048" />
                    </el-select>
                </el-form-item>
                <el-form-item label="公钥 (Base64)"><el-input v-model="createForm.public_key" type="textarea" :rows="4" placeholder="留空自动生成" /></el-form-item>
                <el-form-item label="PEM 格式"><el-input v-model="createForm.public_key_pem" type="textarea" :rows="3" placeholder="可选" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateForm = false">取消</el-button>
                <el-button type="primary" @click="submitCreate" :loading="saving">创建</el-button>
            </template>
        </el-dialog>

        <!-- 吊销对话框 -->
        <el-dialog v-model="showRevokeForm" title="吊销公钥版本" width="450px">
            <p>确认吊销版本 <strong>{{ revokeTarget?.key_version }}</strong>（{{ revokeTarget?.algorithm }}）？</p>
            <el-form :model="revokeForm">
                <el-form-item label="吊销原因"><el-input v-model="revokeForm.reason" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRevokeForm = false">取消</el-button>
                <el-button type="danger" @click="submitRevoke" :loading="revoking">确认吊销</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailForm" :title="'公钥版本 #' + (detail?.key_version || '')" width="650px">
            <el-descriptions :column="2" border size="small" v-if="detail">
                <el-descriptions-item label="版本">{{ detail.key_version }}</el-descriptions-item>
                <el-descriptions-item label="算法">{{ detail.algorithm }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="detail.is_active ? 'success' : (detail.is_revoked ? 'danger' : 'info')" size="small">
                        {{ detail.is_active ? '活跃' : (detail.is_revoked ? '已吊销' : '非活跃') }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="兼容模式">
                    <el-tag v-if="detail.is_compat_mode" type="warning" size="small">是</el-tag>
                    <span v-else>否</span>
                </el-descriptions-item>
                <el-descriptions-item label="激活时间">{{ fmtTime(detail.activated_at) }}</el-descriptions-item>
                <el-descriptions-item label="过期时间">{{ fmtTime(detail.expires_at) }}</el-descriptions-item>
                <el-descriptions-item label="吊销时间">{{ fmtTime(detail.revoked_at) || '—' }}</el-descriptions-item>
                <el-descriptions-item label="吊销原因">{{ detail.revoke_reason || '—' }}</el-descriptions-item>
                <el-descriptions-item label="公钥" :span="2"><code style="font-size:11px;word-break:break-all">{{ detail.public_key }}</code></el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Plus, CircleCheck, Key } from '@element-plus/icons-vue';
import publicKeyApi from '@/api/publicKey';

const loading = ref(false);
const saving = ref(false);
const revoking = ref(false);
const listLoading = ref(false);
const showCreateForm = ref(false);
const showRevokeForm = ref(false);
const showDetailForm = ref(false);
const revokeTarget = ref(null);
const detail = ref(null);

const stats = reactive({ total_versions: 0, active_versions: 0, revoked_versions: 0, compat_mode_versions: 0 });
const versions = ref([]);
const rotationAlert = ref('');
const createForm = reactive({ algorithm: 'ED25519', public_key: '', public_key_pem: '' });
const revokeForm = reactive({ reason: '' });

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadStats(), loadVersions(), loadRotationCheck()]); } finally { loading.value = false; }
}
async function loadStats() {
    try { const r = await publicKeyApi.stats(); Object.assign(stats, r.data?.data || {}); } catch {}
}
async function loadVersions() {
    listLoading.value = true;
    try { const r = await publicKeyApi.index(); versions.value = r.data?.data || []; } finally { listLoading.value = false; }
}
async function loadRotationCheck() {
    try {
        const r = await publicKeyApi.rotationCheck();
        const d = r.data?.data;
        if (d?.needs_rotation) rotationAlert.value = `⚠️ 建议轮换密钥: ${d.reason}`;
        else rotationAlert.value = '';
    } catch {}
}

async function submitCreate() {
    saving.value = true;
    try {
        await publicKeyApi.store(createForm);
        ElMessage.success('密钥版本已创建'); showCreateForm.value = false; loadAll();
    } catch { ElMessage.error('创建失败'); } finally { saving.value = false; }
}

function showRevokeDialog(row) {
    revokeTarget.value = row;
    revokeForm.reason = '';
    showRevokeForm.value = true;
}
async function submitRevoke() {
    revoking.value = true;
    try {
        await publicKeyApi.revoke(revokeTarget.value.key_version, { reason: revokeForm.reason });
        ElMessage.success('已吊销'); showRevokeForm.value = false; loadAll();
    } catch { ElMessage.error('吊销失败'); } finally { revoking.value = false; }
}

async function showDetail(row) {
    try {
        const r = await publicKeyApi.show(row.key_version);
        detail.value = r.data?.data; showDetailForm.value = true;
    } catch { ElMessage.error('加载失败'); }
}

async function testSigningDialog() {
    const { value } = await ElMessageBox.prompt('输入待签名数据（Base64）', '签名测试');
    if (value) {
        try {
            const r = await publicKeyApi.testSigning({ data: value });
            ElMessage.success(`签名测试通过: ${r.data?.data?.result || '成功'}`);
        } catch { ElMessage.error('签名测试失败'); }
    }
}

function fmtTime(t) { if (!t) return '—'; return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
</script>

<style scoped>
.public-key-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
</style>
