<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.sdk_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.sdk_page.meta_desc') }}">
    <meta property="og:title" content="{{ __('app.sdk_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.sdk_page.subtitle') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/sdk') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>
        .sdk-card { transition: all 0.25s ease; }
        .sdk-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(var(--pg-primary-rgb), 0.18); }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.sdk_page.title'),
        'heroSubtitle' => __('app.sdk_page.subtitle'),
        'heroCrumb' => __('app.nav.sdk'),
    ])

    <section class="py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach([
                    ['lang' => 'PHP', 'title' => 'PHP SDK', 'desc' => 'Laravel / Symfony / ThinkPHP', 'cmd' => 'composer require huwutong/sdk', 'q' => 'PHP SDK'],
                    ['lang' => 'JS', 'title' => 'Node.js SDK', 'desc' => 'Express / Koa / Next.js', 'cmd' => 'npm install huwutong-sdk', 'q' => 'Node.js SDK'],
                    ['lang' => 'Py', 'title' => 'Python SDK', 'desc' => 'Django / Flask / FastAPI', 'cmd' => 'pip install huwutong-sdk', 'q' => 'Python SDK'],
                    ['lang' => 'Go', 'title' => 'Go SDK', 'desc' => 'Gin / Echo / Fiber', 'cmd' => 'go get huwutong.com/sdk', 'q' => 'Go SDK'],
                    ['lang' => 'Java', 'title' => 'Java SDK', 'desc' => 'Spring Boot / Micronaut', 'cmd' => '// Maven: com.huwutong:sdk:1.0', 'q' => 'Java SDK'],
                    ['lang' => 'C#', 'title' => 'C# SDK', 'desc' => '.NET Core / ASP.NET', 'cmd' => 'Install-Package HWT.Sdk', 'q' => 'C# SDK'],
                ] as $sdk)
                <div class="sdk-card bg-white rounded-2xl border border-slate-200 p-6">
                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mb-4">
                        <span class="text-lg font-bold text-slate-800">{{ $sdk['lang'] }}</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $sdk['title'] }}</h3>
                    <p class="text-sm text-slate-500 mb-4">{{ $sdk['desc'] }}</p>
                    <div class="bg-slate-50 rounded-lg p-3 mb-4 border border-slate-100"><code class="text-sm text-slate-800">{{ $sdk['cmd'] }}</code></div>
                    <a href="/help?search={{ urlencode($sdk['q']) }}" class="text-slate-800 font-medium hover:text-slate-950 transition">{{ __('app.sdk_page.docs') }}</a>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 md:py-20 bg-slate-50 border-t border-slate-100" id="examples">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-6 tracking-tight">{{ __('app.sdk_page.examples') }}</h2>
            <div class="flex flex-wrap justify-center gap-2 mb-8">
                <button class="code-tab px-4 py-2 text-sm font-medium rounded-lg bg-slate-900 text-white transition" data-lang="php" onclick="switchCode('php')">PHP</button>
                <button class="code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition" data-lang="node" onclick="switchCode('node')">Node.js</button>
                <button class="code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition" data-lang="python" onclick="switchCode('python')">Python</button>
                <button class="code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition" data-lang="go" onclick="switchCode('go')">Go</button>
                <button class="code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition" data-lang="java" onclick="switchCode('java')">Java</button>
                <button class="code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition" data-lang="csharp" onclick="switchCode('csharp')">C#</button>
            </div>
            <div class="bg-slate-900 rounded-2xl p-6 md:p-8 shadow-xl border border-slate-800">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-2.5 h-2.5 bg-rose-400 rounded-full"></span>
                    <span class="w-2.5 h-2.5 bg-amber-400 rounded-full"></span>
                    <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full"></span>
                    <span id="code-lang-label" class="text-slate-400 text-sm ml-2">PHP</span>
                </div>
                <pre class="text-sm text-slate-200 font-mono leading-relaxed overflow-x-auto"><code id="code-snippet">require 'vendor/autoload.php';

$client = new HWT\Client([
    'api_key' => 'your_api_key',
]);

$result = $client->validate('HWT-ENT-XXXX-XXXX');
echo $result->isValid() ? "License valid" : "License invalid";</code></pre>
            </div>
        </div>
    </section>
    <script>
    const CODES = {
        php: "require 'vendor/autoload.php';\n\n$client = new HWT\\Client([\n    'api_key' => 'your_api_key',\n]);\n\n$result = $client->validate('HWT-ENT-XXXX-XXXX');\necho $result->isValid() ? \"License valid\" : \"License invalid\";",
        node: "const HWT = require('huwutong-sdk');\n\nconst client = new HWT.Client({\n    apiKey: 'your_api_key',\n});\n\nconst result = await client.validate('HWT-ENT-XXXX-XXXX');\nconsole.log(result.isValid ? 'License valid' : 'License invalid');",
        python: "from huwutong import Client\n\nclient = Client(api_key='your_api_key')\nresult = client.validate('HWT-ENT-XXXX-XXXX')\nprint('License valid' if result.is_valid else 'License invalid')",
        go: "package main\n\nimport \"github.com/huwutong/sdk-go\"\n\nfunc main() {\n    client := hwt.NewClient(\"your_api_key\")\n    result := client.Validate(\"HWT-ENT-XXXX-XXXX\")\n    if result.IsValid() {\n        fmt.Println(\"License valid\")\n    }\n}",
        java: "import com.huwutong.HWTClient;\n\nHWTClient client = new HWTClient.Builder()\n    .apiKey(\"your_api_key\")\n    .build();\n\nValidationResult result = client.validate(\"HWT-ENT-XXXX-XXXX\");\nSystem.out.println(result.isValid() ? \"License valid\" : \"License invalid\");",
        csharp: "using Huwutong.Sdk;\n\nvar client = new HWTClient(\"your_api_key\");\nvar result = client.Validate(\"HWT-ENT-XXXX-XXXX\");\nConsole.WriteLine(result.IsValid ? \"License valid\" : \"License invalid\");",
    };

    function switchCode(lang) {
        document.querySelectorAll('.code-tab').forEach(function(t) {
            if (t.dataset.lang === lang) {
                t.className = 'code-tab px-4 py-2 text-sm font-medium rounded-lg bg-slate-900 text-white transition';
            } else {
                t.className = 'code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition';
            }
        });
        document.getElementById('code-lang-label').textContent = lang === 'node' ? 'Node.js' : lang === 'csharp' ? 'C#' : lang.charAt(0).toUpperCase() + lang.slice(1);
        document.getElementById('code-snippet').textContent = CODES[lang] || CODES.php;
    }
    </script>
    @include('public.partials.footer')
</body>
</html>
