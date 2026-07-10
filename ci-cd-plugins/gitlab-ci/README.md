# GitLab CI — HWT License Template

在 GitLab CI 流水线中自动获取 HWT License。

## 使用方式

```yaml
# .gitlab-ci.yml
include:
  - local: ci-cd-plugins/gitlab-ci/hwt-license.yml

variables:
  HWT_CI_TOKEN: $HWT_CI_TOKEN   # 在 CI/CD Variables 中设置

stages:
  - license
  - build

fetch-license:
  extends: .hwt_fetch_license
  stage: license

build:
  stage: build
  script:
    - echo "Using license: $HWT_LICENSE_KEY"
  needs: ["fetch-license"]
```

## 环境变量

| 变量 | 说明 |
|------|------|
| `HWT_CI_TOKEN` | CI/CD API Token（必填） |
| `HWT_API_URL` | API 地址（可选，默认 `https://api.huwutong.com`） |

## 输出

- `HWT_LICENSE_KEY` — 获取到的 License key