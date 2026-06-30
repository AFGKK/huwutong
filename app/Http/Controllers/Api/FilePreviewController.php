<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FilePreviewController extends Controller
{
    /**
     * 获取文件预览信息
     */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['url' => 'required|string|max:1000']);

        $url = $request->input('url');
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        $previewable = false;
        $previewType = null;
        $previewUrl = null;
        $fallbackUrl = $url;

        // 可预览的文件类型
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $pdfExts = ['pdf'];
        $officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
        $textExts = ['txt', 'csv', 'json', 'xml', 'md', 'log', 'yaml', 'yml'];

        if (in_array($ext, $imageExts)) {
            $previewable = true;
            $previewType = 'image';
            $previewUrl = $url;
        } elseif (in_array($ext, $pdfExts)) {
            $previewable = true;
            $previewType = 'pdf';
            $previewUrl = $url;
        } elseif (in_array($ext, $officeExts)) {
            $previewable = true;
            $previewType = 'office';
            // 使用 Microsoft Office Online / Google Docs Viewer
            $encodedUrl = urlencode($url);
            $previewUrl = "https://view.officeapps.live.com/op/view.aspx?src={$encodedUrl}";
        } elseif (in_array($ext, $textExts)) {
            $previewable = true;
            $previewType = 'text';
            $previewUrl = $url;
        }

        return ApiResponse::success([
            'previewable' => $previewable,
            'preview_type' => $previewType,
            'preview_url' => $previewUrl,
            'fallback_url' => $fallbackUrl,
            'extension' => $ext,
        ]);
    }
}
