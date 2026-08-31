<template>
  <el-dialog v-model="visible" :title="t('event_detail_dialog.title')" width="700px" :close-on-click-modal="false">
    <div v-loading="loading" v-if="event">
      <el-descriptions :column="2" border size="small" class="mb-4">
        <el-descriptions-item :label="t('event_detail_dialog.event_title')" :span="2">{{ event.title }}</el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.rule')">{{ event.rule?.name }}</el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.type')">{{ event.event_type }}</el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.severity')">
          <el-tag :type="severityTag(event.severity)" size="small">{{ event.severity }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.status')">
          <el-tag :type="statusTag(event.status)" size="small">{{ event.status }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.fired_at')">{{ event.fired_at }}</el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.acked_at')">{{ event.acknowledged_at || '-' }}</el-descriptions-item>
        <el-descriptions-item :label="t('event_detail_dialog.resolved_at')">{{ event.resolved_at || '-' }}</el-descriptions-item>
      </el-descriptions>

      <el-card shadow="never" class="mb-4">
        <template #header>{{ t('event_detail_dialog.message') }}</template>
        <p class="text-sm">{{ event.message }}</p>
      </el-card>

      <el-card shadow="never" class="mb-4" v-if="event.context">
        <template #header>{{ t('event_detail_dialog.context') }}</template>
        <pre class="text-xs bg-gray-50 p-3 rounded overflow-auto max-h-48">{{ JSON.stringify(event.context, null, 2) }}</pre>
      </el-card>

      <el-card shadow="never" v-if="event.notification_logs?.length">
        <template #header>{{ t('event_detail_dialog.notifications') }}</template>
        <el-table :data="event.notification_logs" size="small">
          <el-table-column :label="t('event_detail_dialog.cols.channel')" prop="channel_type" width="100" />
          <el-table-column :label="t('event_detail_dialog.cols.status')" prop="status" width="80">
            <template #default="{ row }"><el-tag :type="row.status === 'sent' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('event_detail_dialog.cols.sent_at')" prop="sent_at" width="160" />
          <el-table-column :label="t('event_detail_dialog.cols.response')" prop="response" min-width="200" show-overflow-tooltip />
        </el-table>
      </el-card>
    </div>
    <el-empty v-else-if="!loading" :description="t('event_detail_dialog.empty')" />
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.close') }}</el-button>
      <el-button v-if="event?.status === 'firing'" type="warning" @click="$emit('acknowledge', event.id)">{{ t('event_detail_dialog.ack') }}</el-button>
      <el-button v-if="['firing','acknowledged'].includes(event?.status)" type="success" @click="$emit('resolve', event.id)">{{ t('event_detail_dialog.resolve') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getEvent } from '@/api/alerting'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  eventId: { type: Number, default: null },
})
const emit = defineEmits(['update:visible'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const event = ref(null)
const loading = ref(false)

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function statusTag(s) { return { firing: 'danger', acknowledged: 'warning', resolved: 'success' }[s] || 'info' }

async function load() {
  if (!props.eventId) return
  loading.value = true
  try {
    const { data } = await getEvent(props.eventId)
    event.value = data
  } catch { event.value = null } finally { loading.value = false }
}

watch(() => props.eventId, (v) => { if (v) load() })
watch(() => props.visible, (v) => { if (v && props.eventId) load() })
</script>
