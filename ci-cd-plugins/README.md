# 🔧 CI/CD 插件生态

本目录包含主流 CI/CD 平台的现成插件，用于在流水线中自动获取/激活 HWT License。

| 平台 | 位置 | 说明 |
|------|------|------|
| **GitHub Actions** | `github-action/` | 复合 Action，支持 fetch / activate |
| **GitLab CI** | `gitlab-ci/` | 模板，使用 curl + API |
| **Jenkins** | `jenkins/` | Groovy Pipeline 库 |
| **CircleCI** | `circleci/` | Orb 命令 |

## 快速开始

### 1. 创建 CI/CD Token

```bash
# 在管理后台生成 token
curl -X POST https://your-domain.com/api/admin/ci-cd/tokens \
  -H "Authorization: Bearer <admin_token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "my-ci-token", "allowed_actions": ["fetch","activate"]}'
```

### 2. 选择插件

参见各平台子目录的 README。
