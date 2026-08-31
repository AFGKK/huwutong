<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SdkDocsController extends Controller
{
    public function show(string $lang): View|RedirectResponse
    {
        $canonical = $this->resolveLang($lang);
        if ($canonical === null) {
            throw new NotFoundHttpException();
        }

        if ($canonical !== strtolower($lang)) {
            return redirect()->route('sdk.docs', ['lang' => $canonical], 301);
        }

        $sdks = config('sdk-docs.sdks', []);
        $sdk = $sdks[$canonical] ?? null;
        if (! is_array($sdk)) {
            throw new NotFoundHttpException();
        }

        return view('public.sdk-doc', [
            'sdk' => $sdk,
            'allSdks' => $sdks,
        ]);
    }

    protected function resolveLang(string $lang): ?string
    {
        $key = strtolower(trim($lang));
        $aliases = config('sdk-docs.aliases', []);

        return $aliases[$key] ?? null;
    }
}
