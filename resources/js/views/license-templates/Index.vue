<template>
  <div class="license-templates">
    <h2 class="mb-4">License 模板管理</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ templates.length }}</div><div class="stat-label">模板总数</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-success">{{ activeCount }}</div><div class="stat-label">已启用</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-primary">{{ batchTasks.length }}</div><div class="stat-label">批量任务</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-success">{{ totalGenerated }}</div><div class="stat-label">已生成 License</div></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs -->
    <el-tabs v-model="activeTab" type="border-card">
      <!-- 模板列表 -->
      <el-tab-pane label="模板列表" name="templates">
        <div class="mb-3 flex items-center justify-between">
          <span class="text-sm text-gray-500">基于模板快速创建 License，支持变量替换和批量生成</span>
          <el-button type="primary" size="small" @click="showCreateTemplate">新建模板</el-button>
        </div>
        <el-table :data="templates" stripe v-loading="loading" @row-click="editTemplate" style="cursor:pointer">
          <el-table-column label="名称" prop="name" min-width="160" />
          <el-table-column label="产品" prop="product?.name" width="120" />
          <el-table-column label="类型" prop="type" width="90">
            <template #default="{ row }"><el-tag size="small">{{ typeLabel(row.type) }}</el-tag></template>
          </el-table-column>
          <el-table-column label="座位数" prop="seats" width="70" />
          <el-table-column label="设备数" prop="max_devices" width="70" />
          <el-table-column label="有效期" prop="expiry_days" width="90">
            <template #default="{ row }">{{ row.expiry_days ? row.expiry_days + ' 天' : '永久' }}</template>
          </el-table-column>
          <el-table-column label="状态" width="70">
            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click.stop="editTemplate(row)">编辑</el-button>
              <el-button size="small" type="success" @click.stop="showBatchGenerate(row)">批量生成</el-button>
              <el-popconfirm title="确认删除？" @confirm.stop="removeTemplate(row)">
                <template #reference><el-button size="small" type="danger" @click.stop>删除</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 批量任务 -->
      <el-tab-pane label="批量生成任务" name="batch">
        <div class="mb-3 text-sm text-gray-500">查看批量生成 License 的历史任务和状态</div>
        <el-table :data="batchTasks" stripe v-loading="batchLoading">
          <el-table-column label="任务名称" prop="name" min-width="160" />
          <el-table-column label="模板" prop="template?.name" width="140" />
          <el-table-column label="总数" prop="total_count" width="70" />
          <el-table-column label="成功" prop="success_count" width="70">
            <template #default="{ row }"><span class="text-success">{{ row.success_count }}</span></template>
          </el-table-column>
          <el-table-column label="失败" prop="failed_count" width="70">
            <template #default="{ row }"><span class="text-danger">{{ row.failed_count }}</span></template>
          </el-table-column>
          <el-table-column label="状态" prop="status" width="100">
            <template #default="{ row }">
              <el-tag :type="batchStatusTag(row.status)" size="small">{{ batchStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作人" prop="user?.name" width="100" />
          <el-table-column label="完成时间" prop="completed_at" width="160" />
          <el-table-column label="操作" width="120" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click.stop="showBatchDetail(row)">详情</el-button>
              <el-popconfirm title="确认删除？" @confirm.stop="removeBatchTask(row)">
                <template #reference><el-button size="small" type="danger" @click.stop>删除</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 模板编辑对话框 -->
    <TemplateDialog v-model:visible="templateDialog.visible" :template-data="templateDialog.data" @saved="loadTemplates" />

    <!-- 批量生成对话框 -->
    <BatchGenerateDialog v-model:visible="batchDialog.visible" :template="batchDialog.template" @saved="loadBatchTasks" />

    <!-- 批量任务详情 -->
    <BatchDetailDialog v-model:visible="detailDialog.visible" :task-id="detailDialog.taskId" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getLicenseTemplates, deleteLicenseTemplate, getBatchTasks, deleteBatchTask } from '../../api/licenseTemplate'
import TemplateDialog from './components/TemplateDialog.vue'
import BatchGenerateDialog from './components/BatchGenerateDialog.vue'
import BatchDetailDialog from './components/BatchDetailDialog.vue'

const activeTab = ref('templates')
const templates = ref([])
const batchTasks = ref([])
const loading = ref(false)
const batchLoading = ref(false)

const templateDialog = ref({ visible: false, data: null })
const batchDialog = ref({ visible: false, template: null })
const detailDialog = ref({ visible: false, taskId: null })

const activeCount = computed(() => templates.value.filter(t => t.is_active).length)
const totalGenerated = computed(() => batchTasks.value.reduce((s, t) => s + t.success_count, 0))

function typeLabel(t) { return { trial: '试用', standard: '标准', enterprise: '企业', development: '开发' }[t] || t }
function batchStatusTag(s) { return { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger', partial: 'warning' }[s] || 'info' }
function batchStatusLabel(s) { return { pending: '待处理', processing: '处理中', completed: '已完成', failed: '失败', partial: '部分成功' }[s] || s }

function showCreateTemplate() { templateDialog.value.data = null; templateDialog.value.visible = true }
function editTemplate(row) { templateDialog.value.data = { ...row }; templateDialog.value.visible = true }
function showBatchGenerate(template) { batchDialog.value.template = template; batchDialog.value.visible = true }
function showBatchDetail(task) { detailDialog.value.taskId = task.id; detailDialog.value.visible = true }

async function removeTemplate(row) {
  try {
    await deleteLicenseTemplate(row.id)
    ElMessage.success('已删除')
    await loadTemplates()
  } catch (e) { ElMessage.error('删除失败') }
}

async function removeBatchTask(row) {
  try {
    await deleteBatchTask(row.id)
    ElMessage.success('已删除')
    await loadBatchTasks()
  } catch (e) { ElMessage.error('删除失败') }
}

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await getLicenseTemplates()
    templates.value = Array.isArray(data) ? data : data?.data || []
  } catch (e) { ElMessage.error('加载模板失败') } finally { loading.value = false }
}

async function loadBatchTasks() {
  batchLoading.value = true
  try {
    const { data } = await getBatchTasks()
    batchTasks.value = Array.isArray(data) ? data : data?.data || []
  } catch (e) { console.error(e) } finally { batchLoading.value = false }
}

onMounted(() => {
  loadTemplates()
  loadBatchTasks()
})
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-danger { color: #f56c6c !important; }
.text-primary { color: #409eff !important; }
</style>
