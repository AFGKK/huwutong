<template>
    <div class="case-studies-page">
        <div class="page-header">
            <h2>{{ t('case_studies_page.title') }}</h2>
            <p class="text-muted">{{ t('case_studies_page.subtitle') }}</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="(count, key) in stats" :key="key">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ count }}</div>
                        <div class="stat-label">{{ statLabel(key) }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>{{ t('case_studies_page.list_title') }}</span>
                    <el-button type="primary" size="small" @click="showCreate = true">{{ t('case_studies_page.new_case') }}</el-button>
                </div>
            </template>
            <el-table :data="cases" v-loading="loading" stripe>
                <el-table-column :label="t('case_studies_page.cols.logo')" width="60">
                    <template #default="{ row }">
                        <el-avatar v-if="row.logo" :src="row.logo" shape="square" size="small" />
                        <el-avatar v-else shape="square" size="small" icon="Picture" />
                    </template>
                </el-table-column>
                <el-table-column prop="title" :label="t('case_studies_page.cols.title')" min-width="180" />
                <el-table-column prop="company" :label="t('case_studies_page.cols.company')" width="150" />
                <el-table-column prop="category" :label="t('case_studies_page.cols.category')" width="100">
                    <template #default="{ row }">{{ categories[row.category] || row.category }}</template>
                </el-table-column>
                <el-table-column prop="industry" :label="t('case_studies_page.cols.industry')" width="100" />
                <el-table-column :label="t('case_studies_page.cols.featured')" width="60">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_featured" color="#e6a23c"><StarFilled /></el-icon>
                    </template>
                </el-table-column>
                <el-table-column :label="t('case_studies_page.cols.status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_published ? 'success' : 'info'" size="small">
                            {{ row.is_published ? t('case_studies_page.published') : t('case_studies_page.draft') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('case_studies_page.cols.actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="handleEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-popconfirm :title="t('case_studies_page.confirm_delete')" @confirm="handleDelete(row.id)">
                            <template #reference>
                                <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('case_studies_page.logo_wall') }}</span>
                    <el-button size="small" @click="showUploadLogo = true">{{ t('case_studies_page.upload_logo') }}</el-button>
                </div>
            </template>
            <el-row :gutter="16">
                <el-col :span="4" v-for="logo in logos" :key="logo.id" class="mb-2">
                    <div class="logo-item">
                        <el-image :src="logo.url" fit="contain" style="height:60px" />
                    </div>
                </el-col>
            </el-row>
            <el-empty v-if="logos.length === 0" :description="t('case_studies_page.no_logos')" />
        </el-card>

        <el-dialog v-model="showCreate" :title="editingId ? t('case_studies_page.edit_title') : t('case_studies_page.create_title')" width="700px">
            <el-form :model="form" label-width="100px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('case_studies_page.cols.title')" required><el-input v-model="form.title" /></el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('case_studies_page.cols.company')" required><el-input v-model="form.company" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('case_studies_page.cols.category')" required>
                            <el-select v-model="form.category" style="width:100%">
                                <el-option v-for="(label, key) in categories" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('case_studies_page.cols.industry')">
                            <el-select v-model="form.industry" style="width:100%">
                                <el-option v-for="tag in industryTags" :key="tag" :label="tag" :value="tag" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('case_studies_page.summary')" required><el-input v-model="form.summary" type="textarea" :rows="2" /></el-form-item>
                <el-form-item :label="t('case_studies_page.content')" required><el-input v-model="form.content" type="textarea" :rows="6" /></el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('case_studies_page.quote')"><el-input v-model="form.quote" type="textarea" :rows="2" /></el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('case_studies_page.quote_author')"><el-input v-model="form.quote_author" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('case_studies_page.cols.featured')">
                            <el-switch v-model="form.is_featured" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('case_studies_page.publish')">
                            <el-switch v-model="form.is_published" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('case_studies_page.cols.logo')">
                            <el-upload :auto-upload="false" :show-file-list="false" @change="(f) => form.logo = f.raw">
                                <el-button size="small">{{ t('case_studies_page.choose_file') }}</el-button>
                            </el-upload>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showUploadLogo" :title="t('case_studies_page.upload_logo')" width="400px">
            <el-upload drag :auto-upload="false" :show-file-list="true" @change="(f) => logoFile = f.raw">
                <el-icon :size="40"><UploadFilled /></el-icon>
                <p>{{ t('case_studies_page.drag_upload') }}</p>
            </el-upload>
            <template #footer>
                <el-button @click="showUploadLogo = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleUploadLogo" :loading="uploading">{{ t('case_studies_page.upload') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { StarFilled, UploadFilled } from '@element-plus/icons-vue'
import {
    getCaseStudies, createCaseStudy, updateCaseStudy, deleteCaseStudy,
    getCaseStudyCategories, getIndustryTags, getCaseStudyStats,
    getLogoWall, uploadCaseLogo,
} from '@/api/caseStudies'

const { t } = useI18n()

const cases = ref([])
const logos = ref([])
const loading = ref(false)
const saving = ref(false)
const uploading = ref(false)
const showCreate = ref(false)
const showUploadLogo = ref(false)
const editingId = ref(null)
const categories = ref({})
const industryTags = ref([])
const stats = ref({})
const logoFile = ref(null)

const form = ref({
    title: '', company: '', category: '', industry: '',
    summary: '', content: '', quote: '', quote_author: '',
    is_featured: false, is_published: false, logo: null,
})

function statLabel(key) {
    const map = {
        total_cases: 'total_cases',
        total_logos: 'total_logos',
        by_category: 'by_category',
        by_industry: 'by_industry',
    }
    return map[key] ? t(`case_studies_page.stats.${map[key]}`) : key
}

const loadData = async () => {
    loading.value = true
    try {
        const [casesRes, catRes, tagsRes, statsRes, logoRes] = await Promise.all([
            getCaseStudies(), getCaseStudyCategories(), getIndustryTags(),
            getCaseStudyStats(), getLogoWall(),
        ])
        if (casesRes.data.success) cases.value = casesRes.data.data.data || []
        if (catRes.data.success) categories.value = catRes.data.data
        if (tagsRes.data.success) industryTags.value = tagsRes.data.data
        if (statsRes.data.success) stats.value = statsRes.data.data
        if (logoRes.data.success) logos.value = logoRes.data.data.logos || []
    } catch { /* ignore */ }
    finally { loading.value = false }
}

const resetForm = () => {
    form.value = { title: '', company: '', category: '', industry: '', summary: '', content: '', quote: '', quote_author: '', is_featured: false, is_published: false, logo: null }
    editingId.value = null
}

const handleEdit = (row) => {
    editingId.value = row.id
    form.value = { ...row }
    showCreate.value = true
}

const handleSave = async () => {
    saving.value = true
    try {
        const res = editingId.value
            ? await updateCaseStudy(editingId.value, form.value)
            : await createCaseStudy(form.value)
        if (res.data.success) {
            ElMessage.success(editingId.value ? t('case_studies_page.messages.updated') : t('case_studies_page.messages.created'))
            showCreate.value = false
            resetForm()
            await loadData()
        }
    } catch { ElMessage.error(t('case_studies_page.messages.save_failed')) }
    finally { saving.value = false }
}

const handleDelete = async (id) => {
    try {
        const res = await deleteCaseStudy(id)
        if (res.data.success) { ElMessage.success(t('case_studies_page.messages.deleted')); await loadData() }
    } catch { ElMessage.error(t('case_studies_page.messages.delete_failed')) }
}

const handleUploadLogo = async () => {
    if (!logoFile.value) return
    uploading.value = true
    try {
        const res = await uploadCaseLogo(logoFile.value)
        if (res.data.success) { ElMessage.success(t('case_studies_page.messages.uploaded')); showUploadLogo.value = false; await loadData() }
    } catch { ElMessage.error(t('case_studies_page.messages.upload_failed')) }
    finally { uploading.value = false }
}

onMounted(() => loadData())
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.logo-item { border: 1px solid #ebeef5; border-radius: 4px; padding: 12px; text-align: center; display: flex; align-items: center; justify-content: center; height: 80px; }
</style>
