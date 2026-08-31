<template>
    <el-card shadow="never">
        <template #header>
            <div class="card-header">
                <span>
                    <el-icon><Clock /></el-icon>
                    {{ t('time_restriction_tab.title') }}
                </span>
                <div>
                    <el-button
                        v-if="!isEnabled"
                        type="primary"
                        size="small"
                        @click="enableEditing"
                    >
                        {{ t('time_restriction_tab.enable_config') }}
                    </el-button>
                    <el-button
                        v-else-if="!isEditing"
                        size="small"
                        @click="isEditing = true"
                    >
                        {{ t('time_restriction_tab.edit_config') }}
                    </el-button>
                </div>
            </div>
        </template>

        <!-- 未启用状态 -->
        <div v-if="!isEnabled && !isEditing" class="empty-text">
            <el-icon><Clock /></el-icon>
            <p>{{ t('time_restriction_tab.empty_desc') }}</p>
            <el-button type="primary" plain @click="enableEditing" class="mt-2">{{ t('time_restriction_tab.configure_btn') }}</el-button>
        </div>

        <!-- 摘要视图 -->
        <div v-else-if="!isEditing && config" class="summary-view">
            <el-row :gutter="16">
                <el-col :span="8">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('time_restriction_page.cols.status') }}</div>
                        <div class="summary-value">
                            <el-tag :type="config.is_active ? 'success' : 'info'" size="small">
                                {{ config.is_active ? t('time_restriction_page.filter_enabled') : t('time_restriction_page.filter_disabled') }}
                            </el-tag>
                        </div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('time_restriction_page.cols.timezone') }}</div>
                        <div class="summary-value">{{ config.timezone || 'UTC' }}</div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="summary-card">
                        <div class="summary-label">{{ t('time_restriction_page.cols.out_of_hours_action') }}</div>
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
            <h4 class="section-title">{{ t('time_restriction_tab.weekly_schedule_title') }}</h4>
            <div v-if="weekScheduleDisplay.length === 0" class="text-secondary">{{ t('time_restriction_page.not_configured') }}</div>
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
                <h4 class="section-title">
                    {{ t('time_restriction_page.cols.holidays') }}（{{ t('time_restriction_page.holiday_days', { n: config.holidays.length }) }}）
                </h4>
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
                    <el-form-item :label="t('time_restriction_tab.enable_restriction')" prop="is_active">
                        <el-switch v-model="form.is_active" />
                    </el-form-item>
                </el-col>
                <el-col :span="8">
                    <el-form-item :label="t('time_restriction_page.cols.timezone')" prop="timezone">
                        <el-select
                            v-model="form.timezone"
                            filterable
                            style="width: 100%"
                            :placeholder="t('time_restriction_tab.timezone_ph')"
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
                    <el-form-item :label="t('time_restriction_tab.out_of_hours_action_label')" prop="out_of_hours_action">
                        <el-select v-model="form.out_of_hours_action" style="width: 100%">
                            <el-option
                                v-for="act in outOfHoursActionOptions"
                                :key="act.value"
                                :label="act.label"
                                :value="act.value"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="t('time_restriction_tab.grace_minutes')" prop="grace_minutes" v-if="form.out_of_hours_action === 'grace'">
                <el-input-number v-model="form.grace_minutes" :min="0" :max="1440" />
                <span class="form-hint">{{ t('time_restriction_tab.grace_minutes_hint') }}</span>
            </el-form-item>

            <el-divider />
            <h4 class="section-title">{{ t('time_restriction_tab.weekly_schedule_title') }}</h4>
            <div class="text-secondary mb-2">{{ t('time_restriction_tab.weekly_schedule_hint') }}</div>

            <div v-for="(item, index) in form.weekly_schedule" :key="index" class="schedule-row">
                <el-select v-model="item.day" style="width: 100px">
                    <el-option v-for="d in dayOptions" :key="d.value" :label="d.label" :value="d.value" />
                </el-select>
                <el-time-picker
                    v-model="item.startObj"
                    format="HH:mm"
                    :placeholder="t('time_restriction_tab.start_time_ph')"
                    style="width: 140px"
                />
                <span class="mx-2">{{ t('time_restriction_tab.to_separator') }}</span>
                <el-time-picker
                    v-model="item.endObj"
                    format="HH:mm"
                    :placeholder="t('time_restriction_tab.end_time_ph')"
                    style="width: 140px"
                />
                <el-button type="danger" :icon="Delete" circle size="small" @click="removeSchedule(index)" />
            </div>
            <el-button class="mt-2" size="small" @click="addSchedule">
                <el-icon><Plus /></el-icon> {{ t('time_restriction_tab.add_schedule') }}
            </el-button>

            <el-divider />
            <h4 class="section-title">{{ t('time_restriction_tab.special_schedule_title') }}</h4>
            <div class="text-secondary mb-2">{{ t('time_restriction_tab.special_schedule_hint') }}</div>

            <div v-for="(item, index) in form.special_schedule" :key="index" class="schedule-row">
                <el-date-picker
                    v-model="item.dateObj"
                    type="date"
                    :placeholder="t('time_restriction_tab.select_date_ph')"
                    style="width: 150px"
                    value-format="YYYY-MM-DD"
                />
                <el-time-picker
                    v-model="item.startObj"
                    format="HH:mm"
                    :placeholder="t('time_restriction_tab.start_short')"
                    style="width: 130px"
                />
                <span class="mx-2">{{ t('time_restriction_tab.to_separator') }}</span>
                <el-time-picker
                    v-model="item.endObj"
                    format="HH:mm"
                    :placeholder="t('time_restriction_tab.end_short')"
                    style="width: 130px"
                />
                <el-button type="danger" :icon="Delete" circle size="small" @click="removeSpecial(index)" />
            </div>
            <el-button class="mt-2" size="small" @click="addSpecial">
                <el-icon><Plus /></el-icon> {{ t('time_restriction_tab.add_special') }}
            </el-button>

            <el-divider />
            <h4 class="section-title">{{ t('time_restriction_page.cols.holidays') }}</h4>
            <div class="text-secondary mb-2">{{ t('time_restriction_tab.holidays_hint') }}</div>
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
                :placeholder="t('time_restriction_tab.select_holiday_ph')"
                value-format="YYYY-MM-DD"
                @change="addHoliday"
                style="width: 160px"
            />

            <el-divider />
            <h4 class="section-title">{{ t('time_restriction_tab.ip_whitelist_title') }}</h4>
            <el-form-item :label="t('time_restriction_tab.allowed_ip_ranges')" prop="allowed_ip_ranges">
                <el-input
                    v-model="form.allowed_ip_ranges"
                    :placeholder="t('time_restriction_tab.allowed_ip_ranges_ph')"
                />
                <span class="form-hint">{{ t('time_restriction_tab.ip_hint') }}</span>
            </el-form-item>

            <el-form-item :label="t('time_restriction_page.cols.description')" prop="description">
                <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>

            <el-divider />
            <div class="form-actions">
                <el-button type="primary" :loading="saving" @click="handleSave">{{ t('time_restriction_tab.save_config') }}</el-button>
                <el-button @click="handleCancel">{{ t('actions.cancel') }}</el-button>
                <el-button
                    v-if="config && config.id"
                    type="danger"
                    plain
                    :loading="deleting"
                    @click="handleDelete"
                    class="ml-auto"
                >
                    {{ t('time_restriction_tab.delete_config') }}
                </el-button>
            </div>
        </el-form>
    </el-card>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Clock, Plus, Delete } from '@element-plus/icons-vue';
