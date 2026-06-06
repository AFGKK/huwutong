<template>
    <div class="settings-page">
        <div class="page-header">
            <h2>系统设置</h2>
        </div>

        <div v-loading="loading" class="settings-content">
            <el-form
                ref="formRef"
                :model="formData"
                label-width="140px"
                label-position="right"
            >
                <el-card v-for="group in groups" :key="group.group" shadow="never" class="setting-group">
                    <template #header>
                        <span class="group-title">{{ group.label }}</span>
                    </template>

                    <el-form-item
                        v-for="setting in group.settings"
                        :key="setting.key"
                        :label="setting.description || setting.key"
                        :prop="setting.key"
                    >
                        <!-- 文本输入 -->
                        <el-input
                            v-if="setting.type === 'text'"
                            v-model="formData[setting.key]"
                            :placeholder="setting.description"
                            clearable
                            style="max-width: 500px;"
                        />
                        <!-- 多行文本 -->
                        <el-input
                            v-else-if="setting.type === 'textarea'"
                            v-model="formData[setting.key]"
                            type="textarea"
                            :rows="3"
                            :placeholder="setting.description"
                            style="max-width: 500px;"
                        />
                        <!-- 颜色选择 -->
                        <el-color-picker
                            v-else-if="setting.type === 'color'"
                            v-model="formData[setting.key]"
                            :predefine="predefineColors"
                        />
                        <!-- 开关 -->
                        <el-switch
                            v-else-if="setting.type === 'switch'"
                            v-model="formData[setting.key]"
                        />
                        <!-- 图片 URL -->
                        <div v-else-if="setting.type === 'image'" class="image-setting">
                            <el-input
                                v-model="formData[setting.key]"
                                placeholder="输入图片 URL"
                                style="max-width: 500px;"
                            />
                            <div v-if="formData[setting.key]" class="image-preview">
                                <el-image
                                    :src="formData[setting.key]"
                                    style="width: 60px; height: 60px; border-radius: 4px;"
                                    fit="contain"
                                />
                            </div>
                        </div>
                        <!-- 选择 -->
                        <el-select
                            v-else-if="setting.type === 'select'"
                            v-model="formData[setting.key]"
                            style="max-width: 300px;"
                        >
                            <el-option
                                v-for="opt in (setting.options || [])"
                                :key="opt"
                                :label="opt"
                                :value="opt"
                            />
                        </el-select>
                        <!-- 默认文本 -->
                        <el-input
                            v-else
                            v-model="formData[setting.key]"
                            style="max-width: 500px;"
                        />

                        <div v-if="setting.is_public" class="setting-badge">
                            <el-tag size="small" type="info" effect="plain">公开</el-tag>
                        </div>
                    </el-form-item>
                </el-card>

                <div class="form-actions">
                    <el-button type="primary" :loading="submitting" @click="submitForm">
                        保存设置
                    </el-button>
                </div>
            </el-form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import settingApi from '@/api/setting';

const loading = ref(false);
const submitting = ref(false);
const groups = ref([]);
const formRef = ref(null);
const formData = reactive({});

const predefineColors = [
    '#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#909399',
    '#1d1e1f', '#303133', '#606266', '#C0C4CC', '#DCDFE6',
];

async function loadSettings() {
    loading.value = true;
    try {
        const { data: res } = await settingApi.grouped();
        if (res.success) {
            groups.value = res.data || [];
            // 初始化表单数据
            for (const group of groups.value) {
                for (const setting of group.settings) {
                    const val = setting.value;
                    if (setting.type === 'switch') {
                        formData[setting.key] = val === '1' || val === 'true' || val === true;
                    } else {
                        formData[setting.key] = val ?? '';
                    }
                }
            }
        }
    } catch {
        ElMessage.error('加载设置失败');
    } finally {
        loading.value = false;
    }
}

async function submitForm() {
    submitting.value = true;
    try {
        const settings = [];
        for (const group of groups.value) {
            for (const setting of group.settings) {
                let value = formData[setting.key];
                if (setting.type === 'switch') {
                    value = value ? '1' : '0';
                }
                settings.push({ key: setting.key, value: String(value ?? '') });
            }
        }
        await settingApi.update(settings);
        ElMessage.success('设置保存成功');
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    loadSettings();
});
</script>

<style scoped>
.settings-page { padding: 20px; }

.page-header {
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }

.settings-content {
    max-width: 800px;
}

.setting-group {
    margin-bottom: 20px;
}
.group-title {
    font-weight: 600;
    font-size: 15px;
}

.setting-badge {
    display: inline-block;
    margin-left: 8px;
}

.image-setting {
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-actions {
    padding: 20px 0;
}

:deep(.el-card__body) { padding: 16px 20px; }
</style>
