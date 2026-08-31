<template>
  <el-dialog v-model="visible" :title="t('widget_library.title')" width="700px" destroy-on-close>
    <el-row :gutter="12" class="wl-mb-4">
      <el-col :span="8">
        <el-select v-model="filterCategory" :placeholder="t('widget_library.category')" clearable style="width:100%" @change="fetchTemplates">
          <el-option :label="t('widget_library.all')" value="" />
          <el-option v-for="(lb, key) in categories" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-col>
      <el-col :span="8">
        <el-select v-model="filterType" :placeholder="t('widget_library.type')" clearable style="width:100%" @change="filterTemplates">
          <el-option :label="t('widget_library.all')" value="" />
          <el-option v-for="(lb, key) in types" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-col>
      <el-col :span="8" class="wl-text-right">
        <el-button type="primary" @click="addCustomWidget">
          <el-icon><Plus /></el-icon> {{ t('widget_library.custom') }}
        </el-button>
      </el-col>
    </el-row>

    <div v-loading="loading" class="wl-template-grid">
      <div v-for="tpl in filteredTemplates" :key="tpl.id" class="wl-template-card" @click="addFromTemplate(tpl)">
        <div class="wl-tpl-header">
          <span class="wl-tpl-type">{{ types[tpl.type] || tpl.type }}</span>
          <el-tag size="small" effect="plain">{{ tpl.category }}</el-tag>
        </div>
        <div class="wl-tpl-name">{{ tpl.name }}</div>
        <div class="wl-tpl-desc">{{ tpl.description || t('widget_library.no_desc') }}</div>
        <div class="wl-tpl-size">{{ t('widget_library.default_size', { size: defaultLayoutLabel(tpl.default_layout) }) }}</div>
      </div>
      <div v-if="!filteredTemplates.length && !loading" class="wl-empty-templates">
        {{ t('widget_library.empty') }}
      </div>
    </div>
  </el-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getWidgetTemplates, createWidget } from '@/api/dashboard'

const { t } = useI18n()
const props = defineProps({ dashboardId: { type: Number, default: null } })
const emit = defineEmits(['added'])

const visible = ref(false)
const loading = ref(false)
const templates = ref([])
const filterCategory = ref('')
const filterType = ref('')

const categories = computed(() => ({
  general: t('widget_library.categories.general'),
  license: 'License',
  billing: t('widget_library.categories.billing'),
  customer: t('widget_library.categories.customer'),
  security: t('widget_library.categories.security'),
  system: t('widget_library.categories.system'),
}))
const types = computed(() => ({
  stat: t('widget_library.types.stat'),
  chart: t('widget_library.types.chart'),
  list: t('widget_library.types.list'),
  metric: t('widget_library.types.metric'),
  table: t('widget_library.types.table'),
  iframe: t('widget_library.types.iframe'),
  html: 'HTML',
  alert: t('widget_library.types.alert'),
  report: t('widget_library.types.report'),
}))

const filteredTemplates = computed(() => {
  let result = templates.value
  if (filterCategory.value) result = result.filter(item => item.category === filterCategory.value)
  if (filterType.value) result = result.filter(item => item.type === filterType.value)
  return result
})

function defaultLayoutLabel(layout) {
  if (!layout) return '4x2'
  return `${layout.w || 4}x${layout.h || 2}`
}

function filterTemplates() {}

async function fetchTemplates() {
  loading.value = true
  try {
    const { data } = await getWidgetTemplates(filterCategory.value || undefined)
    templates.value = data || []
  } catch (e) {
    ElMessage.error(t('widget_library.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

async function addFromTemplate(tpl) {
  if (!props.dashboardId) { ElMessage.warning(t('widget_library.messages.select_dashboard')); return }
  try {
    await createWidget(props.dashboardId, {
      template_id: tpl.id,
      title: tpl.name,
    })
    ElMessage.success(t('widget_library.messages.added', { name: tpl.name }))
    emit('added')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('widget_library.messages.add_failed'))
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
.wl-mb-4 { margin-bottom: 16px; }
.wl-text-right { text-align: right; }
.wl-template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; min-height: 200px; }
.wl-template-card {
  padding: 12px; border: 1px solid #e4e7ed; border-radius: 8px;
  cursor: pointer; transition: all 0.2s;
}
.wl-template-card:hover { border-color: #0f172a; box-shadow: 0 2px 8px rgba(15,23,42,0.1); }
.wl-tpl-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.wl-tpl-type { font-size: 11px; color: #0f172a; font-weight: 600; text-transform: uppercase; }
.wl-tpl-name { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.wl-tpl-desc { font-size: 12px; color: #909399; margin-bottom: 6px; }
.wl-tpl-size { font-size: 11px; color: #c0c4cc; }
.wl-empty-templates { grid-column: 1 / -1; text-align: center; padding: 48px; color: #909399; }
</style>
