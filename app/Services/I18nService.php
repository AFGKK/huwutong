<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use App\Models\TranslationHistory;
use App\Models\TranslationImport;
use App\Models\TranslationNamespace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class I18nService
{
    // ─── Language Management ────────────────────────────────────

    public function getLanguages(): array
    {
        return Language::orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function getActiveLanguages(): array
    {
        return Language::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function createLanguage(array $data): Language
    {
        if (empty($data['is_default'])) {
            $hasDefault = Language::where('is_default', true)->exists();
            if (!$hasDefault) {
                $data['is_default'] = true;
            }
        }

        if (!empty($data['is_default'])) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        return Language::create($data);
    }

    public function updateLanguage(int $id, array $data): Language
    {
        $language = Language::findOrFail($id);

        if (!empty($data['is_default'])) {
            Language::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $language->update($data);
        return $language->fresh();
    }

    public function deleteLanguage(int $id): void
    {
        $language = Language::findOrFail($id);
        if ($language->is_default) {
            throw new \RuntimeException('Cannot delete the default language.');
        }
        $language->translations()->delete();
        $language->delete();
    }

    // ─── Namespace Management ───────────────────────────────────

    public function getNamespaces(): array
    {
        return TranslationNamespace::orderBy('namespace')->get()->toArray();
    }

    public function createNamespace(array $data): TranslationNamespace
    {
        return TranslationNamespace::create($data);
    }

    public function deleteNamespace(int $id): void
    {
        $ns = TranslationNamespace::findOrFail($id);
        $ns->translations()->delete();
        $ns->delete();
    }

    // ─── Translation CRUD ──────────────────────────────────────

    public function getTranslations(array $filters = [], int $perPage = 50): array
    {
        $query = Translation::with(['namespace', 'creator', 'updater']);

        if (!empty($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }
        if (!empty($filters['namespace_id'])) {
            $query->where('namespace_id', $filters['namespace_id']);
        }
        if (!empty($filters['namespace'])) {
            $query->namespace($filters['namespace']);
        }
        if (!empty($filters['search'])) {
            $keyword = $filters['search'];
            $query->where(function ($q) use ($keyword) {
                $q->where('key', 'like', "%{$keyword}%")
                    ->orWhere('value', 'like', "%{$keyword}%")
                    ->orWhere('default_value', 'like', "%{$keyword}%");
            });
        }
        if (isset($filters['is_published'])) {
            $query->where('is_published', $filters['is_published']);
        }
        if (isset($filters['is_auto_translated'])) {
            $query->where('is_auto_translated', $filters['is_auto_translated']);
        }
        if (!empty($filters['missing_only'])) {
            $query->whereNull('value');
        }

        $sortField = $filters['sort_field'] ?? 'key';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        $paginator = $query->paginate(min($perPage, 200));

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function getTranslation(int $id): Translation
    {
        return Translation::with(['namespace', 'histories' => fn ($q) => $q->latest()->limit(50), 'histories.user'])->findOrFail($id);
    }

    public function updateTranslation(int $id, array $data, ?int $userId = null): Translation
    {
        $translation = Translation::findOrFail($id);
        $oldValue = $translation->value;

        $updates = array_merge($data, ['updated_by' => $userId]);
        if (isset($updates['value']) && $updates['value'] !== $oldValue) {
            $updates['is_auto_translated'] = false;
        }
        $translation->update($updates);

        if (isset($updates['value']) && $updates['value'] !== $oldValue) {
            $translation->recordHistory('updated', $oldValue, $updates['value'], $userId);
        }

        return $translation->fresh()->load(['namespace', 'creator', 'updater']);
    }

    public function bulkUpdateTranslations(array $translations, ?int $userId = null): array
    {
        $results = ['updated' => 0, 'failed' => 0, 'errors' => []];
        foreach ($translations as $item) {
            try {
                $this->updateTranslation($item['id'], ['value' => $item['value']], $userId);
                $results['updated']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "ID {$item['id']}: {$e->getMessage()}";
            }
        }
        return $results;
    }

    public function publishTranslation(int $id, bool $publish, ?int $userId = null): Translation
    {
        $translation = Translation::findOrFail($id);
        $translation->update(['is_published' => $publish, 'updated_by' => $userId]);
        $translation->recordHistory($publish ? 'published' : 'updated', null, null, $userId);
        return $translation->fresh();
    }

    // ─── Scan & Sync from PHP language files ────────────────────

    public function scanPhpLanguageFiles(string $locale = null): array
    {
        $results = ['namespaces' => 0, 'keys' => 0, 'new' => 0, 'updated' => 0];
        $langPath = lang_path();

        $locales = $locale ? [$locale] : array_filter(
            scandir($langPath),
            fn ($d) => $d !== '.' && $d !== '..' && is_dir($langPath . DIRECTORY_SEPARATOR . $d)
        );

        foreach ($locales as $loc) {
            $localeDir = $langPath . DIRECTORY_SEPARATOR . $loc;

            // Ensure language record exists
            $language = Language::firstOrCreate(
                ['locale' => $loc],
                ['name' => $loc, 'is_active' => true]
            );

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($localeDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace([$localeDir . DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
                $namespace = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                $ns = TranslationNamespace::firstOrCreate(
                    ['namespace' => $namespace],
                    ['label' => $namespace]
                );
                $results['namespaces']++;

                $translations = require $file->getPathname();
                if (!is_array($translations)) {
                    continue;
                }

                $this->syncTranslations($ns, $loc, $translations, '', $results);
            }
        }

        return $results;
    }

    private function syncTranslations(TranslationNamespace $ns, string $locale, array $items, string $prefix, array &$results): void
    {
        foreach ($items as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $this->syncTranslations($ns, $locale, $value, $fullKey, $results);
                continue;
            }

            $translation = Translation::where('namespace_id', $ns->id)
                ->where('locale', $locale)
                ->where('key', $fullKey)
                ->first();

            if ($translation) {
                if ($translation->default_value !== $value && !$translation->is_auto_translated) {
                    $translation->update([
                        'default_value' => $value,
                        'value' => $translation->value ?: $value,
                    ]);
                    $results['updated']++;
                } elseif ($translation->default_value !== $value) {
                    $translation->update(['default_value' => $value]);
                }
            } else {
                Translation::create([
                    'namespace_id' => $ns->id,
                    'locale' => $locale,
                    'key' => $fullKey,
                    'value' => $value,
                    'default_value' => $value,
                    'is_published' => true,
                ]);
                $results['new']++;
            }
            $results['keys']++;
        }
    }

    // ─── Export ─────────────────────────────────────────────────

    public function exportTranslations(string $locale, string $format = 'json', ?int $namespaceId = null): string
    {
        $query = Translation::with('namespace')->where('locale', $locale);
        if ($namespaceId) {
            $query->where('namespace_id', $namespaceId);
        }
        $translations = $query->get();

        $filename = "translations_{$locale}_" . now()->format('Ymd_His') . '.' . $format;
        $path = "exports/i18n/{$filename}";

        switch ($format) {
            case 'json':
                $data = [];
                foreach ($translations as $t) {
                    $ns = $t->namespace->namespace;
                    $data[$ns][$t->key] = $t->value ?? $t->default_value;
                }
                Storage::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                break;

            case 'csv':
                $csv = "\xEF\xBB\xBF"; // BOM for Excel
                $csv .= "Namespace,Key,Value,Default,Published\n";
                foreach ($translations as $t) {
                    $csv .= sprintf(
                        "\"%s\",\"%s\",\"%s\",\"%s\",%s\n",
                        str_replace('"', '""', $t->namespace->namespace),
                        str_replace('"', '""', $t->key),
                        str_replace('"', '""', $t->value ?? ''),
                        str_replace('"', '""', $t->default_value ?? ''),
                        $t->is_published ? '1' : '0'
                    );
                }
                Storage::put($path, $csv);
                break;

            case 'php':
                $grouped = [];
                foreach ($translations as $t) {
                    $ns = $t->namespace->namespace;
                    $grouped[$ns][$t->key] = $t->value ?? $t->default_value;
                }
                $php = "<?php\n\nreturn " . var_export($grouped, true) . ";\n";
                Storage::put($path, $php);
                break;

            case 'xliff':
                $xml = $this->buildXliff($locale, $translations);
                Storage::put($path, $xml);
                break;

            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }

        // Log export
        TranslationImport::create([
            'type' => 'export',
            'format' => $format,
            'file_path' => $path,
            'status' => 'completed',
        ]);

        return $path;
    }

    // ─── Import ─────────────────────────────────────────────────

    public function importTranslations(UploadedFile $file, string $format, ?int $userId = null): array
    {
        $import = TranslationImport::create([
            'type' => 'import',
            'format' => $format,
            'status' => 'processing',
            'user_id' => $userId,
        ]);

        try {
            $path = $file->store('imports/i18n');
            $import->update(['file_path' => $path]);

            $results = match ($format) {
                'json' => $this->importFromJson(Storage::path($path), $userId),
                'csv' => $this->importFromCsv(Storage::path($path), $userId),
                'xliff' => $this->importFromXliff(Storage::path($path), $userId),
                default => throw new \InvalidArgumentException("Unsupported import format: {$format}"),
            };

            $import->update([
                'status' => 'completed',
                'summary' => $results,
            ]);

            return $results;
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function importFromJson(string $filePath, ?int $userId): array
    {
        $content = json_decode(File::get($filePath), true);
        if (!is_array($content)) {
            throw new \RuntimeException('Invalid JSON file format.');
        }

        $results = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($content as $namespace => $keys) {
            $ns = TranslationNamespace::firstOrCreate(
                ['namespace' => $namespace],
                ['label' => $namespace]
            );

            foreach ($keys as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $results['total']++;

                // Try to detect locale from filename or default to the namespace-level locale
                $locale = request('locale', Language::defaultLocale());

                try {
                    $translation = Translation::where('namespace_id', $ns->id)
                        ->where('locale', $locale)
                        ->where('key', $key)
                        ->first();

                    if ($translation) {
                        if ($translation->value !== $value) {
                            $oldValue = $translation->value;
                            $translation->update(['value' => $value, 'updated_by' => $userId]);
                            $translation->recordHistory('imported', $oldValue, $value, $userId);
                            $results['updated']++;
                        } else {
                            $results['skipped']++;
                        }
                    } else {
                        Translation::create([
                            'namespace_id' => $ns->id,
                            'locale' => $locale,
                            'key' => $key,
                            'value' => $value,
                            'default_value' => $value,
                            'is_published' => true,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "[{$namespace}] {$key}: {$e->getMessage()}";
                }
            }
        }

        return $results;
    }

    private function importFromCsv(string $filePath, ?int $userId): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Cannot open CSV file.');
        }

        $results = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            throw new \RuntimeException('Empty CSV file.');
        }

        $header = array_map('trim', $header);
        $nsIdx = array_search('Namespace', $header);
        $keyIdx = array_search('Key', $header);
        $valueIdx = array_search('Value', $header);
        $localeIdx = array_search('Locale', $header);

        if ($nsIdx === false || $keyIdx === false || $valueIdx === false) {
            fclose($handle);
            throw new \RuntimeException('CSV must contain Namespace, Key, and Value columns.');
        }

        while (($row = fgetcsv($handle)) !== false) {
            $results['total']++;
            $namespace = trim($row[$nsIdx] ?? '');
            $key = trim($row[$keyIdx] ?? '');
            $value = trim($row[$valueIdx] ?? '');
            $locale = $localeIdx !== false ? trim($row[$localeIdx]) : Language::defaultLocale();

            if (empty($namespace) || empty($key)) {
                continue;
            }

            try {
                $ns = TranslationNamespace::firstOrCreate(
                    ['namespace' => $namespace],
                    ['label' => $namespace]
                );

                $translation = Translation::where('namespace_id', $ns->id)
                    ->where('locale', $locale)
                    ->where('key', $key)
                    ->first();

                if ($translation) {
                    if ($translation->value !== $value) {
                        $oldValue = $translation->value;
                        $translation->update(['value' => $value, 'updated_by' => $userId]);
                        $translation->recordHistory('imported', $oldValue, $value, $userId);
                        $results['updated']++;
                    } else {
                        $results['skipped']++;
                    }
                } else {
                    Translation::create([
                        'namespace_id' => $ns->id,
                        'locale' => $locale,
                        'key' => $key,
                        'value' => $value,
                        'default_value' => $value,
                        'is_published' => true,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                    $results['created']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$results['total']}: {$e->getMessage()}";
            }
        }

        fclose($handle);
        return $results;
    }

    private function importFromXliff(string $filePath, ?int $userId): array
    {
        $xml = simplexml_load_file($filePath);
        if (!$xml) {
            throw new \RuntimeException('Invalid XLIFF file.');
        }

        $results = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $locale = (string) $xml['target-language'] ?: Language::defaultLocale();

        foreach ($xml->file ?? [] as $file) {
            $namespace = (string) $file['original'] ?: 'imported';
            $ns = TranslationNamespace::firstOrCreate(
                ['namespace' => $namespace],
                ['label' => $namespace]
            );

            foreach ($file->body->{'trans-unit'} ?? [] as $unit) {
                $results['total']++;
                $key = (string) $unit['id'];
                $source = (string) $unit->source;
                $target = (string) $unit->target;

                if (empty($key)) {
                    continue;
                }

                try {
                    $translation = Translation::where('namespace_id', $ns->id)
                        ->where('locale', $locale)
                        ->where('key', $key)
                        ->first();

                    if ($translation) {
                        if ($translation->value !== $target) {
                            $oldValue = $translation->value;
                            $translation->update([
                                'value' => $target ?: $source,
                                'default_value' => $source ?: $translation->default_value,
                                'updated_by' => $userId,
                            ]);
                            $translation->recordHistory('imported', $oldValue, $target ?: $source, $userId);
                            $results['updated']++;
                        } else {
                            $results['skipped']++;
                        }
                    } else {
                        Translation::create([
                            'namespace_id' => $ns->id,
                            'locale' => $locale,
                            'key' => $key,
                            'value' => $target ?: $source,
                            'default_value' => $source,
                            'is_published' => true,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "[{$namespace}] {$key}: {$e->getMessage()}";
                }
            }
        }

        return $results;
    }

    // ─── Dashboard ─────────────────────────────────────────────

    public function getDashboard(): array
    {
        $languages = Language::all();
        $namespaces = TranslationNamespace::all();

        $stats = [
            'total_languages' => $languages->count(),
            'active_languages' => $languages->where('is_active', true)->count(),
            'total_namespaces' => $namespaces->count(),
            'total_translations' => Translation::count(),
            'total_published' => Translation::where('is_published', true)->count(),
            'total_missing' => Translation::whereNull('value')->count(),
            'total_auto_translated' => Translation::where('is_auto_translated', true)->count(),
            'total_imports' => TranslationImport::count(),
            'recent_imports' => TranslationImport::latest()->take(5)->get()->toArray(),
        ];

        // Per-language stats
        $perLanguage = [];
        foreach ($languages as $lang) {
            $total = Translation::where('locale', $lang->locale)->count();
            $published = Translation::where('locale', $lang->locale)->where('is_published', true)->count();
            $missing = Translation::where('locale', $lang->locale)->whereNull('value')->count();

            $perLanguage[] = [
                'locale' => $lang->locale,
                'name' => $lang->name,
                'native_name' => $lang->native_name,
                'total' => $total,
                'published' => $published,
                'missing' => $missing,
                'progress' => $total > 0 ? round(($published / $total) * 100, 1) : 0,
            ];
        }

        return [
            'stats' => $stats,
            'per_language' => $perLanguage,
            'languages' => $languages->toArray(),
            'namespaces' => $namespaces->toArray(),
        ];
    }

    // ─── Auto-translate ────────────────────────────────────────

    public function autoTranslate(int $translationId, ?int $userId = null): Translation
    {
        $translation = Translation::with('namespace')->findOrFail($translationId);

        if (empty($translation->default_value)) {
            throw new \RuntimeException('No source text available for translation.');
        }

        $sourceLocale = Language::defaultLocale();
        $targetLocale = $translation->locale;

        // Simple auto-translation using basic rules (placeholder — in production, call translation API)
        $translated = $this->simpleTranslate($translation->default_value, $sourceLocale, $targetLocale);

        $oldValue = $translation->value;
        $translation->update([
            'value' => $translated,
            'is_auto_translated' => true,
            'updated_by' => $userId,
        ]);

        $translation->recordHistory('auto_translated', $oldValue, $translated, $userId);

        return $translation->fresh()->load('namespace');
    }

    public function autoTranslateAll(string $locale, ?int $namespaceId = null, ?int $userId = null): array
    {
        $query = Translation::where('locale', $locale)->whereNull('value');
        if ($namespaceId) {
            $query->where('namespace_id', $namespaceId);
        }

        $results = ['total' => 0, 'translated' => 0, 'failed' => 0];
        $query->chunk(100, function ($translations) use ($userId, &$results) {
            foreach ($translations as $t) {
                try {
                    $this->autoTranslate($t->id, $userId);
                    $results['translated']++;
                } catch (\Exception) {
                    $results['failed']++;
                }
                $results['total']++;
            }
        });

        return $results;
    }

    private function simpleTranslate(string $text, string $from, string $to): string
    {
        // Basic fallback for development — returns the source text.
        // In production, integrate with DeepL, Google Translate, or OpenAI.
        if ($from === $to) {
            return $text;
        }

        // Return source text as-is for now; real translation requires API integration
        return $text;
    }

    // ─── XLIFF builder ─────────────────────────────────────────

    private function buildXliff(string $locale, $translations): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $xliff = $doc->createElement('xliff');
        $xliff->setAttribute('version', '1.2');
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
        $doc->appendChild($xliff);

        $grouped = $translations->groupBy(fn ($t) => $t->namespace->namespace);

        foreach ($grouped as $namespace => $items) {
            $file = $doc->createElement('file');
            $file->setAttribute('original', $namespace);
            $file->setAttribute('source-language', Language::defaultLocale());
            $file->setAttribute('target-language', $locale);
            $file->setAttribute('datatype', 'plaintext');

            $body = $doc->createElement('body');
            foreach ($items as $item) {
                $unit = $doc->createElement('trans-unit');
                $unit->setAttribute('id', $item->key);

                $source = $doc->createElement('source');
                $source->appendChild($doc->createCDATASection($item->default_value ?? ''));
                $unit->appendChild($source);

                $target = $doc->createElement('target');
                $target->appendChild($doc->createCDATASection($item->value ?? ''));
                $unit->appendChild($target);

                $body->appendChild($unit);
            }

            $file->appendChild($body);
            $xliff->appendChild($file);
        }

        return $doc->saveXML();
    }

    // ─── History ───────────────────────────────────────────────

    public function getHistory(int $translationId, int $limit = 50): array
    {
        return TranslationHistory::with('user')
            ->where('translation_id', $translationId)
            ->latest()
            ->take($limit)
            ->get()
            ->toArray();
    }
}
