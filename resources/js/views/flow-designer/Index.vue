<template>
  <div class="flow-designer">
    <!-- 顶部操作栏 -->
    <div class="toolbar">
      <h2 class="m-0">低代码工作流设计器</h2>
      <div class="toolbar-actions">
        <el-button @click="activeView = 'list'" :type="activeView === 'list' ? 'default' : 'text'">
          工作流列表
        </el-button>
        <template v-if="activeView === 'canvas'">
          <el-button @click="saveGraph" type="success" :loading="saving" icon="Plus">保存</el-button>
          <el-button @click="exportDesign" type="primary" :loading="exporting">导出到引擎</el-button>
          <el-button @click="previewMode = !previewMode" :type="previewMode ? 'warning' : 'default'">
            {{ previewMode ? '编辑模式' : '预览模式' }}
          </el-button>
        </template>
      </div>
    </div>

    <!-- 列表视图 -->
    <template v-if="activeView === 'list'">
      <!-- 统计 -->
      <el-row :gutter="20" class="mb-4">
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card"><div class="stat-value">{{ stats.total_designs }}</div><div class="stat-label">总设计</div></div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card"><div class="stat-value text-success">{{ stats.published }}</div><div class="stat-label">已发布</div></div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card"><div class="stat-value text-warning">{{ stats.drafts }}</div><div class="stat-label">草稿</div></div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card">
              <div class="stat-value">{{ Object.keys(stats.by_category || {}).length }}</div>
              <div class="stat-label">分类数</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 操作栏 -->
      <div class="mb-4 flex gap-2">
        <el-button type="primary" @click="showCreateDialog" icon="Plus">新建工作流</el-button>
        <el-input v-model="filters.search" placeholder="搜索工作流..." clearable style="width:240px" @clear="loadData" @keyup.enter="loadData" />
        <el-select v-model="filters.status" placeholder="状态" clearable style="width:140px" @change="loadData">
          <el-option label="草稿" value="draft" />
          <el-option label="已发布" value="published" />
          <el-option label="已归档" value="archived" />
        </el-select>
      </div>

      <!-- 设计列表 -->
      <el-table :data="designs" stripe v-loading="loading" empty-text="暂无工作流设计">
        <el-table-column label="名称" prop="name" min-width="200">
          <template #default="{ row }">
            <el-link type="primary" :underline="'never'" @click="openDesign(row)">{{ row.name }}</el-link>
          </template>
        </el-table-column>
        <el-table-column label="分类" prop="category" width="120">
          <template #default="{ row }"><el-tag size="small">{{ catLabel(row.category) }}</el-tag></template>
        </el-table-column>
        <el-table-column label="节点数" prop="nodes_count" width="80" align="center" />
        <el-table-column label="状态" prop="status" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" prop="updated_at" width="160" />
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDesign(row)">编辑</el-button>
            <el-button link type="primary" @click="exportDesign(row)">导出</el-button>
            <el-popconfirm title="确定删除?" @confirm="doDelete(row)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="mt-3 flex justify-center" v-if="pagination.total">
        <el-pagination v-model:current-page="pagination.current" :page-size="pagination.pageSize" :total="pagination.total" layout="prev, pager, next" @current-change="loadData" />
      </div>
    </template>

    <!-- 画布视图 -->
    <template v-if="activeView === 'canvas' && currentDesign">
      <el-alert v-if="previewMode" type="warning" :closable="false" show-icon class="mb-3">
        <template #title>预览模式 — 点击节点查看配置，不可编辑</template>
      </el-alert>

      <div class="canvas-container">
        <!-- 左侧：节点面板 -->
        <div class="node-palette" v-if="!previewMode">
          <div class="palette-title">节点类型</div>
          <div
            v-for="nt in nodeTypes"
            :key="nt.type"
            class="palette-item"
            draggable="true"
            @dragstart="onDragStart($event, nt)"
          >
            <div class="palette-icon" :style="{ background: nt.color + '20', color: nt.color }">
              <el-icon :size="18"><component :is="nt.icon" /></el-icon>
            </div>
            <div>
              <div class="palette-label">{{ nt.label }}</div>
              <div class="palette-desc">{{ nt.description }}</div>
            </div>
          </div>
        </div>

        <!-- 画布区域 -->
        <div
          class="graph-canvas"
          @drop="onDrop"
          @dragover.prevent
          @click.self="deselectAll"
          ref="canvasRef"
          :style="canvasStyle"
        >
          <!-- 网格背景提示 -->
          <div class="canvas-hint" v-if="!currentDesign.nodes?.length && !previewMode">
            从左侧拖拽节点到此处开始设计
          </div>

          <!-- 节点 -->
          <div
            v-for="node in currentDesign.nodes"
            :key="node.node_id"
            class="canvas-node"
            :class="{ selected: selectedNode === node.node_id, 'preview-mode': previewMode }"
            :style="getNodeStyle(node)"
            @click.stop="selectNode(node)"
          >
            <div class="node-header" :style="{ background: getNodeColor(node.type) + '18', borderLeft: '3px solid ' + getNodeColor(node.type) }">
              <el-icon :size="16" :color="getNodeColor(node.type)"><component :is="getNodeIcon(node.type)" /></el-icon>
              <span class="node-type-label">{{ typeLabel(node.type) }}</span>
            </div>
            <div class="node-body">
              <div class="node-label">{{ node.label }}</div>
              <div class="node-id">#{{ node.node_id }}</div>
            </div>
            <!-- 端口锚点 -->
            <div v-if="!previewMode" class="port port-source" @mousedown.stop="startConnection($event, node, 'source')" />
            <div class="port port-target" @mousedown.stop="startConnection($event, node, 'target')" />
          </div>

          <!-- 连线（用SVG） -->
          <svg class="edges-svg" :style="{ width: canvasSize.width + 'px', height: canvasSize.height + 'px' }">
            <path
              v-for="edge in currentDesign.edges"
              :key="edge.edge_id"
              :d="getEdgePath(edge)"
              class="edge-line"
              :class="{ selected: selectedEdge === edge.edge_id }"
              :stroke="edge.color || getEdgeColor(edge.condition_type)"
              stroke-width="2"
              fill="none"
              :stroke-dasharray="edge.line_style === 'dashed' ? '6,3' : edge.line_style === 'dotted' ? '2,2' : 'none'"
              @click.stop="selectEdge(edge)"
            />
            <text v-for="edge in currentDesign.edges" :key="'label-' + edge.edge_id">
              <textPath :href="'#' + edge.edge_id" startOffset="50%" text-anchor="middle">
                {{ edge.label || conditionLabel(edge.condition_type) }}
              </textPath>
            </text>
          </svg>

          <!-- 连线拖拽预览 -->
          <svg v-if="connectLine" class="edges-svg connection-preview">
            <line
              :x1="connectLine.x1" :y1="connectLine.y1"
              :x2="mousePos.x" :y2="mousePos.y"
              stroke="#409eff" stroke-width="2" stroke-dasharray="5,3"
            />
          </svg>
        </div>

        <!-- 右侧：属性面板 -->
        <div class="property-panel" v-if="selectedNode">
          <div class="panel-title">节点属性</div>
          <el-form size="small" label-position="top">
            <el-form-item label="节点ID">
              <el-input v-model="editingNode.node_id" :disabled="true" />
            </el-form-item>
            <el-form-item label="类型">
              <el-tag :color="getNodeColor(editingNode.type) + '30'" size="small">{{ typeLabel(editingNode.type) }}</el-tag>
            </el-form-item>
            <el-form-item label="标签">
              <el-input v-model="editingNode.label" :disabled="previewMode" @change="updateNodeProp('label', editingNode.label)" />
            </el-form-item>
            <el-form-item v-if="editingNode.type === 'action'" label="动作类型">
              <el-select v-model="editingNode.config.action_type" :disabled="previewMode" style="width:100%" @change="updateNodeConfig('action_type', editingNode.config.action_type)">
                <el-option label="Webhook" value="webhook" />
                <el-option label="发送邮件" value="send_email" />
                <el-option label="更新License" value="update_license" />
                <el-option label="更新订阅" value="update_subscription" />
                <el-option label="通知管理员" value="notify_admin" />
              </el-select>
            </el-form-item>
            <el-form-item v-if="editingNode.type === 'webhook'" label="URL">
              <el-input v-model="editingNode.config.url" :disabled="previewMode" placeholder="https://..." @change="updateNodeConfig('url', editingNode.config.url)" />
            </el-form-item>
            <el-form-item v-if="['webhook','action'].includes(editingNode.type) && editingNode.config.action_type === 'webhook'" label="请求方法">
              <el-select v-model="editingNode.config.method" :disabled="previewMode" style="width:100%" @change="updateNodeConfig('method', editingNode.config.method)">
                <el-option label="POST" value="POST" />
                <el-option label="GET" value="GET" />
                <el-option label="PUT" value="PUT" />
                <el-option label="DELETE" value="DELETE" />
              </el-select>
            </el-form-item>
            <el-form-item v-if="editingNode.type === 'approval'" label="审批类型">
              <el-select v-model="editingNode.config.approval_type" :disabled="previewMode" style="width:100%" @change="updateNodeConfig('approval_type', editingNode.config.approval_type)">
                <el-option label="单人审批" value="single" />
                <el-option label="多人审批（任一通过）" value="any" />
                <el-option label="多人审批（全部通过）" value="all" />
              </el-select>
            </el-form-item>
            <el-form-item v-if="editingNode.type === 'condition'" label="条件字段">
              <el-input v-model="editingNode.config.field" :disabled="previewMode" @change="updateNodeConfig('field', editingNode.config.field)" />
            </el-form-item>
            <el-form-item v-if="editingNode.type === 'condition'" label="条件值">
              <el-input v-model="editingNode.config.value" :disabled="previewMode" @change="updateNodeConfig('value', editingNode.config.value)" />
            </el-form-item>
            <el-form-item v-if="!previewMode && selectedNode">
              <el-button type="danger" size="small" @click="deleteSelectedNode" style="width:100%">删除节点</el-button>
            </el-form-item>
          </el-form>
        </div>

        <!-- 连线属性面板 -->
        <div class="property-panel" v-else-if="selectedEdge && !previewMode">
          <div class="panel-title">连线属性</div>
          <el-form size="small" label-position="top">
            <el-form-item label="条件类型">
              <el-select v-model="editingEdge.condition_type" style="width:100%" @change="updateEdge">
                <el-option label="成功" value="success" />
                <el-option label="失败" value="failure" />
                <el-option label="条件分支" value="conditional" />
              </el-select>
            </el-form-item>
            <el-form-item label="标签">
              <el-input v-model="editingEdge.label" @change="updateEdge" />
            </el-form-item>
            <el-form-item>
              <el-button type="danger" size="small" @click="deleteSelectedEdge" style="width:100%">删除连线</el-button>
            </el-form-item>
          </el-form>
        </div>
      </div>
    </template>

    <!-- 创建设计对话框 -->
    <el-dialog v-model="createDialog.visible" title="新建工作流" width="480">
      <el-form :model="createDialog" label-position="top">
        <el-form-item label="名称" required>
          <el-input v-model="createDialog.name" placeholder="如：License 过期自动处理" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="createDialog.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="createDialog.category" style="width:100%">
            <el-option v-for="(l,v) in categories" :key="v" :label="l" :value="v" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="createDialog.loading" @click="doCreate">创建并编辑</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  VideoPlay, QuestionFilled, SetUp, EditPen, Connection, CircleClose,
} from '@element-plus/icons-vue'
import {
  listFlowDesigns, createFlowDesign, deleteFlowDesign, getFlowDesign,
  saveFlowGraph, exportFlowDesign, getFlowDesignerStats, getNodePalette, getFlowCategories,
} from '../../api/flowDesigner'

