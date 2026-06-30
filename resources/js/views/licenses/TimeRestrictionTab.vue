<template>
    <el-card shadow="never">
        <template #header>
            <div class="card-header">
                <span>
                    <el-icon><Clock /></el-icon>
                    ⏰ 使用时段限制
                </span>
                <div>
                    <el-button
                        v-if="!isEnabled"
                        type="primary"
                        size="small"
                        @click="enableEditing"
                    >
                        启用配置
                    </el-button>
                    <el-button
                        v-else-if="!isEditing"
                        size="small"
                        @click="isEditing = true"
                    >
                        编辑配置
                    </el-button>
                </div>
            </div>
        </template>

        <!-- 未启用状态 -->
        <div v-if="!isEnabled && !isEditing" class="empty-text">
            <el-icon><Clock /></el-icon>
            <p>未配置使用时段限制，License 全天可用。</p>
            <el-button type="primary" plain @click="enableEditing" class="mt-2">配置时段限制</el-button>
        </div>

        <!-- 摘要视图 -->
        <div v-else-if="!isEditing && config" class="summary-view">
            <el-row :gutter="16">
                <el-col :span="8">
                    <div class="summary-card">
                        <div class="summary-label">状态</div>
                        <div class="summary-value">
                            <el-tag :type="config.is_active ? 'success' : 'info'" size="small">
                                {{ config.is_active ? '已启用' : '已禁用' }}
                            </el-tag>
                        </div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="summary-card">
                        <div class="summary-label">时区</div>
                        <div class="summary-value">{{ config.timezone || 'UTC' }}</div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="summary-card">
                        <div class="summary-label">非可用时段</div>
                        <div class="summary-value">
                            <el-tag :type="actionTagType(config.out_of_hours_action)" size="small">
                                {{ actionLabel(config.out_of_hours_action) }}
                            </el-tag>
                        </div>
                    </div>
                </el-col>
            </el-row>

            <el-divider />

            <!-- 每周排期 -->
            <h4 class="section-title">每周可用时段</h4>
            <div v-if="weekScheduleDisplay.length === 0" class="text-secondary">未配置</div>
            <el-timeline v-else>
                <el-timeline-item
                    v-for="item in weekScheduleDisplay"
                    :key="item.day"
                    :timestamp="item.dayName"
                    placement="top"
                >
                    <div>{{ item.start }} - {{ item.end }}</div>
                </el-timeline-item>
            </el-timeline>

            <!-- 节假日 -->
            <template v-if="config.holidays && config.holidays.length > 0">
                <el-divider />
                <h4 class="section-title">节假日（{{ config.holidays.length }} 天）</h4>
                <el-space wrap>
                    <el-tag v-for="d in config.holidays" :key="d" size="small" type="warning">
                        {{ d }}
                    </el-tag>
                </el-space>
            </template>
        </div>

        <!-- 编辑表单 -->
        <el-form
            v-else-if="isEditing"
            :model="form"
            label-position="top"
            ref="formRef"
        >
            <el-row :gutter="16">
                <el-col :span="8">
                    <el-form-item label="启用限制" prop="is_active">
                        <el-switch v-model="form.is_active" />
                    </el-form-item>
                </el-col>
                <el-col :span="8">
                    <el-form-item label="时区" prop="timezone">
                        <el-select
                            v-model="form.timezone"
                            filterable
                            style="width: 100%"
                            placeholder="选择时区"
                        >
                            <el-option
                                v-for="tz in metadata.timezones || []"
                                :key="tz"
                                :label="tz"
                                :value="tz"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="8">
                    <el-form-item label="非可用时段行为" prop="out_of_hours_action">
                        <el-select v-model="form.out_of_hours_action" style="width: 100%">
                            <el-option
                                v-for="act in metadata.out_of_hours_actions || []"
                                :key="act.value"
                                :label="act.label"
                                :value="act.value"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item label="宽限分钟数" prop="grace_minutes" v-if="form.out_of_hours_action === 'grace'">
                <el-input-number v-model="form.grace_minutes" :min="0" :max="1440" />
                <span class="form-hint">超出可用时段后允许继续使用的分钟数</span>
            </el-form-item>

            <el-divider />
            <h4 class="section-title">每周可用时段</h4>
            <div class="text-secondary mb-2">设置每周各天的可用时间范围。某天不设置则表示当天不可用。</div>

            <div v-for="(item, index) in form.weekly_schedule" :key="index" class="schedule-row">
                <el-select v-model="item.day" style="width: 100px">
                    <el-option v-for="d in metadata.day_options || []" :key="d.value" :label="d.label" :value="d.value" />
                </el-select>
                <el-time-picker
                    v-model="item.startObj"
                    format="HH:mm"
                    placeholder="开始时间"
                    style="width: 140px"
                />
                <span class="mx-2">至</span>
                <el-time-picker
                    v-model="item.endObj"
                    format="HH:mm"
                    placeholder="结束时间"
                    style="width: 140px"
                />
                <el-button type="danger" :icon="Delete" circle size="small" @click="removeSchedule(index)" />
            </div>
            <el-button class="mt-2" size="small" @click="addSchedule">
                <el-icon><Plus /></el-icon> 添加时段
            </el-button>

            <el-divider />
            <h4 class="section-title">特定期日时段</h4>
            <div class="text-secondary mb-2">覆盖每周排期的特定期日时段配置。</div>

            <div v-for="(item, index) in form.special_schedule" :key="index" class="schedule-row">
                <el-date-picker
                    v-model="item.dateObj"
                    type="date"
                    placeholder="选择日期"
                    style="width: 150px"
                    value-format="YYYY-MM-DD"
                />
                <el-time-picker
                    v-model="item.startObj"
                    format="HH:mm"
                    placeholder="开始"
                    style="width: 130px"
                />
                <span class="mx-2">至</span>
                <el-time-picker
                    v-model="item.endObj"
                    format="HH:mm"
                    placeholder="结束"
                    style="width: 130px"
                />
                <el-button type="danger" :icon="Delete" circle size="small" @click="removeSpecial(index)" />
            </div>
            <el-button class="mt-2" size="small" @click="addSpecial">
                <el-icon><Plus /></el-icon> 添加特定期日
            </el-button>

            <el-divider />
            <h4 class="section-title">节假日</h4>
            <div class="text-secondary mb-2">节假日全天不可用（除非有特定期日配置覆盖）。</div>
            <div v-if="form.holidays && form.holidays.length > 0" class="holiday-list mb-2">
                <el-tag
                    v-for="(d, i) in form.holidays"
                    :key="i"
                    closable
                    @close="removeHoliday(i)"
                    class="mr-1"
                >
                    {{ d }}
                </el-tag>
            </div>
            <el-date-picker
                v-model="newHoliday"
                type="date"
                placeholder="选择节假日"
                value-format="YYYY-MM-DD"
                @change="addHoliday"
                style="width: 160px"
            />

            <el-divider />
            <h4 class="section-title">IP 白名单（可选）</h4>
            <el-form-item label="允许的 IP 范围" prop="allowed_ip_ranges">
                <el-input
                    v-model="form.allowed_ip_ranges"
                    placeholder="192.168.1.0/24, 10.0.0.1"
                />
                <span class="form-hint">逗号分隔的 IP 或 CIDR 范围，匹配白名单的 IP 将不受时段限制</span>
            </el-form-item>

            <el-form-item label="备注" prop="description">
                <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>

            <el-divider />
            <div class="form-actions">
                <el-button type="primary" :loading="saving" @click="handleSave">保存配置</el-button>
                <el-button @click="handleCancel">取消</el-button>
                <el-button
                    v-if="config && config.id"
                    type="danger"
                    plain
                    :loading="deleting"
                    @click="handleDelete"
                    class="ml-auto"
                >
                    删除配置
                </el-button>
            </div>
        </el-form>
    </el-card>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Clock, Plus, Delete } from '@element-plus/icons-vue';
