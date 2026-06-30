<template>
    <div class="case-studies-page">
        <div class="page-header">
            <h2>客户案例 + Logo 墙</h2>
            <p class="text-muted">管理客户案例、Logo 墙展示 — 官网动态展示 "Trusted by 500+"</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="(count, key) in stats" :key="key">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ count }}</div>
                        <div class="stat-label">{{ { total_cases: '案例总数', total_logos: 'Logo 数', by_category: '分类数', by_industry: '行业数' }[key] || key }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>案例列表</span>
                    <el-button type="primary" size="small" @click="showCreate = true">新建案例</el-button>
                </div>
            </template>
            <el-table :data="cases" v-loading="loading" stripe>
                <el-table-column label="Logo" width="60">
                    <template #default="{ row }">
                        <el-avatar v-if="row.logo" :src="row.logo" shape="square" size="small" />
                        <el-avatar v-else shape="square" size="small" icon="Picture" />
                    </template>
                </el-table-column>
                <el-table-column prop="title" label="标题" min-width="180" />
                <el-table-column prop="company" label="公司" width="150" />
                <el-table-column prop="category" label="分类" width="100">
                    <template #default="{ row }">{{ categories[row.category] || row.category }}</template>
                </el-table-column>
                <el-table-column prop="industry" label="行业" width="100" />
                <el-table-column label="推荐" width="60">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_featured" color="#e6a23c"><StarFilled /></el-icon>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_published ? 'success' : 'info'" size="small">{{ row.is_published ? '已发布' : '草稿' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="handleEdit(row)">编辑</el-button>
                        <el-popconfirm title="确定删除?" @confirm="handleDelete(row.id)">
                            <template #reference>
                                <el-button size="small" type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Logo 墙预览 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>Logo 墙预览</span>
                    <el-button size="small" @click="showUploadLogo = true">上传 Logo</el-button>
                </div>
            </template>
            <el-row :gutter="16">
                <el-col :span="4" v-for="logo in logos" :key="logo.id" class="mb-2">
                    <div class="logo-item">
                        <el-image :src="logo.url" fit="contain" style="height:60px" />
                    </div>
                </el-col>
            </el-row>
            <el-empty v-if="logos.length === 0" description="暂无 Logo" />
        </el-card>

        <!-- 新建/编辑对话框 -->
        <el-dialog v-model="showCreate" :title="editingId ? '编辑案例' : '新建案例'" width="700px">
            <el-form :model="form" label-width="100px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="公司" required><el-input v-model="form.company" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="分类" required>
                            <el-select v-model="form.category" style="width:100%">
                                <el-option v-for="(label, key) in categories" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="行业">
                            <el-select v-model="form.industry" style="width:100%">
                                <el-option v-for="tag in industryTags" :key="tag" :label="tag" :value="tag" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="摘要" required><el-input v-model="form.summary" type="textarea" :rows="2" /></el-form-item>
                <el-form-item label="正文" required><el-input v-model="form.content" type="textarea" :rows="6" /></el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="客户语录"><el-input v-model="form.quote" type="textarea" :rows="2" /></el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="语录作者"><el-input v-model="form.quote_author" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="推荐">
                            <el-switch v-model="form.is_featured" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="发布">
                            <el-switch v-model="form.is_published" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="Logo">
                            <el-upload :auto-upload="false" :show-file-list="false" @change="(f) => form.logo = f.raw">
                                <el-button size="small">选择文件</el-button>
                            </el-upload>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>

        <!-- 上传 Logo 对话框 -->
        <el-dialog v-model="showUploadLogo" title="上传 Logo" width="400px">
            <el-upload drag :auto-upload="false" :show-file-list="true" @change="(f) => logoFile = f.raw">
                <el-icon :size="40"><UploadFilled /></el-icon>
                <p>拖拽或点击上传 Logo</p>
            </el-upload>
            <template #footer>
                <el-button @click="showUploadLogo = false">取消</el-button>
                <el-button type="primary" @click="handleUploadLogo" :loading="uploading">上传</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    getCaseStudies, createCaseStudy, updateCaseStudy, deleteCaseStudy,
    getCaseStudyCategories, getIndustryTags, getCaseStudyStats,
    getLogoWall, uploadCaseLogo,
} from '@/api/caseStudies';

const cases = ref([]);
const logos = ref([]);
const loading = ref(false);
const saving = ref(false);
const uploading = ref(false);
const showCreate = ref(false);
const showUploadLogo = ref(false);
const editingId = ref(null);
const categories = ref({});
const industryTags = ref([]);
const stats = ref({});
const logoFile = ref(null);

const form = ref({
    title: '', company: '', category: '', industry: '',
    summary: '', content: '', quote: '', quote_author: '',
    is_featured: false, is_published: false, logo: null,
});

const loadData = async () => {
    loading.value = true;
    try {
        const [casesRes, catRes, tagsRes, statsRes, logoRes] = await Promise.all([
            getCaseStudies(), getCaseStudyCategories(), getIndustryTags(),
            getCaseStudyStats(), getLogoWall(),
        ]);
        if (casesRes.data.success) cases.value = casesRes.data.data.data || [];
        if (catRes.data.success) categories.value = catRes.data.data;
        if (tagsRes.data.success) industryTags.value = tagsRes.data.data;
        if (statsRes.data.success) stats.value = statsRes.data.data;
        if (logoRes.data.success) logos.value = logoRes.data.data.logos || [];
    } catch { /* ignore */ }
    finally { loading.value = false; }
};

const resetForm = () => {
    form.value = { title: '', company: '', category: '', industry: '', summary: '', content: '', quote: '', quote_author: '', is_featured: false, is_published: false, logo: null };
    editingId.value = null;
};

const handleEdit = (row) => {
    editingId.value = row.id;
    form.value = { ...row };
    showCreate.value = true;
};

const handleSave = async () => {
    saving.value = true;
    try {
        const res = editingId.value
            ? await updateCaseStudy(editingId.value, form.value)
            : await createCaseStudy(form.value);
        if (res.data.success) {
            ElMessage.success(editingId.value ? '已更新' : '已创建');
            showCreate.value = false;
            resetForm();
            await loadData();
        }
    } catch { ElMessage.error('保存失败'); }
    finally { saving.value = false; }
};

const handleDelete = async (id) => {
    try {
        const res = await deleteCaseStudy(id);
        if (res.data.success) { ElMessage.success('已删除'); await loadData(); }
    } catch { ElMessage.error('删除失败'); }
};

const handleUploadLogo = async () => {
    if (!logoFile.value) return;
    uploading.value = true;
    try {
        const res = await uploadCaseLogo(logoFile.value);
        if (res.data.success) { ElMessage.success('上传成功'); showUploadLogo.value = false; await loadData(); }
    } catch { ElMessage.error('上传失败'); }
    finally { uploading.value = false; }
};

onMounted(() => loadData());
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.logo-item { border: 1px solid #ebeef5; border-radius: 4px; padding: 12px; text-align: center; display: flex; align-items: center; justify-content: center; height: 80px; }
</style>