const activeView = ref('list')
const loading = ref(false)
const saving = ref(false)
const exporting = ref(false)
const previewMode = ref(false)
const designs = ref([])
const currentDesign = ref(null)
const nodeTypes = ref([])
const categories = ref({})
const canvasRef = ref(null)

const selectedNode = ref(null)
const selectedEdge = ref(null)
const connectLine = ref(null)
const mousePos = reactive({ x: 0, y: 0 })
const editingNode = reactive({ node_id: '', type: '', label: '', config: {} })
const editingEdge = reactive({ edge_id: '', condition_type: '', label: '' })
const canvasSize = reactive({ width: 2000, height: 2000 })

const stats = ref({ total_designs: 0, published: 0, drafts: 0, by_category: {} })
const filters = reactive({ search: '', status: '' })
const pagination = reactive({ current: 1, pageSize: 20, total: 0 })
const createDialog = reactive({ visible: false, name: '', description: '', category: 'general', loading: false })

const canvasStyle = computed(() => ({
  width: canvasSize.width + 'px',
  minHeight: canvasSize.height + 'px',
}))

function getNodeColor(type) {
  const colors = { trigger: '#409eff', condition: '#e6a23c', action: '#67c23a', approval: '#9b59b6', webhook: '#00adef', end: '#909399' }
  return colors[type] || '#909399'
}
function getNodeIcon(type) {
  const icons = { trigger: VideoPlay, condition: QuestionFilled, action: SetUp, approval: EditPen, webhook: Connection, end: CircleClose }
  return icons[type] || CircleClose
}
function typeLabel(t) { const labels = { trigger: '触发器', condition: '条件', action: '动作', approval: '审批', webhook: 'Webhook', end: '结束' }; return labels[t] || t }
function statusTag(s) { return { draft: 'info', published: 'success', archived: 'danger' }[s] || 'info' }
function statusLabel(s) { return { draft: '草稿', published: '已发布', archived: '已归档' }[s] || s }
function catLabel(c) { return categories.value[c] || c }
function conditionLabel(t) { return { success: '成功', failure: '失败', conditional: '条件' }[t] || '' }
function getEdgeColor(t) { return { success: '#67c23a', failure: '#f56c6c', conditional: '#e6a23c' }[t] || '#909399' }

