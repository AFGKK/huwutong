<template>
    <div class="blog-manager-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">总计</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.published }}</div>
                    <div class="stat-label">已发布</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.drafts }}</div>
                    <div class="stat-label">草稿</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">
                        <el-tooltip content="博客 / 更新日志 / 发布说明" placement="top">
                            <span>{{ stats.by_type?.blog || 0 }} / {{ stats.by_type?.changelog || 0 }} / {{ stats.by_type?.release_note || 0 }}</span>
                        </el-tooltip>
                    </div>
                    <div class="stat-label">B / C / R</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- RSS信息 -->
        <el-card class="rss-info" style="margin-bottom: 16px">
            <div class="rss-header">
                <span class="rss-label">RSS 订阅地址：</span>
                <div class="rss-links">
                    <el-tag>全部</el-tag>
                    <code class="rss-url">{{ baseUrl }}/api/rss/all</code>
                    <el-tag type="success">Blog</el-tag>
                    <code class="rss-url">{{ baseUrl }}/api/rss/blog</code>
                    <el-tag type="warning">Changelog</el-tag>
                    <code class="rss-url">{{ baseUrl }}/api/rss/changelog</code>
                </div>
                <el-button size="small" @click="copyRssUrls">复制所有URL</el-button>
            </div>
        </el-card>

        <!-- 操作栏 -->
        <el-card class="search-card">
            <el-row :gutter="16">
                <el-col :span="5">
                    <el-input v-model="filters.search" placeholder="搜索标题/内容" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-col>
                <el-col :span="3">
                    <el-select v-model="filters.type" placeholder="类型" clearable @change="loadList" style="width: 100%">
                        <el-option label="博客" value="blog" />
                        <el-option label="更新日志" value="changelog" />
                        <el-option label="发布说明" value="release_note" />
                    </el-select>
                </el-col>
                <el-col :span="3">
                    <el-select v-model="filters.status" placeholder="状态" clearable @change="loadList" style="width: 100%">
                        <el-option label="已发布" value="published" />
                        <el-option label="草稿" value="draft" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.category_id" placeholder="分类" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-col>
                <el-col :span="9" style="text-align: right">
                    <el-button type="primary" @click="showCreateDialog">
                        <el-icon><Plus /></el-icon> 新建文章
                    </el-button>
                    <el-button @click="showCategoryDialog = true">
                        <el-icon><FolderOpened /></el-icon> 分类管理
                    </el-button>
                    <el-button @click="handleExport" :loading="exporting">
                        <el-icon><Download /></el-icon> 导出CSV
                    </el-button>
                    <el-button @click="loadList">刷新</el-button>
                </el-col>
            </el-row>
        </el-card>

        <!-- 批量操作栏 -->
        <el-card v-if="selectedIds.length > 0" class="batch-bar" style="margin-bottom:12px">
            <div class="batch-bar-inner">
                <span class="batch-info">已选择 {{ selectedIds.length }} 项</span>
                <el-button size="small" @click="clearSelection">取消选择</el-button>
                <el-button size="small" type="primary" @click="handleBatchPublish" :loading="batchLoading">
                    <el-icon><Upload /></el-icon> 批量发布
                </el-button>
                <el-button size="small" type="warning" @click="showBatchCategoryDialog = true" :loading="batchLoading">
                    <el-icon><FolderOpened /></el-icon> 切换分类
                </el-button>
                <el-button size="small" type="danger" @click="handleBatchDelete" :loading="batchLoading">
                    <el-icon><Delete /></el-icon> 批量删除
                </el-button>
            </div>
        </el-card>

        <!-- 文章列表 -->
        <el-card class="table-card">
            <el-table ref="tableRef" :data="list" v-loading="loading" border stripe style="width: 100%"
                @selection-change="onSelectionChange">
                <el-table-column type="selection" width="45" />
                <el-table-column prop="title" label="标题" min-width="250">
                    <template #default="{ row }">
                        <div class="title-cell">
                            <el-tag :type="typeTag(row.type)" size="small" class="type-badge">
                                {{ typeLabel(row.type) }}
                            </el-tag>
                            <span>{{ row.title }}</span>
                            <el-tag v-if="row.is_featured" type="warning" size="small" effect="dark">精选</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="author" label="作者" width="120" />
                <el-table-column label="分类" width="110">
                    <template #default="{ row }">
                        <el-tag v-if="row.category" size="small" effect="plain" :color="row.category.color || ''">
                            {{ row.category.name }}
                        </el-tag>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="版本" width="90">
                    <template #default="{ row }">{{ row.version || '-' }}</template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.is_published ? 'success' : 'info'" size="small">
                            {{ row.is_published ? '已发布' : '草稿' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="发布时间" width="170">
                    <template #default="{ row }">{{ row.published_at || '-' }}</template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="showEditDialog(row)">编辑</el-button>
                        <el-button
                            size="small"
                            :type="row.is_published ? 'warning' : 'success'"
                            @click="handleTogglePublish(row)"
                        >
                            {{ row.is_published ? '下架' : '发布' }}
                        </el-button>
                        <el-button
                            size="small"
                            :type="row.is_featured ? 'warning' : 'default'"
                            @click="handleToggleFeatured(row)"
                        >
                            {{ row.is_featured ? '取消精选' : '精选' }}
                        </el-button>
                        <el-popconfirm title="确认删除此文章？" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 编辑/新建对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? '编辑文章' : '新建文章'"
            width="920px"
            :class="['blog-dialog', isFullscreen ? 'blog-dialog-fullscreen' : '']"
            :close-on-click-modal="false"
            :fullscreen="isFullscreen"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="标题" prop="title">
                            <el-input v-model="form.title" maxlength="255" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="类型" prop="type">
                            <el-select v-model="form.type" style="width: 100%">
                                <el-option label="博客文章" value="blog" />
                                <el-option label="更新日志" value="changelog" />
                                <el-option label="发布说明" value="release_note" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="URL标识">
                            <el-input v-model="form.slug" placeholder="留空自动生成" maxlength="255" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="作者">
                            <el-input v-model="form.author" placeholder="留空使用当前用户" maxlength="100" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="版本号">
                            <el-input v-model="form.version" placeholder="如: v2.1.0" maxlength="30" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="封面图">
                            <div style="display:flex;gap:6px">
                                <el-input v-model="form.featured_image" placeholder="图片URL" maxlength="500" style="flex:1" />
                                <el-button size="small" @click="uploadCoverImage" title="上传封面图">🖼️ 上传</el-button>
                            </div>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="分类">
                    <el-select v-model="form.category_id" clearable placeholder="选择分类" style="width: 100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="标签">
                    <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width: 100%">
                        <el-option v-for="tag in tagOptions" :key="tag" :label="tag" :value="tag" />
                    </el-select>
                </el-form-item>
                <el-form-item label="摘要">
                    <el-input v-model="form.excerpt" type="textarea" :rows="2" maxlength="500" @input="updateSeoPreview" />
                </el-form-item>
                <!-- SEO 预览卡片 -->
                <el-collapse v-model="showSeoPreview" style="margin-bottom:16px">
                    <el-collapse-item title="🔍 SEO 预览" name="seo">
                        <div class="seo-preview-card">
                            <div class="seo-url">{{ seoPreviewUrl }}</div>
                            <div class="seo-title">{{ seoPreviewTitle || '文章标题' }}</div>
                            <div class="seo-desc">{{ seoPreviewDesc || '文章摘要会显示在这里，请填写摘要以获得更好的搜索引擎展示效果。' }}</div>
                        </div>
                    </el-collapse-item>
                </el-collapse>
                <el-form-item label="内容" prop="content">
                    <!-- Tiptap 工具栏 -->
                    <div v-if="editor" class="blog-editor-toolbar">
                        <el-button-group size="small">
                            <el-button :type="editor.isActive('bold') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBold().run()" title="加粗"><b>B</b></el-button>
                            <el-button :type="editor.isActive('italic') ? 'primary' : 'default'" @click="editor.chain().focus().toggleItalic().run()" title="斜体"><i>I</i></el-button>
                            <el-button :type="editor.isActive('underline') ? 'primary' : 'default'" @click="editor.chain().focus().toggleUnderline().run()" title="下划线"><u>U</u></el-button>
                            <el-button :type="editor.isActive('strike') ? 'primary' : 'default'" @click="editor.chain().focus().toggleStrike().run()" title="删除线"><s>S</s></el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button :type="editor.isActive('heading', { level: 1 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</el-button>
                            <el-button :type="editor.isActive('heading', { level: 2 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</el-button>
                            <el-button :type="editor.isActive('heading', { level: 3 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button :type="editor.isActive('bulletList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBulletList().run()" title="无序列表">•</el-button>
                            <el-button :type="editor.isActive('orderedList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleOrderedList().run()" title="有序列表">1.</el-button>
                            <el-button :type="editor.isActive('blockquote') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBlockquote().run()" title="引用">❝</el-button>
                            <el-button :type="editor.isActive('code') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCode().run()" title="行内代码">`code`</el-button>
                            <el-button :type="editor.isActive('codeBlock') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCodeBlock().run()" title="代码块">&lt;/&gt;</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button @click="insertImageFile" title="上传图片">🖼️</el-button>
                            <el-button @click="insertVideo" title="插入视频">🎬</el-button>
                            <el-button @click="insertTable" title="插入表格">⊞</el-button>
                            <el-button @click="insertLink" title="插入链接">🔗</el-button>
                            <el-button @click="editor?.chain().focus().setHorizontalRule().run()" title="插入分割线">—</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button :type="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'default'" @click="editor?.chain().focus().setTextAlign('left').run()" title="左对齐">⬅</el-button>
                            <el-button :type="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'default'" @click="editor?.chain().focus().setTextAlign('center').run()" title="居中">⇔</el-button>
                            <el-button :type="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'default'" @click="editor?.chain().focus().setTextAlign('right').run()" title="右对齐">➔</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button @click="editor?.chain().focus().undo().run()" title="撤销">↩️</el-button>
                            <el-button @click="editor?.chain().focus().redo().run()" title="重做">↪️</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-popover placement="bottom" :width="220" trigger="click" v-model:visible="showColorPicker">
                            <template #reference>
                                <el-button size="small" title="文字颜色" :style="{borderBottom:'3px solid '+(editor?.getAttributes('textStyle')?.color||'#999')}">A</el-button>
                            </template>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;padding:4px">
                                <div v-for="c in ['#333333','#666666','#999999','#c0c4cc','#409eff','#67c23a','#e6a23c','#f56c6c','#b37feb','#ff85c0','#ff7a45','#a0d911']" :key="c"
                                    @click="editor?.chain().focus().setColor(c).run(); showColorPicker=false"
                                    :style="{width:28,height:28,borderRadius:4,background:c,cursor:'pointer',border:c==='#ffffff'?'1px solid #dcdfe6':'none'}"
                                    :title="c">
                                </div>
                                <div style="width:100%;margin-top:4px;border-top:1px solid #eee;padding-top:4px">
                                    <el-button size="small" text @click="editor?.chain().focus().unsetColor().run(); showColorPicker=false">清除颜色</el-button>
                                </div>
                            </div>
                        </el-popover>

                        <el-popover placement="bottom" :width="220" trigger="click" v-model:visible="showHighlightPicker">
                            <template #reference>
                                <el-button size="small" title="背景高亮" :style="{background:editor?.getAttributes('highlight')?.color||'transparent'}">🖊</el-button>
                            </template>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;padding:4px">
                                <div v-for="c in ['#ffd8bf','#ffd6e7','#d3adf7','#bae7ff','#b7eb8f','#ffe58f','#ffffff']" :key="c"
                                    @click="editor?.chain().focus().toggleHighlight({color:c}).run(); showHighlightPicker=false"
                                    :style="{width:28,height:28,borderRadius:4,background:c,cursor:'pointer',border:c==='#ffffff'?'1px solid #dcdfe6':'none'}">
                                </div>
                                <div style="width:100%;margin-top:4px;border-top:1px solid #eee;padding-top:4px">
                                    <el-button size="small" text @click="editor?.chain().focus().toggleHighlight().run(); showHighlightPicker=false">清除高亮</el-button>
                                </div>
                            </div>
                        </el-popover>

                        <el-button size="small" :type="showSearch ? 'primary' : 'default'" @click="toggleSearch" title="搜索替换">🔍 搜索</el-button>
                        <el-button size="small" @click="showTemplateDialog = true" title="文章模板">📋 模板</el-button>
                        <el-button size="small" @click="applyQuickFormat" title="一键排版">✨ 排版</el-button>
                        <el-button size="small" @click="showTocDialog = true" title="目录导航">📑 目录</el-button>
                        <el-button size="small" @click="showEmojiDialog = true" title="插入表情">😊 Emoji</el-button>

                        <el-dropdown trigger="click" @command="handleCopy">
                            <el-button size="small" title="一键复制内容">📋 复制 <el-icon><ArrowDown /></el-icon></el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="html">📋 复制 HTML</el-dropdown-item>
                                    <el-dropdown-item command="text">📝 复制纯文本</el-dropdown-item>
                                    <el-dropdown-item command="markdown">📄 复制 Markdown</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>

                        <el-button size="small" @click="clearFormatting" title="清除格式">🧹 清格式</el-button>

                        <el-divider direction="vertical" />

                        <el-dropdown trigger="click" @command="handleAiAction">
                            <el-button size="small" type="primary" plain>🤖 AI 工具 <el-icon><ArrowDown /></el-icon></el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="create">✍️ AI 创作</el-dropdown-item>
                                    <el-dropdown-item command="typo">✏️ 错别字识别</el-dropdown-item>
                                    <el-dropdown-item command="assistant">💬 AI 助手对话</el-dropdown-item>
                                    <el-dropdown-item command="polish">✨ AI 润色全文</el-dropdown-item>
                                    <el-dropdown-item command="summary">📝 AI 生成摘要</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>

                        <el-divider direction="vertical" />

                        <el-button size="small" @click="showHelpDialog = true" title="快捷键帮助">⌨️</el-button>
                        <el-button size="small" @click="showStatsDialog = true" title="文章统计">📊</el-button>
                        <el-button size="small" @click="toggleFullscreen" :type="isFullscreen ? 'primary' : 'default'" title="全屏编辑">⛶</el-button>
                        <el-button :type="showSource ? 'primary' : 'default'" size="small" @click="toggleSource" title="HTML源码">&lt;/&gt; HTML</el-button>
                    </div>
                    <!-- 搜索替换面板 -->
                    <div v-if="showSearch" class="search-panel">
                        <div class="search-row">
                            <el-input v-model="searchQuery" placeholder="搜索..." size="small" clearable style="width:180px" @input="doSearch" @keyup.enter="nextMatch" />
                            <el-button size="small" @click="prevMatch" :disabled="!searchMatches">▲</el-button>
                            <el-button size="small" @click="nextMatch" :disabled="!searchMatches">▼</el-button>
                            <span class="search-count" v-if="searchMatches > 0">{{ currentMatch + 1 }}/{{ searchMatches }}</span>
                            <span class="search-count no-result" v-else-if="searchQuery && searchMatches === 0">无结果</span>
                        </div>
                        <div class="search-row">
                            <el-input v-model="searchReplace" placeholder="替换为..." size="small" style="width:180px" @keyup.enter="doReplace" />
                            <el-button size="small" @click="doReplace" :disabled="!searchMatches" type="primary">替换</el-button>
                            <el-button size="small" @click="doReplaceAll" :disabled="!searchMatches">全部替换</el-button>
                            <el-button size="small" text @click="showSearch = false">✕ 关闭</el-button>
                        </div>
                    </div>
                    <!-- 代码块语言选择 -->
                    <div v-if="editor?.isActive('codeBlock')" class="code-lang-bar">
                        <span style="font-size:12px;color:#909399;margin-right:6px">语言：</span>
                        <el-select v-model="codeLang" size="small" style="width:130px" placeholder="选择语言" @change="changeCodeLang">
                            <el-option v-for="lang in codeLanguages" :key="lang" :label="lang" :value="lang" />
                        </el-select>
                        <span class="code-lang-badge" v-if="codeLang">{{ codeLang }}</span>
                        <span style="margin-left:auto;display:flex;gap:4px">
                            <el-button size="small" text type="primary" @click="copyCodeBlock" title="一键复制该代码块全部内容">📋 复制代码</el-button>
                            <el-button size="small" text @click="exitCodeBlock">✕ 退出代码块</el-button>
                        </span>
                    </div>
                    <!-- 表格操作栏 -->
                    <div v-if="editor?.isActive('table')" class="table-toolbar">
                        <el-button-group size="small">
                            <el-button @click="addColumnBefore" title="左侧插入列">◀ 列</el-button>
                            <el-button @click="addColumnAfter" title="右侧插入列">列 ▶</el-button>
                            <el-button @click="addRowBefore" title="上方插入行">▲ 行</el-button>
                            <el-button @click="addRowAfter" title="下方插入行">行 ▼</el-button>
                        </el-button-group>
                        <el-divider direction="vertical" />
                        <el-button-group size="small">
                            <el-button @click="deleteColumn" title="删除列">✕ 列</el-button>
                            <el-button @click="deleteRow" title="删除行">✕ 行</el-button>
                            <el-button @click="deleteTable" title="删除表格">✕ 表格</el-button>
                        </el-button-group>
                        <el-divider direction="vertical" />
                        <el-button-group size="small">
                            <el-button @click="toggleHeaderCell" title="切换表头">表头</el-button>
                            <el-button @click="mergeOrSplit" title="合并/拆分单元格">合并</el-button>
                        </el-button-group>
                    </div>
                    <!-- 编辑器内容区 -->
                    <div v-if="editor && !showSource" class="blog-editor-content" @dragover="handleDragOver" @dragleave="handleDragLeave" @drop="handleDrop">
                        <editor-content :editor="editor" class="blog-prose-editor" @click="onEditorClick" @keydown="handleEditorKeydown" />
                        <!-- 链接气泡菜单 -->
                        <div v-if="showLinkBubble" class="link-bubble" :style="{ top: linkBubblePos.top + 'px', left: linkBubblePos.left + 'px' }">
                            <span class="link-bubble-url">{{ linkBubbleHref }}</span>
                            <el-button size="small" text @click="editLink(linkBubbleHref, linkBubbleText)">编辑</el-button>
                            <el-divider direction="vertical" />
                            <el-button size="small" text type="danger" @click="removeLink">移除</el-button>
                        </div>
                        <div class="blog-editor-footer">
                            <span>📝 {{ wordCount }} 字 · 🇨🇳{{ chineseChars }} · 🇬🇧{{ englishWords }} · ¶{{ paragraphCount }} · ⏱️ {{ readingTime }} 分钟</span>
                            <span style="display:flex;align-items:center;gap:6px">
                                <span v-if="wordGoal > 0" class="word-goal-progress">
                                    <el-progress :percentage="Math.min(100, Math.round(wordCount / wordGoal * 100))" :stroke-width="6" :width="80" />
                                </span>
                                <el-input-number v-model="wordGoal" :min="0" :max="100000" :step="100" size="small" style="width:80px" placeholder="目标" title="设置字数目标" />
                                <span v-if="draftSaved" style="color:#67c23a">💾</span>
                            </span>
                        </div>
                    </div>
                    <!-- 拖拽上传提示 -->
                    <div v-if="dragOver" class="drag-overlay">
                        <div class="drag-hint">📁 释放以上传图片</div>
                    </div>
                    <!-- HTML 源码编辑区 -->
                    <div v-else-if="showSource" class="blog-editor-source">
                        <el-input v-model="sourceHtml" type="textarea" :rows="16" placeholder="HTML 源码..." @change="applySource" />
                    </div>
                    <div v-else-if="!editor" class="blog-editor-loading" style="border:1px solid #dcdfe6;border-radius:4px;min-height:320px;display:flex;align-items:center;justify-content:center;color:#909399">
                        <el-icon class="is-loading"><Loading /></el-icon> 加载编辑器...
                    </div>
                </el-form-item>
                <el-form-item label="发布设置">
                    <el-checkbox v-model="form.is_published">立即发布</el-checkbox>
                    <el-checkbox v-model="form.is_featured">标记为精选</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="previewBeforePublish">
                    👁️ 预览并{{ editingId ? '保存' : '发布' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 发布预览对话框（支持桌面/手机预览） -->
        <el-dialog v-model="previewVisible" :title="'👁️ 发布预览' + (previewDevice === 'mobile' ? ' 📱' : ' 💻')" :width="previewDevice === 'mobile' ? '420px' : '700px'" top="3vh" :close-on-click-modal="true" destroy-on-close>
            <!-- 设备切换栏 -->
            <div style="display:flex;gap:8px;margin-bottom:12px;justify-content:center">
                <el-radio-group v-model="previewDevice" size="small">
                    <el-radio-button value="desktop">💻 桌面</el-radio-button>
                    <el-radio-button value="mobile">📱 手机</el-radio-button>
                </el-radio-group>
            </div>
            <!-- 预览内容 -->
            <div :style="previewDevice === 'mobile' ? 'max-height:60vh;overflow-y:auto;padding:16px 12px;background:#fff;border-radius:8px;border:1px solid #e4e7ed;width:100%;box-sizing:border-box' : 'max-height:65vh;overflow-y:auto;padding:0 4px'">
                <h1 style="font-size:22px;font-weight:600;margin-bottom:12px;color:#303133" v-html="form.title || '（无标题）'"></h1>
                <div class="preview-content" v-html="form.content || '<p style=color:#c0c4cc>（内容为空）</p>'"></div>
            </div>
            <template #footer>
                <el-button size="small" @click="previewVisible = false">返回编辑</el-button>
                <el-button size="small" type="primary" @click="confirmPublish" :loading="submitting">
                    ✅ 确认{{ editingId ? '保存' : '发布' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 分类管理对话框 -->
        <el-dialog v-model="showCategoryDialog" title="文章分类管理" width="550px">
            <div class="mb-4">
                <el-button type="primary" size="small" @click="showCategoryForm=true; catForm={name:'',slug:'',description:'',color:'',sort_order:0}" :icon="Plus">添加分类</el-button>
            </div>

            <!-- 添加/编辑分类表单 -->
            <el-form v-if="showCategoryForm" :model="catForm" label-width="80px" size="small" class="mb-4" :inline="true">
                <el-form-item label="名称">
                    <el-input v-model="catForm.name" placeholder="分类名称" style="width:150px" />
                </el-form-item>
                <el-form-item label="标识">
                    <el-input v-model="catForm.slug" placeholder="URL标识" style="width:120px" />
                </el-form-item>
                <el-form-item label="颜色">
                    <el-color-picker v-model="catForm.color" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="catForm.sort_order" :min="0" :max="999" style="width:100px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="saveCategory">保存</el-button>
                    <el-button @click="showCategoryForm=false">取消</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="categories" stripe size="small">
                <el-table-column prop="name" label="名称" width="120" />
                <el-table-column label="标识" width="120">
                    <template #default="{ row }"><code>{{ row.slug }}</code></template>
                </el-table-column>
                <el-table-column label="颜色" width="70">
                    <template #default="{ row }">
                        <el-tag v-if="row.color" :color="row.color" style="color:#fff;border:none">{{ row.color }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="posts_count" label="文章数" width="70" align="center" />
                <el-table-column prop="sort_order" label="排序" width="60" align="center" />
                <el-table-column label="操作" width="140">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="editCategory(row)">编辑</el-button>
                        <el-popconfirm title="确定删除?" @confirm="removeCategory(row)">
                            <template #reference>
                                <el-button size="small" text type="danger" :disabled="row.posts_count > 0">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>

        <!-- 批量切换分类对话框 -->
        <el-dialog v-model="showBatchCategoryDialog" title="批量切换分类" width="400px">
            <el-form label-position="top">
                <el-form-item label="选择目标分类">
                    <el-select v-model="batchCategoryId" placeholder="选择分类" filterable style="width:100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchCategoryDialog = false">取消</el-button>
                <el-button type="primary" @click="handleBatchCategory" :loading="batchLoading">确认切换</el-button>
            </template>
        </el-dialog>

        <!-- 图片尺寸设置对话框（支持上传预览 + 网络图片 URL） -->
        <el-dialog v-model="showImageSizeDialog" title="🖼️ 设置图片" width="460px" :close-on-click-modal="false">
            <!-- 网络图片 URL 输入 -->
            <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
                <span style="font-size:13px;color:#666;white-space:nowrap">🔗 网络图片：</span>
                <el-input v-model="pendingMediaUrl" placeholder="粘贴图片网址，支持 http:// 或 https://" size="small" clearable @input="imageCustomWidth = 400" />
                <el-button size="small" @click="pickImageFile" title="上传本地图片">📁 上传</el-button>
            </div>
            <el-divider style="margin:8px 0" />
            <!-- 图片预览 -->
            <div style="text-align:center;margin-bottom:12px;min-height:80px;display:flex;align-items:center;justify-content:center;background:#f5f7fa;border-radius:6px">
                <img v-if="pendingMediaUrl" :src="pendingMediaUrl" style="max-width:100%;max-height:180px;border-radius:6px;object-fit:contain" @error="onImagePreviewError" />
                <span v-else style="color:#c0c4cc;font-size:13px">暂无预览</span>
            </div>
            <!-- 尺寸预设 -->
            <div style="display:flex;gap:8px;justify-content:center;margin-bottom:12px">
                <el-button v-for="preset in imageSizePresets" :key="preset.value"
                    :type="imageCustomWidth === preset.value ? 'primary' : 'default'"
                    size="small" @click="imageCustomWidth = preset.value">
                    {{ preset.label }}{{ preset.value ? ` (${preset.value}px)` : '' }}
                </el-button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                <span style="font-size:13px;color:#666">自定义宽度：</span>
                <el-input-number v-model="imageCustomWidth" :min="50" :max="1200" :step="10" size="small" style="width:140px" />
                <span style="font-size:12px;color:#999">px</span>
            </div>
            <div style="font-size:12px;color:#909399;text-align:center;margin-top:8px">
                💡 高度自动等比缩放，图片不变形
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
                <span style="font-size:13px;color:#666">对齐：</span>
                <el-radio-group v-model="imageCustomAlign" size="small">
                    <el-radio-button value="left">居左</el-radio-button>
                    <el-radio-button value="center">居中</el-radio-button>
                    <el-radio-button value="right">居右</el-radio-button>
                </el-radio-group>
            </div>
            <template #footer>
                <el-button size="small" @click="showImageSizeDialog = false">取消</el-button>
                <el-button size="small" type="primary" @click="confirmImageInsert" :disabled="!pendingMediaUrl">插入图片</el-button>
            </template>
        </el-dialog>

        <!-- 文章模板对话框 -->
        <el-dialog v-model="showTemplateDialog" title="📋 文章模板" width="600px">
            <div class="template-grid">
                <div class="template-card" @click="insertTemplate('product')">
                    <div class="template-icon">🏷️</div>
                    <div class="template-name">产品介绍</div>
                    <div class="template-desc">产品图片、标题、特点和购买引导</div>
                </div>
                <div class="template-card" @click="insertTemplate('announcement')">
                    <div class="template-icon">📢</div>
                    <div class="template-name">版本发布</div>
                    <div class="template-desc">版本标题、更新清单、升级说明</div>
                </div>
                <div class="template-card" @click="insertTemplate('changelog')">
                    <div class="template-icon">📋</div>
                    <div class="template-name">更新日志</div>
                    <div class="template-desc">新增功能、Bug修复、优化项列表</div>
                </div>
                <div class="template-card" @click="insertTemplate('guide')">
                    <div class="template-icon">📖</div>
                    <div class="template-name">使用指南</div>
                    <div class="template-desc">步骤说明、注意事项和图文搭配</div>
                </div>
            </div>
        </el-dialog>

        <!-- 目录导航对话框 -->
        <el-dialog v-model="showTocDialog" title="📑 目录导航" width="400px">
            <div v-if="tocItems.length === 0" style="padding:20px;text-align:center;color:#909399">
                当前文章没有标题（H1/H2/H3），请先添加标题
            </div>
            <div v-else class="toc-list">
                <div v-for="(item, idx) in tocItems" :key="idx" class="toc-item"
                    :style="{ paddingLeft: (item.level - 1) * 20 + 'px' }"
                    @click="scrollToHeading(idx)">
                    <span class="toc-level" :class="'toc-h' + item.level">H{{ item.level }}</span>
                    <span class="toc-text">{{ item.text }}</span>
                </div>
            </div>
        </el-dialog>

        <!-- Emoji 选择器对话框 -->
        <el-dialog v-model="showEmojiDialog" title="😊 插入表情" width="420px">
            <div class="emoji-grid">
                <span v-for="emoji in emojiList" :key="emoji" class="emoji-item" @click="insertEmoji(emoji)">{{ emoji }}</span>
            </div>
        </el-dialog>

        <!-- AI 结果对话框 -->
        <el-dialog v-model="showAiResult" :title="aiResultTitle" width="600px" :close-on-click-modal="true">
            <div v-loading="aiLoading">
                <div v-if="aiResult" class="ai-result-content" style="white-space:pre-wrap;font-size:14px;line-height:1.7;max-height:400px;overflow-y:auto">{{ aiResult }}</div>
                <div v-else-if="!aiLoading" style="text-align:center;padding:32px 0;color:#999">点击按钮开始分析...</div>
            </div>
            <template #footer>
                <el-button v-if="aiResult && (aiAction === 'typo' || aiAction === 'polish')" size="small" type="primary" @click="applyAiFix">✅ 应用到编辑器</el-button>
                <el-button v-if="aiResult && aiAction === 'summary'" size="small" @click="applyAiFix">📋 填入摘要</el-button>
                <el-button size="small" @click="showAiResult = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- AI 助手对话对话框 -->
        <el-dialog v-model="showAiAssistant" title="💬 AI 助手" width="480px" :close-on-click-modal="false">
            <div style="max-height:360px;overflow-y:auto;margin-bottom:8px">
                <div v-for="(msg, i) in aiChatMessages" :key="i" class="ai-chat-msg" :class="msg.role">
                    <div style="font-size:12px;font-weight:500;margin-bottom:2px;color:#909399">{{ msg.role === 'user' ? '我' : 'AI' }}</div>
                    <div style="font-size:13px;white-space:pre-wrap">{{ msg.content }}</div>
                </div>
                <div v-if="aiChatLoading" style="text-align:center;padding:16px;color:#999">AI 思考中...</div>
            </div>
            <div style="display:flex;gap:6px">
                <el-input v-model="aiChatInput" placeholder="输入你的问题..." size="small" style="flex:1" @keydown.enter="sendAiChat" />
                <el-button size="small" type="primary" :loading="aiChatLoading" @click="sendAiChat">发送</el-button>
            </div>
        </el-dialog>

        <!-- AI 创作对话框 -->
        <el-dialog v-model="showAiCreate" title="✍️ AI 创作" width="560px" :close-on-click-modal="false">
            <el-form label-position="top" size="small">
                <el-form-item label="创作主题" required>
                    <el-input v-model="aiCreateTopic" placeholder="输入文章主题，如：ChatGPT 对企业软件的影响" :rows="2" type="textarea" />
                </el-form-item>
                <el-form-item label="文章风格">
                    <el-radio-group v-model="aiCreateStyle">
                        <el-radio value="general">通用</el-radio>
                        <el-radio value="professional">专业</el-radio>
                        <el-radio value="popular">通俗易懂</el-radio>
                        <el-radio value="news">新闻报导</el-radio>
                        <el-radio value="story">故事叙述</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="字数要求">
                    <el-select v-model="aiCreateLength" style="width:140px">
                        <el-option label="简短（~500字）" value="short" />
                        <el-option label="适中（~1000字）" value="medium" />
                        <el-option label="详细（~2000字）" value="long" />
                    </el-select>
                </el-form-item>
                <el-form-item label="额外要求（选填）">
                    <el-input v-model="aiCreateExtra" placeholder="如：需要包含数据引用、分三个章节、语气正式等" :rows="2" type="textarea" />
                </el-form-item>
            </el-form>
            <div v-if="aiCreateResult" class="ai-create-preview" style="border:1px solid #e4e7ed;border-radius:6px;padding:12px;margin-bottom:8px;max-height:280px;overflow-y:auto;background:#fafafa">
                <div style="font-size:12px;color:#909399;margin-bottom:6px">📖 生成预览</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap">{{ aiCreateResult.substring(0, 500) }}{{ aiCreateResult.length > 500 ? '...' : '' }}</div>
            </div>
            <template #footer>
                <el-button size="small" @click="showAiCreate = false">取消</el-button>
                <el-button size="small" type="primary" :loading="aiCreateLoading" @click="doAiCreate">{{ aiCreateResult ? '🔄 重新生成' : '✍️ 开始创作' }}</el-button>
                <el-button v-if="aiCreateResult" size="small" type="success" @click="insertAiCreate">📥 插入到编辑器</el-button>
            </template>
        </el-dialog>

        <!-- 快捷键帮助对话框 -->
        <el-dialog v-model="showHelpDialog" title="⌨️ 快捷键帮助" width="500px">
            <div class="shortcut-list">
                <div class="shortcut-item" v-for="s in shortcuts" :key="s.key">
                    <span class="shortcut-keys">
                        <kbd v-for="k in s.keys.split(' + ')" :key="k">{{ k }}</kbd>
                    </span>
                    <span class="shortcut-desc">{{ s.desc }}</span>
                </div>
            </div>
        </el-dialog>

        <!-- 文章统计对话框 -->
        <el-dialog v-model="showStatsDialog" title="📊 文章统计" width="420px">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.totalChars }}</div>
                    <div class="stat-label">总字数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.chineseChars }}</div>
                    <div class="stat-label">🇨🇳 中文字数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.englishWords }}</div>
                    <div class="stat-label">🇬🇧 英文词数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.paragraphs }}</div>
                    <div class="stat-label">¶ 段落数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.headings }}</div>
                    <div class="stat-label">📑 标题数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.links }}</div>
                    <div class="stat-label">🔗 链接数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.images }}</div>
                    <div class="stat-label">🖼️ 图片数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.codeBlocks }}</div>
                    <div class="stat-label">&lt;/&gt; 代码块</div>
                </div>
                <div class="stat-item" style="grid-column:1/-1">
                    <div class="stat-num">⏱️ {{ articleStats.readingTime }} 分钟</div>
                    <div class="stat-label">预计阅读时间</div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, reactive, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, FolderOpened, Download, Upload, Delete, Loading, ArrowDown } from '@element-plus/icons-vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import ImageExt from '@tiptap/extension-image'
