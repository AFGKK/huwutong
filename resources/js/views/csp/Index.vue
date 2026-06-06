<template>
    <div class="csp-page">
        <div class="page-header">
            <h2>CSP 内容安全策略</h2>
            <div class="header-actions">
                <el-button @click="activeTab = 'violations'" :type="activeTab === 'violations' ? 'default' : 'default'">
                    违规报告 ({{ violationCount }})
                </el-button>
                <el-button type="primary" @click="openDialog()">
                    <el-icon><Plus /></el-icon> 新增策略
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane label="策略配置" name="configs">
                <el-table :data="configs" v-loading="loading" stripe>
                    <el-table-column prop="name" label="名称" min-width="120" />
                    <el-table-column label="模式" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.mode === 'report-only' ? 'warning' : 'success'" size="small">
                                {{ row.mode === 'report-only' ? '仅报告' : '强制执行' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="指令" min-width="250">
                        <template #default="{ row }">
                            <div v-if="row.directives" class="directive-tags">
                                <el-tag v-for="(sources, directive) in row.directives" :key="directive"
                                    type="info" size="small" effect="plain">
                                    {{ directive }} {{ sources.join(' ') }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="路由模式" width="120">
                        <template #default="{ row }">
                            <el-tag v-if="row.route_pattern" type="info" size="small">{{ row.route_pattern }}</el-tag>
                            <span v-else class="text-muted">全部</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="priority" label="优先级" width="80" />
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                {{ row.is_active ? '启用' : '禁用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="openDialog(row)">编辑</el-button>
                            <el-popconfirm title="确定删除?" @confirm="handleDelete(row)">
                                <template #reference>
                                    <el-button size="small" type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="违规报告" name="violations">
                <el-row :gutter="16" style="margin-bottom: 16px;">
                    <el-col :span="8">
                        <el-statistic title="总违规" :value="stats?.total || 0" />
                    </el-col>
                    <el-col :span="8">
                        <el-statistic title="最近 24 小时" :value="stats?.last_24h || 0" />
                    </el-col>
                </el-row>

                <el-table :data="violations" v-loading="violationsLoading" stripe>
                    <el-table-column prop="document_uri" label="文档 URI" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="blocked_uri" label="被拦截 URI" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="violated_directive" label="违例指令" width="160" />
                    <el-table-column prop="disposition" label="处置" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.disposition === 'enforce' ? 'danger' : 'warning'" size="small">
                                {{ row.disposition === 'enforce' ? '拦截' : '报告' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="时间" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <!-- 编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="editingId ? '编辑 CSP 策略' : '新增 CSP 策略'" width="800px">
            <el-form ref="formRef" :model="form" :rules="rules" label-width="140px">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="例如: 严格策略" />
                </el-form-item>
                <el-form-item label="模式">
                    <el-radio-group v-model="form.mode">
                        <el-radio value="enforce">强制执行 (Content-Security-Policy)</el-radio>
                        <el-radio value="report-only">仅报告 (Content-Security-Policy-Report-Only)</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="指令 (directives)" prop="directives">
                    <div class="directive-form">
                        <div v-for="(entry, i) in directiveEntries" :key="i" class="directive-row">
                            <el-select v-model="entry.directive" filterable allow-create default-first-option
                                placeholder="指令" style="width: 200px">
                                <el-option v-for="d in knownDirectives" :key="d" :value="d" />
                            </el-select>
                            <el-input v-model="entry.sources" placeholder="来源列表，空格分隔" style="flex: 1" />
                            <el-button @click="removeDirective(i)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="addDirective()" type="primary" link>
                            + 添加指令
                        </el-button>
                    </div>
                    <div v-if="policyPreview" class="policy-preview">
                        <label>策略预览:</label>
                        <code>{{ policyPreview }}</code>
                    </div>
                </el-form-item>
                <el-form-item label="路由模式">
                    <el-input v-model="form.route_pattern" placeholder="留空匹配全部，例如 api/*" />
                </el-form-item>
                <el-form-item label="优先级">
                    <el-input-number v-model="form.priority" :min="-100" :max="100" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Plus, Delete } from '@element-plus/icons-vue';
import {
    getCspConfigs, createCspConfig, updateCspConfig, deleteCspConfig,
    getCspViolations, getCspViolationStats,
} from '@/api/csp';
import { ElMessage } from 'element-plus';

const loading = ref(false);
const saving = ref(false);
const configs = ref([]);
const dialogVisible = ref(false);
const editingId = ref(null);
const formRef = ref(null);
const activeTab = ref('configs');

// CSP directives
const knownDirectives = [
    'default-src', 'script-src', 'style-src', 'img-src', 'font-src',
    'connect-src', 'media-src', 'object-src', 'frame-src', 'frame-ancestors',
    'form-action', 'base-uri', 'manifest-src', 'worker-src', 'report-uri',
    'report-to', 'block-all-mixed-content', 'upgrade-insecure-requests',
];

const form = ref({
    name: '',
    mode: 'enforce',
    directives: {},
    route_pattern: '',
    priority: 0,
});

const directiveEntries = ref([{ directive: 'default-src', sources: "'self'" }]);

const rules = {
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
};

const policyPreview = computed(() => {
    const parts = [];
    for (const entry of directiveEntries.value) {
        if (entry.directive && entry.sources) {
            parts.push(`${entry.directive} ${entry.sources}`);
        }
    }
    return parts.join('; ');
});

// Violations
const violations = ref([]);
const violationsLoading = ref(false);
const stats = ref(null);
const violationCount = computed(() => stats.value?.total || 0);

async function fetchConfigs() {
    loading.value = true;
    try {
        const res = await getCspConfigs();
        configs.value = res.data || [];
    } catch (e) {
        ElMessage.error('获取 CSP 配置失败');
    } finally {
        loading.value = false;
    }
}

async function fetchViolations() {
    violationsLoading.value = true;
    try {
        const res = await getCspViolations({ per_page: 50 });
        violations.value = res.data?.data || [];
    } catch (e) {
        // ignore
    } finally {
        violationsLoading.value = false;
    }
}

async function fetchStats() {
    try {
        const res = await getCspViolationStats();
        stats.value = res.data;
    } catch (e) {
        // ignore
    }
}

function buildDirectivesFromEntries() {
    const directives = {};
    for (const entry of directiveEntries.value) {
        if (entry.directive) {
            directives[entry.directive] = entry.sources.split(/\s+/).filter(Boolean);
        }
    }
    return directives;
}

function buildEntriesFromDirectives(directives) {
    if (!directives || Object.keys(directives).length === 0) {
        return [{ directive: 'default-src', sources: "'self'" }];
    }
    return Object.entries(directives).map(([directive, sources]) => ({
        directive,
        sources: Array.isArray(sources) ? sources.join(' ') : String(sources),
    }));
}

function addDirective() {
    directiveEntries.value.push({ directive: '', sources: '' });
}

function removeDirective(i) {
    directiveEntries.value.splice(i, 1);
}

function openDialog(row) {
    if (row) {
        editingId.value = row.id;
        form.value.name = row.name || '';
        form.value.mode = row.mode || 'enforce';
        form.value.route_pattern = row.route_pattern || '';
        form.value.priority = row.priority ?? 0;
        directiveEntries.value = buildEntriesFromDirectives(row.directives);
    } else {
        editingId.value = null;
        form.value.name = '';
        form.value.mode = 'enforce';
        form.value.route_pattern = '';
        form.value.priority = 0;
        directiveEntries.value = [{ directive: 'default-src', sources: "'self'" }];
    }
    dialogVisible.value = true;
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    const payload = {
        name: form.value.name,
        mode: form.value.mode,
        directives: buildDirectivesFromEntries(),
        route_pattern: form.value.route_pattern || null,
        priority: form.value.priority,
    };

    if (Object.keys(payload.directives).length === 0) {
        ElMessage.warning('至少添加一个指令');
        return;
    }

    saving.value = true;
    try {
        if (editingId.value) {
            await updateCspConfig(editingId.value, payload);
            ElMessage.success('策略已更新');
        } else {
            await createCspConfig(payload);
            ElMessage.success('策略已创建');
        }
        dialogVisible.value = false;
        await fetchConfigs();
    } catch (e) {
        ElMessage.error('操作失败');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(row) {
    try {
        await deleteCspConfig(row.id);
        ElMessage.success('已删除');
        await fetchConfigs();
    } catch (e) {
        ElMessage.error('删除失败');
    }
}

watch(activeTab, (tab) => {
    if (tab === 'violations') {
        fetchViolations();
        fetchStats();
    }
});

onMounted(() => {
    fetchConfigs();
    fetchStats();
});
</script>

<style scoped>
.csp-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-actions { display: flex; gap: 8px; }
.directive-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.text-muted { color: #999; }
.directive-form { width: 100%; }
.directive-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
.policy-preview { margin-top: 12px; }
.policy-preview label { font-size: 12px; color: #666; display: block; margin-bottom: 4px; }
.policy-preview code { background: #f5f7fa; padding: 8px 12px; border-radius: 4px; font-size: 12px; display: block; word-break: break-all; }
</style>