function getNodeStyle(node) {
  const pos = node.position || { x: 100, y: 100 }
  return { left: pos.x + 'px', top: pos.y + 'px' }
}

function getEdgePath(edge) {
  const nodes = currentDesign.value?.nodes || []
  const src = nodes.find(n => n.node_id === edge.source_node)
  const tgt = nodes.find(n => n.node_id === edge.target_node)
  if (!src || !tgt) return ''
  const sp = src.position || { x: 100, y: 100 }
  const tp = tgt.position || { x: 300, y: 100 }
  const x1 = sp.x + 120, y1 = sp.y + 40
  const x2 = tp.x, y2 = tp.y + 40
  const cx = (x1 + x2) / 2
  return `M${x1},${y1} C${cx},${y1} ${cx},${y2} ${x2},${y2}`
}

// Load data
async function loadData() {
  loading.value = true
  try {
    const params = { page: pagination.current, per_page: pagination.pageSize, ...filters }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const { data: designsRes } = await listFlowDesigns(params)
    designs.value = Array.isArray(designsRes.data) ? designsRes.data : (designsRes.data?.data || [])
    if (designsRes.data?.meta) {
      pagination.current = designsRes.data.meta.current_page
      pagination.total = designsRes.data.meta.total
    }
  } catch (e) { ElMessage.error('加载失败') }
  finally { loading.value = false }
}

