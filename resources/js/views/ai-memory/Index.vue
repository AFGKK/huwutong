<template>
  <div class="p-6">
    <!-- 头部 -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">🤖 AI 长期记忆</h1>
        <p class="text-gray-500 text-sm mt-1">跨会话记住用户偏好与上下文，自动提取+手动管理</p>
      </div>
      <div class="flex gap-2">
        <el-button type="warning" plain :disabled="!stats.total" @click="handleClearAll">
          <el-icon><Delete /></el-icon> 清空所有
        </el-button>
        <el-button type="primary" @click="showCreate = true">
          <el-icon><Plus /></el-icon> 手动添加
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">总记忆数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.high_priority }}</div>
          <div class="stat-label">高优先级</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.expiring_soon }}</div>
          <div class="stat-label">即将过期</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ (stats.avg_confidence * 100).toFixed(0) }}%</div>
          <div class="stat-label">平均置信度</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 类型分布 + 分类分布 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>📊 类型分布</span></template>
          <div v-if="stats.by_type?.length" class="flex flex-wrap gap-2">
            <el-tag v-for="t in stats.by_type" :key="t.type" :type="tagType(t.type)" class="text-sm">
              {{ typeLabel(t.type) }}: {{ t.total }}
            </el-tag>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>📂 分类分布</span></template>
          <div v-if="stats.by_category?.length" class="flex flex-wrap gap-2">
            <el-tag v-for="c in stats.by_category" :key="c.category" type="info" class="text-sm">
              {{ categoryLabel(c.category) }}: {{ c.total }}
            </el-tag>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 最近记忆 -->
    <el-card shadow="never" class="mb-6">
      <template #header><span>🕐 最近记忆</span></template>
      <div v-if="stats.recent?.length" class="space-y-2">
        <div v-for="m in stats.recent" :key="m.id" class="flex items-start gap-3 p-2 hover:bg-gray-50 rounded">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate">{{ m.content }}</div>
            <div class="text-xs text-gray-400 mt-1">
              <el-tag :type="tagType(m.type)" size="small">{{ typeLabel(m.type) }}</el-tag>
              <span class="ml-2">置信度: {{ (m.confidence * 100).toFixed(0) }}%</span>
              <span class="ml-2">优先级: {{ m.priority }}</span>
              <span class="ml-2">{{ formatTime(m.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
      <el-empty v-else description="暂无记忆，AI 将在对话中自动提取" :image-size="80" />
    </el-card>

    <!-- 全部记忆列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>📋 全部记忆</span>
          <div class="flex gap-2">
            <el-input v-model="filters.search" placeholder="搜索记忆..." clearable size="small" style="width:200px" @clear="fetchList" @keyup.enter="fetchList" />
            <el-select v-model="filters.type" placeholder="类型筛选" clearable size="small" style="width:120px" @change="fetchList">
              <el-option v-for="(l, v) in typeOptions" :key="v" :label="l" :value="v" />
            </el-select>
            <el-select v-model="filters.category" placeholder="分类筛选" clearable size="small" style="width:140px" @change="fetchList">
              <el-option v-for="(l, v) in categoryOptions" :key="v" :label="l" :value="v" />
            </el-select>
          </div>
        </div>
      </template>

      <el-table :data="list" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="content" label="记忆内容" min-width="300" show-overflow-tooltip />
        <el-table-column prop="type" label="类型" width="80">
          <template #default="{ row }">
            <el-tag :type="tagType(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="100">
          <template #default="{ row }">
            {{ categoryLabel(row.category) }}
          </template>
        </el-table-column>
        <el-table-column prop="source" label="来源" width="80">
          <template #default="{ row }">
            <span class="text-xs text-gray-500">{{ sourceLabel(row.source) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="confidence" label="置信度" width="80">
          <template #default="{ row }">
            <span :class="confidenceColor(row.confidence)">{{ (row.confidence * 100).toFixed(0) }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="priority" label="优先级" width="70" />
        <el-table-column label="创建时间" width="150">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleConfirm(row)">确认</el-button>
            <el-button link type="warning" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-popconfirm title="确定遗忘这条记忆？" @confirm="handleDelete(row)">
              <template #reference>
                <el-button link type="danger" size="small">遗忘</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-4" v-if="pagination.total > pagination.per_page">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <!-- 手动添加对话框 -->
    <el-dialog v-model="showCreate" title="手动添加记忆" width="500px">
      <el-form :model="createForm" label-position="top">
        <el-form-item label="记忆内容" required>
          <el-input v-model="createForm.content" type="textarea" :rows="3" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="createForm.type" style="width:100%">
            <el-option v-for="(l, v) in typeOptions" :key="v" :label="l" :value="v" />
          </el-select>
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="createForm.category" clearable style="width:100%">
            <el-option v-for="(l, v) in categoryOptions" :key="v" :label="l" :value="v" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-slider v-model="createForm.priority" :min="0" :max="255" show-input style="width:300px" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleCreate">保存</el-button>
      </template>
    </el-dialog>

    <!-- 编辑对话框 -->
    <el-dialog v-model="showEdit" title="编辑记忆" width="500px">
      <el-form :model="editForm" label-position="top">
        <el-form-item label="记忆内容" required>
          <el-input v-model="editForm.content" type="textarea" :rows="3" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="editForm.type" style="width:100%">
            <el-option v-for="(l, v) in typeOptions" :key="v" :label="l" :value="v" />
          </el-select>
        </el-form-item>
        <el-form-item label="置信度">
          <el-slider v-model="editForm.confidence" :min="0" :max="1" :step="0.05" show-input style="width:300px" />
        </el-form-item>
        <el-form-item label="优先级">
          <el-slider v-model="editForm.priority" :min="0" :max="255" show-input style="width:300px" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEdit = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleUpdate">保存</el-button>
      </template>
    </el-dialog>

    <!-- AI 提取对话框 -->
    <el-dialog v-model="showExtract" title="🤖 AI 从文本提取记忆" width="600px">
      <p class="text-sm text-gray-500 mb-3">粘贴对话或文本内容，AI 将自动提取值得记住的信息</p>
      <el-input v-model="extractText" type="textarea" :rows="8" placeholder="粘贴对话内容..." maxlength="10000" show-word-limit />
      <template #footer>
        <el-button @click="showExtract = false">取消</el-button>
        <el-button type="primary" :loading="extracting" @click="handleExtract">开始提取</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete } from '@element-plus/icons-vue';
import memoryApi from '@/api/memory';

// ── 状态 ──
const loading = ref(false);
const saving = ref(false);
const extracting = ref(false);
const list = ref([]);
const stats = ref({ total: 0, by_type: [], by_category: [], recent: [], expiring_soon: 0, high_priority: 0, avg_confidence: 0 });

const filters = reactive({ search: '', type: '', category: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const showCreate = ref(false);
const showEdit = ref(false);
const showExtract = ref(false);
const createForm = reactive({ content: '', type: 'fact', category: null, priority: 5 });
const editForm = reactive({ id: null, content: '', type: 'fact', confidence: 0.8, priority: 5 });
const extractText = ref('');

const typeOptions = {};
const categoryOptions = {};

// ── 工具函数 ──
const TYPE_MAP = { preference: '偏好', fact: '事实', context: '上下文', insight: '洞察', behavior: '行为' };
const CATEGORY_MAP = {
  user_preference: '用户偏好', user_fact: '用户事实', business_info: '业务信息',
  personal_info: '个人信息', technical_context: '技术上下文', project_context: '项目上下文',
  conversation_style: '对话风格', ai_insight: 'AI洞察',
};
const SOURCE_MAP = { ai_extracted: 'AI提取', manual: '手动', system: '系统', conversation: '对话' };

function typeLabel(t) { return TYPE_MAP[t] || t; }
function categoryLabel(c) { return CATEGORY_MAP[c] || c || '-'; }
function sourceLabel(s) { return SOURCE_MAP[s] || s; }
function tagType(t) {
  const map = { preference: 'success', fact: 'primary', context: 'info', insight: 'warning', behavior: 'danger' };
  return map[t] || '';
}
function confidenceColor(c) {
  return c >= 0.8 ? 'text-green-600' : c >= 0.5 ? 'text-yellow-600' : 'text-gray-500';
}
function formatTime(t) {
  if (!t) return '';
  return new Date(t).toLocaleString('zh-CN');
}

// ── 数据加载 ──
async function fetchList() {
  loading.value = true;
  try {
    const params = { page: pagination.current_page, per_page: pagination.per_page, ...filters };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await memoryApi.list(params);
    list.value = res.data?.data || [];
    pagination.current_page = res.data?.meta?.current_page || 1;
    pagination.per_page = res.data?.meta?.per_page || 20;
    pagination.total = res.data?.meta?.total || 0;
  } catch (e) {
    ElMessage.error('加载记忆列表失败');
  } finally {
    loading.value = false;
  }
}

async function fetchDashboard() {
  try {
    const res = await memoryApi.dashboard();
    stats.value = res.data?.data || stats.value;
  } catch (_) { /* ignore */ }
}

// ── 操作 ──
async function handleCreate() {
  if (!createForm.content.trim()) return ElMessage.warning('请输入记忆内容');
  saving.value = true;
  try {
    await memoryApi.create({ ...createForm });
    ElMessage.success('记忆已保存');
    showCreate.value = false;
    createForm.content = '';
    createForm.type = 'fact';
    createForm.priority = 5;
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error('保存失败');
  } finally {
    saving.value = false;
  }
}

function handleEdit(row) {
  editForm.id = row.id;
  editForm.content = row.content;
  editForm.type = row.type;
  editForm.confidence = row.confidence;
  editForm.priority = row.priority;
  showEdit.value = true;
}

async function handleUpdate() {
  if (!editForm.content.trim()) return ElMessage.warning('请输入记忆内容');
  saving.value = true;
  try {
    await memoryApi.update(editForm.id, {
      content: editForm.content,
      type: editForm.type,
      confidence: editForm.confidence,
      priority: editForm.priority,
    });
    ElMessage.success('记忆已更新');
    showEdit.value = false;
    await fetchList();
  } catch (e) {
    ElMessage.error('更新失败');
  } finally {
    saving.value = false;
  }
}

async function handleConfirm(row) {
  try {
    await memoryApi.confirm(row.id);
    ElMessage.success('记忆已确认');
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error('确认失败');
  }
}

async function handleDelete(row) {
  try {
    await memoryApi.remove(row.id);
    ElMessage.success('记忆已遗忘');
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error('删除失败');
  }
}

async function handleExtract() {
  if (!extractText.value.trim()) return ElMessage.warning('请输入文本');
  extracting.value = true;
  try {
    const res = await memoryApi.extract(extractText.value);
    ElMessage.success(res.data?.message || '提取完成');
    showExtract.value = false;
    extractText.value = '';
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error('提取失败');
  } finally {
    extracting.value = false;
  }
}

function handleClearAll() {
  ElMessageBox.confirm('确定清空所有记忆？此操作不可撤销！', '警告', {
    type: 'warning', confirmButtonText: '确认清空', cancelButtonText: '取消',
  }).then(async () => {
    await memoryApi.clearAll();
    ElMessage.success('记忆已清空');
    await fetchDashboard();
    await fetchList();
  }).catch(() => {});
}

// ── 初始化 ──
onMounted(() => {
  fetchDashboard();
  fetchList();
});
</script>

<style scoped>
.stat-card {
  text-align: center;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #409eff;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}
</style>