import timeRestrictionApi from '@/api/timeRestriction';

const props = defineProps({
    licenseId: { type: Number, required: true },
});

const emit = defineEmits(['saved']);

const formRef = ref();
const isEditing = ref(false);
const isEnabled = ref(false);
const config = ref(null);
const saving = ref(false);
const deleting = ref(false);
const newHoliday = ref('');
const metadata = reactive({
    day_options: [],
    out_of_hours_actions: [],
    timezones: [],
});

const form = reactive({
    is_active: true,
    timezone: 'Asia/Shanghai',
    weekly_schedule: [],
    special_schedule: [],
    holidays: [],
    out_of_hours_action: 'deny',
    grace_minutes: 0,
    allowed_ip_ranges: '',
    description: '',
});

const dayNames = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];

const weekScheduleDisplay = computed(() => {
    if (!config.value?.weekly_schedule) return [];
    return config.value.weekly_schedule.map(item => ({
        day: item.day,
        dayName: dayNames[item.day] || `周${item.day}`,
        start: item.start,
        end: item.end,
    }));
});

function actionTagType(action) {
    return { deny: 'danger', grace: 'warning', warn: 'info' }[action] || 'info';
}

function actionLabel(action) {
    return { deny: '拒绝访问', grace: '宽限使用', warn: '仅警告' }[action] || action;
}

async function fetchMetadata() {
    try {
        const res = await timeRestrictionApi.getMetadata();
        if (res.data?.success) {
            Object.assign(metadata, res.data.data);
        }
    } catch (e) {
        console.error('获取元数据失败', e);
    }
}

async function fetchConfig() {
    try {
        const res = await timeRestrictionApi.getConfig(props.licenseId);
        if (res.data?.success && res.data.data?.id) {
            config.value = res.data.data;
            isEnabled.value = true;
        } else {
            isEnabled.value = false;
        }
    } catch (e) {
        isEnabled.value = false;
    }
}

function enableEditing() {
    isEditing.value = true;
    isEnabled.value = true;
    form.is_active = true;
}

function addSchedule() {
    form.weekly_schedule.push({
        day: 1,
        start: '09:00',
        end: '18:00',
        startObj: new Date(2000, 0, 1, 9, 0),
        endObj: new Date(2000, 0, 1, 18, 0),
    });
}

