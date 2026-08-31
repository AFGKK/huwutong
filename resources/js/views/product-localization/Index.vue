<template>
    <div class="product-localization-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_entries }}</div>
                    <div class="stat-label">{{ t('product_localization_page.stats.total_entries') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.auto_translated }}</div>
                    <div class="stat-label">{{ t('product_localization_page.stats.auto_translated') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.manual_translated }}</div>
                    <div class="stat-label">{{ t('product_localization_page.stats.manual_translated') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.language_count }}</div>
                    <div class="stat-label">{{ t('product_localization_page.stats.language_count') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <el-col :span="8">
                <el-card class="side-card">
                    <template #header>
                        <span>{{ t('product_localization_page.select_content') }}</span>
                    </template>
                    <el-form label-position="top" size="small">
                        <el-form-item :label="t('product_localization_page.label_type')">
                            <el-select v-model="selectedType" style="width: 100%">
                                <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('product_localization_page.label_id')">
                            <el-input-number v-model="selectedItemId" :min="1" :placeholder="t('product_localization_page.id_placeholder')" style="width: 100%" />
                        </el-form-item>
                        <el-form-item :label="t('product_localization_page.label_target_locale')">
                            <el-select v-model="selectedLocale" style="width: 100%">
                                <el-option v-for="lang in languages" :key="lang.locale" :label="`${lang.flag || ''} ${lang.name} (${lang.native_name})`" :value="lang.locale" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('product_localization_page.label_translatable_fields')">
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
                            <span>{{ t('product_localization_page.editor_title') }}</span>
                            <div>
                                <el-button size="small" @click="loadTranslations">{{ t('product_localization_page.refresh') }}</el-button>
                                <el-button size="small" type="primary" :loading="saving" @click="saveTranslations">
                                    <el-icon><Check /></el-icon> {{ t('actions.save') }}
                                </el-button>
                            </div>
                        </div>
                    </template>
                    <template v-if="!selectedItemId || !selectedLocale">
                        <el-empty :description="t('product_localization_page.empty_hint')" />
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
                                :placeholder="t('product_localization_page.input_translation_ph')"
                            />
                            <el-input
                                v-else
                                v-model="translationValues[field]"
                                type="textarea"
                                :rows="field === 'description' ? 3 : 1"
                                :placeholder="fieldLocalePlaceholder(field)"
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
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import {
    getLocalizationLanguages, getLocalizationStats,
    getProductTranslations, saveProductTranslations,
    getPlanTranslations, savePlanTranslations,
} from '@/api/productLocalization'

const { t } = useI18n()

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

const typeOptions = computed(() => [
    { value: 'product', label: t('product_localization_page.type_product') },
    { value: 'plan', label: t('product_localization_page.type_plan') },
])

const fieldLabels = computed(() => ({
    name: t('product_localization_page.fields.name'),
    description: t('product_localization_page.fields.description'),
    features: t('product_localization_page.fields.features'),
}))

function fieldLabel(f) {
    return fieldLabels.value[f] || f
}

function fieldLocalePlaceholder(field) {
    return t('product_localization_page.field_locale_ph', {
        field: fieldLabel(field),
        locale: selectedLocale.value,
    })
}

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
        ElMessage.warning(t('product_localization_page.messages.not_found'))
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

        ElMessage.success(t('product_localization_page.messages.saved'))
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
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