import LinkExt from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { CodeBlockLowlight } from '@tiptap/extension-code-block-lowlight'
import { common, createLowlight } from 'lowlight'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableCell } from '@tiptap/extension-table-cell'
import { TableHeader } from '@tiptap/extension-table-header'
import { TextAlign } from '@tiptap/extension-text-align'
import { Color } from '@tiptap/extension-color'
import { TextStyle } from '@tiptap/extension-text-style'
import { Highlight } from '@tiptap/extension-highlight'
import apiClient from '@/api/client'

const lowlight = createLowlight(common)
import {
    getBlogList, getBlogStats, getBlogDetail,
    createBlog, updateBlog, deleteBlog,
    togglePublish, toggleFeatured,
    getSubscriptionStats, getSubscriptionList,
    getCategories, createCategory, updateCategory, deleteCategory,
    batchDeletePosts, batchPublishPosts, batchCategoryPosts, exportBlogCsv,
} from '@/api/blog'

const baseUrl = computed(() => window.location.origin)
const loading = ref(false)
const submitting = ref(false)
const stats = ref({ total: 0, published: 0, drafts: 0, by_type: { blog: 0, changelog: 0, release_note: 0 } })
const subStats = ref({ total: 0, verified: 0, unverified: 0, unsubscribed: 0, by_type: {} })
const list = ref([])
const currentPage = ref(1)
const perPage = ref(15)
const total = ref(0)
const dialogVisible = ref(false)
const editingId = ref(null)
const formRef = ref(null)