function removeSchedule(index) {
    form.weekly_schedule.splice(index, 1);
}

function addSpecial() {
    form.special_schedule.push({
        date: '',
        start: '09:00',
        end: '18:00',
        dateObj: null,
        startObj: new Date(2000, 0, 1, 9, 0),
        endObj: new Date(2000, 0, 1, 18, 0),
    });
}

function removeSpecial(index) {
    form.special_schedule.splice(index, 1);
}

function addHoliday() {
    if (newHoliday.value && !form.holidays.includes(newHoliday.value)) {
        form.holidays.push(newHoliday.value);
    }
    newHoliday.value = '';
}

function removeHoliday(index) {
    form.holidays.splice(index, 1);
}

function timeToStr(date) {
    if (!date) return '';
    const d = new Date(date);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function buildPayload() {
    const payload = {
        is_active: form.is_active,
        timezone: form.timezone,
        weekly_schedule: form.weekly_schedule.map(item => ({
            day: item.day,
            start: item.startObj ? timeToStr(item.startObj) : item.start,
            end: item.endObj ? timeToStr(item.endObj) : item.end,
        })),
        special_schedule: form.special_schedule.map(item => ({
            date: item.dateObj || item.date,
            start: item.startObj ? timeToStr(item.startObj) : item.start,
            end: item.endObj ? timeToStr(item.endObj) : item.end,
        })),
        holidays: form.holidays,
        out_of_hours_action: form.out_of_hours_action,
        grace_minutes: form.grace_minutes,
        allowed_ip_ranges: form.allowed_ip_ranges,
        description: form.description,
    };
    return payload;
}

function initFormFromConfig(cfg) {
    if (!cfg) return;
    form.is_active = cfg.is_active ?? true;
    form.timezone = cfg.timezone || 'Asia/Shanghai';
    form.out_of_hours_action = cfg.out_of_hours_action || 'deny';
    form.grace_minutes = cfg.grace_minutes || 0;
    form.allowed_ip_ranges = cfg.allowed_ip_ranges || '';
    form.description = cfg.description || '';
    form.holidays = cfg.holidays || [];

    form.weekly_schedule = (cfg.weekly_schedule || []).map(item => ({
        day: item.day,
        start: item.start,
        end: item.end,
        startObj: item.start ? new Date(`2000-01-01T${item.start}:00`) : null,
        endObj: item.end ? new Date(`2000-01-01T${item.end}:00`) : null,
    }));

    form.special_schedule = (cfg.special_schedule || []).map(item => ({
        date: item.date,
        start: item.start,
        end: item.end,
        dateObj: item.date || null,
        startObj: item.start ? new Date(`2000-01-01T${item.start}:00`) : null,
        endObj: item.end ? new Date(`2000-01-01T${item.end}:00`) : null,
    }));
}

async function handleSave() {
    saving.value = true;
    try {
        const payload = buildPayload();
        const res = await timeRestrictionApi.saveConfig(props.licenseId, payload);
        if (res.data?.success) {
            ElMessage.success('时段限制配置已保存');
            isEditing.value = false;
            await fetchConfig();
            emit('saved');
        } else {
            ElMessage.error(res.data?.message || '保存失败');
        }
    } catch (e) {
        ElMessage.error('保存失败: ' + (e.response?.data?.message || e.message));
    } finally {
        saving.value = false;
    }
}

function handleCancel() {
    if (config.value?.id) {
        initFormFromConfig(config.value);
        isEditing.value = false;
    } else {
        isEditing.value = false;
        isEnabled.value = false;
    }
}

async function handleDelete() {
    try {
        await ElMessageBox.confirm('确定删除时段限制配置？License 将恢复为全天可用。', '确认删除', {
            type: 'warning',
        });
    } catch {
        return;
    }

    deleting.value = true;
    try {
        const res = await timeRestrictionApi.deleteConfig(props.licenseId);
        if (res.data?.success) {
            ElMessage.success('时段限制配置已删除');
            config.value = null;
            isEnabled.value = false;
            isEditing.value = false;
            emit('saved');
        }
    } catch (e) {
        ElMessage.error('删除失败');
    } finally {
        deleting.value = false;
    }
}

onMounted(() => {
    fetchMetadata();
    fetchConfig();
});
</script>

<style scoped>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.empty-text {
    text-align: center;
    color: #909399;
    padding: 24px;
    font-size: 14px;
}
.empty-text .el-icon { font-size: 32px; margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.mb-2 { margin-bottom: 8px; }
.mx-2 { margin: 0 8px; }
.mr-1 { margin-right: 4px; }
.ml-auto { margin-left: auto; }
.form-hint {
    font-size: 12px;
    color: #909399;
    margin-left: 8px;
}
.section-title {
    margin: 0 0 8px;
    font-size: 15px;
    color: #303133;
}
.text-secondary { color: #909399; }
.summary-view .summary-card {
    background: #f5f7fa;
    border-radius: 6px;
    padding: 12px 16px;
    text-align: center;
}
.summary-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}
.summary-value {
    font-size: 15px;
    font-weight: 600;
}
.schedule-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.form-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.holiday-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
</style>
