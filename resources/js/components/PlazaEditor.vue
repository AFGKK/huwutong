<template>
  <div class="plaza-editor">
    <!-- 工具栏 -->
    <div class="editor-toolbar" v-if="editor">
      <el-tooltip content="加粗" :show-after="300">
        <button @click="editor.chain().focus().toggleBold().run()" :class="{ 'is-active': editor.isActive('bold') }" class="editor-btn"><strong>B</strong></button>
      </el-tooltip>
      <el-tooltip content="斜体" :show-after="300">
        <button @click="editor.chain().focus().toggleItalic().run()" :class="{ 'is-active': editor.isActive('italic') }" class="editor-btn"><em>I</em></button>
      </el-tooltip>
      <el-tooltip content="删除线" :show-after="300">
        <button @click="editor.chain().focus().toggleStrike().run()" :class="{ 'is-active': editor.isActive('strike') }" class="editor-btn"><s>S</s></button>
      </el-tooltip>
      <span class="toolbar-sep"></span>
      <el-tooltip content="标题" :show-after="300">
        <button @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }" class="editor-btn">H2</button>
      </el-tooltip>
      <el-tooltip content="小标题" :show-after="300">
        <button @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }" class="editor-btn">H3</button>
      </el-tooltip>
      <span class="toolbar-sep"></span>
      <el-tooltip content="无序列表" :show-after="300">
        <button @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'is-active': editor.isActive('bulletList') }" class="editor-btn">•••</button>
      </el-tooltip>
      <el-tooltip content="有序列表" :show-after="300">
        <button @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'is-active': editor.isActive('orderedList') }" class="editor-btn">1.</button>
      </el-tooltip>
      <el-tooltip content="引用" :show-after="300">
        <button @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'is-active': editor.isActive('blockquote') }" class="editor-btn">"</button>
      </el-tooltip>
      <span class="toolbar-sep"></span>
      <el-tooltip content="插入链接" :show-after="300">
        <button @click="addLink" :class="{ 'is-active': editor.isActive('link') }" class="editor-btn">🔗</button>
      </el-tooltip>
      <el-tooltip content="插入图片" :show-after="300">
        <button @click="selectImage" class="editor-btn">🖼️</button>
      </el-tooltip>
      <input ref="fileInputRef" type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" @change="handleImageUpload" />
      <el-tooltip content="代码块" :show-after="300">
        <button @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ 'is-active': editor.isActive('codeBlock') }" class="editor-btn">&lt;/&gt;</button>
      </el-tooltip>
      <span class="toolbar-sep"></span>
      <el-tooltip content="清除格式" :show-after="300">
        <button @click="editor.chain().focus().clearNodes().unsetAllMarks().run()" class="editor-btn">✕</button>
      </el-tooltip>
    </div>
    <!-- 编辑器 -->
    <editor-content :editor="editor" class="editor-content" :style="{ minHeight: height + 'px' }" />
  </div>
</template>

<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import { ref, watch, onBeforeUnmount } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/api/client'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '分享新鲜事...' },
  height: { type: Number, default: 200 },
})
const emit = defineEmits(['update:modelValue'])

const fileInputRef = ref(null)

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({ heading: { levels: [2, 3] } }),
    Link.configure({ openOnClick: false }),
    Image.configure({ inline: false }),
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
})

watch(() => props.modelValue, (val) => {
  if (editor.value && val !== editor.value.getHTML()) {
    editor.value.commands.setContent(val, false)
  }
})

function addLink() {
  const previousUrl = editor.value.getAttributes('link').href
  const url = window.prompt('输入链接地址', previousUrl || 'https://')
  if (url === null) return
  if (url === '') { editor.value.chain().focus().extendMarkRange('link').unsetLink().run(); return }
  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function selectImage() {
  fileInputRef.value?.click()
}

async function handleImageUpload(e) {
  const file = e.target.files?.[0]
  if (!file) return
  e.target.value = ''
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data: res } = await apiClient.post('/products/upload-image', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (res.success && res.data?.url) {
      editor.value.chain().focus().setImage({ src: res.data.url }).run()
    } else {
      ElMessage.error(res.message || '上传失败')
    }
  } catch {
    ElMessage.error('图片上传失败')
  }
}

onBeforeUnmount(() => editor.value?.destroy())
</script>

<style scoped>
.plaza-editor { border: 1px solid #dcdfe6; border-radius: 6px; overflow: hidden; width: 100%; }
.editor-toolbar {
  display: flex; align-items: center; gap: 2px; padding: 6px 8px;
  background: #f8f9fa; border-bottom: 1px solid #eee; flex-wrap: wrap;
}
.editor-btn {
  width: 30px; height: 28px; border: none; background: transparent;
  border-radius: 4px; cursor: pointer; font-size: 13px;
  display: flex; align-items: center; justify-content: center;
  color: #606266; transition: all .15s;
}
.editor-btn:hover { background: #e8e8e8; }
.editor-btn.is-active { background: #409eff; color: #fff; }
.toolbar-sep { width: 1px; height: 18px; background: #e0e0e0; margin: 0 4px; }
.editor-content {
  padding: 12px; cursor: text; overflow-y: auto;
}
.editor-content :deep(.ProseMirror) { outline: none; min-height: inherit; }
.editor-content :deep(.ProseMirror p) { margin: 0 0 4px; line-height: 1.6; }
.editor-content :deep(.ProseMirror h2) { font-size: 18px; font-weight: 700; margin: 12px 0 6px; }
.editor-content :deep(.ProseMirror h3) { font-size: 16px; font-weight: 600; margin: 10px 0 4px; }
.editor-content :deep(.ProseMirror ul), .editor-content :deep(.ProseMirror ol) { padding-left: 20px; margin: 4px 0; }
.editor-content :deep(.ProseMirror li) { margin-bottom: 2px; }
.editor-content :deep(.ProseMirror blockquote) { border-left: 3px solid #409eff; padding-left: 12px; color: #666; margin: 8px 0; }
.editor-content :deep(.ProseMirror code) { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
.editor-content :deep(.ProseMirror pre) { background: #1e1e2e; color: #e0e0e0; padding: 12px; border-radius: 6px; margin: 8px 0; overflow-x: auto; }
.editor-content :deep(.ProseMirror pre code) { background: none; padding: 0; color: inherit; }
.editor-content :deep(.ProseMirror a) { color: #409eff; text-decoration: underline; }
.editor-content :deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder); float: left; color: #c0c4cc; pointer-events: none; height: 0;
}
</style>
