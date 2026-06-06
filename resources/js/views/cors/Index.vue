<template>
    <div class="cors-configs-page">
        <div class="page-header">
            <h2>CORS 跨域配置</h2>
            <el-button type="primary" @click="openDialog()">
                <el-icon><Plus /></el-icon> 新增配置
            </el-button>
        </div>

        <el-table :data="configs" v-loading="loading" stripe style="width: 100%">
            <el-table-column prop="name" label="名称" min-width="120" />
            <el-table-column label="允许 Origin" min-width="200">
                <template #default="{ row }">
                    <el-tag v-for="origin in (row.allowed_origins || [])" :key="origin" size="small" style="margin: 1px">
                        {{ origin }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="路由模式" width="120">
                <template #default="{ row }">
                    <el-tag v-if="row.route_pattern" type="info" size="small">{{ row.route_pattern }}</el-tag>
                    <span v-else class="text-muted">全部</span>
                </template>
            </el-table-column>
            <el-table-column prop="max_age" label="Max Age" width="90" />
            <el-table-column prop="priority" label="优先级" width="80" />
            <el-table-column label="状态" width="80">
                <template #default="{ row }">
                    <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                        {{ row.is_active ? '启用' : '禁用' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="操作" width="180" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" @click="testDialog(row)">测试</el-button>
                    <el-button size="small" @click="openDialog(row)">编辑</el-button>
                    <el-popconfirm title="确定删除?" @confirm="handleDelete(row)">
                        <template #reference>
                            <el-button size="small" type="danger">删除</el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>

        <!-- 编辑/新建对话框 -->
        <el-dialog v-model="dialogVisible" :title="editingId ? '编辑 CORS 配置' : '新增 CORS 配置'" width="700px">
            <el-form ref="formRef" :model="form" :rules="rules" label-width="140px">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="例如: 前端应用" />
                </el-form-item>
                <el-form-item label="允许 Origin" prop="allowed_origins">
                    <div class="origin-list">
                        <div v-for="(origin, i) in form.allowed_origins" :key="i" class="origin-item">
                            <el-input v-model="form.allowed_origins[i]" placeholder="https://example.com 或 *" />
                            <el-button @click="form.allowed_origins.splice(i, 1)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="form.allowed_origins.push('')" type="primary" link>
                            + 添加 Origin
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item label="允许 Methods">
                    <el-checkbox-group v-model="form.allowed_methods">
                        <el-checkbox value="GET">GET</el-checkbox>
                        <el-checkbox value="POST">POST</el-checkbox>
                        <el-checkbox value="PUT">PUT</el-checkbox>
                        <el-checkbox value="PATCH">PATCH</el-checkbox>
                        <el-checkbox value="DELETE">DELETE</el-checkbox>
                        <el-checkbox value="OPTIONS">OPTIONS</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item label="允许 Headers">
                    <el-select v-model="form.allowed_headers" multiple filterable allow-create default-first-option
                        placeholder="输入 header 名称" style="width: 100%">
                        <el-option v-for="h in commonHeaders" :key="h" :value="h" :label="h" />
                    </el-select>
                </el-form-item>
                <el-form-item label="暴露 Headers">
                    <el-select v-model="form.exposed_headers" multiple filterable allow-create default-first-option
                        placeholder="输入 header 名称" style="width: 100%">
                        <el-option v-for="h in commonHeaders" :key="h" :value="h" :label="h" />
                    </el-select>
                </el-form-item>
                <el-form-item label="允许凭据">
                    <el-switch v-model="form.allow_credentials" />
                </el-form-item>
                <el-form-item label="Max Age (秒)">
                    <el-input-number v-model="form.max_age" :min="0" :max="86400" />
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

        <!-- 测试对话框 -->
        <el-dialog v-model="testVisible" title="CORS 匹配测试" width="500px">
            <el-form label-width="100px">
                <el-form-item label="Origin">
                    <el-input v-model="testOrigin" placeholder="https://example.com" />
                </el-form-item>
                <el-form-item label="路径">
                    <el-input v-model="testPath" placeholder="api/license/activate" />
                </el-form-item>
            </el-form>
            <div v-if="testResult" class="test-result">
                <h4>结果:</h4>
                <el-alert v-if="testResult.matched" title="匹配成功" type="success" show-icon />
                <el-alert v-else title="未匹配" type="warning" show-icon />
                <pre v-if="testResult.headers">{{ JSON.stringify(testResult.headers, null, 2) }}</pre>
            </div>
            <template #footer>
                <el-button @click="testVisible = false">关闭</el-button>
                <el-button type="primary" @click="runTest" :loading="testing">测试</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { Plus, Delete } from '@element-plus/icons-vue';
import { getCorsConfigs, createCorsConfig, updateCorsConfig, deleteCorsConfig, testCorsConfig } from '@/api/cors';
import { ElMessage } from 'element-plus';

const loading = ref(false);
const saving = ref(false);
const configs = ref([]);
const dialogVisible = ref(false);
const editingId = ref(null);
const formRef = ref(null);

const commonHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key', 'X-License-Key',
    'X-Tenant-Id', 'X-Idempotency-Key', 'X-Nonce', 'X-Signature',
];

const form = reactive({
    name: '',
    allowed_origins: [''],
    allowed_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowed_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key', 'X-License-Key', 'X-Tenant-Id', 'X-Idempotency-Key', 'X-Nonce', 'X-Signature'],
    exposed_headers: ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'X-Request-Id'],
    allow_credentials: true,
    max_age: 86400,
    route_pattern: '',
    priority: 0,
});

const rules = {
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
    allowed_origins: [{ required: true, message: '至少添加一个 Origin', trigger: 'change' }],
};

const testVisible = ref(false);
const testOrigin = ref('');
const testPath = ref('');
const testResult = ref(null);
const testing = ref(false);

async function fetchConfigs() {
    loading.value = true;
    try {
        const res = await getCorsConfigs();
        configs.value = res.data || [];
    } catch (e) {
        ElMessage.error('获取配置列表失败');
    } finally {
        loading.value = false;
    }
}

function openDialog(row) {
    if (row) {
        editingId.value = row.id;
        Object.assign(form, {
            name: row.name || '',
            allowed_origins: row.allowed_origins?.length ? [...row.allowed_origins] : [''],
            allowed_methods: row.allowed_methods || ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowed_headers: row.allowed_headers || ['Content-Type', 'Authorization'],
            exposed_headers: row.exposed_headers || [],
            allow_credentials: row.allow_credentials ?? true,
            max_age: row.max_age ?? 86400,
            route_pattern: row.route_pattern || '',
            priority: row.priority ?? 0,
        });
    } else {
        editingId.value = null;
        Object.assign(form, {
            name: '',
            allowed_origins: [''],
            allowed_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowed_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key',
                'X-License-Key', 'X-Tenant-Id', 'X-Idempotency-Key', 'X-Nonce', 'X-Signature',
            ],
            exposed_headers: ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'X-Request-Id'],
            allow_credentials: true,
            max_age: 86400,
            route_pattern: '',
            priority: 0,
        });
    }
    dialogVisible.value = true;
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        // 过滤掉空的 origin 项
        const payload = {
            ...form,
            allowed_origins: form.allowed_origins.filter(o => o.trim() !== ''),
        };

        if (editingId.value) {
            await updateCorsConfig(editingId.value, payload);
            ElMessage.success('配置已更新');
        } else {
            await createCorsConfig(payload);
            ElMessage.success('配置已创建');
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
        await deleteCorsConfig(row.id);
        ElMessage.success('已删除');
        await fetchConfigs();
    } catch (e) {
        ElMessage.error('删除失败');
    }
}

function testDialog(row) {
    testVisible.value = true;
    testOrigin.value = '';
    testPath.value = '';
    testResult.value = null;
    // Pre-fill from row
    if (row?.allowed_origins?.length) {
        testOrigin.value = row.allowed_origins[0];
    }
}

async function runTest() {
    if (!testOrigin.value || !testPath.value) {
        ElMessage.warning('请输入 Origin 和路径');
        return;
    }
    testing.value = true;
    try {
        const res = await testCorsConfig(testOrigin.value, testPath.value);
        testResult.value = res.data;
    } catch (e) {
        ElMessage.error('测试失败');
    } finally {
        testing.value = false;
    }
}

onMounted(fetchConfigs);
</script>

<style scoped>
.cors-configs-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.origin-list { width: 100%; }
.origin-item { display: flex; gap: 8px; margin-bottom: 8px; }
.origin-item .el-input { flex: 1; }
.text-muted { color: #999; }
.test-result { margin-top: 16px; }
.test-result pre { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; }
</style>
