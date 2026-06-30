<template>
    <div class="saved-search-bar">
        <el-dropdown
            trigger="click"
            @command="handleSelect"
            :max-height="320"
        >
            <el-button size="small" :icon="Search" class="saved-search-btn">
                {{ selectedName || '保存的搜索' }}
                <el-icon class="el-icon--right"><ArrowDown /></el-icon>
            </el-button>
            <template #dropdown>
                <el-dropdown-menu>
                    <el-dropdown-item
                        v-if="searches.length === 0"
                        disabled
                    >
                        <span class="text-muted">暂无保存的搜索</span>
                    </el-dropdown-item>
                    <el-dropdown-item
                        v-for="s in searches"
                        :key="s.id"
                        :command="s"
                        :divided="s.is_shared"
                    >
                        <div class="search-item">
                            <div class="search-item-left">
                                <el-icon v-if="s.is_shared" :size="14"><Share /></el-icon>
                                <span>{{ s.name }}</span>
                            </div>
                            <div class="search-item-actions" @click.stop>
                                <el-tooltip content="更新为当前筛选" placement="top">
                                    <el-button text size="small" @click="handleUpdate(s)">
                                        <el-icon><Refresh /></el-icon>
                                    </el-button>
                                </el-tooltip>
                                <el-tooltip content="删除" placement="top">
                                    <el-button text size="small" @click="handleDelete(s)">
                                        <el-icon><Delete /></el-icon>
                                    </el-button>
                                </el-tooltip>
                            </div>
                        </div>
                    </el-dropdown-item>
                </el-dropdown-menu>
            </template>
        </el-dropdown>

        <el-button
            size="small"
            :icon="Plus"
            @click="dialogVisible = true"
            :disabled="!hasActiveFilters"
        >
            保存当前筛选
        </el-button>

        <el-dialog
            v-model="dialogVisible"
            title="保存搜索"
            width="400px"
            :close-on-click-modal="false"
        >
            <el-form @submit.prevent="handleSave">
                <el-form-item label="名称">
                    <el-input
                        v-model="saveName"
                        placeholder="例如: 30天到期的VIP客户"
                        maxlength="100"
                        ref="nameInput"
                    />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="saveShared">分享给团队成员</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { ElMessage } from 'element-plus';
import {
    Search, ArrowDown, Plus, Share, Refresh, Delete,
} from '@element-plus/icons-vue';
import savedSearchApi from '@/api/savedSearch';

const props = defineProps({
    page: { type: String, required: true },
    currentFilters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['apply']);

const searches = ref([]);
const selectedId = ref(null);
const dialogVisible = ref(false);
const saveName = ref('');
const saveShared = ref(false);
const saving = ref(false);
const nameInput = ref(null);

const selectedName = computed(() => {
    if (!selectedId.value) return null;
    const s = searches.value.find(x => x.id === selectedId.value);
    return s?.name || null;
});

const hasActiveFilters = computed(() => {
    return Object.keys(props.currentFilters).length > 0
        && Object.values(props.currentFilters).some(v => v !== null && v !== '' && v !== undefined);
});

function buildFilterPayload() {
    // Normalize: remove empty values for storage
    const cleaned = {};
    for (const [k, v] of Object.entries(props.currentFilters)) {
        if (v !== null && v !== '' && v !== undefined) {
            cleaned[k] = v;
        }
    }
    return cleaned;
}

async function fetchSearches() {
    try {
        const { data: res } = await savedSearchApi.list({ page: props.page });
        if (res.success) {
            searches.value = res.data || [];
        }
    } catch {
        // ignore
    }
}

function handleSelect(saved) {
    selectedId.value = saved.id;
    emit('apply', saved.filters);
}

async function handleSave() {
    if (!saveName.value?.trim()) return;
    saving.value = true;
    try {
        const { data: res } = await savedSearchApi.create({
            name: saveName.value.trim(),
            page: props.page,
            filters: buildFilterPayload(),
            is_shared: saveShared.value,
        });
        if (res.success) {
            ElMessage.success('搜索已保存');
            dialogVisible.value = false;
            saveName.value = '';
            saveShared.value = false;
            await fetchSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleUpdate(saved) {
    try {
        const { data: res } = await savedSearchApi.update(saved.id, {
            filters: buildFilterPayload(),
        });
        if (res.success) {
            ElMessage.success('搜索已更新');
            await fetchSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '更新失败');
    }
}

async function handleDelete(saved) {
    try {
        const { data: res } = await savedSearchApi.destroy(saved.id);
        if (res.success) {
            ElMessage.success('搜索已删除');
            if (selectedId.value === saved.id) {
                selectedId.value = null;
            }
            await fetchSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '删除失败');
    }
}

watch(dialogVisible, (visible) => {
    if (visible) {
        nextTick(() => nameInput.value?.focus());
    }
});

fetchSearches();
</script>

<style scoped>
.saved-search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
}
.saved-search-btn {
    max-width: 200px;
}
.saved-search-btn .el-icon--right {
    margin-left: 4px;
}
.search-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-width: 200px;
}
.search-item-left {
    display: flex;
    align-items: center;
    gap: 6px;
}
.search-item-actions {
    display: flex;
    gap: 2px;
    opacity: 0.3;
    transition: opacity 0.2s;
}
.search-item:hover .search-item-actions {
    opacity: 1;
}
.text-muted {
    color: var(--el-text-color-placeholder);
    font-size: 12px;
}
</style>
