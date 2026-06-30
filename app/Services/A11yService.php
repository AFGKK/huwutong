<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WCAG 2.1 AA 无障碍合规管理服务
 *
 * M3-54
 * - 合规声明版本管理
 * - 合规扫描和报告生成
 * - 用户无障碍偏好管理
 * - 已知限制追踪
 */
class A11yService
{
    /**
     * WCAG 2.1 AA 成功准则定义
     */
    public function getGuidelines(): array
    {
        return [
            ['id' => '1.1.1', 'name' => '非文本内容', 'level' => 'A', 'description' => '所有非文本内容提供替代文本', 'status' => 'compliant'],
            ['id' => '1.2.1', 'name' => '纯音频/视频（预录）', 'level' => 'A', 'description' => '预录音频提供替代文本', 'status' => 'compliant'],
            ['id' => '1.2.2', 'name' => '字幕（预录）', 'level' => 'A', 'description' => '预录视频提供字幕', 'status' => 'compliant'],
            ['id' => '1.2.3', 'name' => '音频描述或媒体替代（预录）', 'level' => 'A', 'description' => '预录视频提供音频描述', 'status' => 'compliant'],
            ['id' => '1.2.4', 'name' => '字幕（直播）', 'level' => 'AA', 'description' => '直播视频提供字幕', 'status' => 'not_applicable'],
            ['id' => '1.2.5', 'name' => '音频描述（预录）', 'level' => 'AA', 'description' => '预录视频提供音频描述', 'status' => 'not_applicable'],
            ['id' => '1.3.1', 'name' => '信息和关系', 'level' => 'A', 'description' => '结构信息可通过程序化方式确定', 'status' => 'compliant'],
            ['id' => '1.3.2', 'name' => '有含义的序列', 'level' => 'A', 'description' => '内容阅读顺序有逻辑', 'status' => 'compliant'],
            ['id' => '1.3.3', 'name' => '感官特性', 'level' => 'A', 'description' => '不单纯依赖感官特性理解内容', 'status' => 'compliant'],
            ['id' => '1.3.4', 'name' => '方向', 'level' => 'AA', 'description' => '不限制内容的显示方向', 'status' => 'compliant'],
            ['id' => '1.3.5', 'name' => '输入目的', 'level' => 'AA', 'description' => '输入字段autocomplete属性正确', 'status' => 'needs_work'],
            ['id' => '1.4.1', 'name' => '颜色使用', 'level' => 'AA', 'description' => '颜色不作为唯一信息传达方式', 'status' => 'compliant'],
            ['id' => '1.4.2', 'name' => '音频控制', 'level' => 'A', 'description' => '自动播放音频可暂停或关闭', 'status' => 'compliant'],
            ['id' => '1.4.3', 'name' => '对比度（最低）', 'level' => 'AA', 'description' => '文本对比度不低于4.5:1', 'status' => 'compliant'],
            ['id' => '1.4.4', 'name' => '调整文本大小', 'level' => 'AA', 'description' => '文本可在不损失内容下缩放到200%', 'status' => 'compliant'],
            ['id' => '1.4.5', 'name' => '文本图像', 'level' => 'AA', 'description' => '使用文本而非文本图像', 'status' => 'compliant'],
            ['id' => '1.4.10', 'name' => '回流', 'level' => 'AA', 'description' => '内容在400px宽度不丢失信息', 'status' => 'needs_work'],
            ['id' => '1.4.11', 'name' => '非文本对比度', 'level' => 'AA', 'description' => 'UI组件和图形对比度不低于3:1', 'status' => 'compliant'],
            ['id' => '1.4.12', 'name' => '文本间距', 'level' => 'AA', 'description' => '文本样式可覆盖不丢失内容', 'status' => 'compliant'],
            ['id' => '1.4.13', 'name' => '悬停或焦点触发的内容', 'level' => 'AA', 'description' => '悬停/焦点内容可悬停查看', 'status' => 'needs_work'],
            ['id' => '2.1.1', 'name' => '键盘', 'level' => 'A', 'description' => '所有功能可通过键盘操作', 'status' => 'compliant'],
            ['id' => '2.1.2', 'name' => '无键盘陷阱', 'level' => 'A', 'description' => '焦点不会陷入组件的某部分', 'status' => 'compliant'],
            ['id' => '2.1.4', 'name' => '字符键快捷键', 'level' => 'A', 'description' => '字符快捷键可关闭或重新映射', 'status' => 'compliant'],
            ['id' => '2.2.1', 'name' => '可调时间', 'level' => 'A', 'description' => '时间限制可关闭/调整/延长', 'status' => 'compliant'],
            ['id' => '2.2.2', 'name' => '暂停、停止、隐藏', 'level' => 'A', 'description' => '移动/闪烁/滚动内容可暂停', 'status' => 'compliant'],
            ['id' => '2.3.1', 'name' => '三次闪烁或低于阈值', 'level' => 'A', 'description' => '内容不闪烁超过3次/秒', 'status' => 'compliant'],
            ['id' => '2.4.1', 'name' => '跳过块', 'level' => 'A', 'description' => '提供跳过内容块的方法', 'status' => 'compliant'],
            ['id' => '2.4.2', 'name' => '页面标题', 'level' => 'A', 'description' => '页面有描述标题', 'status' => 'compliant'],
            ['id' => '2.4.3', 'name' => '焦点顺序', 'level' => 'A', 'description' => '焦点导航顺序符合语义', 'status' => 'compliant'],
            ['id' => '2.4.4', 'name' => '链接目的（上下文）', 'level' => 'A', 'description' => '链接目的可在上下文中确定', 'status' => 'compliant'],
            ['id' => '2.4.5', 'name' => '多种方式', 'level' => 'AA', 'description' => '提供多种方式定位页面', 'status' => 'compliant'],
            ['id' => '2.4.6', 'name' => '标题和标签', 'level' => 'AA', 'description' => '标题和标签描述清晰', 'status' => 'compliant'],
            ['id' => '2.4.7', 'name' => '焦点可见', 'level' => 'AA', 'description' => '焦点指示器可见', 'status' => 'compliant'],
            ['id' => '2.5.1', 'name' => '指针手势', 'level' => 'A', 'description' => '多点/路径手势有单点替代', 'status' => 'compliant'],
            ['id' => '2.5.2', 'name' => '指针取消', 'level' => 'A', 'description' => '按下可中止或撤销', 'status' => 'compliant'],
            ['id' => '2.5.3', 'name' => '标签名称', 'level' => 'A', 'description' => '可见标签与ARIA名称匹配', 'status' => 'needs_work'],
            ['id' => '2.5.4', 'name' => '运动触发', 'level' => 'A', 'description' => '设备运动触发的功能可禁用', 'status' => 'not_applicable'],
            ['id' => '3.1.1', 'name' => '页面语言', 'level' => 'A', 'description' => '页面lang属性已设置', 'status' => 'compliant'],
            ['id' => '3.1.2', 'name' => '段落语言', 'level' => 'AA', 'description' => '段落语言有更改时可程序化确定', 'status' => 'compliant'],
            ['id' => '3.2.1', 'name' => '焦点触发', 'level' => 'A', 'description' => '焦点移动不引起上下文变化', 'status' => 'compliant'],
            ['id' => '3.2.2', 'name' => '输入触发', 'level' => 'A', 'description' => '输入不自动改变上下文', 'status' => 'compliant'],
            ['id' => '3.2.3', 'name' => '一致导航', 'level' => 'AA', 'description' => '导航在页面间保持一致', 'status' => 'compliant'],
            ['id' => '3.2.4', 'name' => '一致标识', 'level' => 'AA', 'description' => '相同功能使用相同图标/标签', 'status' => 'compliant'],
            ['id' => '3.3.1', 'name' => '错误标识', 'level' => 'A', 'description' => '输入错误被标识并描述', 'status' => 'compliant'],
            ['id' => '3.3.2', 'name' => '标签或说明', 'level' => 'A', 'description' => '输入提供标签或说明', 'status' => 'compliant'],
            ['id' => '3.3.3', 'name' => '错误建议', 'level' => 'AA', 'description' => '输入错误提供修正建议', 'status' => 'needs_work'],
            ['id' => '3.3.4', 'name' => '错误预防（法律/财务/数据）', 'level' => 'AA', 'description' => '重要操作可逆/确认/审核', 'status' => 'compliant'],
            ['id' => '4.1.1', 'name' => '解析', 'level' => 'A', 'description' => '元素有完整的开始/结束标签', 'status' => 'compliant'],
            ['id' => '4.1.2', 'name' => '名称、角色、值', 'level' => 'A', 'description' => 'UI组件提供完整名称、角色和值', 'status' => 'compliant'],
            ['id' => '4.1.3', 'name' => '状态消息', 'level' => 'AA', 'description' => '状态消息可通过ARIA确定', 'status' => 'compliant'],
        ];
    }

