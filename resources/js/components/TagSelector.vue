<template>
    <div class="tag-selector">
        <el-tag
            v-for="tag in modelTags"
            :key="tag.id"
            :color="tag.color"
            effect="dark"
            size="small"
            closable
            :disable-transitions="false"
            @close="handleRemove(tag)"
            class="tag-item"
        >
            {{ tag.name }}
        </el-tag>

        <el-popover
            placement="bottom"
            :width="300"
            trigger="click"
            @show="loadAllTags"
        >
            <template #reference>
                <el-button size="small" icon="Plus" circle class="tag-add-btn" />
            </template>

            <div class="tag-picker">
                <el-input
                    v-model="searchQuery"
                    :placeholder="t('tags.search_ph')"
                    size="small"
                    clearable
                    class="mb-2"
                    @input="onSearchInput"
                />
                <div class="tag-list" v-loading="loading">
                    <div
                        v-for="group in groupedTags"
                        :key="group.key"
                        class="tag-group"
                    >
                        <div class="tag-group-label" v-if="group.key !== '_ungrouped'">
                            {{ groupLabels[group.key] || group.key }}
                        </div>
                        <div class="tag-group-items">
                            <el-tag
                                v-for="tag in group.items"
                                :key="tag.id"
                                :color="tag.color"
                                :effect="isSelected(tag) ? 'dark' : 'plain'"
                                size="small"
                                class="tag-option"
                                :type="isSelected(tag) ? undefined : 'info'"
                                @click="handleSelect(tag)"
                            >
                                {{ tag.name }}
                            </el-tag>
                        </div>
                    </div>
                    <el-empty v-if="allTags.length === 0" :image-size="40" :description="t('tags.empty')" />
                </div>
                <div class="tag-picker-footer">
                    <el-input
                        v-model="newTagName"
                        :placeholder="t('tags.create_ph')"
                        size="small"
                        class="new-tag-input"
                        @keyup.enter="handleCreate"
                    >
                        <template #append>
                            <el-button @click="handleCreate" :loading="creating">
                                <el-icon><Plus /></el-icon>
                            </el-button>
                        </template>
                    </el-input>
                </div>
            </div>
        </el-popover>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from '@element-plus/icons-vue';
import tagApi from '@/api/tag';

const { t } = useI18n();

const props = defineProps({
    taggableType: { type: String, required: true },
    taggableId: { type: [Number, String], required: true },
    tags: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:tags', 'change']);

const modelTags = ref([...props.tags]);
const allTags = ref([]);
const searchQuery = ref('');
const newTagName = ref('');
const loading = ref(false);
const creating = ref(false);

const groupLabels = computed(() => ({
    priority: t('tags.g_priority'),
    status: t('tags.g_status'),
    type: t('tags.g_type'),
    tier: t('tags.g_tier'),
    alert: t('tags.g_alert'),
}));

const filteredTags = computed(() => {
    if (!searchQuery.value) return allTags.value;
    const q = searchQuery.value.toLowerCase();
    return allTags.value.filter(t => t.name.toLowerCase().includes(q) || t.slug.includes(q));
});

const groupedTags = computed(() => {
    const groups = {};
    filteredTags.value.forEach(tag => {
        const key = tag.group || '_ungrouped';
        if (!groups[key]) groups[key] = { key, items: [] };
        groups[key].items.push(tag);
    });
    return Object.values(groups);
});

function isSelected(tag) {
    return modelTags.value.some(t => t.id === tag.id);
}

async function loadAllTags() {
    loading.value = true;
    try {
        const { data: res } = await tagApi.list({ per_page: 200 });
        if (res.success) {
            allTags.value = res.data?.data || [];
        }
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

function onSearchInput() {
    // 实时搜索，已经通过 filteredTags computed 处理
}

async function handleSelect(tag) {
    if (isSelected(tag)) {
        await handleRemove(tag);
        return;
    }
    try {
        const { data: res } = await tagApi.attach(props.taggableType, props.taggableId, tag.name);
        if (res.success) {
            modelTags.value = res.data.tags;
            emit('update:tags', modelTags.value);
            emit('change', modelTags.value);
        }
    } catch {
        // ignore
    }
}

async function handleRemove(tag) {
    try {
        const { data: res } = await tagApi.detach(props.taggableType, props.taggableId, tag.name);
        if (res.success) {
            modelTags.value = res.data.tags;
            emit('update:tags', modelTags.value);
            emit('change', modelTags.value);
        }
    } catch {
        // ignore
    }
}

async function handleCreate() {
    const name = newTagName.value?.trim();
    if (!name) return;
    creating.value = true;
    try {
        const { data: res } = await tagApi.create({ name });
        if (res.success) {
            await handleSelect(res.data);
            newTagName.value = '';
            await loadAllTags();
        }
    } catch {
        // ignore
    } finally {
        creating.value = false;
    }
}

watch(() => props.tags, (val) => {
    modelTags.value = [...val];
}, { deep: true });
</script>

<style scoped>
.tag-selector {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    min-height: 28px;
}
.tag-item {
    cursor: default;
}
.tag-add-btn {
    flex-shrink: 0;
}
.tag-picker {
    max-height: 400px;
    display: flex;
    flex-direction: column;
}
.tag-list {
    flex: 1;
    overflow-y: auto;
    max-height: 280px;
    margin: 4px 0;
}
.tag-group {
    margin-bottom: 8px;
}
.tag-group-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    margin-bottom: 4px;
    padding: 0 2px;
}
.tag-group-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.tag-option {
    cursor: pointer;
    transition: transform 0.1s;
}
.tag-option:hover {
    transform: scale(1.05);
}
.tag-picker-footer {
    border-top: 1px solid var(--el-border-color-lighter);
    padding-top: 8px;
}
.new-tag-input {
    width: 100%;
}
.mb-2 {
    margin-bottom: 8px;
}
</style>