import timeRestrictionApi from '@/api/timeRestriction';

const { t } = useI18n();

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

const outOfHoursActionLabels = computed(() => ({
    deny: t('time_restriction_page.out_of_hours.deny'),
    grace: t('time_restriction_page.out_of_hours.grace'),
    warn: t('time_restriction_page.out_of_hours.warn'),
}));

const dayNames = computed(() => [
    t('time_restriction_tab.weekdays.0'),
    t('time_restriction_tab.weekdays.1'),
    t('time_restriction_tab.weekdays.2'),
    t('time_restriction_tab.weekdays.3'),
    t('time_restriction_tab.weekdays.4'),
    t('time_restriction_tab.weekdays.5'),
    t('time_restriction_tab.weekdays.6'),
]);

const dayOptions = computed(() =>
    (metadata.day_options || []).map((d) => ({
        value: d.value,
        label: dayNames.value[d.value] ?? d.label,
    })),
);

const outOfHoursActionOptions = computed(() =>
    (metadata.out_of_hours_actions || []).map((act) => ({
        value: act.value,
        label: outOfHoursActionLabels.value[act.value] ?? act.label,
    })),
);

const weekScheduleDisplay = computed(() => {
    if (!config.value?.weekly_schedule) return [];
    return config.value.weekly_schedule.map(item => ({
        day: item.day,
        dayName: dayNames.value[item.day] || t('time_restriction_tab.day_fallback', { n: item.day }),
        start: item.start,
        end: item.end,
    }));
});

function actionTagType(action) {
    return { deny: 'danger', grace: 'warning', warn: 'info' }[action] || 'info';
}

function actionLabel(action) {
    return outOfHoursActionLabels.value[action] || action;
}

async function fetchMetadata() {
    try {
        const res = await timeRestrictionApi.getMetadata();
        if (res.data?.success) {
            Object.assign(metadata, res.data.data);
        }
    } catch (e) {
        console.error(t('time_restriction_tab.messages.metadata_fetch_failed'), e);
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
            ElMessage.success(t('time_restriction_tab.messages.saved'));
            isEditing.value = false;
            await fetchConfig();
            emit('saved');
        } else {
            ElMessage.error(res.data?.message || t('time_restriction_tab.messages.save_failed'));
        }
    } catch (e) {
        ElMessage.error(t('time_restriction_tab.messages.save_failed') + ': ' + (e.response?.data?.message || e.message));
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
        await ElMessageBox.confirm(
            t('time_restriction_tab.delete_confirm_message'),
            t('time_restriction_tab.delete_confirm_title'),
            { type: 'warning' },
        );
    } catch {
        return;
    }

    deleting.value = true;
    try {
        const res = await timeRestrictionApi.deleteConfig(props.licenseId);
        if (res.data?.success) {
            ElMessage.success(t('time_restriction_tab.messages.deleted'));
            config.value = null;
            isEnabled.value = false;
            isEditing.value = false;
            emit('saved');
        }
    } catch (e) {
        ElMessage.error(t('time_restriction_page.messages.delete_failed'));
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
