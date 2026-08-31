<template>
  <div class="rate-limit-manager">
    <!-- 统计概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">{{ t('rate_limits_page.stat_total_hits') }}</div>
            <div class="stat-value">{{ stats.total_hits || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">{{ t('rate_limits_page.stat_total_blocked') }}</div>
            <div class="stat-value">{{ stats.total_blocked || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-label">{{ t('rate_limits_page.stat_block_rate') }}</div>
            <div class="stat-value">{{ stats.block_rate || 0 }}%</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 规则管理 -->
    <el-tabs v-model="activeTab">
      <el-tab-pane :label="t('rate_limits_page.tab_rules')" name="rules">
        <div class="flex justify-between mb-3">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="filterKeyType" :placeholder="t('rate_limits_page.filter_type_ph')" clearable @change="fetchRules">
                <el-option v-for="opt in keyTypeOptions" :key="opt.id" :label="opt.name" :value="opt.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-switch v-model="filterActive" :active-text="t('rate_limits_page.filter_active_only')" @change="fetchRules" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchRules">{{ t('rate_limits_page.refresh') }}</el-button>
            </el-form-item>
          </el-form>
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon>{{ t('rate_limits_page.create_btn') }}
          </el-button>
        </div>

        <el-table :data="rules" v-loading="loading" stripe>
          <el-table-column prop="name" :label="t('rate_limits_page.col_name')" min-width="140" />
          <el-table-column prop="slug" :label="t('rate_limits_page.col_slug')" width="120" />
          <el-table-column :label="t('rate_limits_page.col_type')" width="90">
            <template #default="{ row }">
              <el-tag size="small">{{ typeLabel(row.key_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('rate_limits_page.col_limit')" width="140">
            <template #default="{ row }">
              {{ t('rate_limits_page.limit_fmt', { attempts: row.max_attempts, seconds: row.window_seconds }) }}
            </template>
          </el-table-column>
          <el-table-column :label="t('rate_limits_page.col_status')" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.is_active" type="success" size="small">{{ t('rate_limits_page.status_active') }}</el-tag>
              <el-tag v-else type="info" size="small">{{ t('rate_limits_page.status_inactive') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="priority" :label="t('rate_limits_page.col_priority')" width="70" />
          <el-table-column :label="t('rate_limits_page.col_actions')" width="180" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
              <el-popconfirm :title="t('rate_limits_page.delete_confirm')" @confirm="handleDelete(row.id)">
                <template #reference>
                  <el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 统计详情 -->
      <el-tab-pane :label="t('rate_limits_page.tab_stats')" name="stats">
        <el-table :data="topRules" v-loading="statsLoading" stripe>
          <el-table-column prop="rule_slug" :label="t('rate_limits_page.col_rule_slug')" width="140" />
          <el-table-column prop="hits" :label="t('rate_limits_page.col_hits')" width="100" />
          <el-table-column prop="blocked" :label="t('rate_limits_page.col_blocked')" width="100" />
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建/编辑 Dialog -->
    <el-dialog
      v-model="showDialog"
      :title="editingRule ? t('rate_limits_page.edit_dialog_title') : t('rate_limits_page.create_dialog_title')"
      width="560px"
    >
      <el-form :model="form" :rules="formRules" ref="formRef" label-width="120px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('rate_limits_page.name_label')" prop="name">
              <el-input v-model="form.name" :placeholder="t('rate_limits_page.name_ph')" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('rate_limits_page.slug_label')" prop="slug">
              <el-input v-model="form.slug" :placeholder="t('rate_limits_page.slug_ph')" :disabled="!!editingRule" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('rate_limits_page.type_label')" prop="key_type">
              <el-select v-model="form.key_type" style="width: 100%">
                <el-option v-for="opt in keyTypeOptions" :key="opt.id" :label="opt.name" :value="opt.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item :label="t('rate_limits_page.max_attempts_label')" prop="max_attempts">
              <el-input-number v-model="form.max_attempts" :min="1" :max="100000" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item :label="t('rate_limits_page.window_seconds_label')" prop="window_seconds">
              <el-input-number v-model="form.window_seconds" :min="1" :max="86400" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('rate_limits_page.priority_label')">
              <el-input-number v-model="form.priority" :min="0" :max="999" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('rate_limits_page.enabled_label')">
              <el-switch v-model="form.is_active" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('rate_limits_page.description_label')">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/rate-limit';

const { t } = useI18n();

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
const rawKeyTypes = ref([]);

const keyTypeOptions = computed(() =>
  rawKeyTypes.value.map(kt => ({
    id: kt.id,
    name: t(`rate_limits_page.key_types.${kt.id}`),
  }))
);

const keyTypeLabels = computed(() => {
  const map = {};
  keyTypeOptions.value.forEach(kt => { map[kt.id] = kt.name; });
  return map;
});

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
    rawKeyTypes.value = res.data.data || [];
  });
}

function typeLabel(type) {
  return keyTypeLabels.value[type] || type;
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

const formRules = computed(() => ({
  name: [{ required: true, message: t('rate_limits_page.name_required'), trigger: 'blur' }],
  slug: [{ required: true, message: t('rate_limits_page.slug_required'), trigger: 'blur' }],
  key_type: [{ required: true, message: t('rate_limits_page.type_required'), trigger: 'change' }],
  max_attempts: [{ required: true, message: t('rate_limits_page.max_attempts_required'), trigger: 'blur' }],
  window_seconds: [{ required: true, message: t('rate_limits_page.window_seconds_required'), trigger: 'blur' }],
}));

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
        ElMessage.success(editingRule.value ? t('rate_limits_page.update_ok') : t('rate_limits_page.create_ok'));
        showDialog.value = false;
        fetchRules();
      })
      .catch(() => ElMessage.error(t('rate_limits_page.action_fail')))
      .finally(() => submitting.value = false);
  });
}

function handleDelete(id) {
  api.destroy(id).then(() => {
    ElMessage.success(t('rate_limits_page.delete_ok'));
    fetchRules();
  }).catch(() => ElMessage.error(t('rate_limits_page.delete_fail')));
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
