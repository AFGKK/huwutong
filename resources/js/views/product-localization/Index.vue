<template>
    <div class="product-localization-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_entries }}</div>
                    <div class="stat-label">翻译条目数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.auto_translated }}</div>
                    <div class="stat-label">AI自动翻译</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.manual_translated }}</div>
                    <div class="stat-label">手动翻译</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.language_count }}</div>
                    <div class="stat-label">语言数量</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <el-col :span="8">
                <el-card class="side-card">
                    <template #header>
                        <span>选择内容</span>
                    </template>
                    <el-form label-position="top" size="small">
                        <el-form-item label="类型">
                            <el-select v-model="selectedType" style="width: 100%">
                                <el-option label="商品 (Product)" value="product" />
                                <el-option label="方案 (PricingPlan)" value="plan" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="ID">
                            <el-input-number v-model="selectedItemId" :min="1" placeholder="输入ID" style="width: 100%" />
                        </el-form-item>
                        <el-form-item label="目标语言">
                            <el-select v-model="selectedLocale" style="width: 100%">
                                <el-option v-for="lang in languages" :key="lang.locale" :label="`${lang.flag || ''} ${lang.name} (${lang.native_name})`" :value="lang.locale" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="可翻译字段">
                            <el-checkbox-group v-model="editableFields">
                                <el-checkbox v-for="f in translatableFields" :key="f" :label="f">{{ fieldLabel(f) }}</el-checkbox>
                            </el-checkbox-group>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card class="editor-card">
                    <template #header>
                        <div class="editor-header">
                            <span>翻译编辑器</span>
                            <div>
                                <el-button size="small" @click="loadTranslations">刷新</el-button>
                                <el-button size="small" type="primary" :loading="saving" @click="saveTranslations">
                                    <el-icon><Check /></el-icon> 保存
                                </el-button>
                            </div>
                        </div>
                    </template>
                    <template v-if="!selectedItemId || !selectedLocale">
                        <el-empty description="请输入ID并选择语言" />
                    </template>
                    <template v-else>
                        <div v-for="field in editableFields" :key="field" class="translation-field">
                            <div class="field-header">
                                <span class="field-label">{{ fieldLabel(field) }}</span>
                            </div>
                            <el-input
                                v-if="field === 'features'"
                                v-model="translationValues[field]"
                                type="textarea"
                                :rows="4"
                                placeholder="输入翻译..."
                            />
                            <el-input
                                v-else
                                v-model="translationValues[field]"
                                type="textarea"
                                :rows="field === 'description' ? 3 : 1"
                                :placeholder="`输入${fieldLabel(field)}的${selectedLocale}翻译...`"
                            />
                        </div>
                    </template>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import {
    getLocalizationLanguages, getLocalizationStats,
    getProductTranslations, saveProductTranslations,
    getPlanTranslations, savePlanTranslations,
} from '@/api/productLocalization'

const stats = ref({ total_entries: 0, auto_translated: 0, manual_translated: 0, language_count: 0 })
const languages = ref([])
const saving = ref(false)

const selectedType = ref('product')
const selectedItemId = ref(null)
const selectedLocale = ref('en')
const translationValues = ref({})
const editableFields = ref(['name', 'description'])

const translatableFields = computed(() => {
    if (selectedType.value === 'plan') return ['name', 'description', 'features']
    return ['name', 'description']
})

const fieldLabels = { name: '名称', description: '描述', features: '功能特点' }
function fieldLabel(f) { return fieldLabels[f] || f }

async function loadStats() {
    try {
        const res = await getLocalizationStats()
        stats.value = res.data || res
    } catch { /* ignore */ }
}

async function loadLanguages() {
    try {
        const res = await getLocalizationLanguages()
        languages.value = res.data || res
    } catch { /* ignore */ }
}

async function loadTranslations() {
    if (!selectedItemId.value || !selectedLocale.value) return

    try {
        const loader = selectedType.value === 'product' ? getProductTranslations : getPlanTranslations
        const res = await loader(selectedItemId.value)
        const translations = res.data || []

        const localeTranslations = translations.filter(t => t.locale === selectedLocale.value)
        const vals = {}
        localeTranslations.forEach(t => { vals[t.field] = t.value })
        translationValues.value = vals
    } catch {
        translationValues.value = {}
        ElMessage.warning('未找到该ID的翻译数据')
    }
}

async function saveTranslations() {
    if (!selectedItemId.value || !selectedLocale.value) return

    saving.value = true
    try {
        const data = {
            locale: selectedLocale.value,
            translations: { ...translationValues.value },
        }

        const saver = selectedType.value === 'product' ? saveProductTranslations : savePlanTranslations
        await saver(selectedItemId.value, data)

        ElMessage.success('翻译已保存')
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

watch(selectedLocale, () => loadTranslations())

onMounted(() => {
    loadStats()
    loadLanguages()
})
</script>

<style scoped>
.product-localization-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; margin-bottom: 10px; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.side-card { margin-bottom: 16px; }
.editor-card { margin-bottom: 16px; }
.editor-header { display: flex; justify-content: space-between; align-items: center; }
.translation-field { margin-bottom: 16px; padding: 12px; background: #fafafa; border-radius: 6px; }
.field-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
.field-label { font-weight: bold; color: #303133; }
</style>