const filters = ref({ search: '', type: '', status: '', category_id: '' })
const form = ref({ title: '', type: 'blog', category_id: null, content: '', slug: '', excerpt: '', featured_image: '', author: '', tags: [], version: '', is_published: false, is_featured: false })
const rules = {
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
    type: [{ required: true, message: '请选择类型', trigger: 'change' }],
    content: [{ required: true, message: '请输入内容', trigger: 'blur' }],
}

// ─── Tiptap 编辑器 ───
const showSource = ref(false)
const sourceHtml = ref('')
const showColorPicker = ref(false)
const showHighlightPicker = ref(false)
const previewDevice = ref('desktop') // 'desktop' | 'mobile'
const previewVisible = ref(false)
const showLinkDialog = ref(false)
const linkUrl = ref('')
const linkText = ref('')
const linkEditMode = ref(false) // false=insert, true=edit

// ─── 链接气泡菜单 ───
const showLinkBubble = ref(false)
const linkBubblePos = ref({ top: 0, left: 0 })
const linkBubbleHref = ref('')
const linkBubbleText = ref('')

// ─── 图片尺寸设置 ───
const showImageSizeDialog = ref(false)
const pendingMediaUrl = ref('')
const imageCustomWidth = ref(400)
const imageCustomAlign = ref('left')
const imageSizePresets = [
    { label: '小', value: 250 },
    { label: '中', value: 400 },
    { label: '大', value: 600 },
    { label: '原图', value: 0 },
]

