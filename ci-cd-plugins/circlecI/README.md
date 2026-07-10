# CircleCI Orb — HWT License

在 CircleCI 流水线中自动获取 HWT License。

## 使用方式

```yaml
version: 2.1

orbs:
  hwt: huwutong/hwt-license@1.0.0

jobs:
  build:
    docker:
      - image: cimg/base:stable
    steps:
      - checkout
      - hwt/fetch-license:
          token: ${HWT_CI_TOKEN}

workflows:
  main:
    jobs:
      - build
```

## 参数

### fetch-license

| 参数 | 必填 | 默认值 | 说明 |
|------|------|--------|------|
| `token` | 是 | — | CI/CD API Token |
| `api-url` | 否 | `https://api.huwutong.com` | API 地址 |

### activate-license

| 参数 | 必填 | 说明 |
|------|------|------|
| `token` | 是 | CI/CD API Token |
| `license-key` | 是 | License key |
| `api-url` | 否 | API 地址 |

## 输出

- `HWT_LICENSE_KEY` 环境变量