# Huwutong Flutter SDK

M2-21: 基于统一错误码标准 M2-34

## 安装

```yaml
# pubspec.yaml
dependencies:
  huwutong_sdk:
    path: ./sdk/flutter
```

## 快速开始

```dart
import 'package:huwutong_sdk/huwutong_sdk.dart';

final client = HwtClient(
  apiKey: 'your_api_key',
  host: 'https://api.huwutong.com',
);

void main() async {
  // 激活
  final activation = await client.activate('LICENSE-KEY', {
    'machine_id': 'unique-machine-id',
  });
  print(activation.success);

  // 验证
  final validation = await client.validate('LICENSE-KEY', {
    'machine_id': 'unique-machine-id',
  });
  print(validation.isValid);
}
```