// ─── 搜索替换 ───
const showSearch = ref(false)
const searchQuery = ref('')
const searchReplace = ref('')
const searchMatches = ref(0)
const currentMatch = ref(-1)

// ─── 文章模板 ───
const showTemplateDialog = ref(false)
const templates = {
    product: {
        title: '🏷️ 产品介绍',
        html: '<h2>产品名称</h2><p style="font-size:15px;color:#666">一句话产品简介，突出核心卖点。</p><div style="display:flex;gap:12px;margin:16px 0"><div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center"><div style="font-size:28px;font-weight:700;color:#f56c6c">核心特点一</div><div style="font-size:13px;color:#666;margin-top:4px">特点描述</div></div><div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center"><div style="font-size:28px;font-weight:700;color:#f56c6c">核心特点二</div><div style="font-size:13px;color:#666;margin-top:4px">特点描述</div></div></div><p>详细的产品功能介绍、使用场景和客户案例。</p>'
    },
    announcement: {
        title: '📢 版本发布',
        html: '<h2>v2.0.0 版本发布</h2><div style="background:#f0f7ff;border:1px solid #409eff;border-radius:8px;padding:16px;margin:16px 0"><div style="display:flex;gap:20px;flex-wrap:wrap"><div><strong>📅 发布日期：</strong>2026年X月X日</div><div><strong>🏷️ 版本号：</strong>v2.0.0</div></div></div><h3>🚀 新增功能</h3><ul><li>功能一：功能描述</li><li>功能二：功能描述</li></ul><h3>🔧 优化改进</h3><ul><li>优化项一</li><li>优化项二</li></ul><h3>🐛 Bug 修复</h3><ul><li>修复了...</li></ul>'
    },
    changelog: {
        title: '📋 更新日志',
        html: '<h2>📋 更新日志</h2><p>本次更新包含以下改动：</p><h3>✅ 新增</h3><ul><li>新增功能...</li></ul><h3>🔄 变更</h3><ul><li>变更内容...</li></ul><h3>🐛 修复</h3><ul><li>修复问题...</li></ul><h3>⚡ 优化</h3><ul><li>性能优化...</li></ul>'
    },
    guide: {
        title: '📖 使用指南',
        html: '<h2>📖 使用指南：功能名称</h2><p>本指南将帮助你快速上手使用该功能。</p><h3>第一步：准备工作</h3><ul><li>确保已登录账号</li><li>检查系统版本要求</li></ul><h3>第二步：操作步骤</h3><ol><li>打开功能页面</li><li>填写必要信息</li><li>点击确认提交</li></ol><div style="background:#f0f9eb;border:1px solid #67c23a;border-radius:8px;padding:12px;margin:16px 0"><strong>💡 小提示：</strong>操作过程中如有疑问，可联系客服获取帮助。</div>'
    }
}

// ─── Emoji ───
const showEmojiDialog = ref(false)
const emojiList = ['😀','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤑','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','💪','✌️','🤟','🤘','👌','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','💖','💗','💫','⭐','🌟','✨','🔥','💯','🎉','🎊','🎈','🎁','🎀','🏆','🥇','🥈','🥉','🏅','💎','🔔','📣','💬','🗨️','💡','😎','🤩','🥳','🤗','🤔','🤭','🤫','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😢','😭','😤','😠','😡','🤬','😱','😨','😰','😥','😓','🤒','🤕','🥺','😳','🤯']

// ─── 目录导航 ───
const showTocDialog = ref(false)
const tocItems = computed(() => {
    const html = editor.value?.getHTML() || form.value.content || ''
    const regex = /<h([1-3])(?:\s[^>]*)?>(.*?)<\/h[1-3]>/gi
    const items = []
    let match
    while ((match = regex.exec(html)) !== null) {
        const level = parseInt(match[1])
        const text = match[2].replace(/<[^>]*>/g, '')
        items.push({ level, text })
    }
    return items
})
function scrollToHeading(idx) {
    const item = tocItems.value[idx]
    if (!item || !editor.value) return
    const docText = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size)
    const pos = docText.indexOf(item.text)
    if (pos >= 0) {
        editor.value.chain().focus().setTextSelection({ from: pos, to: pos + item.text.length }).run()
    }
    showTocDialog.value = false
}

// ─── 表格操作 ───
function insertTable() {
    if (!editor.value) return
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}
function addColumnBefore() { editor.value?.chain().focus().addColumnBefore().run() }
function addColumnAfter() { editor.value?.chain().focus().addColumnAfter().run() }
function addRowBefore() { editor.value?.chain().focus().addRowBefore().run() }
function addRowAfter() { editor.value?.chain().focus().addRowAfter().run() }
function deleteColumn() { editor.value?.chain().focus().deleteColumn().run() }
function deleteRow() { editor.value?.chain().focus().deleteRow().run() }
function deleteTable() { editor.value?.chain().focus().deleteTable().run() }
function toggleHeaderCell() { editor.value?.chain().focus().toggleHeaderCell().run() }
function mergeOrSplit() {
    if (editor.value?.isActive('table')) {
        editor.value.chain().focus().mergeOrSplit().run()
    }
}

// ─── 代码块语言 ───
const codeLang = ref('')
const codeLanguages = ['javascript','typescript','php','python','html','css','sql','bash','json','xml','yaml','markdown','go','rust','java','csharp','ruby']

function changeCodeLang(lang) {
    if (!editor.value) return
    editor.value.chain().focus().updateAttributes('codeBlock', { language: lang }).run()
}

function exitCodeBlock() {
    if (!editor.value) return
    editor.value.chain().focus().toggleCodeBlock().run()
}

// ─── 代码块复制（公众号风格：智能定位当前代码块 → 一键复制） ───
function copyCodeBlock() {
    if (!editor.value) return
    // 智能定位：从光标所在位置向上查找代码块节点
    const pos = editor.value.state.selection.$from
    for (let i = pos.depth; i > 0; i--) {
        const node = pos.node(i)
        if (node.type.name === 'codeBlock') {
            navigator.clipboard.writeText(node.textContent).then(() => {
                ElMessage.success('✅ 代码已复制')
            }).catch(() => ElMessage.error('复制失败'))
            return
        }
    }
    // 兜底：如果选区不在代码块内，尝试获取当前选中文本
    const selectedText = editor.value.state.doc.textBetween(
        editor.value.state.selection.from,
        editor.value.state.selection.to
    )
    if (selectedText) {
        navigator.clipboard.writeText(selectedText).then(() => {
            ElMessage.success('✅ 代码已复制')
        }).catch(() => ElMessage.error('复制失败'))
    } else {
        ElMessage.warning('未找到代码块')
    }
}