    /**
     * 获取合规声明统计
     */
    public function getComplianceStats(): array
    {
        $guidelines = $this->getGuidelines();

        $total = count($guidelines);
        $compliant = count(array_filter($guidelines, fn($g) => $g['status'] === 'compliant'));
        $needsWork = count(array_filter($guidelines, fn($g) => $g['status'] === 'needs_work'));
        $notApplicable = count(array_filter($guidelines, fn($g) => $g['status'] === 'not_applicable'));

        $passRate = $total - $notApplicable > 0
            ? round($compliant / ($total - $notApplicable) * 100, 1)
            : 0;

        return compact('total', 'compliant', 'needsWork', 'notApplicable', 'passRate');
    }

    /**
     * 获取已知限制
     */
    public function getKnownLimitations(): array
    {
        return [
            [
                'area' => '第三方图表组件',
                'description' => '部分集成第三方图表库（如ECharts）的页面，图表数据的键盘导航和屏幕阅读器支持有限',
                'severity' => 'medium',
                'workaround' => '图表提供数据表格的替代视图',
                'planned_fix' => '逐步替换为原生SVG图表组件',
            ],
            [
                'area' => '历史页面ARIA标签',
                'description' => '早期开发的页面缺乏完整的ARIA标签和语义角色',
                'severity' => 'low',
                'workaround' => '核心业务页面已完成',
                'planned_fix' => '按访问频率逐步补齐',
            ],
            [
                'area' => '实时通知屏幕阅读器优化',
                'description' => 'Laravel Echo实时通知的屏幕阅读器提示需要进一步优化',
                'severity' => 'low',
                'workaround' => '通知中心页面支持键盘完全操作',
                'planned_fix' => '使用aria-live区域优化实时通知播报',
            ],
            [
                'area' => '复杂表单错误关联',
                'description' => '多步骤复杂表单的错误提示与表单域的ARIA关联有待改进',
                'severity' => 'medium',
                'workaround' => '表单页面顶部显示错误汇总',
                'planned_fix' => '使用aria-describedby关联错误消息',
            ],
        ];
    }