async function loadMeta() {
  try {
    const [statsRes, paletteRes, catRes] = await Promise.all([
      getFlowDesignerStats(), getNodePalette(), getFlowCategories(),
    ])
    stats.value = statsRes.data || {}
    nodeTypes.value = paletteRes.data || []
    categories.value = catRes.data || {}
  } catch (e) {}
}

// Create design
function showCreateDialog() { createDialog.visible = true }
async function doCreate() {
  if (!createDialog.name) { ElMessage.warning('请输入名称'); return }
  createDialog.loading = true
  try {
    const { data } = await createFlowDesign({
      name: createDialog.name,
      description: createDialog.description,
      category: createDialog.category,
    })
    ElMessage.success('创建成功')
    createDialog.visible = false
    openDesign(data)
  } catch (e) { ElMessage.error('创建失败') }
  finally { createDialog.loading = false }
}

async function doDelete(row) {
  try {
    await deleteFlowDesign(row.id)
    ElMessage.success('已删除')
    loadData()
  } catch (e) { ElMessage.error('删除失败') }
}

// Open designer
async function openDesign(design) {
  const id = design.id || design
  const { data } = await getFlowDesign(id)
  currentDesign.value = data
  if (!currentDesign.value.nodes) currentDesign.value.nodes = []
  if (!currentDesign.value.edges) currentDesign.value.edges = []
  selectedNode.value = null
  selectedEdge.value = null
  activeView.value = 'canvas'
  await nextTick()
}

// Save graph
async function saveGraph() {
  saving.value = true
  try {
    const graph = {
      nodes: currentDesign.value.nodes.map(n => ({
        node_id: n.node_id, type: n.type, label: n.label, icon: n.icon, config: n.config, position: n.position, sort_order: n.sort_order,
      })),
      edges: currentDesign.value.edges.map(e => ({
        edge_id: e.edge_id, source_node: e.source_node, target_node: e.target_node, condition_type: e.condition_type, label: e.label, color: e.color, line_style: e.line_style,
      })),
    }
    await saveFlowGraph(currentDesign.value.id, graph)
    ElMessage.success('已保存')
  } catch (e) { ElMessage.error('保存失败') }
  finally { saving.value = false }
}