// ─── AI 工具 ───
const showAiResult = ref(false)
const showAiAssistant = ref(false)
const showAiCreate = ref(false)
const aiResultTitle = ref('')
const aiResult = ref('')
const aiLoading = ref(false)
const aiAction = ref('')
const aiChatMessages = ref([])
const aiChatInput = ref('')
const aiChatLoading = ref(false)
const aiCreateTopic = ref('')
const aiCreateStyle = ref('general')
const aiCreateLength = ref('medium')
const aiCreateExtra = ref('')
const aiCreateResult = ref('')
const aiCreateLoading = ref(false)

// ─── 快捷键帮助 ───
const showHelpDialog = ref(false)
const draftSaved = ref(false)
const isFullscreen = ref(false)
const showStatsDialog = ref(false)
const wordGoal = ref(0)
const showSeoPreview = ref([])
let draftSaveTimer = null

// SEO 预览
const seoPreviewUrl = computed(() => {
    const base = window.location.origin
    const slug = form.value.slug || '{slug}'
    return base + '/blog/' + slug
})
const seoPreviewTitle = computed(() => {
    const t = form.value.title || ''
    return t.length > 60 ? t.substring(0, 57) + '...' : t
})
const seoPreviewDesc = computed(() => {
    const d = form.value.excerpt || ''
    return d.length > 160 ? d.substring(0, 157) + '...' : d
})
function updateSeoPreview() {
    // 触发 computed 更新
}

function copyContent() {
    const text = getArticleText() || getArticleHtml()
    navigator.clipboard.writeText(text).then(() => ElMessage.success('内容已复制')).catch(() => ElMessage.error('复制失败'))
}

function handleCopy(cmd) {
    if (cmd === 'html') {
        const html = getArticleHtml()
        navigator.clipboard.writeText(html).then(() => ElMessage.success('✅ HTML 已复制')).catch(() => ElMessage.error('复制失败'))
    } else if (cmd === 'text') {
        const text = getArticleText()
        navigator.clipboard.writeText(text).then(() => ElMessage.success('✅ 纯文本已复制')).catch(() => ElMessage.error('复制失败'))
    } else if (cmd === 'markdown') {
        copyAsMarkdown()
    }
}

function copyAsMarkdown() {
    if (!editor.value) return
    const html = editor.value.getHTML()
    // 简单 HTML 转 Markdown
    let md = html
        .replace(/<h1[^>]*>(.*?)<\/h1>/gi, '# $1\n\n')
        .replace(/<h2[^>]*>(.*?)<\/h2>/gi, '## $1\n\n')
        .replace(/<h3[^>]*>(.*?)<\/h3>/gi, '### $1\n\n')
        .replace(/<strong>(.*?)<\/strong>/gi, '**$1**')
        .replace(/<b>(.*?)<\/b>/gi, '**$1**')
        .replace(/<em>(.*?)<\/em>/gi, '*$1*')
        .replace(/<i>(.*?)<\/i>/gi, '*$1*')
        .replace(/<code>(.*?)<\/code>/gi, '`$1`')
        .replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gi, '[$2]($1)')
        .replace(/<img[^>]*src="([^"]*)"[^>]*>/gi, '![image]($1)')
        .replace(/<li>(.*?)<\/li>/gi, '- $1\n')
        .replace(/<\/ul>/gi, '\n')
        .replace(/<\/ol>/gi, '\n')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<p[^>]*>(.*?)<\/p>/gi, '$1\n\n')
        .replace(/<blockquote[^>]*>(.*?)<\/blockquote>/gi, '> $1\n\n')
        .replace(/<pre[^>]*>(.*?)<\/pre>/gis, '```\n$1\n```\n\n')
        .replace(/<[^>]*>/g, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim()
    navigator.clipboard.writeText(md).then(() => ElMessage.success('✅ Markdown 已复制')).catch(() => ElMessage.error('复制失败'))
}
const shortcuts = [
    { keys: 'Ctrl + B', desc: '加粗' },
    { keys: 'Ctrl + I', desc: '斜体' },
    { keys: 'Ctrl + U', desc: '下划线' },
    { keys: 'Ctrl + Z', desc: '撤销' },
    { keys: 'Ctrl + Shift + Z', desc: '重做' },
    { keys: 'Ctrl + K', desc: '插入链接' },
    { keys: 'Ctrl + S', desc: '保存/发布' },
    { keys: 'Ctrl + Shift + P', desc: '预览' },
    { keys: 'Ctrl + F', desc: '搜索替换' },
    { keys: 'Enter', desc: '在列表/引用中换行' },
    { keys: 'Tab', desc: '缩进列表项' },
    { keys: 'Shift + Tab', desc: '减少缩进' },
]

const editor = useEditor({
    content: '',
    extensions: [
        StarterKit.configure({
            codeBlock: false,
            history: {
                depth: 100,
            },
        }),
        CodeBlockLowlight.configure({ lowlight }),
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableCell,
        TableHeader,
        Underline,
        ImageExt.configure({
            inline: true,
        }),
        LinkExt.configure({
            openOnClick: false,
            HTMLAttributes: {
                rel: 'noopener noreferrer',
                target: '_blank',
            },
        }),
        Placeholder.configure({
            placeholder: '开始写文章...',
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
    ],
    editorProps: {
        transformPastedHTML(html) {
            // 移除 Word 等外部编辑器带来的垃圾样式
            return html
                .replace(/<meta[^>]*>/gi, '')
                .replace(/<style[^>]*>[^<]*<\/style>/gi, '')
                .replace(/<!--[^>]*-->/g, '')
                .replace(/<o:[^>]*>[^<]*<\/o:[^>]*>/gi, '')
                .replace(/class="[^"]*"/gi, '')
                // 不再清除所有 style，保留颜色/对齐等有效样式
                .replace(/lang="[^"]*"/gi, '')
                .replace(/<span[^>]*>/gi, '<span>')
                .replace(/<\/span>/g, '')
        },
    },
    onUpdate: ({ editor }) => {
        form.value.content = editor.getHTML()
    },
})

const wordCount = computed(() => {
    if (!editor.value) return 0
    const text = editor.value.getText()
    return text.replace(/\s/g, '').length
})

const chineseChars = computed(() => {
    if (!editor.value) return 0
    const text = editor.value.getText()
    return (text.match(/[\u4e00-\u9fff]/g) || []).length
})

const englishWords = computed(() => {
    if (!editor.value) return 0
    const text = editor.value.getText()
    return text.replace(/[\u4e00-\u9fff]/g, ' ').trim().split(/\s+/).filter(Boolean).length
})

const paragraphCount = computed(() => {
    if (!editor.value) return 0
    const html = editor.value.getHTML()
    return (html.match(/<p[^>]*>/g) || []).length
})

const readingTime = computed(() => {
    const wc = wordCount.value
    if (wc === 0) return 0
    return Math.max(1, Math.ceil(wc / 400))
})

function toggleSource() {
    if (!showSource.value) {
        // 切换到源码模式：将编辑器内容同步到 sourceHtml
        sourceHtml.value = editor.value?.getHTML() || form.value.content || ''
    } else {
        // 切回可视化模式：应用源码
        applySource()
    }
    showSource.value = !showSource.value
}

function applySource() {
    if (editor.value && sourceHtml.value !== undefined) {
        editor.value.commands.setContent(sourceHtml.value || '')
    }
}

// ─── 文件上传 ───
async function uploadFile(file) {
    const formData = new FormData()
    formData.append('files[]', file)
    try {
        const { data: res } = await apiClient.post('/files/upload/simple', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        return res?.data?.files?.[0]?.url || null
    } catch {
        ElMessage.error('上传失败')
        return null
    }
}

// ─── 封面上传 ───
function uploadCoverImage() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return
        const url = await uploadFile(file)
        if (url) {
            form.value.featured_image = url
            ElMessage.success('封面已上传')
        }
    }
    input.click()
}

// ─── 图片上传（如公众号：打开对话框 → 上传或粘贴 URL → 设置尺寸 → 插入） ───
function insertImageFile() {
    pendingMediaUrl.value = ''
    imageCustomWidth.value = 400
    imageCustomAlign.value = 'left'
    showImageSizeDialog.value = true
}

function pickImageFile() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return
        const url = await uploadFile(file)
        if (url) {
            pendingMediaUrl.value = url
        }
    }
    input.click()
}

function confirmImageInsert() {
    if (!pendingMediaUrl.value || !editor.value) return
    const w = imageCustomWidth.value > 0 ? ` width="${imageCustomWidth.value}"` : ''
    const style = `max-width:100%;height:auto`
    const alignStyle = imageCustomAlign.value === 'center' ? 'display:block;margin:12px auto;' : 
        imageCustomAlign.value === 'right' ? 'float:right;margin:12px 0 12px 16px;' :
        'margin:12px 0;'
    editor.value.chain().focus().insertContent(`<img src="${pendingMediaUrl.value}"${w} style="${style};${alignStyle}" />`).run()
    showImageSizeDialog.value = false
    pendingMediaUrl.value = ''
}

function onImagePreviewError() {
    // 预览加载失败时静默处理，图片输入框仍可编辑
}

// ─── 视频插入 ───
function insertVideo() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'video/*'
    input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return
        const url = await uploadFile(file)
        if (url && editor.value) {
            editor.value.chain().focus().insertContent(`<video controls src="${url}" style="max-width:100%;border-radius:6px"></video>`).run()
        }
    }
    input.click()
}

// ─── 搜索替换 ───
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
function toggleSearch() {
    showSearch.value = !showSearch.value
    if (!showSearch.value) {
        searchQuery.value = ''
        searchReplace.value = ''
        searchMatches.value = 0
        currentMatch.value = -1
    }
}
function doSearch() {
    if (!searchQuery.value || !editor.value) {
        searchMatches.value = 0
        currentMatch.value = -1
        return
    }
    const text = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size)
    const regex = new RegExp(escapeRegex(searchQuery.value), 'gi')
    const matches = text.match(regex)
    searchMatches.value = matches ? matches.length : 0
    currentMatch.value = searchMatches.value > 0 ? 0 : -1
}
function focusOnMatch() {
    if (!searchQuery.value || !editor.value || searchMatches.value === 0) return
    const query = searchQuery.value
    const text = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size)
    const regex = new RegExp(escapeRegex(query), 'gi')
    let match, count = 0
    while ((match = regex.exec(text)) !== null) {
        if (count === currentMatch.value) {
            const from = match.index
            const to = from + query.length
            editor.value.chain().focus().setTextSelection({ from, to }).run()
            break
        }
        count++
    }
}
function nextMatch() { if (searchMatches.value > 0) { currentMatch.value = (currentMatch.value + 1) % searchMatches.value; focusOnMatch() } }
function prevMatch() { if (searchMatches.value > 0) { currentMatch.value = (currentMatch.value - 1 + searchMatches.value) % searchMatches.value; focusOnMatch() } }
function doReplace() {
    if (!editor.value || searchMatches.value === 0 || !searchReplace.value) return
    const html = editor.value.getHTML()
    const regex = new RegExp(escapeRegex(searchQuery.value), 'i')
    const match = html.match(regex)
    if (match) {
        editor.value.commands.setContent(html.replace(regex, searchReplace.value))
        doSearch()
        ElMessage.success('已替换 1 处')
    }
}
function doReplaceAll() {
    if (!editor.value || searchMatches.value === 0 || !searchReplace.value) return
    const html = editor.value.getHTML()
    const regex = new RegExp(escapeRegex(searchQuery.value), 'gi')
    const count = (html.match(regex) || []).length
    editor.value.commands.setContent(html.replace(regex, searchReplace.value))
    showSearch.value = false
    searchQuery.value = ''
    searchReplace.value = ''
    searchMatches.value = 0
    ElMessage.success(`已全部替换 ${count} 处`)
}

