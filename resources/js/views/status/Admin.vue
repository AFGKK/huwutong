<template>
    <div class="admin-status-page">
        <div class="page-header">
            <div class="header-left">
                <h2>状态页管理</h2>
                <span class="header-subtitle">管理公开状态页的组件、事件和订阅者</span>
            </div>
            <div class="header-actions">
                <el-button @click="runHealthCheck" :loading="checking">运行健康检查</el-button>
                <el-button type="primary" @click="activeTab = 'incidents'; showIncidentDialog = true">创建事件</el-button>
            </div>
        </div>

        <!-- Stats -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="s in statsData" :key="s.key">
                <el-card shadow="never">
                    <div class="stat-box">
                        <div class="stat-num" :style="{ color: s.color }">{{ s.value }}</div>
                        <div class="stat-lbl">{{ s.label }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- Components Tab -->
            <el-tab-pane label="监控组件" name="components">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>组件列表</span>
                            <el-button type="primary" size="small" @click="showComponentDialog = true; editingComponent = null">添加组件</el-button>
                        </div>
                    </template>
                    <el-table :data="components" v-loading="loadingComponents" stripe>
                        <el-table-column prop="name" label="名称" width="130" />
                        <el-table-column prop="slug" label="标识" width="120" />
                        <el-table-column prop="description" label="描述" min-width="160" />
                        <el-table-column label="分组" width="100">
                            <template #default="{ row }">{{ row.group }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status_tag_type || 'info'" size="small">{{ row.status_label || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="公开" width="60" align="center">
                            <template #default="{ row }">
                                <el-icon :color="row.is_public ? '#67c23a' : '#909399'">
                                    <CircleCheck v-if="row.is_public" /><CloseBold v-else />
                                </el-icon>
                            </template>
                        </el-table-column>
                        <el-table-column label="排序" width="60" prop="sort_order" />
                        <el-table-column label="操作" width="150" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="editComponent(row)">编辑</el-button>
                                <el-button text size="small" type="danger" @click="handleDeleteComponent(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Incidents Tab -->
            <el-tab-pane label="事件管理" name="incidents">
                <el-card shadow="never">
                    <el-table :data="incidents" v-loading="loadingIncidents" stripe>
                        <el-table-column prop="title" label="标题" min-width="200" />
                        <el-table-column label="严重程度" width="80">
                            <template #default="{ row }">{{ row.severity_label || row.severity }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">
                                    {{ row.status_label || row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="影响组件" min-width="140">
                            <template #default="{ row }">
                                <span v-for="(c, i) in row.components" :key="c.id">
                                    {{ c.name }}{{ i < row.components.length - 1 ? ', ' : '' }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="160">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="showIncidentDetail(row)">详情</el-button>
                                <el-button text size="small" type="danger" @click="handleDeleteIncident(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Subscribers Tab -->
            <el-tab-pane label="订阅者 ({{ subscriberCount }})" name="subscribers">
                <el-card shadow="never">
                    <el-table :data="subscribers" v-loading="loadingSubscribers" stripe>
                        <el-table-column prop="email" label="邮箱" min-width="250" />
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '活跃' : '已退订' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="订阅时间" width="160">
                            <template #default="{ row }">{{ formatTime(row.subscribed_at) }}</template>
                        </el-table-column>
                        <el-table-column label="退订时间" width="160">
                            <template #default="{ row }">{{ row.unsubscribed_at ? formatTime(row.unsubscribed_at) : '—' }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- Component Dialog -->
        <el-dialog v-model="showComponentDialog" :title="editingComponent ? '编辑组件' : '添加组件'" width="500px">
            <el-form ref="componentForm" :model="componentForm" :rules="componentRules" label-width="80px">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="componentForm.name" />
                </el-form-item>
                <el-form-item label="标识" prop="slug">
                    <el-input v-model="componentForm.slug" :disabled="!!editingComponent" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="componentForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="分组">
                    <el-select v-model="componentForm.group">
                        <el-option label="核心服务" value="core" />
                        <el-option label="业务服务" value="services" />
                        <el-option label="基础设施" value="infrastructure" />
                        <el-option label="第三方" value="third_party" />
                    </el-select>
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="componentForm.sort_order" :min="0" />
                </el-form-item>
                <el-form-item label="公开">
                    <el-switch v-model="componentForm.is_public" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showComponentDialog = false">取消</el-button>
                <el-button type="primary" @click="handleSaveComponent" :loading="savingComponent">保存</el-button>
            </template>
        </el-dialog>

        <!-- Incident Create Dialog -->
        <el-dialog v-model="showIncidentDialog" title="创建事件" width="550px">
            <el-form ref="incidentForm" :model="incidentForm" :rules="incidentRules" label-width="100px">
                <el-form-item label="标题" prop="title">
                    <el-input v-model="incidentForm.title" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="incidentForm.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item label="严重程度" prop="severity">
                    <el-select v-model="incidentForm.severity">
                        <el-option label="轻微" value="minor" />
                        <el-option label="严重" value="major" />
                        <el-option label="重大" value="critical" />
                    </el-select>
                </el-form-item>
                <el-form-item label="影响组件">
                    <el-select v-model="incidentForm.component_ids" multiple filterable>
                        <el-option v-for="c in components" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="公开">
                    <el-switch v-model="incidentForm.is_public" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showIncidentDialog = false">取消</el-button>
                <el-button type="primary" @click="handleCreateIncident" :loading="savingIncident">创建</el-button>
            </template>
        </el-dialog>

        <!-- Incident Detail Dialog -->
        <el-dialog v-model="showIncidentDetailDialog" :title="'事件: ' + (incidentDetail?.title || '')" width="600px">
            <template v-if="incidentDetail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="严重程度">{{ incidentDetail.severity_label }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="incidentDetail.status === 'resolved' ? 'success' : 'warning'" size="small">
                            {{ incidentDetail.status_label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="发生时间">{{ formatTime(incidentDetail.occurred_at) }}</el-descriptions-item>
                    <el-descriptions-item label="解决时间">{{ formatTime(incidentDetail.resolved_at) || '—' }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="section-title">更新状态</h4>
                <div class="status-update-form">
                    <el-select v-model="updateStatus.status" placeholder="选择新状态">
                        <el-option label="调查中" value="investigating" />
                        <el-option label="已确认" value="identified" />
                        <el-option label="监控中" value="monitoring" />
                        <el-option label="已解决" value="resolved" />
                        <el-option label="事后分析" value="postmortem" />
                    </el-select>
                    <el-input v-model="updateStatus.message" type="textarea" :rows="2" placeholder="更新信息" />
                    <el-button type="primary" @click="handleUpdateIncident">更新</el-button>
                </div>

                <h4 class="section-title">更新时间线</h4>
                <el-timeline>
                    <el-timeline-item v-for="up in incidentDetail.updates" :key="up.created_at"
                        :type="up.status === 'resolved' ? 'success' : 'primary'"
                        :timestamp="formatTime(up.created_at)">
                        <strong>{{ statusLabel(up.status) }}</strong>
                        <p>{{ up.message }}</p>
                    </el-timeline-item>
                </el-timeline>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CircleCheck, CloseBold } from '@element-plus/icons-vue';
import statusApi from '@/api/statusPage';

const activeTab = ref('components');

// Stats
const statsData = reactive([
    { key: 'total_components', label: '组件总数', color: '#409eff', value: '0' },
    { key: 'open_incidents', label: '未解决事件', color: '#e6a23c', value: '0' },
    { key: 'active_subscribers', label: '活跃订阅者', color: '#67c23a', value: '0' },
    { key: 'uptime_30d', label: '30天Uptime', color: '#67c23a', value: '0%' },
]);

// Components
const components = ref([]);
const loadingComponents = ref(false);
const showComponentDialog = ref(false);
const editingComponent = ref(null);
const savingComponent = ref(false);
const componentForm = reactive({
    name: '', slug: '', description: '', group: 'core',
    sort_order: 0, is_public: true,
});
const componentRules = {
    name: [{ required: true, message: '请输入名称' }],
    slug: [{ required: true, message: '请输入标识' }],
};

// Incidents
const incidents = ref([]);
const loadingIncidents = ref(false);
const showIncidentDialog = ref(false);
const savingIncident = ref(false);
const incidentForm = reactive({
    title: '', description: '', severity: 'minor',
    component_ids: [], is_public: true,
});
const incidentRules = {
    title: [{ required: true, message: '请输入标题' }],
    severity: [{ required: true, message: '请选择严重程度' }],
};
const incidentDetail = ref(null);
const showIncidentDetailDialog = ref(false);
const updateStatus = reactive({ status: 'identified', message: '' });

// Subscribers
const subscribers = ref([]);
const loadingSubscribers = ref(false);
const subscriberCount = ref(0);
const checking = ref(false);

function statusLabel(s) {
    const map = {
        investigating: '调查中', identified: '已确认',
        monitoring: '监控中', resolved: '已解决', postmortem: '事后分析',
    };
    return map[s] || s;
}

function formatTime(t) {
    if (!t) return '';
    return new Date(t).toLocaleString('zh-CN');
}

async function fetchStats() {
    try {
        const { data: res } = await statusApi.getStats();
        if (res.success) {
            const d = res.data;
            statsData[0].value = String(d.total_components || 0);
            statsData[1].value = String(d.open_incidents || 0);
            statsData[2].value = String(d.active_subscribers || 0);
            statsData[3].value = (d.uptime_30d || 0) + '%';
        }
    } catch { /* ignore */ }
}

async function fetchComponents() {
    loadingComponents.value = true;
    try {
        const { data: res } = await statusApi.getComponents();
        if (res.success) components.value = res.data || [];
    } catch { ElMessage.error('获取组件失败'); }
    finally { loadingComponents.value = false; }
}

async function fetchIncidents() {
    loadingIncidents.value = true;
    try {
        const { data: res } = await statusApi.getIncidents({ per_page: 50 });
        if (res.success) incidents.value = res.data?.data || [];
    } catch { ElMessage.error('获取事件失败'); }
    finally { loadingIncidents.value = false; }
}

async function fetchSubscribers() {
    loadingSubscribers.value = true;
    try {
        const { data: res } = await statusApi.getSubscribers({ per_page: 100 });
        if (res.success) {
            subscribers.value = res.data?.data || [];
            subscriberCount.value = res.data?.total || 0;
        }
    } catch { /* ignore */ }
    finally { loadingSubscribers.value = false; }
}

function editComponent(comp) {
    editingComponent.value = comp;
    Object.assign(componentForm, {
        name: comp.name, slug: comp.slug, description: comp.description || '',
        group: comp.group || 'core', sort_order: comp.sort_order || 0, is_public: comp.is_public,
    });
    showComponentDialog.value = true;
}

function resetComponentForm() {
    editingComponent.value = null;
    Object.assign(componentForm, {
        name: '', slug: '', description: '', group: 'core',
        sort_order: 0, is_public: true,
    });
}

async function handleSaveComponent() {
    savingComponent.value = true;
    try {
        if (editingComponent.value) {
            await statusApi.updateComponent(editingComponent.value.id, componentForm);
            ElMessage.success('组件已更新');
        } else {
            await statusApi.createComponent(componentForm);
            ElMessage.success('组件已创建');
        }
        showComponentDialog.value = false;
        resetComponentForm();
        await fetchComponents();
    } catch { ElMessage.error('保存失败'); }
    finally { savingComponent.value = false; }
}

async function handleDeleteComponent(comp) {
    try {
        await ElMessageBox.confirm(`确认删除组件 "${comp.name}"？`, '确认', { type: 'warning' });
        await statusApi.deleteComponent(comp.id);
        ElMessage.success('已删除');
        await fetchComponents();
    } catch { /* cancelled */ }
}

async function handleCreateIncident() {
    savingIncident.value = true;
    try {
        await statusApi.createIncident(incidentForm);
        ElMessage.success('事件已创建');
        showIncidentDialog.value = false;
        Object.assign(incidentForm, { title: '', description: '', severity: 'minor', component_ids: [], is_public: true });
        await fetchIncidents();
        await fetchComponents();
        await fetchStats();
    } catch { ElMessage.error('创建失败'); }
    finally { savingIncident.value = false; }
}

async function showIncidentDetail(inc) {
    try {
        const { data: res } = await statusApi.getIncident(inc.id);
        if (res.success) {
            incidentDetail.value = res.data;
            updateStatus.status = res.data.status === 'resolved' ? 'resolved' : res.data.status;
            updateStatus.message = '';
            showIncidentDetailDialog.value = true;
        }
    } catch { ElMessage.error('获取详情失败'); }
}

async function handleUpdateIncident() {
    if (!updateStatus.status || !updateStatus.message.trim()) {
        ElMessage.warning('请选择状态并填写更新信息');
        return;
    }
    try {
        await statusApi.updateIncidentStatus(incidentDetail.value.id, {
            status: updateStatus.status,
            message: updateStatus.message,
        });
        ElMessage.success('事件已更新');
        showIncidentDetailDialog.value = false;
        await fetchIncidents();
        if (activeTab.value === 'subscribers') await fetchSubscribers();
    } catch { ElMessage.error('更新失败'); }
}

async function handleDeleteIncident(inc) {
    try {
        await ElMessageBox.confirm(`确认删除事件 "${inc.title}"？`, '确认', { type: 'warning' });
        await statusApi.deleteIncident(inc.id);
        ElMessage.success('已删除');
        await fetchIncidents();
        await fetchStats();
    } catch { /* cancelled */ }
}

async function runHealthCheck() {
    checking.value = true;
    try {
        const { data: res } = await statusApi.runChecks();
        ElMessage.success('健康检查完成');
        await fetchComponents();
        await fetchStats();
    } catch { ElMessage.error('检查失败'); }
    finally { checking.value = false; }
}

onMounted(() => {
    fetchStats();
    fetchComponents();
    fetchIncidents();
    fetchSubscribers();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0 0 4px; }
.header-subtitle { font-size: 14px; color: #909399; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.stat-box { text-align: center; padding: 8px; }
.stat-num { font-size: 28px; font-weight: 700; }
.stat-lbl { font-size: 13px; color: #909399; margin-top: 4px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.section-title { margin: 16px 0 8px; }
.status-update-form { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.status-update-form .el-select { width: 160px; }
.status-update-form .el-textarea { flex: 1; min-width: 200px; }
</style>