// Export
async function exportDesign(design) {
  const id = design?.id || currentDesign.value?.id
  if (!id) return
  exporting.value = true
  try {
    await exportFlowDesign(id)
    ElMessage.success('已导出到工作流引擎')
  } catch (e) { ElMessage.error('导出失败') }
  finally { exporting.value = false }
}

// Drag & drop
function onDragStart(e, nt) {
  e.dataTransfer.setData('nodeType', nt.type)
  e.dataTransfer.setData('nodeLabel', nt.label)
}
function onDrop(e) {
  const type = e.dataTransfer.getData('nodeType')
  const label = e.dataTransfer.getData('nodeLabel')
  if (!type) return
  const rect = canvasRef.value?.getBoundingClientRect()
  const x = e.clientX - (rect?.left || 0) + (canvasRef.value?.parentElement?.scrollLeft || 0)
  const y = e.clientY - (rect?.top || 0) + (canvasRef.value?.parentElement?.scrollTop || 0)
  const nodeId = `node_${Date.now()}`
  const node = {
    node_id: nodeId,
    type: type,
    label: label || type,
    config: getDefaultConfig(type),
    position: { x: Math.round(x - 60), y: Math.round(y - 20) },
    sort_order: currentDesign.value.nodes.length,
  }
  currentDesign.value.nodes.push(node)
}

function getDefaultConfig(type) {
  switch (type) {
    case 'trigger': return { event: 'manual' }
    case 'condition': return { field: 'status', operator: 'eq', value: 'active' }
    case 'action': return { action_type: 'webhook', url: '' }
    case 'approval': return { approval_type: 'single', assignee: 'admin' }
    case 'webhook': return { url: '', method: 'POST', headers: {} }
    default: return {}
  }
}

// Select / deselect
function selectNode(node) {
  if (previewMode.value) return
  selectedNode.value = node.node_id
  selectedEdge.value = null
  Object.assign(editingNode, {
    node_id: node.node_id, type: node.type, label: node.label,
    config: { ...(node.config || {}) },
  })
}
function selectEdge(edge) {
  if (previewMode.value) return
  selectedEdge.value = edge.edge_id
  selectedNode.value = null
  Object.assign(editingEdge, {
    edge_id: edge.edge_id, condition_type: edge.condition_type, label: edge.label || '',
  })
}
function deselectAll() {
  selectedNode.value = null
  selectedEdge.value = null
}

function deleteSelectedNode() {
  if (!selectedNode.value) return
  const id = selectedNode.value
  currentDesign.value.nodes = currentDesign.value.nodes.filter(n => n.node_id !== id)
  currentDesign.value.edges = currentDesign.value.edges.filter(e => e.source_node !== id && e.target_node !== id)
  selectedNode.value = null
}
function deleteSelectedEdge() {
  if (!selectedEdge.value) return
  currentDesign.value.edges = currentDesign.value.edges.filter(e => e.edge_id !== selectedEdge.value)
  selectedEdge.value = null
}

function updateNodeProp(key, val) {
  const node = currentDesign.value.nodes.find(n => n.node_id === selectedNode.value)
  if (node) node[key] = val
}
function updateNodeConfig(key, val) {
  const node = currentDesign.value.nodes.find(n => n.node_id === selectedNode.value)
  if (node) {
    if (!node.config) node.config = {}
    node.config[key] = val
  }
}

function updateEdge() {
  const edge = currentDesign.value.edges.find(e => e.edge_id === editingEdge.edge_id)
  if (edge) {
    edge.condition_type = editingEdge.condition_type
    edge.label = editingEdge.label
  }
}

// Connection dragging
function startConnection(e, node, port) {
  if (previewMode.value) return
  const rect = canvasRef.value?.getBoundingClientRect()
  const pos = node.position || { x: 100, y: 100 }
  const x = pos.x + (rect?.left || 0) + (port === 'source' ? 120 : 0)
  const y = pos.y + 40 + (rect?.top || 0)
  connectLine.value = {
    sourceNode: node.node_id, sourcePort: port,
    x1: port === 'source' ? pos.x + 120 : pos.x,
    y1: pos.y + 40,
  }
  document.addEventListener('mousemove', onConnectMove)
  document.addEventListener('mouseup', onConnectEnd)
}

function onConnectMove(e) {
  mousePos.x = e.clientX - (canvasRef.value?.getBoundingClientRect().left || 0) + (canvasRef.value?.parentElement?.scrollLeft || 0)
  mousePos.y = e.clientY - (canvasRef.value?.getBoundingClientRect().top || 0) + (canvasRef.value?.parentElement?.scrollTop || 0)
}