// ─── 文章模板 ───
function insertTemplate(name) {
    if (!editor.value) return
    const tpl = templates[name]
    if (!tpl) return
    editor.value.chain().focus().insertContent(tpl.html).run()
    showTemplateDialog.value = false
    ElMessage.success(`已插入「${tpl.title}」模板`)
}

// ─── 清除格式 ───
function clearFormatting() {
    if (!editor.value) return
    editor.value.chain().focus().clearNodes().unsetAllMarks().run()
    ElMessage.success('🧹 已清除格式')
}

// ─── 全屏编辑 ───
function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value
}

// ─── 文章统计 ───
const articleStats = computed(() => ({
    totalChars: wordCount.value,
    chineseChars: chineseChars.value,
    englishWords: englishWords.value,
    paragraphs: paragraphCount.value,
    headings: (editor.value?.getHTML() || '').match(/<h[1-3][^>]*>/g)?.length || 0,
    links: (editor.value?.getHTML() || '').match(/<a [^>]*>/g)?.length || 0,
    images: (editor.value?.getHTML() || '').match(/<img [^>]*>/g)?.length || 0,
    codeBlocks: (editor.value?.getHTML() || '').match(/<pre[^>]*>/g)?.length || 0,
    readingTime: readingTime.value,
}))

// ─── 拖拽上传 ───
const dragOver = ref(false)

function handleDragOver(e) {
    e.preventDefault()
    dragOver.value = true
}
function handleDragLeave() {
    dragOver.value = false
}
function handleDrop(e) {
    e.preventDefault()
    dragOver.value = false
    const files = e.dataTransfer?.files
    if (!files?.length) return
    const file = files[0]
    if (!file.type.startsWith('image/')) {
        ElMessage.warning('仅支持图片文件')
        return
    }
    uploadFile(file).then(url => {
        if (url) {
            pendingMediaUrl.value = url
            imageCustomWidth.value = 400
            showImageSizeDialog.value = true
        }
    })
}

// ─── 一键排版 ───
function applyQuickFormat() {
    if (!editor.value) return
    const html = editor.value.getHTML()
    let cleaned = html
        .replace(/<p[^>]*>/gi, '<p style="margin:8px 0;line-height:1.8;font-size:15px">')
        .replace(/<h1[^>]*>/gi, '<h1 style="font-size:24px;font-weight:700;margin:16px 0 8px">')
        .replace(/<h2[^>]*>/gi, '<h2 style="font-size:20px;font-weight:600;margin:14px 0 6px">')
        .replace(/<h3[^>]*>/gi, '<h3 style="font-size:17px;font-weight:600;margin:12px 0 6px">')
        .replace(/<img /gi, '<img style="max-width:100%;height:auto;border-radius:6px;margin:12px 0" ')
    editor.value.commands.setContent(cleaned)
    ElMessage.success('✨ 一键排版完成')
}

// ─── Emoji ───
function insertEmoji(emoji) {
    if (!editor.value) return
    editor.value.chain().focus().insertContent(emoji).run()
    showEmojiDialog.value = false
}

// ─── AI 工具 ───
function getArticleText() {
    return editor.value?.getText() || form.value.content?.replace(/<[^>]*>/g, '') || ''
}
function getArticleHtml() {
    return editor.value?.getHTML() || form.value.content || ''
}
function handleAiAction(cmd) {
    if (cmd === 'copy') {
        const text = getArticleText() || getArticleHtml()
        navigator.clipboard.writeText(text).then(() => ElMessage.success('内容已复制')).catch(() => ElMessage.error('复制失败'))
        return
    }
    if (cmd === 'assistant') { showAiAssistant.value = true; return }
    if (cmd === 'create') {
        aiCreateTopic.value = form.value.title || ''
        aiCreateResult.value = ''
        showAiCreate.value = true
        return
    }
    aiLoading.value = true; aiResult.value = ''; showAiResult.value = true
    const text = getArticleText()
    if (cmd === 'typo') {
        aiAction.value = 'typo'
        aiResultTitle.value = '✏️ 错别字识别'
        callLlm('你是一个中文校对专家。请检查以下文章中的错别字、语法错误和标点问题。列出每个问题及其位置和修改建议。\n\n' + text)
    } else if (cmd === 'polish') {
        aiAction.value = 'polish'
        aiResultTitle.value = '✨ AI 润色全文'
        callLlm('你是一个专业文字编辑。请润色以下文章，改进表达方式，使语言更流畅、专业，但不改变原意。直接返回润色后的完整文章。\n\n' + text)
    } else if (cmd === 'summary') {
        aiAction.value = 'summary'
        aiResultTitle.value = '📝 AI 生成摘要'
        callLlm('请为以下文章生成一段简洁的摘要（100-200字），突出核心内容和亮点。\n\n' + text)
    }
}
async function callLlm(prompt) {
    try {
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiResult.value = res.data?.data?.reply || '暂无结果'
    } catch (e) {
        aiResult.value = 'AI 服务暂时不可用，请稍后重试。\n' + (e.response?.data?.message || '')
    } finally { aiLoading.value = false }
}
function applyAiFix() {
    if (!aiResult.value) return
    if (aiAction.value === 'polish') {
        editor.value?.commands.setContent('<p>' + aiResult.value.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>')
        ElMessage.success('✅ 已应用润色结果')
    } else if (aiAction.value === 'summary') {
        form.value.excerpt = aiResult.value
        ElMessage.success('📋 摘要已填入')
    } else {
        ElMessage.success('请手动参考建议修改')
    }
    showAiResult.value = false
}
async function sendAiChat() {
    if (!aiChatInput.value.trim()) return
    const q = aiChatInput.value.trim()
    aiChatMessages.value.push({ role: 'user', content: q })
    aiChatInput.value = ''
    aiChatLoading.value = true
    try {
        const context = '当前文章内容：\n' + getArticleText().substring(0, 2000) + '\n\n'
        const res = await apiClient.post('/user-chat/ai-conversation', { message: context + '用户问题：' + q })
        const reply = res.data?.data?.reply || '抱歉，无法回答。'
        aiChatMessages.value.push({ role: 'assistant', content: reply })
    } catch {
        aiChatMessages.value.push({ role: 'assistant', content: 'AI 服务暂时不可用。' })
    } finally { aiChatLoading.value = false }
}
async function doAiCreate() {
    if (!aiCreateTopic.value.trim()) { ElMessage.warning('请输入创作主题'); return }
    aiCreateLoading.value = true
    aiCreateResult.value = ''
    try {
        const styleMap = { general: '通用风格', professional: '专业正式风格', popular: '通俗易懂的风格', news: '新闻报导风格', story: '故事叙述风格' }
        const lengthMap = { short: '约500字', medium: '约1000字', long: '约2000字' }
        let prompt = `请以「${aiCreateTopic.value}」为主题，写一篇${styleMap[aiCreateStyle.value] || '通用风格'}的文章，字数${lengthMap[aiCreateLength.value] || '约1000字'}。`
        if (aiCreateExtra.value.trim()) prompt += `\n额外要求：${aiCreateExtra.value.trim()}`
        prompt += '\n请使用标题和段落组织内容。'
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiCreateResult.value = res.data?.data?.reply || '生成失败，请重试。'
        if (!form.value.title && aiCreateTopic.value) form.value.title = aiCreateTopic.value
    } catch (e) {
        aiCreateResult.value = 'AI 服务暂时不可用：' + (e.response?.data?.message || '请稍后重试')
    } finally { aiCreateLoading.value = false }
}
function insertAiCreate() {
    if (!aiCreateResult.value || !editor.value) return
    const html = aiCreateResult.value
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h2>$1</h2>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^\- (.+)$/gm, '<li>$1</li>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
    editor.value.commands.setContent('<p>' + html + '</p>')
    ElMessage.success('✅ AI 创作内容已插入编辑器')
    showAiCreate.value = false
}

// ─── 编辑器点击(链接气泡菜单) ───
function onEditorClick(event) {
    const target = event.target
    if (!editor.value) return
    
    // 检查点击的是否是链接
    if (target && target.tagName === 'A') {
        event.preventDefault()
        linkBubbleHref.value = target.getAttribute('href') || ''
        linkBubbleText.value = target.textContent || ''
        const rect = target.getBoundingClientRect()
        const editorRect = event.currentTarget?.getBoundingClientRect()
        if (editorRect) {
            linkBubblePos.value = {
                top: rect.bottom - editorRect.top + 4,
                left: rect.left - editorRect.left,
            }
        }
        showLinkBubble.value = true
        return
    }
    
    // 检查代码块右上角点击（📋复制）
    const pre = target?.closest?.('pre')
    if (pre) {
        const rect = pre.getBoundingClientRect()
        const x = event.clientX - rect.left
        const y = event.clientY - rect.top
        if (x > rect.width - 50 && y < 30) {
            const code = pre.textContent || ''
            navigator.clipboard.writeText(code).then(() => {
                ElMessage.success('✅ 代码已复制')
            }).catch(() => ElMessage.error('复制失败'))
            return
        }
    }
    
    // 点击其他区域隐藏气泡
    showLinkBubble.value = false
}

// ─── Ctrl+S 保存快捷键 ───
function handleEditorKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault()
        previewBeforePublish()
    }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault()
        previewVisible.value = true
    }
}

// ─── slug 自动生成 ───
watch(() => form.value.title, (title) => {
    if (!form.value.slug && title) {
        // 从标题生成简单 slug：取前 30 个字符，转小写，空格转中划线
        let slug = title.toLowerCase()
            .replace(/[^\w\u4e00-\u9fff\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .substring(0, 50)
        // 如果全是非 ASCII（如中文），用时间戳
        if (!/[a-z0-9]/.test(slug)) {
            slug = 'post-' + Date.now().toString(36)
        }
        form.value.slug = slug
    }
})

// ─── 预览并发布 ───
// ─── 自动保存草稿 ───
const DRAFT_KEY = 'blog_editor_draft'
let draftTimer = null

function saveDraft() {
    if (!form.value.title && !form.value.content) return
    const draft = {
        title: form.value.title,
        type: form.value.type,
        content: editor.value?.getHTML() || form.value.content,
        excerpt: form.value.excerpt,
        tags: form.value.tags,
        featured_image: form.value.featured_image,
        savedAt: new Date().toISOString(),
    }
    try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft))
        draftSaved.value = true
        if (draftSaveTimer) clearTimeout(draftSaveTimer)
        draftSaveTimer = setTimeout(() => { draftSaved.value = false }, 3000)
    } catch { /* ignore quota errors */ }
}

function restoreDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY)
        if (!raw) return
        const draft = JSON.parse(raw)
        if (!draft.title && !draft.content) return
        // 检查是否过期（超过 24 小时）
        const savedAt = new Date(draft.savedAt)
        if (Date.now() - savedAt.getTime() > 24 * 60 * 60 * 1000) {
            localStorage.removeItem(DRAFT_KEY)
            return
        }
        form.value.title = draft.title || ''
        form.value.type = draft.type || 'blog'
        form.value.content = draft.content || ''
        form.value.excerpt = draft.excerpt || ''
        form.value.tags = draft.tags || []
        form.value.featured_image = draft.featured_image || ''
        // 编辑器内容在挂载后设置
        setTimeout(() => {
            if (editor.value && form.value.content) {
                editor.value.commands.setContent(form.value.content)
            }
        }, 200)
        ElMessage.info('💾 已恢复上次未保存的草稿')
    } catch { /* ignore parse errors */ }
}

function clearDraft() {
    try {
        localStorage.removeItem(DRAFT_KEY)
    } catch {}
}

function previewBeforePublish() {
    if (editor.value) {
        form.value.content = editor.value.getHTML()
    }
    // 验证必填字段
    if (!form.value.title) { ElMessage.warning('请输入标题'); return }
    if (!form.value.content) { ElMessage.warning('请输入内容'); return }
    previewVisible.value = true
}

async function confirmPublish() {
    clearDraft()
    await submitForm()
    previewVisible.value = false
}

function insertLink() {
    linkEditMode.value = false
    linkText.value = ''
    linkUrl.value = ''
    showLinkDialog.value = true
}

function doInsertLink() {
    if (!linkUrl.value || !editor.value) return
    const selected = editor.value.state.selection.content().size > 0
    if (selected) {
        editor.value.chain().focus().setLink({ href: linkUrl.value }).run()
    } else {
        editor.value.chain().focus().insertContent(`<a href="${linkUrl.value}" target="_blank" rel="noopener noreferrer">${linkText.value || linkUrl.value}</a>`).run()
    }
    showLinkDialog.value = false
}

function editLink(href, text) {
    linkEditMode.value = true
    linkUrl.value = href
    linkText.value = text
    showLinkDialog.value = true
}

function removeLink() {
    if (!editor.value) return
    editor.value.chain().focus().unsetLink().run()
    showLinkBubble.value = false
}

// 监听对话框关闭，销毁编辑器释放资源
watch(dialogVisible, (val) => {
    if (val) {
        // 打开对话框时，启动自动保存定时器（每 30 秒）
        draftTimer = setInterval(saveDraft, 30000)
    } else {
        // 关闭对话框时，清除定时器
        if (draftTimer) {
            clearInterval(draftTimer)
            draftTimer = null
        }
        showSource.value = false
        previewVisible.value = false
        showLinkBubble.value = false
    }
})

// 监听编辑内容变化，初始化编辑器内容
watch(() => form.value.content, (newContent) => {
    if (editor.value && newContent && editor.value.isEmpty && form.value.content) {
        editor.value.commands.setContent(form.value.content)
    }
})

const tagOptions = ['产品更新', '功能发布', 'Bug修复', '安全更新', '性能优化', '新版本', '开发者', '技术博客', '使用技巧', '最佳实践']
const categories = ref([])
const showCategoryDialog = ref(false)
const showCategoryForm = ref(false)
const catForm = reactive({ name: '', slug: '', description: '', color: '#409eff', sort_order: 0 })
const editingCatId = ref(null)

async function loadCategories() {
    try {
        const { data: res } = await getCategories()
        categories.value = res.data || []
    } catch { categories.value = [] }
}

async function saveCategory() {
    if (!catForm.name) { ElMessage.warning('请输入分类名称'); return }
    try {
        if (editingCatId.value) {
            await updateCategory(editingCatId.value, catForm)
            ElMessage.success('分类已更新')
        } else {
            await createCategory(catForm)
            ElMessage.success('分类已创建')
        }
        showCategoryForm.value = false
        editingCatId.value = null
        await loadCategories()
    } catch { ElMessage.error('操作失败') }
}

function editCategory(cat) {
    editingCatId.value = cat.id
    Object.assign(catForm, { name: cat.name, slug: cat.slug, description: cat.description || '', color: cat.color || '#409eff', sort_order: cat.sort_order || 0 })
    showCategoryForm.value = true
}

async function removeCategory(cat) {
    try {
        await deleteCategory(cat.id)
        ElMessage.success('分类已删除')
        await loadCategories()
    } catch { ElMessage.error('删除失败') }
}

// ─── 批量操作 ───
const selectedIds = ref([])
const tableRef = ref(null)
const batchLoading = ref(false)
const showBatchCategoryDialog = ref(false)
const batchCategoryId = ref(null)
const exporting = ref(false)

function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id)
}

function clearSelection() {
    tableRef.value?.clearSelection()
    selectedIds.value = []
}

async function handleBatchPublish() {
    if (selectedIds.value.length === 0) return
    try {
        await ElMessageBox.confirm(`确定发布 ${selectedIds.value.length} 篇文章？`, '批量发布')
        batchLoading.value = true
        const { data: res } = await batchPublishPosts(selectedIds.value)
        ElMessage.success(res?.message || '发布成功')
        clearSelection()
        loadList()
        loadStats()
    } catch { /* cancelled */ }
    finally { batchLoading.value = false }
}

async function handleBatchDelete() {
    if (selectedIds.value.length === 0) return
    try {
        await ElMessageBox.confirm(`确定删除 ${selectedIds.value.length} 篇文章？此操作不可恢复。`, '批量删除', { type: 'warning' })
        batchLoading.value = true
        const { data: res } = await batchDeletePosts(selectedIds.value)
        ElMessage.success(res?.message || '删除成功')
        clearSelection()
        loadList()
        loadStats()
    } catch { /* cancelled */ }
    finally { batchLoading.value = false }
}

async function handleBatchCategory() {
    if (!batchCategoryId.value) { ElMessage.warning('请选择分类'); return }
    batchLoading.value = true
    try {
        const { data: res } = await batchCategoryPosts(selectedIds.value, batchCategoryId.value)
        ElMessage.success(res?.message || '分类切换成功')
        showBatchCategoryDialog.value = false
        clearSelection()
        loadList()
    } catch { ElMessage.error('操作失败') }
    finally { batchLoading.value = false }
}

async function handleExport() {
    exporting.value = true
    try {
        const res = await exportBlogCsv({ ...filters.value })
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = 'blog-export-' + new Date().toISOString().slice(0, 10) + '.csv'
        a.click()
        URL.revokeObjectURL(url)
        ElMessage.success('导出成功')
    } catch { ElMessage.error('导出失败') }
    finally { exporting.value = false }
}

const typeLabels = { blog: '博客', changelog: '更新日志', release_note: '发布说明' }
const typeTags = { blog: 'primary', changelog: 'success', release_note: 'warning' };
function typeLabel(t) { return typeLabels[t] || t }
function typeTag(t) { return typeTags[t] || '' }

async function loadStats() {
    try {
        const res = await getBlogStats()
        stats.value = res.data?.data || res.data || res
    } catch { /* ignore */ }
}

async function loadList() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value, ...filters.value }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await getBlogList(params)
        list.value = res.data?.data?.data || res.data?.data || res.data || []
        total.value = res.data?.data?.total || res.data?.total || res.total || 0
    } catch { ElMessage.error('加载失败') }
    finally { loading.value = false }
}

function showCreateDialog() {
    editingId.value = null
    form.value = { title: '', type: 'blog', category_id: null, content: '', slug: '', excerpt: '', featured_image: '', author: '', tags: [], version: '', is_published: false, is_featured: false }
    // 清空编辑器
    if (editor.value) {
        editor.value.commands.setContent('')
    }
    dialogVisible.value = true
    // 恢复自动保存的草稿
    setTimeout(() => restoreDraft(), 300)
}

async function showEditDialog(row) {
    editingId.value = row.id
    try {
        const res = await getBlogDetail(row.id)
        const data = res.data?.data || res.data || res
        form.value = {
            title: data.title,
            type: data.type,
            content: data.content || '',
            slug: data.slug || '',
            excerpt: data.excerpt || '',
            featured_image: data.featured_image || '',
            author: data.author || '',
            tags: data.tags || [],
            version: data.version || '',
            is_published: data.is_published,
            is_featured: data.is_featured,
        }
        dialogVisible.value = true
        // 编辑器内容在下一个 tick 设置，确保编辑器已挂载
        setTimeout(() => {
            if (editor.value && form.value.content) {
                editor.value.commands.setContent(form.value.content)
            }
        }, 100)
    } catch { ElMessage.error('加载详情失败') }
}

async function submitForm() {
    // 确保编辑器内容已同步到 form
    if (editor.value) {
        form.value.content = editor.value.getHTML()
    }
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
        if (editingId.value) {
            await updateBlog(editingId.value, form.value)
            ElMessage.success('保存成功')
        } else {
            await createBlog(form.value)
            ElMessage.success('发布成功')
        }
        dialogVisible.value = false
        previewVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally {
        submitting.value = false
    }
}

async function handleTogglePublish(row) {
    try {
        await togglePublish(row.id)
        ElMessage.success(row.is_published ? '已下架' : '已发布')
        loadList()
        loadStats()
    } catch { ElMessage.error('操作失败') }
}

async function handleToggleFeatured(row) {
    try {
        await toggleFeatured(row.id)
        ElMessage.success(row.is_featured ? '已取消精选' : '已设为精选')
        loadList()
    } catch { ElMessage.error('操作失败') }
}

async function handleDelete(row) {
    try {
        await deleteBlog(row.id)
        ElMessage.success('已删除')
        loadList()
        loadStats()
    } catch { ElMessage.error('删除失败') }
}

function copyRssUrls() {
    const urls = ['/api/rss/all', '/api/rss/blog', '/api/rss/changelog']
        .map(p => baseUrl.value + p)
        .join('\n')
    navigator.clipboard.writeText(urls)
    ElMessage.success('已复制RSS订阅地址')
}

onMounted(() => {
    loadStats()
    loadList()
    loadSubStats()
    loadCategories()
})

// 编辑器销毁清理
onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy()
    }
})

async function loadSubStats() {
    try {
        const { data } = await getSubscriptionStats()
        if (data?.data) subStats.value = data.data
    } catch { /* */ }
}
</script>

