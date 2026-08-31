<template>
  <div class="certification-page">
    <!-- 全局统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6" v-for="item in statItems" :key="item.label">
        <el-card shadow="hover" :body-style="{ padding: '16px' }">
          <div class="stat-value text-2xl font-bold" :class="item.color">{{ item.value }}</div>
          <div class="stat-label text-gray-500 text-sm">{{ item.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 标签切换 -->
    <el-tabs v-model="activeTab" type="border-card">
      <!-- Tab: 认证等级 -->
      <el-tab-pane :label="t('certification_page.tabs.levels')" name="levels">
        <div class="flex justify-between items-center mb-3">
          <span class="font-semibold">{{ t('certification_page.levels.count', { count: levels.length }) }}</span>
          <el-button type="primary" size="small" @click="showLevelDialog = true">
            <el-icon><Plus /></el-icon> {{ t('certification_page.levels.create') }}
          </el-button>
        </div>

        <el-table :data="levels" stripe v-loading="loadingLevels" size="small">
          <el-table-column :label="t('certification_page.cols.name')" width="160">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <div class="badge-dot" :style="{ background: row.color || '#0f172a' }" />
                <span>{{ row.name }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.order')" prop="level_order" width="60" align="center" />
          <el-table-column :label="t('certification_page.cols.passing_score')" width="80" align="center">
            <template #default="{ row }">{{ row.passing_score }}%</template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.certified')" width="80" align="center">
            <template #default="{ row }">{{ row.total_certified || 0 }}</template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.status')" width="80">
            <template #default="{ row }">
              <el-tag size="small" :type="row.is_active ? 'success' : 'info'">
                {{ row.is_active ? t('actions.enable') : t('actions.disable') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.description')" min-width="200">
            <template #default="{ row }">
              <span class="text-gray-500">{{ row.description || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.actions')" width="240" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewQuestions(row)">{{ t('certification_page.levels.question_bank') }}</el-button>
              <el-button size="small" @click="editLevel(row)">{{ t('actions.edit') }}</el-button>
              <el-button
                size="small"
                type="primary"
                @click="startExamForUser(row)"
              >{{ t('certification_page.levels.take_exam') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab: 题库管理 -->
      <el-tab-pane :label="t('certification_page.tabs.questions')" name="questions">
        <div class="mb-3">
          <el-form :inline="true" size="small">
            <el-form-item :label="t('certification_page.cols.cert_level')">
              <el-select v-model="selectedLevelId" :placeholder="t('certification_page.questions.select_level')" @change="loadQuestions" style="width:200px">
                <el-option v-for="l in levels" :key="l.id" :label="l.name" :value="l.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="showAddQuestionDialog = true" :disabled="!selectedLevelId">
                <el-icon><Plus /></el-icon> {{ t('certification_page.questions.add') }}
              </el-button>
              <el-button @click="showBulkImport = true" :disabled="!selectedLevelId">
                {{ t('certification_page.questions.bulk_import') }}
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table :data="questions" stripe v-loading="loadingQuestions" size="small">
          <el-table-column label="#" prop="sort_order" width="50" align="center" />
          <el-table-column :label="t('certification_page.cols.question')" min-width="300">
            <template #default="{ row }">{{ row.question }}</template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.type')" width="120">
            <template #default="{ row }">{{ typeLabel(row.type) }}</template>
          </el-table-column>
          <el-table-column :label="t('certification_page.cols.points')" prop="points" width="60" align="center" />
          <el-table-column :label="t('certification_page.cols.status')" width="70">
            <template #default="{ row }">
              <el-tag size="small" :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab: 我的认证 -->
      <el-tab-pane :label="t('certification_page.tabs.my_certs')" name="my-certs">
        <el-card shadow="never" v-loading="loadingMyCerts">
          <div v-if="myCerts.length === 0">
            <el-empty :description="t('certification_page.my_certs.empty')">
              <template #actions>
                <el-button type="primary" @click="activeTab = 'levels'">{{ t('certification_page.my_certs.browse_levels') }}</el-button>
              </template>
            </el-empty>
          </div>

          <div v-else>
            <el-table :data="myCerts" stripe size="small">
              <el-table-column :label="t('certification_page.cols.cert_level')" width="160">
                <template #default="{ row }">{{ row.certification_level?.name || '-' }}</template>
              </el-table-column>
              <el-table-column :label="t('certification_page.cols.cert_number')" prop="certificate_number" width="180" />
              <el-table-column :label="t('certification_page.cols.status')" width="100">
                <template #default="{ row }">
                  <el-tag size="small" :type="myStatusType(row.status)">{{ myStatusLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column :label="t('certification_page.cols.score')" width="80" align="center">
                <template #default="{ row }">{{ row.score !== null ? row.score + '%' : '-' }}</template>
              </el-table-column>
              <el-table-column :label="t('certification_page.cols.attempts')" width="80" align="center">
                <template #default="{ row }">{{ row.attempts }}/{{ row.max_attempts }}</template>
              </el-table-column>
              <el-table-column :label="t('certification_page.cols.badge')" width="80" align="center">
                <template #default="{ row }">
                  <el-image v-if="row.badge_url" :src="row.badge_url" style="width:32px;height:32px" />
                  <span v-else class="text-gray-400">-</span>
                </template>
              </el-table-column>
              <el-table-column :label="t('certification_page.cols.issued_at')" width="160">
                <template #default="{ row }">{{ row.certificate_issued_at ? new Date(row.certificate_issued_at).toLocaleDateString() : '-' }}</template>
              </el-table-column>
            </el-table>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ─── M3-58: 权益管理 ─── -->
      <el-tab-pane :label="t('certification_page.tabs.benefits')" name="benefits">
        <el-alert :title="t('certification_page.benefits.alert')" type="info" show-icon :closable="false" class="mb-3" />
        <div class="flex gap-2 mb-3">
          <el-select v-model="benefitLevelId" :placeholder="t('certification_page.questions.select_level')" @change="loadBenefits" style="width:200px">
            <el-option v-for="l in levels" :key="l.id" :label="l.name" :value="l.id" />
          </el-select>
          <el-button type="primary" size="small" @click="showAddBenefit = true" :disabled="!benefitLevelId">
            <el-icon><Plus /></el-icon> {{ t('certification_page.benefits.add') }}
          </el-button>
        </div>
        <el-table :data="benefitsList" stripe size="small" v-if="benefitsList.length">
          <el-table-column prop="title" :label="t('certification_page.cols.benefit_title')" width="160" />
          <el-table-column prop="description" :label="t('certification_page.cols.description')" min-width="250" />
          <el-table-column prop="type" :label="t('certification_page.cols.type')" width="120" />
          <el-table-column :label="t('certification_page.cols.actions')" width="80">
            <template #default="{ row }">
              <el-popconfirm :title="t('messages.confirm_delete')" @confirm="handleDeleteBenefit(row.id)">
                <template #reference><el-button text type="danger" size="small">{{ t('actions.delete') }}</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── M3-58: 公开目录 ─── -->
      <el-tab-pane :label="t('certification_page.tabs.directory')" name="directory">
        <el-alert :title="t('certification_page.directory.alert')" type="success" show-icon :closable="false" class="mb-3" />
        <el-table :data="directoryList" stripe v-loading="dirLoading">
          <el-table-column prop="developer_name" :label="t('certification_page.cols.developer')" width="150" />
          <el-table-column :label="t('certification_page.cols.cert_level')" width="140">
            <template #default="{ row }">
              <el-tag :color="row.level_color" style="color:#fff" size="small">{{ row.level_name }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="certificate_number" :label="t('certification_page.cols.cert_number')" width="200" />
          <el-table-column prop="certified_at" :label="t('certification_page.cols.certified_at')" width="170" />
          <el-table-column :label="t('certification_page.cols.badge')" width="100">
            <template #default="{ row }">
              <el-image v-if="row.badge_url" :src="row.badge_url" style="width:40px;height:40px" />
              <span v-else class="text-gray">-</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── M3-58: 证书验证 ─── -->
      <el-tab-pane :label="t('certification_page.tabs.verify')" name="verify">
        <el-card shadow="never" class="mb-3">
          <el-form :inline="true" @submit.prevent="handleVerify">
            <el-form-item :label="t('certification_page.cols.cert_number')">
              <el-input v-model="verifyCertNumber" :placeholder="t('certification_page.verify.cert_number_ph')" style="width:300px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="verifying">{{ t('certification_page.verify.verify_btn') }}</el-button>
            </el-form-item>
          </el-form>
        </el-card>
        <el-result v-if="verifyResult !== null" :icon="verifyResult.valid ? 'success' : 'error'">
          <template #title>
            {{ verifyResult.valid ? t('certification_page.verify.valid_title') : t('certification_page.verify.invalid_title') }}
          </template>
          <template #extra>
            <el-descriptions :column="2" border size="small" v-if="verifyResult.valid">
              <el-descriptions-item :label="t('certification_page.cols.developer')">{{ verifyResult.developer_name }}</el-descriptions-item>
              <el-descriptions-item :label="t('certification_page.cols.level')">{{ verifyResult.level_name }}</el-descriptions-item>
              <el-descriptions-item :label="t('certification_page.cols.issued_at')">{{ verifyResult.issued_at }}</el-descriptions-item>
              <el-descriptions-item :label="t('certification_page.cols.expires_at')">{{ verifyResult.expires_at || t('certification_page.verify.permanent') }}</el-descriptions-item>
              <el-descriptions-item :label="t('certification_page.cols.cert_number')">{{ verifyResult.certificate_number }}</el-descriptions-item>
            </el-descriptions>
            <p v-else class="text-gray">{{ t('certification_page.verify.not_found') }}</p>
          </template>
        </el-result>
      </el-tab-pane>
    </el-tabs>

    <!-- 新建/编辑等级对话框 -->
    <el-dialog v-model="showLevelDialog" :title="editingLevel ? t('certification_page.levels.edit') : t('certification_page.levels.create')" width="550px">
      <el-form ref="levelFormRef" :model="levelForm" :rules="levelRules" label-width="120px">
        <el-form-item :label="t('certification_page.form.name')" prop="name">
          <el-input v-model="levelForm.name" :placeholder="t('certification_page.form.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('certification_page.form.level_order')" prop="level_order">
          <el-input-number v-model="levelForm.level_order" :min="0" />
        </el-form-item>
        <el-form-item :label="t('certification_page.form.description')" prop="description">
          <el-input v-model="levelForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('certification_page.form.passing_score')" prop="passing_score">
              <el-input-number v-model="levelForm.passing_score" :min="1" :max="100" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('certification_page.form.badge_color')" prop="color">
              <el-color-picker v-model="levelForm.color" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('certification_page.form.enabled')" prop="is_active">
          <el-switch v-model="levelForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showLevelDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingLevel" @click="handleSaveLevel">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 添加试题对话框 -->
    <el-dialog v-model="showAddQuestionDialog" :title="t('certification_page.dialog.add_question')" width="650px">
      <el-form ref="questionFormRef" :model="questionForm" :rules="questionRules" label-width="100px">
        <el-form-item :label="t('certification_page.form.question')" prop="question">
          <el-input v-model="questionForm.question" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t('certification_page.form.type')" prop="type">
          <el-select v-model="questionForm.type">
            <el-option v-for="opt in questionTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('certification_page.form.options')" prop="options">
          <div v-for="(opt, idx) in questionForm.options" :key="idx" class="option-row mb-2">
            <el-input v-model="opt.text" :placeholder="t('certification_page.form.option_ph', { letter: String.fromCharCode(65 + idx) })" style="width:350px" />
            <el-checkbox v-model="opt.is_correct" class="ml-2">{{ t('certification_page.form.correct') }}</el-checkbox>
            <el-button v-if="questionForm.options.length > 2" type="danger" text @click="removeOption(idx)">{{ t('actions.delete') }}</el-button>
          </div>
          <el-button type="primary" text @click="addOption">+ {{ t('certification_page.dialog.add_option') }}</el-button>
        </el-form-item>
        <el-form-item :label="t('certification_page.form.points')" prop="points">
          <el-input-number v-model="questionForm.points" :min="1" />
        </el-form-item>
        <el-form-item :label="t('certification_page.form.sort_order')" prop="sort_order">
          <el-input-number v-model="questionForm.sort_order" :min="0" />
        </el-form-item>
        <el-form-item :label="t('certification_page.form.explanation')" prop="explanation">
          <el-input v-model="questionForm.explanation" type="textarea" :rows="2" :placeholder="t('certification_page.form.explanation_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddQuestionDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingQuestion" @click="handleAddQuestion">{{ t('certification_page.questions.add_btn') }}</el-button>
      </template>
    </el-dialog>

    <!-- 批量导入对话框 -->
    <el-dialog v-model="showBulkImport" :title="t('certification_page.dialog.bulk_import')" width="700px">
      <p class="text-gray-500 mb-3">{{ t('certification_page.bulk.format_hint') }}</p>
      <el-input
        v-model="bulkText"
        type="textarea"
        :rows="12"
        :placeholder="t('certification_page.bulk.placeholder')"
      />
      <template #footer>
        <el-button @click="showBulkImport = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="importing" @click="handleBulkImport">{{ t('actions.import') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import certApi from '@/api/certification';
import axios from 'axios';

const { t } = useI18n();

const questionTypeKeys = ['single_choice', 'multiple_choice', 'true_false'];
const myStatusKeys = ['in_progress', 'passed', 'failed', 'expired', 'revoked'];

const questionTypeOptions = computed(() =>
  questionTypeKeys.map((value) => ({
    value,
    label: t(`certification_page.types.${value}`),
  }))
);

const typeMap = computed(() =>
  Object.fromEntries(questionTypeKeys.map((key) => [key, t(`certification_page.types.${key}`)]))
);

const myStatusMap = computed(() =>
  Object.fromEntries(myStatusKeys.map((key) => [key, t(`certification_page.status.${key}`)]))
);

// ─── 状态 ───
const activeTab = ref('levels');

// 统计
const globalStats = reactive({
  total_levels: 0,
  total_certifications: 0,
  total_passed: 0,
  total_failed: 0,
  total_in_progress: 0,
});

const statItems = computed(() => [
  { label: t('certification_page.stats.levels'), value: globalStats.total_levels, color: 'text-blue-500' },
  { label: t('certification_page.stats.total'), value: globalStats.total_certifications, color: 'text-purple-500' },
  { label: t('certification_page.stats.passed'), value: globalStats.total_passed, color: 'text-green-500' },
  { label: t('certification_page.stats.in_progress'), value: globalStats.total_in_progress, color: 'text-orange-500' },
]);

// 认证等级
const loadingLevels = ref(false);
const levels = ref([]);

// 试题
const loadingQuestions = ref(false);
const selectedLevelId = ref(null);
const questions = ref([]);

// 我的认证
const loadingMyCerts = ref(false);
const myCerts = ref([]);

// 新建/编辑等级
const showLevelDialog = ref(false);
const editingLevel = ref(null);
const savingLevel = ref(false);
const levelFormRef = ref(null);

const levelForm = reactive({
  name: '',
  level_order: 0,
  description: '',
  passing_score: 70,
  color: '#0f172a',
  is_active: true,
});

const levelRules = computed(() => ({
  name: [{ required: true, message: t('certification_page.messages.level_name_required') }],
}));

// 添加试题
const showAddQuestionDialog = ref(false);
const savingQuestion = ref(false);
const questionFormRef = ref(null);

const questionForm = reactive({
  question: '',
  type: 'single_choice',
  options: [
    { text: '', is_correct: false },
    { text: '', is_correct: false },
  ],
  points: 1,
  sort_order: 0,
  explanation: '',
});

const questionRules = computed(() => ({
  question: [{ required: true, message: t('certification_page.messages.question_required') }],
}));

// 批量导入
const showBulkImport = ref(false);
const bulkText = ref('');
const importing = ref(false);

const bulkCorrectMarker = computed(() => t('certification_page.bulk.correct_marker'));

// M3-58 增强: 权益/目录/验证
const benefitLevelId = ref(null);
const benefitsList = ref([]);
const showAddBenefit = ref(false);
const directoryList = ref([]);
const dirLoading = ref(false);
const verifyCertNumber = ref('');
const verifyResult = ref(null);
const verifying = ref(false);

// ─── 方法 ───

function typeLabel(type) {
  return typeMap.value[type] || type;
}

function myStatusType(status) {
  const map = { in_progress: 'warning', passed: 'success', failed: 'danger', expired: 'info', revoked: 'danger' };
  return map[status] || 'info';
}

function myStatusLabel(status) {
  return myStatusMap.value[status] || status;
}

async function loadStats() {
  try {
    const { data } = await certApi.globalStats();
    Object.assign(globalStats, data?.data || {});
  } catch (e) { /* ignore */ }
}

async function loadLevels() {
  loadingLevels.value = true;
  try {
    const { data } = await certApi.getLevels({ all: true });
    levels.value = data?.data || [];
  } catch (e) {
    ElMessage.error(t('certification_page.messages.load_levels_failed'));
  } finally {
    loadingLevels.value = false;
  }
}

async function loadQuestions() {
  if (!selectedLevelId.value) return;
  loadingQuestions.value = true;
  try {
    const { data } = await certApi.getQuestions(selectedLevelId.value, { show_answers: true, all: true });
    questions.value = data?.data || [];
  } catch (e) {
    ElMessage.error(t('certification_page.messages.load_questions_failed'));
  } finally {
    loadingQuestions.value = false;
  }
}

async function loadMyCerts() {
  loadingMyCerts.value = true;
  try {
    const { data } = await certApi.myCertifications();
    myCerts.value = data?.data || [];
  } catch (e) {
    ElMessage.error(t('certification_page.messages.load_my_certs_failed'));
  } finally {
    loadingMyCerts.value = false;
  }
}

async function handleSaveLevel() {
  const valid = await levelFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  savingLevel.value = true;
  try {
    if (editingLevel.value) {
      await certApi.updateLevel(editingLevel.value.id, levelForm);
      ElMessage.success(t('certification_page.messages.level_updated'));
    } else {
      await certApi.createLevel(levelForm);
      ElMessage.success(t('certification_page.messages.level_created'));
    }
    showLevelDialog.value = false;
    loadLevels();
    loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('certification_page.messages.save_failed'));
  } finally {
    savingLevel.value = false;
  }
}

function editLevel(row) {
  editingLevel.value = row;
  Object.assign(levelForm, {
    name: row.name,
    level_order: row.level_order || 0,
    description: row.description || '',
    passing_score: row.passing_score || 70,
    color: row.color || '#0f172a',
    is_active: row.is_active ?? true,
  });
  showLevelDialog.value = true;
}

function viewQuestions(row) {
  selectedLevelId.value = row.id;
  activeTab.value = 'questions';
  loadQuestions();
}

function startExamForUser(row) {
  ElMessage.info(t('certification_page.messages.exam_hint', { name: row.name }));
  activeTab.value = 'my-certs';
}

function addOption() {
  questionForm.options.push({ text: '', is_correct: false });
}

function removeOption(idx) {
  questionForm.options.splice(idx, 1);
}

async function handleAddQuestion() {
  const valid = await questionFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  savingQuestion.value = true;
  try {
    await certApi.addQuestion(selectedLevelId.value, { ...questionForm });
    ElMessage.success(t('certification_page.messages.question_added'));
    showAddQuestionDialog.value = false;
    loadQuestions();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('certification_page.messages.add_failed'));
  } finally {
    savingQuestion.value = false;
  }
}

async function handleBulkImport() {
  if (!bulkText.value.trim()) {
    ElMessage.warning(t('certification_page.messages.bulk_empty'));
    return;
  }

  importing.value = true;
  try {
    const marker = bulkCorrectMarker.value;
    const lines = bulkText.value.trim().split('\n');
    const parsedQuestions = lines.map(line => {
      const parts = line.split('|').map(s => s.trim());
      const question = parts[0];
      const options = parts.slice(1).map(opt => {
        const isCorrect = opt.includes(marker);
        return {
          text: opt.replace(marker, '').trim(),
          is_correct: isCorrect,
        };
      });
      return { question, options, points: 1 };
    }).filter(q => q.question && q.options.length >= 2);

    if (parsedQuestions.length === 0) {
      ElMessage.warning(t('certification_page.messages.bulk_no_valid'));
      return;
    }

    await certApi.bulkAddQuestions(selectedLevelId.value, { questions: parsedQuestions });
    ElMessage.success(t('certification_page.messages.bulk_success', { count: parsedQuestions.length }));
    showBulkImport.value = false;
    bulkText.value = '';
    loadQuestions();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('certification_page.messages.bulk_failed'));
  } finally {
    importing.value = false;
  }
}

onMounted(() => {
  loadStats();
  loadLevels();
  loadMyCerts();
});

// ─── M3-58 增强 ───
async function loadBenefits() {
  if (!benefitLevelId.value) return;
  try {
    const res = await axios.get(`/admin/certification/levels/${benefitLevelId.value}/benefits`);
    benefitsList.value = res?.data?.data || [];
  } catch { /* */ }
}

async function loadDirectory() {
  dirLoading.value = true;
  try {
    const res = await axios.get('/api/certification/directory');
    directoryList.value = res?.data?.data?.data || res?.data?.data || [];
  } finally { dirLoading.value = false; }
}

async function handleVerify() {
  if (!verifyCertNumber.value) return;
  verifying.value = true;
  try {
    const res = await axios.get(`/api/certification/verify/${verifyCertNumber.value}`);
    verifyResult.value = res?.data?.data;
  } catch {
    verifyResult.value = { valid: false };
  } finally { verifying.value = false; }
}

async function handleDeleteBenefit(id) {
  try {
    await axios.delete(`/admin/certification/benefits/${id}`);
    await loadBenefits();
  } catch { /* */ }
}
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.gap-2 { gap: 8px; }
.text-gray-400 { color: #909399; }
.text-gray-500 { color: #909399; }
.text-2xl { font-size: 24px; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.text-sm { font-size: 13px; }

.badge-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.option-row {
  display: flex;
  align-items: center;
}
</style>
