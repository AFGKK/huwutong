<template>
  <el-dialog v-model="visible" title="Widget 仓库" width="700px" destroy-on-close>
    <el-row :gutter="12" class="mb-4">
      <el-col :span="8">
        <el-select v-model="filterCategory" placeholder="分类" clearable style="width:100%" @change="fetchTemplates">
          <el-option label="全部" value="" />
          <el-option v-for="(lb, key) in categories" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-col>
      <el-col :span="8">
        <el-select v-model="filterType" placeholder="类型" clearable style="width:100%" @change="filterTemplates">
          <el-option label="全部" value="" />
          <el-option v-for="(lb, key) in types" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-col>
      <el-col :span="8" class="text-right">
        <el-button type="primary" @click="addCustomWidget">
          <el-icon><Plus /></el-icon> 自定义
        </el-button>
      </el-col>
    </el-row>

    <div v-loading="loading" class="template-grid">
      <div v-for="tpl in filteredTemplates" :key="tpl.id" class="template-card" @click="addFromTemplate(tpl)">
        <div class="tpl-header">
          <span class="tpl-type">{{ types[tpl.type] || tpl.type }}</span>
          <el-tag size="small" effect="plain">{{ tpl.category }}</el-tag>
        </div>
        <div class="tpl-name">{{ tpl.name }}</div>
        <div class="tpl-desc">{{ tpl.description || '暂无描述' }}</div>
        <div class="tpl-size">默认: {{ defaultLayoutLabel(tpl.default_layout) }}</div>
      </div>
      <div v-if="!filteredTemplates.length && !loading" class="empty-templates">
        暂无匹配的 Widget 模板
      </div>
    </div>
  </el-dialog>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getWidgetTemplates, createWidget } from '../../../api/dashboard'

const props = defineProps({ dashboardId: { type: Number, default: null } })
const emit = defineEmits(['added'])

const visible = ref(false)
const loading = ref(false)
const templates = ref([])
const filterCategory = ref('')
const filterType = ref('')

const categories = { general: '通用', license: 'License', billing: '账单', customer: '客户', security: '安全', system: '系统' }
const types = { stat: '统计', chart: '图表', list: '列表', metric: '指标', table: '表格', iframe: '嵌入', html: 'HTML', alert: '告警', report: '报表' }

const filteredTemplates = computed(() => {
  let result = templates.value
  if (filterCategory.value) result = result.filter(t => t.category === filterCategory.value)
  if (filterType.value) result = result.filter(t => t.type === filterType.value)
  return result
})

function defaultLayoutLabel(layout) {
  if (!layout) return '4×2'
  return `${layout.w || 4}×${layout.h || 2}`
}

async function fetchTemplates() {
  loading.value = true
  try {
    const { data } = await getWidgetTemplates(filterCategory.value || undefined)
    templates.value = data || []
  } catch (e) {
    ElMessage.error('获取模板列表失败')
  } finally {
    loading.value = false
  }
}

async function addFromTemplate(tpl) {
  if (!props.dashboardId) { ElMessage.warning('请先选择仪表盘'); return }
  try {
    await createWidget(props.dashboardId, {
      template_id: tpl.id,
      title: tpl.name,
    })
    ElMessage.success(`已添加「${tpl.name}」`)
    emit('added')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '添加失败')
  }
}

function addCustomWidget() {
  emit('added')
  visible.value = false
}

function open() {
  visible.value = true
  fetchTemplates()
}

defineExpose({ open })
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-right { text-align: right; }
.template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; min-height: 200px; }
.template-card {
  padding: 12px; border: 1px solid #e4e7ed; border-radius: 8px;
  cursor: pointer; transition: all 0.2s;
}
.template-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.1); }
.tpl-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.tpl-type { font-size: 11px; color: #409eff; font-weight: 600; text-transform: uppercase; }
.tpl-name { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.tpl-desc { font-size: 12px; color: #909399; margin-bottom: 6px; }
.tpl-size { font-size: 11px; color: #c0c4cc; }
.empty-templates { grid-column: 1 / -1; text-align: center; padding: 48px; color: #909399; }
</style>