<style scoped>
.blog-manager-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; margin-bottom: 10px; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.rss-info .rss-header { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.rss-label { font-weight: bold; white-space: nowrap; }
.rss-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 1; }
.rss-url { font-size: 12px; background: #f5f7fa; padding: 2px 6px; border-radius: 3px; }
.search-card { margin-bottom: 16px; }
.table-card { margin-bottom: 20px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.title-cell { display: flex; align-items: center; gap: 8px; }
.type-badge { flex-shrink: 0; }

/* Tiptap 编辑器样式 */
.blog-editor-toolbar {
    width: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    padding: 4px 8px;
    background: #fafafa;
    border: 1px solid #dcdfe6;
    border-bottom: none;
    border-radius: 4px 4px 0 0;
    box-sizing: border-box;
}
/* 代码块语言栏 */
.code-lang-bar {
    display: flex;
    align-items: center;
    padding: 4px 12px;
    background: #f5f5f5;
    border: 1px solid #dcdfe6;
    border-bottom: none;
    border-top: none;
    flex-shrink: 0;
}
.code-lang-badge {
    font-size: 11px;
    background: #409eff;
    color: #fff;
    padding: 1px 8px;
    border-radius: 3px;
    margin-left: 6px;
    font-family: 'Fira Code', 'Consolas', monospace;
}

/* 表格操作栏 */
.table-toolbar {
    display: flex;
    align-items: center;
    padding: 4px 12px;
    background: #f0f7ff;
    border: 1px solid #dcdfe6;
    border-bottom: none;
    border-top: none;
    flex-shrink: 0;
    gap: 4px;
    flex-wrap: wrap;
}

.blog-editor-content {
    border: 1px solid #dcdfe6;
    border-top: none;
    border-radius: 0 0 4px 4px;
    background: #fff;
    display: flex;
    flex-direction: column;
    width: 100%;
}
.blog-prose-editor {
    flex: 1;
    min-height: 300px;
    padding: 0;
    outline: none;
    cursor: text;
    width: 100%;
}
.blog-editor-footer {
    padding: 6px 16px;
    font-size: 12px;
    color: #909399;
    border-top: 1px solid #f0f0f0;
    flex-shrink: 0;
}

/* 链接气泡菜单 */
.link-bubble {
    position: absolute;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    padding: 4px 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,.1);
    display: flex;
    align-items: center;
    gap: 4px;
    z-index: 100;
    white-space: nowrap;
    font-size: 12px;
}
.link-bubble-url {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #409eff;
    margin-right: 4px;
}
.blog-editor-content {
    position: relative;
}
.blog-prose-editor {
    min-height: 320px;
    padding: 0;
    outline: none;
    cursor: text;
}
.blog-prose-editor :deep(.ProseMirror) {
    min-height: 300px;
    padding: 16px 20px;
    outline: none;
}
.blog-prose-editor :deep(.ProseMirror p) {
    margin: 6px 0;
    line-height: 1.8;
    font-size: 15px;
}
.blog-prose-editor :deep(.ProseMirror h1) {
    font-size: 24px;
    font-weight: 700;
    margin: 16px 0 8px;
}
.blog-prose-editor :deep(.ProseMirror h2) {
    font-size: 20px;
    font-weight: 600;
    margin: 14px 0 6px;
}
.blog-prose-editor :deep(.ProseMirror h3) {
    font-size: 17px;
    font-weight: 600;
    margin: 12px 0 6px;
}
.blog-prose-editor :deep(.ProseMirror pre) {
    background: #2d2d2d;
    color: #ccc;
    padding: 16px 44px 16px 16px;
    border-radius: 6px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.5;
    position: relative;
}
.blog-prose-editor :deep(.ProseMirror pre)::after {
    content: '📋';
    position: absolute;
    top: 6px;
    right: 8px;
    font-size: 14px;
    cursor: pointer;
    opacity: 0;
    transition: opacity .2s;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255,255,255,.1);
    line-height: 1.4;
    pointer-events: none;
}
.blog-prose-editor :deep(.ProseMirror pre:hover)::after {
    opacity: .85;
}
.blog-prose-editor :deep(.ProseMirror code) {
    font-family: 'Fira Code', 'Consolas', monospace;
    font-size: 13px;
}
.blog-prose-editor :deep(.ProseMirror blockquote) {
    border-left: 4px solid #409eff;
    padding: 8px 16px;
    margin: 12px 0;
    background: #f5f7fa;
    border-radius: 0 4px 4px 0;
    color: #606266;
}
.blog-prose-editor :deep(.ProseMirror table) {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0;
}
.blog-prose-editor :deep(.ProseMirror th),
.blog-prose-editor :deep(.ProseMirror td) {
    border: 1px solid #dcdfe6;
    padding: 8px 12px;
    text-align: left;
    min-width: 60px;
}
.blog-prose-editor :deep(.ProseMirror th) {
    background: #f5f7fa;
    font-weight: 600;
}
.blog-prose-editor :deep(.ProseMirror .selectedCell) {
    background: #ecf5ff;
}
.blog-prose-editor :deep(.ProseMirror img) {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 12px 0;
}
.blog-prose-editor :deep(.ProseMirror ul),
.blog-prose-editor :deep(.ProseMirror ol) {
    padding-left: 24px;
    margin: 8px 0;
}
.blog-prose-editor :deep(.ProseMirror li) {
    margin: 4px 0;
    line-height: 1.7;
}
.blog-prose-editor :deep(.ProseMirror a) {
    color: #409eff;
    text-decoration: underline;
}
.blog-prose-editor :deep(.ProseMirror-gapcursor) {
    display: none;
}
.blog-prose-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: #adb5bd;
    pointer-events: none;
    height: 0;
}
.blog-prose-editor :deep(.is-empty)::before {
    color: #c0c4cc;
}

/* 搜索替换面板 */
.search-panel {
    padding: 8px 12px;
    border-bottom: 1px solid #e4e7ed;
    background: #fefce8;
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
    width: 100%;
    box-sizing: border-box;
}

.search-panel .search-row .el-input { width: auto !important; }
.search-row { display: flex; align-items: center; gap: 6px; }
.search-count { font-size: 12px; color: #409eff; font-weight: 600; min-width: 50px; }
.search-count.no-result { color: #f56c6c; }

/* 文章模板 */
.template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.template-card {
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all .2s;
    text-align: center;
}
.template-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,.15); }
.template-icon { font-size: 36px; margin-bottom: 8px; }
.template-name { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 4px; }
.template-desc { font-size: 12px; color: #909399; line-height: 1.4; }

/* Emoji */
.emoji-grid { display: flex; flex-wrap: wrap; gap: 4px; max-height: 320px; overflow-y: auto; }
.emoji-item {
    font-size: 24px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background .15s;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.emoji-item:hover { background: #f0f5ff; }

/* AI 工具 */
.ai-chat-msg { padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; }
.ai-chat-msg.user { background: #e6f0ff; }
.ai-chat-msg.assistant { background: #f0f0f0; }

/* 快捷键 */
.shortcut-list { display: flex; flex-direction: column; gap: 8px; }
.shortcut-item { display: flex; align-items: center; gap: 16px; padding: 6px 0; }
.shortcut-keys { display: flex; gap: 4px; min-width: 160px; }
.shortcut-keys kbd {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 12px;
    font-family: 'Fira Code', 'Consolas', monospace;
    box-shadow: 0 1px 0 #d0d5dd;
}
.shortcut-desc { font-size: 13px; color: #606266; }

/* 文章统计 */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
}
.stat-item {
    text-align: center;
    padding: 12px 8px;
    background: #f5f7fa;
    border-radius: 8px;
}
.stat-num {
    font-size: 24px;
    font-weight: 700;
    color: #303133;
}
.stat-label {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}

/* SEO 预览卡片 */
.seo-preview-card {
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    padding: 12px 16px;
    max-width: 600px;
}
.seo-url { font-size: 12px; color: #006621; line-height: 1.5; word-break: break-all; }
.seo-title { font-size: 18px; color: #1a0dab; line-height: 1.3; margin: 2px 0; cursor: pointer; }
.seo-title:hover { text-decoration: underline; }
.seo-desc { font-size: 13px; color: #545454; line-height: 1.5; word-break: break-all; }
.word-goal-progress { display: inline-flex; align-items: center; }
</style>
<style>
/* 对话框内边距（全局样式，因为 el-dialog 是 teleport 到 body 的） */
.blog-dialog .el-dialog__body { padding: 20px !important; }
/* 发布预览内容样式 */
.preview-content { font-size: 15px; line-height: 1.8; color: #303133; }
.preview-content h1 { font-size: 22px; margin: 20px 0 12px; }
.preview-content h2 { font-size: 18px; margin: 18px 0 10px; }
.preview-content h3 { font-size: 16px; margin: 16px 0 8px; }
.preview-content p { margin: 8px 0; }
.preview-content img { max-width: 100%; height: auto; border-radius: 4px; }
.preview-content blockquote { border-left: 4px solid #409eff; padding: 8px 16px; margin: 12px 0; background: #f5f7fa; border-radius: 0 4px 4px 0; color: #606266; }
.preview-content pre { background: #1e1e1e; color: #d4d4d4; padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
.preview-content code { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
.preview-content table { border-collapse: collapse; width: 100%; margin: 12px 0; }
.preview-content th, .preview-content td { border: 1px solid #dcdfe6; padding: 8px 12px; text-align: left; }
.preview-content th { background: #f5f7fa; font-weight: 600; }
.preview-content ul, .preview-content ol { padding-left: 24px; margin: 8px 0; }
.preview-content a { color: #409eff; text-decoration: none; }
.preview-content a:hover { text-decoration: underline; }
/* 代码高亮预览样式（VS Code Dark 主题） */
.preview-content pre { background: #1e1e1e; color: #d4d4d4; padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
.preview-content code { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
.preview-content .hljs-keyword { color: #c586c0; }
.preview-content .hljs-string { color: #ce9178; }
.preview-content .hljs-number { color: #b5cea8; }
.preview-content .hljs-comment { color: #6a9955; font-style: italic; }
.preview-content .hljs-built_in { color: #4ec9b0; }
.preview-content .hljs-literal { color: #569cd6; }
.preview-content .hljs-function { color: #dcdcaa; }
.preview-content .hljs-title { color: #dcdcaa; }
.preview-content .hljs-params { color: #9cdcfe; }
.preview-content .hljs-attr { color: #9cdcfe; }
.preview-content .hljs-attribute { color: #9cdcfe; }
.preview-content .hljs-type { color: #4ec9b0; }
.preview-content .hljs-meta { color: #d4d4d4; }
.preview-content .hljs-tag { color: #569cd6; }
.preview-content .hljs-name { color: #569cd6; }
.preview-content .hljs-selector-class { color: #d7ba7d; }
.preview-content .hljs-selector-id { color: #d7ba7d; }
.preview-content .hljs-selector-tag { color: #569cd6; }
.preview-content .hljs-regexp { color: #d16969; }
.preview-content .hljs-symbol { color: #569cd6; }
.preview-content .hljs-bullet { color: #d7ba7d; }
.preview-content .hljs-link { color: #569cd6; text-decoration: underline; }
.preview-content .hljs-deletion { color: #d16969; background: #470000; }
.preview-content .hljs-addition { color: #6a9955; background: #003100; }
</style>
