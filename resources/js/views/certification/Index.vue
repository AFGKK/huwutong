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
      <el-tab-pane label="认证等级" name="levels">
        <div class="flex justify-between items-center mb-3">
          <span class="font-semibold">共 {{ levels.length }} 个等级</span>
          <el-button type="primary" size="small" @click="showLevelDialog = true">
            <el-icon><Plus /></el-icon> 新建等级
          </el-button>
        </div>

        <el-table :data="levels" stripe v-loading="loadingLevels" size="small">
          <el-table-column label="等级名称" width="160">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <div class="badge-dot" :style="{ background: row.color || '#409eff' }" />
                <span>{{ row.name }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="级别" prop="level_order" width="60" align="center" />
          <el-table-column label="通过分" width="80" align="center">
            <template #default="{ row }">{{ row.passing_score }}%</template>
          </el-table-column>
          <el-table-column label="已认证" width="80" align="center">
            <template #default="{ row }">{{ row.total_certified || 0 }}</template>
          </el-table-column>
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag size="small" :type="row.is_active ? 'success' : 'info'">
                {{ row.is_active ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="描述" min-width="200">
            <template #default="{ row }">
              <span class="text-gray-500">{{ row.description || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="240" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewQuestions(row)">题库</el-button>
              <el-button size="small" @click="editLevel(row)">编辑</el-button>
              <el-button
                size="small"
                type="primary"
                @click="startExamForUser(row)"
              >参加考试</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab: 题库管理 -->
      <el-tab-pane label="题库管理" name="questions">
        <div class="mb-3">
          <el-form :inline="true" size="small">
            <el-form-item label="认证等级">
              <el-select v-model="selectedLevelId" placeholder="选择等级" @change="loadQuestions" style="width:200px">
                <el-option v-for="l in levels" :key="l.id" :label="l.name" :value="l.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="showAddQuestionDialog = true" :disabled="!selectedLevelId">
                <el-icon><Plus /></el-icon> 添加试题
              </el-button>
              <el-button @click="showBulkImport = true" :disabled="!selectedLevelId">
                批量导入
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table :data="questions" stripe v-loading="loadingQuestions" size="small">
          <el-table-column label="#" prop="sort_order" width="50" align="center" />
          <el-table-column label="题目" min-width="300">
            <template #default="{ row }">{{ row.question }}</template>
          </el-table-column>
          <el-table-column label="类型" width="120">
            <template #default="{ row }">{{ typeLabel(row.type) }}</template>
          </el-table-column>
          <el-table-column label="分值" prop="points" width="60" align="center" />
          <el-table-column label="状态" width="70">
            <template #default="{ row }">
              <el-tag size="small" :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? '启用' : '禁用' }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab: 我的认证 -->
      <el-tab-pane label="我的认证" name="my-certs">
        <el-card shadow="never" v-loading="loadingMyCerts">
          <div v-if="myCerts.length === 0">
            <el-empty description="您还没有参加任何认证考试">
              <template #actions>
                <el-button type="primary" @click="activeTab = 'levels'">浏览认证等级</el-button>
              </template>
            </el-empty>
          </div>

          <div v-else>
            <el-table :data="myCerts" stripe size="small">
              <el-table-column label="认证等级" width="160">
                <template #default="{ row }">{{ row.certification_level?.name || '-' }}</template>
              </el-table-column>
              <el-table-column label="证书编号" prop="certificate_number" width="180" />
              <el-table-column label="状态" width="100">
                <template #default="{ row }">
                  <el-tag size="small" :type="myStatusType(row.status)">{{ myStatusLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="得分" width="80" align="center">
                <template #default="{ row }">{{ row.score !== null ? row.score + '%' : '-' }}</template>
              </el-table-column>
              <el-table-column label="尝试次数" width="80" align="center">
                <template #default="{ row }">{{ row.attempts }}/{{ row.max_attempts }}</template>
              </el-table-column>
              <el-table-column label="徽章" width="80" align="center">
                <template #default="{ row }">
                  <el-image v-if="row.badge_url" :src="row.badge_url" style="width:32px;height:32px" />
                  <span v-else class="text-gray-400">-</span>
                </template>
              </el-table-column>
              <el-table-column label="颁发时间" width="160">
                <template #default="{ row }">{{ row.certificate_issued_at ? new Date(row.certificate_issued_at).toLocaleDateString() : '-' }}</template>
              </el-table-column>
            </el-table>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ─── M3-58: 权益管理 ─── -->
      <el-tab-pane label="权益管理" name="benefits">
        <el-alert title="每个认证等级的权益配置" type="info" show-icon :closable="false" class="mb-3" />
        <div class="flex gap-2 mb-3">
          <el-select v-model="benefitLevelId" placeholder="选择等级" @change="loadBenefits" style="width:200px">
            <el-option v-for="l in levels" :key="l.id" :label="l.name" :value="l.id" />
          </el-select>
          <el-button type="primary" size="small" @click="showAddBenefit = true" :disabled="!benefitLevelId">
            <el-icon><Plus /></el-icon> 添加权益
          </el-button>
        </div>
        <el-table :data="benefitsList" stripe size="small" v-if="benefitsList.length">
          <el-table-column prop="title" label="权益名称" width="160" />
          <el-table-column prop="description" label="描述" min-width="250" />
          <el-table-column prop="type" label="类型" width="120" />
          <el-table-column label="操作" width="80">
            <template #default="{ row }">
              <el-popconfirm title="确认删除？" @confirm="handleDeleteBenefit(row.id)">
                <template #reference><el-button text type="danger" size="small">删除</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── M3-58: 公开目录 ─── -->
      <el-tab-pane label="公开目录" name="directory">
        <el-alert title="已认证开发者公开目录 — 可在官网展示" type="success" show-icon :closable="false" class="mb-3" />
        <el-table :data="directoryList" stripe v-loading="dirLoading">
          <el-table-column prop="developer_name" label="开发者" width="150" />
          <el-table-column label="认证等级" width="140">
            <template #default="{ row }">
              <el-tag :color="row.level_color" style="color:#fff" size="small">{{ row.level_name }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="certificate_number" label="证书编号" width="200" />
          <el-table-column prop="certified_at" label="认证时间" width="170" />
          <el-table-column label="徽章" width="100">
            <template #default="{ row }">
              <el-image v-if="row.badge_url" :src="row.badge_url" style="width:40px;height:40px" />
              <span v-else class="text-gray">-</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── M3-58: 证书验证 ─── -->
      <el-tab-pane label="证书验证" name="verify">
        <el-card shadow="never" class="mb-3">
          <el-form :inline="true" @submit.prevent="handleVerify">
            <el-form-item label="证书编号">
              <el-input v-model="verifyCertNumber" placeholder="输入证书编号" style="width:300px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="verifying">验证</el-button>
            </el-form-item>
          </el-form>
        </el-card>
        <el-result v-if="verifyResult !== null" :icon="verifyResult.valid ? 'success' : 'error'">
          <template #title>
            {{ verifyResult.valid ? '✅ 有效证书' : '❌ 证书无效' }}
          </template>
          <template #extra>
            <el-descriptions :column="2" border size="small" v-if="verifyResult.valid">
              <el-descriptions-item label="开发者">{{ verifyResult.developer_name }}</el-descriptions-item>
              <el-descriptions-item label="等级">{{ verifyResult.level_name }}</el-descriptions-item>
              <el-descriptions-item label="颁发时间">{{ verifyResult.issued_at }}</el-descriptions-item>
              <el-descriptions-item label="过期时间">{{ verifyResult.expires_at || '永久' }}</el-descriptions-item>
              <el-descriptions-item label="证书编号">{{ verifyResult.certificate_number }}</el-descriptions-item>
            </el-descriptions>
            <p v-else class="text-gray">未找到该证书编号或证书已失效</p>
          </template>
        </el-result>
      </el-tab-pane>
    </el-tabs>

    <!-- 新建/编辑等级对话框 -->
    <el-dialog v-model="showLevelDialog" :title="editingLevel ? '编辑等级' : '新建等级'" width="550px">
      <el-form ref="levelFormRef" :model="levelForm" :rules="levelRules" label-width="120px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="levelForm.name" placeholder="例如：初级开发者" />
        </el-form-item>
        <el-form-item label="级别序号" prop="level_order">
          <el-input-number v-model="levelForm.level_order" :min="0" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="levelForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="通过分数(%)" prop="passing_score">
              <el-input-number v-model="levelForm.passing_score" :min="1" :max="100" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="徽章颜色" prop="color">
              <el-color-picker v-model="levelForm.color" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="启用" prop="is_active">
          <el-switch v-model="levelForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showLevelDialog = false">取消</el-button>
        <el-button type="primary" :loading="savingLevel" @click="handleSaveLevel">保存</el-button>
      </template>
    </el-dialog>

    <!-- 添加试题对话框 -->
    <el-dialog v-model="showAddQuestionDialog" title="添加试题" width="650px">
      <el-form ref="questionFormRef" :model="questionForm" :rules="questionRules" label-width="100px">
        <el-form-item label="题目" prop="question">
          <el-input v-model="questionForm.question" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="questionForm.type">
            <el-option label="单选题" value="single_choice" />
            <el-option label="多选题" value="multiple_choice" />
            <el-option label="判断题" value="true_false" />
          </el-select>
        </el-form-item>
        <el-form-item label="选项" prop="options">
          <div v-for="(opt, idx) in questionForm.options" :key="idx" class="option-row mb-2">
            <el-input v-model="opt.text" :placeholder="`选项 ${String.fromCharCode(65 + idx)}`" style="width:350px" />
            <el-checkbox v-model="opt.is_correct" class="ml-2">正确</el-checkbox>
            <el-button v-if="questionForm.options.length > 2" type="danger" text @click="removeOption(idx)">删除</el-button>
          </div>
          <el-button type="primary" text @click="addOption">+ 添加选项</el-button>
        </el-form-item>
        <el-form-item label="分值" prop="points">
          <el-input-number v-model="questionForm.points" :min="1" />
        </el-form-item>
        <el-form-item label="排序" prop="sort_order">
          <el-input-number v-model="questionForm.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="解析" prop="explanation">
          <el-input v-model="questionForm.explanation" type="textarea" :rows="2" placeholder="答案解析" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddQuestionDialog = false">取消</el-button>
        <el-button type="primary" :loading="savingQuestion" @click="handleAddQuestion">添加</el-button>
      </template>
    </el-dialog>

    <!-- 批量导入对话框 -->
    <el-dialog v-model="showBulkImport" title="批量导入试题" width="700px">
      <p class="text-gray-500 mb-3">每行一道题，格式：<code>题目 | 选项A(正确) | 选项B | 选项C</code>，用 | 分隔，正确选项后加 (正确)</p>
      <el-input
        v-model="bulkText"
        type="textarea"
        :rows="12"
        placeholder="什么是REST API？ | 一种架构风格(正确) | 一种数据库 | 一种编程语言&#10;HTTP GET 请求的作用是？ | 获取资源(正确) | 创建资源 | 删除资源"
      />
      <template #footer>
        <el-button @click="showBulkImport = false">取消</el-button>
        <el-button type="primary" :loading="importing" @click="handleBulkImport">导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Plus } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import certApi from '@/api/certification';
import axios from 'axios';

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
  { label: '认证等级', value: globalStats.total_levels, color: 'text-blue-500' },
  { label: '认证总数', value: globalStats.total_certifications, color: 'text-purple-500' },
  { label: '已通过', value: globalStats.total_passed, color: 'text-green-500' },
  { label: '进行中', value: globalStats.total_in_progress, color: 'text-orange-500' },
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
  color: '#409eff',
  is_active: true,
});

const levelRules = {
  name: [{ required: true, message: '请输入等级名称' }],
};

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

const questionRules = {
  question: [{ required: true, message: '请输入题目' }],
};

// 批量导入
const showBulkImport = ref(false);
const bulkText = ref('');
const importing = ref(false);

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
  const map = { single_choice: '单选题', multiple_choice: '多选题', true_false: '判断题' };
  return map[type] || type;
}

function myStatusType(status) {
  const map = { in_progress: 'warning', passed: 'success', failed: 'danger', expired: 'info', revoked: 'danger' };
  return map[status] || 'info';
}

function myStatusLabel(status) {
  const map = { in_progress: '进行中', passed: '已通过', failed: '未通过', expired: '已过期', revoked: '已吊销' };
  return map[status] || status;
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
    ElMessage.error('加载认证等级失败');
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
    ElMessage.error('加载试题失败');
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
    ElMessage.error('加载我的认证失败');
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
      ElMessage.success('等级已更新');
    } else {
      await certApi.createLevel(levelForm);
      ElMessage.success('等级已创建');
    }
    showLevelDialog.value = false;
    loadLevels();
    loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败');
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
    color: row.color || '#409eff',
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
  ElMessage.info(`点击"我的认证"标签页，选择 "${row.name}" 等级参加考试`);
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
    ElMessage.success('试题已添加');
    showAddQuestionDialog.value = false;
    loadQuestions();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '添加失败');
  } finally {
    savingQuestion.value = false;
  }
}

async function handleBulkImport() {
  if (!bulkText.value.trim()) {
    ElMessage.warning('请输入批量导入内容');
    return;
  }

  importing.value = true;
  try {
    const lines = bulkText.value.trim().split('\n');
    const questions = lines.map(line => {
      const parts = line.split('|').map(s => s.trim());
      const question = parts[0];
      const options = parts.slice(1).map(opt => {
        const isCorrect = opt.includes('(正确)');
        return {
          text: opt.replace('(正确)', '').trim(),
          is_correct: isCorrect,
        };
      });
      return { question, options, points: 1 };
    }).filter(q => q.question && q.options.length >= 2);

    if (questions.length === 0) {
      ElMessage.warning('未解析到有效试题');
      return;
    }

    await certApi.bulkAddQuestions(selectedLevelId.value, { questions });
    ElMessage.success(`成功导入 ${questions.length} 道试题`);
    showBulkImport.value = false;
    bulkText.value = '';
    loadQuestions();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '批量导入失败');
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
