# 服务网格 Istio 配置

> M3-68: 面向大型企业级部署的服务网格

## 概览

使用 Istio 服务网格实现：
- **mTLS 加密通信** — 所有服务间通信自动加密
- **流量管理** — 金丝雀发布、灰度分流、熔断降级
- **可观测性** — 调用链追踪、指标收集、访问日志
- **安全策略** — 基于身份的授权控制

## 架构

```
                            ┌─────────────┐
                            │  Istio       │
                            │  Ingress     │
                            │  Gateway     │
                            └──────┬──────┘
         ┌─────────────────────────┼─────────────────────┐
         │          ┌──────────────┼──────────────┐      │
         ▼          ▼              ▼              ▼      ▼
    ┌────────┐ ┌────────┐  ┌──────────┐  ┌──────────┐
    │ hwt-api │ │ hwt-   │  │ hwt-     │  │ hwt-     │
    │(Laravel)│ │ admin  │  │ portal   │  │ reverb   │
    │  v1/v2  │ │ (Vue)  │  │ (Vue)    │  │ (WS)     │
    └────┬───┘ └────────┘  └──────────┘  └──────────┘
         │
    ┌────┴────┐ ┌──────────┐
    │ hwt-    │ │ hwt-     │
    │ mysql   │ │ redis    │
    └─────────┘ └──────────┘

    ── mTLS 加密 ──
    ══ Istio Sidecar Proxy ══
```

## 前置条件

```bash
# 1. 安装 Istio
istioctl install --set profile=default -y

# 2. 启用命名空间注入
kubectl label namespace huwutong istio-injection=enabled

# 3. 安装 Prometheus + Grafana + Jaeger (可选)
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.21/samples/addons/prometheus.yaml
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.21/samples/addons/grafana.yaml
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.21/samples/addons/jaeger.yaml
```

## 部署

```bash
# 部署所有 Istio 配置
kubectl apply -k deploy/istio/

# 单独部署
kubectl apply -f deploy/istio/gateway/
kubectl apply -f deploy/istio/security/
kubectl apply -f deploy/istio/traffic/
kubectl apply -f deploy/istio/observability/
```

## 金丝雀发布

```bash
# 部署 canary 版本
kubectl set image deployment/hwt-api-canary hwt-api=huwutong/hwt-api:canary

# 测试金丝雀
curl -H "x-canary: enabled" https://admin.huwutong.com

# 全量发布
kubectl set image deployment/hwt-api hwt-api=huwutong/hwt-api:new-version
```

## 监控

```bash
# 查看服务拓扑
istioctl dashboard kiali

# 查看指标
istioctl dashboard grafana

# 查看追踪
istioctl dashboard jaeger

# 查看 Envoy 状态
istioctl proxy-status
istioctl proxy-config clusters -n huwutong deploy/hwt-api
```

## TLS 证书

证书通过 Kubernetes Secret 管理：

```bash
# 使用 cert-manager 自动签发
kubectl create secret tls admin-tls-cert \
  --cert=tls.crt --key=tls.key
```

## 混沌测试

故障注入已预先配置（默认关闭），可通过调整 `percentage.value` 启用：

```yaml
# traffic/03-traffic-management.yaml
fault:
  abort:
    percentage:
      value: 0.1   # 0.1% 请求返回 500
  delay:
    percentage:
      value: 0.5   # 0.5% 请求延迟 3 秒
```

## 文件结构

```
deploy/istio/
├── 01-service-accounts.yaml      # K8s 服务账户
├── 02-sidecar-injection.yaml     # Sidecar 注入配置
├── kustomization.yaml            # Kustomize 部署清单
├── gateway/
│   └── 01-ingress-gateway.yaml   # 入口网关 (5个域名)
├── traffic/
│   ├── 01-virtual-services.yaml  # 路由规则 (5个服务)
│   ├── 02-destination-rules.yaml # 目标规则 (熔断/连接池)
│   └── 03-traffic-management.yaml # 限流/金丝雀/故障注入
├── security/
│   └── 01-mtls-authz.yaml        # mTLS + 授权策略
└── observability/
    └── 01-telemetry.yaml         # 指标/日志/追踪
```
