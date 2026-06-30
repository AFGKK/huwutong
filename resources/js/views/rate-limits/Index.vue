<template>
  <div class="rate-limit-manager">
    <!-- 统计概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">总请求数</div>
            <div class="stat-value">{{ stats.total_hits || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">被限流数</div>
            <div class="stat-value">{{ stats.total_blocked || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">限流率</div>
            <div class="stat-value">{{ stats.block_rate || 0 }}%</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 规则管理 -->
    <el-tabs v-model="activeTab">
      <el-tab-pane label="限流规则" name="rules">
        <div class="flex justify-between mb-3">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="filterKeyType" placeholder="类型" clearable @change="fetchRules">
                <el-option v-for="t in keyTypes" :key="t.id" :label="t.name" :value="t.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-switch v-model="filterActive" active-text="仅活跃" @change="fetchRules" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchRules">刷新</el-button>
            </el-form-item>
          </el-form>
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon>新建规则
          </el-button>
        </div>

        <el-table :data="rules" v-loading="loading" stripe>
          <el-table-column prop="name" label="名称" min-width="140" />
          <el-table-column prop="slug" label="Slug" width="120" />
          <el-table-column label="类型" width="90">
            <template #default="{ row }">
              <el-tag size="small">{{ typeLabel(row.key_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="限制" width="140">
            <template #default="{ row }">
              {{ row.max_attempts }} 次 / {{ row.window_seconds }}秒
            </template>
          </el-table-column>
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.is_active" type="success" size="small">启用</el-tag>
              <el-tag v-else type="info" size="small">停用</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="priority" label="优先级" width="70" />
          <el-table-column label="操作" width="180" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="openEditDialog(row)">编辑</el-button>
              <el-popconfirm title="确定删除此规则?" @confirm="handleDelete(row.id)">
                <template #reference>
                  <el-button size="small" type="danger" plain>删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 统计详情 -->
      <el-tab-pane label="限流统计" name="stats">
        <el-table :data="topRules" v-loading="statsLoading" stripe>
          <el-table-column prop="rule_slug" label="规则 Slug" width="140" />
          <el-table-column prop="hits" label="请求数" width="100" />
          <el-table-column prop="blocked" label="被限流" width="100" />
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建/编辑 Dialog -->
    <el-dialog v-model="showDialog" :title="editingRule ? '编辑规则' : '新建规则'" width="560px">
      <el-form :model="form" :rules="formRules" ref="formRef" label-width="120px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="名称" prop="name">
              <el-input v-model="form.name" placeholder="如：激活API限流" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Slug" prop="slug">
              <el-input v-model="form.slug" placeholder="如：activate" :disabled="!!editingRule" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="类型" prop="key_type">
              <el-select v-model="form.key_type" style="width: 100%">
                <el-option v-for="t in keyTypes" :key="t.id" :label="t.name" :value="t.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="次数" prop="max_attempts">
              <el-input-number v-model="form.max_attempts" :min="1" :max="100000" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="窗口(秒)" prop="window_seconds">
              <el-input-number v-model="form.window_seconds" :min="1" :max="86400" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="优先级">
              <el-input-number v-model="form.priority" :min="0" :max="999" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="启用">
              <el-switch v-model="form.is_active" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/rate-limit';

const activeTab = ref('rules');

// 统计
const stats = reactive({ total_hits: 0, total_blocked: 0, block_rate: 0 });
const topRules = ref([]);
const statsLoading = ref(false);

function fetchStats() {
  statsLoading.value = true;
  api.stats().then(res => {
    const d = res.data.data || {};
    Object.assign(stats, d);
    topRules.value = d.top_rules || [];
  }).finally(() => statsLoading.value = false);
}

// 规则列表
const loading = ref(false);
const rules = ref([]);
const filterKeyType = ref('');
const filterActive = ref(false);
const keyTypes = ref([]);

function fetchRules() {
  loading.value = true;
  const params = {};
  if (filterKeyType.value) params.key_type = filterKeyType.value;
  if (filterActive.value) params.is_active = 1;
  api.list(params).then(res => {
    rules.value = res.data.data?.data || [];
  }).finally(() => loading.value = false);
}

function fetchKeyTypes() {
  api.keyTypes().then(res => {
    keyTypes.value = res.data.data || [];
  });
}

function typeLabel(type) {
  const map = {};
  keyTypes.value.forEach(t => { map[t.id] = t.name; });
  return map[type] || type;
}

// 创建/编辑
const showDialog = ref(false);
const editingRule = ref(null);
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
  name: '',
  slug: '',
  key_type: 'ip',
  max_attempts: 60,
  window_seconds: 60,
  priority: 0,
  is_active: true,
  description: '',
});

const formRules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  slug: [{ required: true, message: '请输入 Slug', trigger: 'blur' }],
  key_type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  max_attempts: [{ required: true, message: '请输入次数', trigger: 'blur' }],
  window_seconds: [{ required: true, message: '请输入时间窗口', trigger: 'blur' }],
};

function openCreateDialog() {
  editingRule.value = null;
  form.name = '';
  form.slug = '';
  form.key_type = 'ip';
  form.max_attempts = 60;
  form.window_seconds = 60;
  form.priority = 0;
  form.is_active = true;
  form.description = '';
  showDialog.value = true;
}

function openEditDialog(rule) {
  editingRule.value = rule;
  Object.assign(form, {
    name: rule.name,
    slug: rule.slug,
    key_type: rule.key_type,
    max_attempts: rule.max_attempts,
    window_seconds: rule.window_seconds,
    priority: rule.priority,
    is_active: rule.is_active,
    description: rule.description || '',
  });
  showDialog.value = true;
}

function submitForm() {
  formRef.value.validate(valid => {
    if (!valid) return;
    submitting.value = true;

    const promise = editingRule.value
      ? api.update(editingRule.value.id, form)
      : api.create(form);

    promise
      .then(() => {
        ElMessage.success(editingRule.value ? '规则已更新' : '规则已创建');
        showDialog.value = false;
        fetchRules();
      })
      .catch(() => ElMessage.error('操作失败'))
      .finally(() => submitting.value = false);
  });
}

function handleDelete(id) {
  api.destroy(id).then(() => {
    ElMessage.success('规则已删除');
    fetchRules();
  }).catch(() => ElMessage.error('删除失败'));
}

onMounted(() => {
  fetchRules();
  fetchStats();
  fetchKeyTypes();
});
</script>

<style scoped>
.rate-limit-manager { padding: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.stat-item { text-align: center; }
.stat-label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
.stat-value { font-size: 20px; font-weight: 600; }
</style>
