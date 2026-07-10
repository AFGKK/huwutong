# Jenkins — HWT License Pipeline

在 Jenkins Pipeline 中自动获取 HWT License。

## 使用方式

```groovy
// Jenkinsfile
library identifier: 'hwt-license@main', retriever: modernSCM(
  [$class: 'GitSCMSource', remote: 'https://github.com/huwutong/hwt-jenkins-plugin.git']
)

pipeline {
  agent any
  environment {
    HWT_CI_TOKEN = credentials('hwt-ci-token')
  }
  stages {
    stage('License') {
      steps {
        script {
          hwtFetchLicense(token: env.HWT_CI_TOKEN)
        }
      }
    }
    stage('Build') {
      steps {
        echo "Using license: ${env.HWT_LICENSE_KEY}"
      }
    }
    stage('Activate') {
      steps {
        script {
          hwtActivateLicense(token: env.HWT_CI_TOKEN, licenseKey: env.HWT_LICENSE_KEY)
        }
      }
    }
  }
}
```

## Pipeline DSL

| 步骤 | 参数 | 说明 |
|------|------|------|
| `hwtFetchLicense` | `token` | 获取 License |
| `hwtActivateLicense` | `token`, `licenseKey` | 激活 License |