function onConnectEnd(e) {
  document.removeEventListener('mousemove', onConnectMove)
  document.removeEventListener('mouseup', onConnectEnd)
  if (!connectLine.value) return
  // Find target node under cursor
  const target = findNodeAt(e.clientX, e.clientY)
  if (target && target.node_id !== connectLine.value.sourceNode) {
    const edgeId = `edge_${Date.now()}`
    if (currentDesign.value.edges) {
      currentDesign.value.edges.push({
        edge_id: edgeId,
        source_node: connectLine.value.sourceNode,
        target_node: target.node_id,
        condition_type: 'success',
        line_style: 'solid',
      })
    }
  }
  connectLine.value = null
}

function findNodeAt(clientX, clientY) {
  for (const node of currentDesign.value.nodes || []) {
    const pos = node.position || {}
    const nx = pos.x + (canvasRef.value?.parentElement?.scrollLeft || 0)
    const ny = pos.y + (canvasRef.value?.parentElement?.scrollTop || 0)
    const rect = canvasRef.value?.getBoundingClientRect()
    if (rect) {
      const absX = clientX - rect.left + (canvasRef.value?.parentElement?.scrollLeft || 0)
      const absY = clientY - rect.top + (canvasRef.value?.parentElement?.scrollTop || 0)
      if (absX >= pos.x && absX <= pos.x + 240 && absY >= pos.y && absY <= pos.y + 80) {
        return node
      }
    }
  }
  return null
}

onMounted(() => {
  loadData()
  loadMeta()
})
onUnmounted(() => {
  document.removeEventListener('mousemove', onConnectMove)
  document.removeEventListener('mouseup', onConnectEnd)
})
</script>

<style scoped>
.flow-designer { min-height: 500px; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.toolbar-actions { display: flex; gap: 8px; }
.m-0 { margin: 0; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.justify-center { justify-content: center; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }

/* 画布 */
.canvas-container { display: flex; gap: 12px; position: relative; }
.node-palette { width: 200px; flex-shrink: 0; background: #f5f7fa; border-radius: 8px; padding: 12px; }
.palette-title { font-size: 13px; font-weight: 600; color: #606266; margin-bottom: 10px; }
.palette-item { display: flex; gap: 8px; padding: 8px; margin-bottom: 4px; border-radius: 6px; cursor: grab; transition: all 0.2s; }
.palette-item:hover { background: #ecf5ff; }
.palette-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; flex-shrink: 0; }
.palette-label { font-size: 13px; font-weight: 500; color: #303133; }
.palette-desc { font-size: 11px; color: #909399; }
.graph-canvas { flex: 1; position: relative; background: #fafafa; border: 1px solid #e4e7ed; border-radius: 8px; overflow: auto; min-height: 600px; }
.canvas-hint { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); color: #c0c4cc; font-size: 16px; }
.canvas-node {
  position: absolute; width: 240px; background: #fff; border: 2px solid #e4e7ed;
  border-radius: 8px; cursor: pointer; transition: box-shadow 0.2s; z-index: 2;
}
.canvas-node:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
.canvas-node.selected { border-color: #409eff; box-shadow: 0 0 0 2px rgba(64,158,255,0.2); }
.canvas-node.preview-mode { cursor: default; }
.node-header { display: flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 6px 6px 0 0; font-size: 12px; }
.node-type-label { font-size: 11px; color: #606266; font-weight: 500; }
.node-body { padding: 8px 10px; }
.node-label { font-size: 14px; color: #303133; font-weight: 500; }
.node-id { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.port { position: absolute; width: 12px; height: 12px; background: #fff; border: 2px solid #909399; border-radius: 50%; z-index: 3; }
.port:hover { border-color: #409eff; background: #ecf5ff; }
.port-source { top: 50%; right: -6px; transform: translateY(-50%); cursor: crosshair; }
.port-target { top: 50%; left: -6px; transform: translateY(-50%); }

/* SVG 连线 */
.edges-svg { position: absolute; top: 0; left: 0; pointer-events: none; z-index: 1; }
.edge-line { pointer-events: stroke; }
.edge-line:hover { stroke-width: 3; cursor: pointer; }
.edge-line.selected { stroke-width: 3; stroke: #409eff; }

/* 属性面板 */
.property-panel { width: 280px; flex-shrink: 0; background: #f5f7fa; border-radius: 8px; padding: 12px; }
.panel-title { font-size: 13px; font-weight: 600; color: #606266; margin-bottom: 10px; }
.connection-preview { z-index: 10; }
</style>
