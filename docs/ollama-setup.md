# Ollama 部署与模型拉取（D-37）

## 环境变量

```env
LOCAL_LLM_ENABLED=true
OLLAMA_API_BASE=http://127.0.0.1:11434
OLLAMA_DEFAULT_MODEL=qwen2.5:7b
```

## 启动方式

### 方式 A：Docker（Linux / macOS / Windows + Docker Desktop）

```bash
bash deploy/llm/setup.sh ollama
# 或
powershell -ExecutionPolicy Bypass -File deploy/llm/setup.ps1 ollama
```

容器名：`hwt-ollama`，API 端口 `11434`，可选 WebUI `3000`。

### 方式 B：Windows 原生安装

1. 下载安装 [Ollama for Windows](https://ollama.com/download)
2. 启动：

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-ollama.ps1
```

### 方式 C：Linux/macOS 原生

```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama serve
```

## 健康检查

```bash
curl http://127.0.0.1:11434/api/tags
```

或使用 Artisan：

```bash
php artisan ollama:setup --status
```

期望返回 `status: available` 且 `models` 数组非空（拉取模型后）。

## 拉取推荐模型

```bash
# 推荐三件套（中文 7B + 轻量 1.5B + RAG 嵌入）
bash deploy/llm/setup.sh models

# 或 Artisan（调用 Ollama HTTP API）
php artisan ollama:setup --pull

# 单个模型
php artisan ollama:setup --pull --model=qwen2.5:7b
ollama pull qwen2.5:7b
docker exec hwt-ollama ollama pull qwen2.5:7b
```

| 模型 | 用途 |
|------|------|
| `qwen2.5:7b` | 默认对话（中文优秀） |
| `qwen2.5:1.5b` | CPU/低内存环境 |
| `nomic-embed-text` | RAG 向量嵌入 |

## 管理后台

- **LLM 管理** → Provider 添加 `ollama`，API Base = `http://127.0.0.1:11434`
- **本地 LLM** → `/api/admin/local-llm/status` 查看实例与模型列表

## 验收（D-37）

1. `GET http://127.0.0.1:11434/api/tags` 返回 200
2. `php artisan ollama:setup --status` 显示 `available` 且模型数 > 0
3. `.env` 中 `OLLAMA_DEFAULT_MODEL` 与已拉取模型一致

## 业务默认路由（D-38）

启用本地 LLM 后，站点默认 Provider 为 `ollama`：

```env
LOCAL_LLM_ENABLED=true
LLM_DEFAULT_PROVIDER=ollama
OLLAMA_DEFAULT_MODEL=qwen2.5:7b
```

执行种子（或后台 **站点设置 → AI → 默认 AI 提供商** 选 `ollama`）：

```bash
php artisan db:seed --class=LlmProviderSeeder
php artisan db:seed --class=SiteSettingsSeeder
```

### 路由行为

| 场景 | 行为 |
|------|------|
| AI 智能客服（RAG / 流式） | 未指定 provider 时走 `ollama` |
| IM AI 好友 | 未自定义 `api_base_url` 时走 `ollama` |
| `/api/llm/chat` | 站点默认 provider，Ollama 熔断后降级 DeepSeek |
| Ollama 全不可用 | 静态兜底文案（关键词匹配） |

### 联调步骤

1. 确认 D-37：`php artisan ollama:setup --status` → `available`
2. 后台 **LLM 管理** 确认 `ollama` Provider 已启用且排序最前
3. 后台 **站点设置** → `llm_default_provider` = `ollama`
4. 调用对话 API 或打开 AI 客服，回复应来自本地模型
5. 停止 Ollama 后再次对话，应自动降级到 DeepSeek 或兜底文案

### 验收（D-38）

1. `llm_default_provider` 默认为 `ollama`（`LOCAL_LLM_ENABLED=true`）
2. AI 客服 / IM Bot 对话请求命中 `127.0.0.1:11434/api/generate`
3. 模型不存在或 Ollama 宕机时，降级链生效

## 故障排查

| 现象 | 处理 |
|------|------|
| 连接拒绝 | 确认 Ollama 进程/容器已启动 |
| Docker GPU 报错 | 使用 CPU 版 compose（已默认去掉 GPU 预留） |
| 拉取超时 | 网络问题；可换 `qwen2.5:1.5b` 小模型 |
| 后台连接失败 | 检查 `OLLAMA_API_BASE` 与防火墙 |

## 相关任务

- **D-38**：业务默认路由联调（站点设置默认 ollama）
- **T-23**：Feature 测试 health / chat 降级