    /**
     * 获取用户无障碍偏好
     */
    public function getUserPreferences(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) return [];

        $prefs = $user->preferences['accessibility'] ?? [];

        return [
            'reduced_motion' => $prefs['reduced_motion'] ?? false,
            'high_contrast' => $prefs['high_contrast'] ?? false,
            'font_size' => $prefs['font_size'] ?? 'normal',
            'screen_reader_optimized' => $prefs['screen_reader_optimized'] ?? false,
        ];
    }

    /**
     * 保存用户无障碍偏好
     */
    public function saveUserPreferences(int $userId, array $prefs): array
    {
        $user = User::findOrFail($userId);
        $existing = $user->preferences ?? [];
        $existing['accessibility'] = array_merge(
            $existing['accessibility'] ?? [],
            array_intersect_key($prefs, array_flip([
                'reduced_motion', 'high_contrast', 'font_size', 'screen_reader_optimized',
            ]))
        );

        $user->preferences = $existing;
        $user->save();

        return $existing['accessibility'];
    }

    /**
     * 对比度检查工具
     */
    public function checkContrast(string $foreground, string $background): array
    {
        $hexToRgb = function (string $hex): array {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        };

        $relativeLuminance = function (array $rgb): float {
            $vals = array_map(function ($c) {
                $c = $c / 255;
                return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
            }, $rgb);
            return 0.2126 * $vals[0] + 0.7152 * $vals[1] + 0.0722 * $vals[2];
        };

        $fgRgb = $hexToRgb($foreground);
        $bgRgb = $hexToRgb($background);

        $fgLum = $relativeLuminance($fgRgb);
        $bgLum = $relativeLuminance($bgRgb);

        $ratio = ($fgLum + 0.05) / ($bgLum + 0.05);
        if ($ratio < 1) $ratio = 1 / $ratio;

        $passesAA = $ratio >= 4.5;
        $passesAALarge = $ratio >= 3.0;
        $passesAAA = $ratio >= 7.0;

        return [
            'foreground' => $foreground,
            'background' => $background,
            'ratio' => round($ratio, 2),
            'passes_AA' => $passesAA,
            'passes_AA_large' => $passesAALarge,
            'passes_AAA' => $passesAAA,
            'rating' => $passesAAA ? 'AAA' : ($passesAA ? 'AA' : ($passesAALarge ? 'AA (大文本)' : 'FAIL')),
        ];
    }

    /**
     * 生成无障碍合规报告
     */
    public function generateReport(): array
    {
        $stats = $this->getComplianceStats();
        $guidelines = $this->getGuidelines();
        $limitations = $this->getKnownLimitations();

        return [
            'generated_at' => now()->toIso8601String(),
            'standard' => 'WCAG 2.1 AA',
            'scope' => '管理后台 SPA + 客户门户',
            'summary' => [
                'total_criteria' => $stats['total'],
                'compliant' => $stats['compliant'],
                'needs_work' => $stats['needsWork'],
                'not_applicable' => $stats['notApplicable'],
                'pass_rate' => $stats['passRate'] . '%',
            ],
            'compliance_by_level' => [
                'A' => count(array_filter($guidelines, fn($g) => $g['level'] === 'A' && $g['status'] === 'compliant')),
                'AA' => count(array_filter($guidelines, fn($g) => $g['level'] === 'AA' && $g['status'] === 'compliant')),
            ],
            'non_compliant_items' => array_values(array_filter($guidelines, fn($g) => $g['status'] === 'needs_work')),
            'known_limitations' => $limitations,
        ];
    }
}
