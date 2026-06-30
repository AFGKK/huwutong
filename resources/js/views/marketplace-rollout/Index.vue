<template>
    <div class="rollout-page">
        <div class="page-header">
            <div>
                <h2>灰度发布</h2>
                <p class="text-muted">应用新版本分阶段发布、自动回滚与全量上线</p>
            </div>
            <el-button type="primary" @click="showCreate = true">
                <el-icon><Plus /></el-icon> 新建灰度发布
            </el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="rollouts" v-loading="loading" stripe>
                <el-table-column label="应用" min-width="200">
                    <template #default="{ row }">
                        <div class="app-cell">
                            <el-avatar :src="row.app?.icon_url" size="small" shape="square" />
                            <span>{{ row.app?.name || '-' }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="发布名称" prop="name" min-width="180" />
                <el-table-column label="版本" width="100">
                    <template #default="{ row }">{{ row.version?.version || '-' }}</template>
                </el-table-column>
                <el-table-column label="百分比" width="80" align="center">
                    <template #default="{ row }">{{ row.percentage }}%</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="分配/安装" width="120" align="center">
                    <template #default="{ row }">{{ row.assigned_count }}/{{ row.installed_count }}</template>
                </el-table-column>
                <el-table-column label="错误率" width="80" align="center">
                    <template #default="{ row }">
                        <span :class="{ 'text-danger': row.error_count > 0 }">{{ row.error_count }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="160">
                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="300" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'draft'" size="small" @click="editRollout(row)">编辑</el-button>
                        <el-button v-if="row.status === 'draft'" type="primary" size="small" @click="handleStart(row)">启动</el-button>
                        <el-button v-if="row.status === 'active'" size="small" @click="handlePause(row)">暂停</el-button>
                        <el-button v-if="row.status === 'active' || row.status === 'paused'" type="success" size="small" @click="handleComplete(row)">完成</el-button>
                        <el-button v-if="row.status === 'active' || row.status === 'paused'" type="danger" size="small" @click="handleRollback(row)">回滚</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!rollouts.length && !loading" class="empty">
                <el-empty description="暂无灰度发布记录" :image-size="60" />
            </div>

            <div v-if="total > 0" class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    :page-size="perPage"
                    :total="total"
                    layout="prev, pager, next, total"
                    @current-change="loadRollouts"
                />
            </div>
        </el-card>

        <!-- 创建/编辑 Dialog -->
        <el-dialog v-model="showCreate" :title="editingId ? '编辑灰度发布' : '新建灰度发布'" width="650px" :close-on-click-modal="false">
            <el-form :model="form" label-width="120px" v-loading="formLoading">
                <el-form-item label="目标应用" required v-if="!editingId">
                    <el-select v-model="form.app_id" filterable style="width:100%" @change="onAppChange">
                        <el-option v-for="app in availableApps" :key="app.id" :label="`${app.name} (v${app.current_version || '-'})`" :value="app.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="目标版本" required v-if="!editingId">
                    <el-select v-model="form.version_id" filterable style="width:100%">
                        <el-option v-for="v in filteredVersions" :key="v.id" :label="`v${v.version}`" :value="v.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="发布名称" required>
                    <el-input v-model="form.name" placeholder="如：v1.2 灰度发布" maxlength="200" />
                </el-form-item>
                <el-form-item label="发布说明">
                    <el-input v-model="form.description" type="textarea" :rows="3" maxlength="2000" />
                </el-form-item>
                <el-form-item label="发布方式">
                    <el-radio-group v-model="form.rollout_type">
                        <el-radio value="percentage">百分比</el-radio>
                        <el-radio value="tenant_group">指定租户</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="form.rollout_type === 'percentage'" label="灰度比例">
                    <el-slider v-model="form.percentage" :min="1" :max="100" show-input style="width:300px" />
                </el-form-item>
                <el-form-item v-if="form.rollout_type === 'tenant_group'" label="包含租户">
                    <el-select v-model="form.tenant_ids" multiple filterable remote :remote-method="searchTenants" style="width:100%">
                        <el-option v-for="t in tenantOptions" :key="t.id" :label="`${t.name} (${t.domain || '-'})`" :value="t.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="排除租户">
                    <el-select v-model="form.excluded_tenant_ids" multiple filterable remote :remote-method="searchTenantsExclude" style="width:100%">
                        <el-option v-for="t in tenantExcludeOptions" :key="t.id" :label="`${t.name} (${t.domain || '-'})`" :value="t.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="自动回滚">
                    <el-switch v-model="form.auto_rollback" />
                    <span class="text-muted ml-2">错误率超过阈值时自动回滚</span>
                </el-form-item>
                <el-form-item v-if="form.auto_rollback" label="错误阈值">
                    <el-input-number v-model="form.error_threshold" :min="0.1" :max="100" :step="0.5" :precision="1" /> %
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveRollout">保存</el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="showDetail" title="灰度发布详情" width="750px">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="应用">{{ detail.app?.name }}</el-descriptions-item>
                    <el-descriptions-item label="版本">v{{ detail.version?.version }}</el-descriptions-item>
                    <el-descriptions-item label="发布名称" :span="2">{{ detail.name }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="灰度比例">{{ detail.percentage }}%</el-descriptions-item>
                    <el-descriptions-item label="分配/安装">{{ detail.assigned_count }}/{{ detail.installed_count }}</el-descriptions-item>
                    <el-descriptions-item label="错误数">
                        <span :class="{ 'text-danger': detail.error_count > 0 }">{{ detail.error_count }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="创建者">{{ detail.creator?.name }}</el-descriptions-item>
                    <el-descriptions-item label="开始时间">{{ fmtDate(detail.started_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.completed_at" label="完成时间">{{ fmtDate(detail.completed_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.rolled_back_at" label="回滚时间">{{ fmtDate(detail.rolled_back_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.description" label="说明" :span="2">{{ detail.description }}</el-descriptions-item>
                </el-descriptions>

                <!-- Stats -->
                <el-row :gutter="16" class="mt-4" v-if="detailStats">
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ detailStats.progress }}%</div><div class="stat-label">安装进度</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value" :class="{ 'text-danger': detailStats.error_rate > 0 }">{{ detailStats.error_rate }}%</div><div class="stat-label">错误率</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ detailStats.event_counts?.assigned || 0 }}</div><div class="stat-label">已分配</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ detailStats.event_counts?.installed || 0 }}</div><div class="stat-label">已安装</div></el-card></el-col>
                </el-row>

                <!-- Events -->
                <h4 class="mt-4">事件记录</h4>
                <el-timeline>
                    <el-timeline-item v-for="evt in (detail.events || [])" :key="evt.id" :timestamp="fmtDate(evt.created_at)" :type="evt.event_type === 'error' ? 'danger' : evt.event_type === 'rollback' ? 'warning' : 'primary'">
                        <span :class="{ 'text-danger': evt.event_type === 'error' }">{{ evt.message || evt.event_type }}</span>
                    </el-timeline-item>
                </el-timeline>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/marketplaceRollout';

const rollouts = ref([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const showCreate = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formLoading = ref(false);
const availableApps = ref([]);
const allVersions = ref([]);
const tenantOptions = ref([]);
const tenantExcludeOptions = ref([]);
const showDetail = ref(false);
const detail = ref(null);
const detailStats = ref(null);
const form = reactive({
    app_id: null, version_id: null, name: '', description: '',
    rollout_type: 'percentage', percentage: 10, auto_rollback: false,
    error_threshold: 5, tenant_ids: [], excluded_tenant_ids: [],
});

const filteredVersions = computed(() => allVersions.value.filter(v => v.app_id === form.app_id));

function statusTag(s) {
    return { draft: 'info', active: 'success', paused: 'warning', completed: '', rolled_back: 'danger' }[s] || '';
}
function statusLabel(s) {
    return { draft: '草稿', active: '进行中', paused: '已暂停', completed: '已完成', rolled_back: '已回滚' }[s] || s;
}
function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-'; }

async function loadRollouts() {
    loading.value = true;
    try { const { data: r } = await api.list({ page: page.value, per_page: perPage.value }); rollouts.value = r.data?.data || []; total.value = r.data?.total || 0; }
    catch {} finally { loading.value = false; }
}

async function loadAvailableApps() {
    try { const { data: r } = await api.availableApps();
        availableApps.value = r.data || [];
        allVersions.value = (r.data || []).flatMap(a => (a.versions || []).map(v => ({ ...v, app_id: a.id })));
    } catch {}
}

function onAppChange(appId) {
    form.version_id = null;
}

async function searchTenants(q) {
    try { const { data: r } = await api.availableTenants({ search: q }); tenantOptions.value = r.data || []; } catch {}
}
async function searchTenantsExclude(q) {
    try { const { data: r } = await api.availableTenants({ search: q }); tenantExcludeOptions.value = r.data || []; } catch {}
}

async function saveRollout() {
    if (!form.name || !form.app_id || !form.version_id) { ElMessage.warning('请填写必填字段'); return; }
    saving.value = true;
    try {
        const payload = { ...form };
        if (form.rollout_type !== 'tenant_group') { payload.tenant_ids = []; }
        if (editingId.value) {
            await api.update(editingId.value, payload);
            ElMessage.success('灰度发布已更新');
        } else {
            await api.create(payload);
            ElMessage.success('灰度发布已创建');
        }
        showCreate.value = false; resetForm(); loadRollouts();
    } catch {} finally { saving.value = false; }
}

function editRollout(row) {
    editingId.value = row.id;
    Object.assign(form, {
        app_id: row.app_id, version_id: row.version_id, name: row.name, description: row.description || '',
        rollout_type: row.rollout_type, percentage: row.percentage, auto_rollback: !!row.auto_rollback,
        error_threshold: Number(row.error_threshold) || 5, tenant_ids: [], excluded_tenant_ids: [],
    });
    showCreate.value = true;
}

function resetForm() {
    editingId.value = null;
    Object.assign(form, { app_id: null, version_id: null, name: '', description: '', rollout_type: 'percentage', percentage: 10, auto_rollback: false, error_threshold: 5, tenant_ids: [], excluded_tenant_ids: [] });
}

async function viewDetail(row) {
    showDetail.value = true;
    detail.value = null; detailStats.value = null;
    try {
        const [detailRes, statsRes] = await Promise.all([api.show(row.id), api.stats(row.id)]);
        detail.value = detailRes.data?.data;
        detailStats.value = statsRes.data?.data;
    } catch {}
}

async function handleStart(row) {
    try { await ElMessageBox.confirm(`启动灰度发布"${row.name}"？版本 v${row.version?.version} 将逐步推送给 ${row.percentage}% 的用户。`, '确认启动'); await api.start(row.id); ElMessage.success('已启动'); loadRollouts(); } catch {}
}
async function handlePause(row) {
    try { await ElMessageBox.confirm(`暂停灰度发布"${row.name}"？`, '确认暂停'); await api.pause(row.id); ElMessage.success('已暂停'); loadRollouts(); } catch {}
}
async function handleComplete(row) {
    try { await ElMessageBox.confirm(`完成灰度发布"${row.name}"？版本 v${row.version?.version} 将全量上线成为正式版本。`, '确认完成'); await api.complete(row.id); ElMessage.success('已全量上线'); loadRollouts(); } catch {}
}
async function handleRollback(row) {
    try { await ElMessageBox.confirm(`回滚灰度发布"${row.name}"？所有通过灰度更新的安装将恢复到上一个版本。`, '确认回滚', { confirmButtonText: '确认回滚', confirmButtonClass: 'el-button--danger' }); await api.rollback(row.id); ElMessage.success('已回滚'); loadRollouts(); } catch {}
}

onMounted(() => { loadRollouts(); loadAvailableApps(); });
</script>

<style scoped>
.rollout-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mt-4 { margin-top: 16px; }
.ml-2 { margin-left: 8px; }
.text-danger { color: #f56c6c; font-weight: 600; }
.app-cell { display: flex; align-items: center; gap: 8px; }
.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
.empty { padding: 40px 0; }
.stat-value { font-size: 20px; font-weight: 600; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
