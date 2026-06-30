<template>
    <div class="changelog-manager-page">
        <el-page-header title="返回" @back="$router.back()" class="page-header">
            <template #content>
                <span class="page-title">API Changelog 自动生成</span>
            </template>
        </el-page-header>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_changelogs }}</div>
                    <div class="stat-label">总 Changelog</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_versions }}</div>
                    <div class="stat-label">版本数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.latest_version || '-' }}</div>
                    <div class="stat-label">最新版本</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_snapshots }}</div>
                    <div class="stat-label">快照数</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab1: Changelog 列表 -->
            <el-tab-pane label="Changelog 列表" name="list">
                <div class="toolbar">
                    <el-row :gutter="12">
                        <el-col :span="5">
                            <el-input v-model="filters.search" placeholder="搜索标题/内容" clearable @clear="loadList" @keyup.enter="loadList" />
                        </el-col>
                        <el-col :span="3">
                            <el-input v-model="filters.version" placeholder="版本号" clearable @clear="loadList" @keyup.enter="loadList" />
                        </el-col>
                        <el-col :span="3">
                            <el-select v-model="filters.type" placeholder="类型" clearable @change="loadList" style="width:100%">
                                <el-option label="发布版" value="release" />
                                <el-option label="Beta" value="beta" />
                                <el-option label="热修复" value="hotfix" />
                                <el-option label="安全更新" value="security" />
                            </el-select>
                        </el-col>
                        <el-col :span="5">
                            <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" @change="onDateRangeChange" style="width:100%" />
                        </el-col>
                        <el-col :span="8" style="text-align:right">
                            <el-button type="primary" @click="showCreateDialog">
                                <el-icon><Plus /></el-icon> 新建 Changelog
                            </el-button>
                            <el-button @click="loadList">刷新</el-button>
                        </el-col>
                    </el-row>
                </div>

                <el-table :data="list" v-loading="loading" border stripe style="width:100%;margin-top:12px">
                    <el-table-column prop="version" label="版本" width="100">
                        <template #default="{ row }">
                            <el-tag type="primary" size="small">v{{ row.version }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="type" label="类型" width="90">
                        <template #default="{ row }">
                            <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="release_date" label="发布日期" width="110">
                        <template #default="{ row }">{{ row.release_date }}</template>
                    </el-table-column>
                    <el-table-column prop="source" label="来源" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.source === 'auto_detect' ? 'success' : 'info'" size="small">
                                {{ row.source === 'auto_detect' ? '自动' : '手动' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="迁移指南" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.migration_guide" type="warning" size="small">有</el-tag>
                            <span v-else class="no-guide">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="200" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="showDetail(row)">详情</el-button>
                            <el-button size="small" @click="showEditDialog(row)">编辑</el-button>
                            <el-popconfirm title="确认删除？" @confirm="handleDelete(row.id)">
                                <template #reference>
                                    <el-button size="small" type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-row">
                    <el-pagination
                        v-model:current-page="currentPage"
                        v-model:page-size="pageSize"
                        :total="total"
                        :page-sizes="[10, 20, 50]"
                        layout="total, sizes, prev, pager, next"
                        @size-change="loadList"
                        @current-change="loadList"
                    />
                </div>
            </el-tab-pane>

            <!-- Tab2: 自动生成 -->
            <el-tab-pane label="自动生成" name="auto">
                <el-card>
                    <template #header>
                        <div class="auto-header">
                            <span>API 端点变更自动检测</span>
                            <el-button type="primary" :loading="autoLoading" @click="handleAutoGenerate">
                                <el-icon><Refresh /></el-icon> 立即检测并生成
                            </el-button>
                        </div>
                    </template>

                    <el-form :model="autoForm" label-width="120px">
                        <el-form-item label="API 版本">
                            <el-select v-model="autoForm.api_version_id" placeholder="选择 API 版本" style="width:300px">
                                <el-option v-for="v in apiVersions" :key="v.id" :label="v.version + ' - ' + (v.name || '')" :value="v.id" />
                            </el-select>
                        </el-form-item>
                    </el-form>

                    <el-divider />

                    <div class="history-section">
                        <h4>检测历史</h4>
                        <el-table :data="detectHistory" border stripe v-loading="historyLoading" style="width:100%">
                            <el-table-column prop="version" label="快照版本" width="180" />
                            <el-table-column prop="snapshot_at" label="创建时间" width="180" />
                            <el-table-column prop="endpoint_count" label="端点数" width="100" />
                        </el-table>
                    </div>
                </el-card>

                <el-card style="margin-top:16px">
                    <template #header>
                        <span>创建端点快照</span>
                    </template>
                    <el-form :model="snapshotForm" label-width="120px">
                        <el-form-item label="API 版本">
                            <el-select v-model="snapshotForm.api_version_id" placeholder="选择 API 版本" style="width:300px">
                                <el-option v-for="v in apiVersions" :key="v.id" :label="v.version + ' - ' + (v.name || '')" :value="v.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="快照标签">
                            <el-input v-model="snapshotForm.version_label" placeholder="留空自动生成" style="width:300px" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :loading="snapshotLoading" @click="handleCreateSnapshot">创建快照</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- Tab3: 迁移指南 -->
            <el-tab-pane label="迁移指南" name="migration">
                <el-card>
                    <template #header>
                        <span>生成大版本迁移指南</span>
                    </template>

                    <el-form :model="migrationForm" label-width="120px">
                        <el-form-item label="从版本">
                            <el-input v-model="migrationForm.from_version" placeholder="例如 1.0" style="width:200px" />
                        </el-form-item>
                        <el-form-item label="到版本">
                            <el-input v-model="migrationForm.to_version" placeholder="例如 2.0" style="width:200px" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :loading="migrationLoading" @click="handleGenerateMigration">
                                生成迁移指南
                            </el-button>
                        </el-form-item>
                    </el-form>

                    <el-divider v-if="migrationResult" />

                    <div v-if="migrationResult" class="migration-result">
                        <el-alert
                            :title="`v${migrationResult.from_version} → v${migrationResult.to_version}`"
                            type="info"
                            :description="`共 ${migrationResult.changelog_count} 条关联 Changelog，${migrationResult.breaking_changes.length} 项破坏性变更`"
                            show-icon
                            style="margin-bottom:16px"
                        />

                        <h4>破坏性变更</h4>
                        <el-table :data="migrationResult.breaking_changes" border stripe style="width:100%;margin-bottom:16px">
                            <el-table-column prop="type" label="类型" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.type === 'removed' ? 'danger' : 'warning'" size="small">
                                        {{ row.type === 'removed' ? '已移除' : '已弃用' }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="endpoint" label="端点" min-width="250" />
                            <el-table-column prop="version" label="版本" width="80" />
                            <el-table-column prop="summary" label="说明" min-width="200" show-overflow-tooltip />
                        </el-table>

                        <h4>迁移步骤</h4>
                        <el-timeline>
                            <el-timeline-item
                                v-for="(step, idx) in migrationResult.migration_steps"
                                :key="idx"
                                :type="step.includes('✅') ? 'success' : 'primary'"
                            >
                                {{ step }}
                            </el-timeline-item>
                        </el-timeline>

                        <el-card v-if="migrationResult.recommended_upgrade_path" shadow="never" style="margin-top:12px">
                            <template #header><span>升级路径建议</span></template>
                            <p>{{ migrationResult.recommended_upgrade_path }}</p>
                        </el-card>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab4: 版本视图 -->
            <el-tab-pane label="版本视图" name="versions">
                <el-timeline v-if="versionData.length > 0">
                    <el-timeline-item
                        v-for="ver in versionData"
                        :key="ver.version"
                        :timestamp="ver.latest_release"
                        placement="top"
                    >
                        <el-card shadow="hover">
                            <h3>v{{ ver.version }}</h3>
                            <p class="version-count">{{ ver.total }} 条变更记录</p>
                            <ul class="changelog-summary-list">
                                <li v-for="log in ver.changelogs.slice(0, 5)" :key="log.id">
                                    <el-tag :type="typeTag(log.type)" size="small">{{ typeLabel(log.type) }}</el-tag>
                                    {{ log.title }}
                                </li>
                            </ul>
                            <el-button v-if="ver.changelogs.length > 5" size="small" text type="primary" @click="showVersionDetail(ver)">
                                查看全部 {{ ver.changelogs.length }} 条
                            </el-button>
                        </el-card>
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无版本数据" />
            </el-tab-pane>
        </el-tabs>

        <!-- 创建/编辑 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑 Changelog' : '新建 Changelog'" width="700px">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="110px">
                <el-form-item label="版本号" prop="version">
                    <el-input v-model="form.version" placeholder="例如 2.0.1" />
                </el-form-item>
                <el-form-item label="标题" prop="title">
                    <el-input v-model="form.title" placeholder="Changelog 标题" />
                </el-form-item>
                <el-form-item label="类型" prop="type">
                    <el-select v-model="form.type" style="width:200px">
                        <el-option label="发布版" value="release" />
                        <el-option label="Beta" value="beta" />
                        <el-option label="热修复" value="hotfix" />
                        <el-option label="安全更新" value="security" />
                    </el-select>
                </el-form-item>
                <el-form-item label="发布日期">
                    <el-date-picker v-model="form.release_date" type="date" style="width:200px" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="6" placeholder="支持 Markdown 格式" />
                </el-form-item>
                <el-form-item label="迁移指南">
                    <el-input v-model="form.migration_guide" type="textarea" :rows="4" placeholder="从上一版本迁移至此版本的指南（可选）" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="detailVisible" title="Changelog 详情" width="700px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="版本" :span="1">
                        <el-tag type="primary">v{{ detailData.version }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="类型" :span="1">
                        <el-tag :type="typeTag(detailData.type)" size="small">{{ typeLabel(detailData.type) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="发布日期" :span="1">{{ detailData.release_date }}</el-descriptions-item>
                    <el-descriptions-item label="来源" :span="1">
                        {{ detailData.source === 'auto_detect' ? '自动检测' : '手动创建' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="标题" :span="2">{{ detailData.title }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>描述</h4>
                <div class="markdown-content" v-html="renderedDescription"></div>

                <div v-if="detailData.migration_guide">
                    <el-divider />
                    <h4>迁移指南</h4>
                    <div class="markdown-content" v-html="renderedMigrationGuide"></div>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import changelogApi from '@/api/changelog';
import { getApiVersions } from '@/api/apiDocs';

// ─── 状态 ───
const activeTab = ref('list');
const loading = ref(false);
const saving = ref(false);
const autoLoading = ref(false);
const snapshotLoading = ref(false);
const historyLoading = ref(false);
const migrationLoading = ref(false);

const list = ref([]);
const total = ref(0);
const currentPage = ref(1);
const pageSize = ref(20);
const stats = ref({});
const apiVersions = ref([]);
const detectHistory = ref([]);

const filters = reactive({
    search: '',
    version: '',
    type: '',
    start_date: '',
    end_date: '',
});
const dateRange = ref(null);

const dialogVisible = ref(false);
const detailVisible = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const detailData = ref(null);

const formRef = ref(null);
const form = reactive({
    version: '',
    title: '',
    type: 'release',
    release_date: null,
    description: '',
    migration_guide: '',
});

const formRules = {
    version: [{ required: true, message: '请输入版本号', trigger: 'blur' }],
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
};

const autoForm = reactive({ api_version_id: '' });
const snapshotForm = reactive({ api_version_id: '', version_label: '' });
const migrationForm = reactive({ from_version: '', to_version: '' });
const migrationResult = ref(null);

const versionData = ref([]);

const renderedDescription = computed(() => {
    return detailData.value ? renderMarkdown(detailData.value.description || '') : '';
});

const renderedMigrationGuide = computed(() => {
    return detailData.value ? renderMarkdown(detailData.value.migration_guide || '') : '';
});

// ─── 方法 ───

function typeTag(type) {
    const map = { release: 'primary', beta: 'warning', hotfix: 'danger', security: 'info' };
    return map[type] || 'info';
}

function typeLabel(type) {
    const map = { release: '发布版', beta: 'Beta', hotfix: '热修复', security: '安全更新' };
    return map[type] || type;
}

function renderMarkdown(text) {
    if (!text) return '';
    return text
        .replace(/### (.+)/g, '<h5>$1</h5>')
        .replace(/## (.+)/g, '<h4>$1</h4>')
        .replace(/# (.+)/g, '<h3>$1</h3>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n- /g, '<br>• ')
        .replace(/\n/g, '<br>')
        .replace(/`(.+?)`/g, '<code>$1</code>');
}

async function loadList() {
    loading.value = true;
    try {
        const params = {
            page: currentPage.value,
            per_page: pageSize.value,
            ...filters,
        };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const res = await changelogApi.list(params);
        list.value = res.data?.data ?? [];
        total.value = res.data?.total ?? 0;
    } catch (e) {
        console.error('Failed to load changelog list', e);
    } finally {
        loading.value = false;
    }
}

async function loadStats() {
    try {
        const res = await changelogApi.stats();
        stats.value = res.data ?? {};
    } catch (e) {
        console.error('Failed to load stats', e);
    }
}

async function loadApiVersions() {
    try {
        const res = await getApiVersions();
        apiVersions.value = res.data ?? [];
    } catch (e) {
        console.error('Failed to load API versions', e);
    }
}

async function loadDetectHistory() {
    historyLoading.value = true;
    try {
        const res = await changelogApi.autoDetectHistory();
        detectHistory.value = res.data ?? [];
    } catch (e) {
        console.error('Failed to load detect history', e);
    } finally {
        historyLoading.value = false;
    }
}

async function loadVersionData() {
    try {
        const res = await changelogApi.publicByVersion();
        versionData.value = res.data ?? [];
    } catch (e) {
        console.error('Failed to load version data', e);
    }
}

function onDateRangeChange(range) {
    if (range) {
        filters.start_date = range[0];
        filters.end_date = range[1];
    } else {
        filters.start_date = '';
        filters.end_date = '';
    }
    loadList();
}

function showCreateDialog() {
    isEditing.value = false;
    editId.value = null;
    form.version = '';
    form.title = '';
    form.type = 'release';
    form.release_date = null;
    form.description = '';
    form.migration_guide = '';
    dialogVisible.value = true;
}

function showEditDialog(row) {
    isEditing.value = true;
    editId.value = row.id;
    form.version = row.version;
    form.title = row.title;
    form.type = row.type || 'release';
    form.release_date = row.release_date;
    form.description = row.description || '';
    form.migration_guide = row.migration_guide || '';
    dialogVisible.value = true;
}

async function showDetail(row) {
    try {
        const res = await changelogApi.show(row.id);
        detailData.value = res.data;
        detailVisible.value = true;
    } catch (e) {
        ElMessage.error('加载详情失败');
    }
}

function showVersionDetail(ver) {
    filters.version = ver.version;
    activeTab.value = 'list';
    loadList();
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const data = {
            version: form.version,
            title: form.title,
            type: form.type,
            release_date: form.release_date,
            description: form.description,
            migration_guide: form.migration_guide,
        };

        if (isEditing.value) {
            await changelogApi.update(editId.value, data);
            ElMessage.success('更新成功');
        } else {
            await changelogApi.create(data);
            ElMessage.success('创建成功');
        }

        dialogVisible.value = false;
        loadList();
        loadStats();
    } catch (e) {
        ElMessage.error('保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(id) {
    try {
        await changelogApi.delete(id);
        ElMessage.success('删除成功');
        loadList();
        loadStats();
    } catch (e) {
        ElMessage.error('删除失败');
    }
}

async function handleAutoGenerate() {
    if (!autoForm.api_version_id) {
        ElMessage.warning('请选择 API 版本');
        return;
    }

    autoLoading.value = true;
    try {
        const res = await changelogApi.autoGenerate(autoForm.api_version_id);
        ElMessage.success(res.data?.message || '检测完成');
        loadDetectHistory();
        loadList();
        loadStats();
    } catch (e) {
        ElMessage.error('自动检测失败');
    } finally {
        autoLoading.value = false;
    }
}

async function handleCreateSnapshot() {
    if (!snapshotForm.api_version_id) {
        ElMessage.warning('请选择 API 版本');
        return;
    }

    snapshotLoading.value = true;
    try {
        const res = await changelogApi.createSnapshot(
            snapshotForm.api_version_id,
            snapshotForm.version_label || null
        );
        ElMessage.success(`快照创建完成，共 ${res.data?.endpoints_snapshotted || 0} 个端点`);
        loadDetectHistory();
    } catch (e) {
        ElMessage.error('创建快照失败');
    } finally {
        snapshotLoading.value = false;
    }
}

async function handleGenerateMigration() {
    if (!migrationForm.from_version || !migrationForm.to_version) {
        ElMessage.warning('请填写起始和目标版本');
        return;
    }

    migrationLoading.value = true;
    try {
        const res = await changelogApi.migrationGuide(
            migrationForm.from_version,
            migrationForm.to_version
        );
        migrationResult.value = res.data;
    } catch (e) {
        ElMessage.error('生成迁移指南失败');
    } finally {
        migrationLoading.value = false;
    }
}

// ─── 初始化 ───
onMounted(() => {
    loadList();
    loadStats();
    loadApiVersions();
    loadDetectHistory();
    loadVersionData();
});
</script>

<style scoped>
.changelog-manager-page {
    padding: 20px;
}
.page-header {
    margin-bottom: 20px;
}
.page-title {
    font-size: 20px;
    font-weight: 600;
}
.stats-row {
    margin-bottom: 16px;
}
.stat-card {
    text-align: center;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #409eff;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}
.toolbar {
    margin-bottom: 8px;
}
.pagination-row {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
.auto-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.history-section {
    margin-top: 8px;
}
.migration-result h4 {
    margin: 12px 0 8px;
    font-size: 15px;
}
.changelog-summary-list {
    list-style: none;
    padding: 0;
    margin: 8px 0;
}
.changelog-summary-list li {
    padding: 4px 0;
    font-size: 13px;
}
.no-guide {
    color: #c0c4cc;
}
.version-count {
    color: #909399;
    font-size: 13px;
}
.markdown-content {
    line-height: 1.8;
    padding: 8px 0;
}
.markdown-content code {
    background: #f5f7fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}
</style>
