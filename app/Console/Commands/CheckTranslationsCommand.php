<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class CheckTranslationsCommand extends Command
{
    protected $signature = 'gaeld:check-translations
        {--lang= : Check a specific language (en, fr, de, it)}
        {--unused : Also report keys defined but not used in code}
        {--fix : Add missing keys to translation files with TODO placeholder}';

    protected $description = 'Scan the codebase for missing or hardcoded translation strings';

    /** @var list<string> */
    private array $languages = ['en', 'fr', 'de', 'it'];

    /** @var list<string> */
    private array $translationFiles = ['app', 'exports', 'mail', 'migration', 'validation'];

    public function handle(): int
    {
        $this->info('Scanning codebase for translation usage...');
        $this->newLine();

        $usedKeys = $this->extractUsedKeys();
        $hardcodedStrings = $this->extractHardcodedStrings();
        $definedKeys = $this->loadDefinedKeys();

        $languages = $this->option('lang')
            ? [strtolower($this->option('lang'))]
            : $this->languages;

        $hasIssues = false;

        // 1. Hardcoded strings (not using translation keys)
        if (count($hardcodedStrings) > 0) {
            $hasIssues = true;
            $this->error('Hardcoded strings found (should use translation keys):');
            $this->table(
                ['String', 'File', 'Line'],
                collect($hardcodedStrings)->map(fn ($item) => [
                    mb_strimwidth($item['string'], 0, 60, '…'),
                    str_replace(base_path().'/', '', $item['file']),
                    $item['line'],
                ])->toArray()
            );
            $this->newLine();
        }

        // 2. Keys used in code but missing from translation files
        foreach ($languages as $lang) {
            $missing = [];

            foreach ($usedKeys as $key) {
                $parts = explode('.', $key, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                [$file, $subKey] = $parts;

                if (! in_array($file, $this->translationFiles)) {
                    continue;
                }

                if (! isset($definedKeys[$lang][$file]) || ! array_key_exists($subKey, $definedKeys[$lang][$file])) {
                    $missing[] = $key;
                }
            }

            if (count($missing) > 0) {
                $hasIssues = true;
                $this->error("Missing keys in [{$lang}] (".count($missing).' keys):');

                // Group by file prefix
                $grouped = collect($missing)->groupBy(fn ($k) => explode('.', $k, 2)[0]);

                foreach ($grouped as $file => $keys) {
                    $this->warn("  {$file}.php:");
                    foreach ($keys as $key) {
                        $this->line("    - {$key}");
                    }
                }

                if ($this->option('fix')) {
                    $this->addMissingKeys($lang, $missing, $definedKeys);
                }

                $this->newLine();
            } else {
                $this->info("✓ [{$lang}] All used keys are defined.");
            }
        }

        // 3. Unused keys (optional)
        if ($this->option('unused')) {
            $this->newLine();
            $this->info('Checking for unused keys (defined but not used in PHP)...');
            $this->warn('Note: Keys used only via frontend (Inertia/Vue) may appear here as false positives.');
            $this->newLine();

            $refLang = $languages[0];

            foreach ($this->translationFiles as $file) {
                if (! isset($definedKeys[$refLang][$file])) {
                    continue;
                }

                $unused = [];

                foreach (array_keys($definedKeys[$refLang][$file]) as $subKey) {
                    $fullKey = "{$file}.{$subKey}";
                    if (! in_array($fullKey, $usedKeys)) {
                        $unused[] = $fullKey;
                    }
                }

                if (count($unused) > 0) {
                    $this->warn("{$file}.php: ".count($unused).' potentially unused keys');

                    if ($this->getOutput()->isVerbose()) {
                        foreach ($unused as $key) {
                            $this->line("    - {$key}");
                        }
                    }
                }
            }
        }

        // 4. Cross-language consistency
        $this->newLine();
        $this->info('Checking cross-language consistency...');

        $refLang = 'en';
        foreach ($this->translationFiles as $file) {
            if (! isset($definedKeys[$refLang][$file])) {
                continue;
            }

            $refKeys = array_keys($definedKeys[$refLang][$file]);

            foreach ($languages as $lang) {
                if ($lang === $refLang) {
                    continue;
                }

                $langKeys = isset($definedKeys[$lang][$file]) ? array_keys($definedKeys[$lang][$file]) : [];

                $missingInLang = array_diff($refKeys, $langKeys);
                $extraInLang = array_diff($langKeys, $refKeys);

                if (count($missingInLang) > 0) {
                    $hasIssues = true;
                    $this->error("[{$lang}/{$file}.php] Missing ".count($missingInLang)." keys present in [{$refLang}]:");
                    foreach ($missingInLang as $k) {
                        $this->line("    - {$file}.{$k}");
                    }
                }

                if (count($extraInLang) > 0) {
                    $this->warn("[{$lang}/{$file}.php] Has ".count($extraInLang)." extra keys not in [{$refLang}]:");
                    foreach ($extraInLang as $k) {
                        $this->line("    - {$file}.{$k}");
                    }
                }
            }
        }

        $this->newLine();

        if ($hasIssues) {
            $this->error('Translation issues found. See above for details.');

            return self::FAILURE;
        }

        $this->info('All translations are in sync!');

        return self::SUCCESS;
    }

    /**
     * Extract all translation keys used with __(), trans(), @lang() in PHP/Blade files.
     *
     * @return list<string>
     */
    private function extractUsedKeys(): array
    {
        $keys = [];

        $patterns = [
            '/__\(\s*[\'"]([a-z_]+\.[a-z_.]+)[\'"]/i',
            '/trans\(\s*[\'"]([a-z_]+\.[a-z_.]+)[\'"]/i',
            '/@lang\(\s*[\'"]([a-z_]+\.[a-z_.]+)[\'"]/i',
        ];

        $finder = (new Finder())
            ->in([
                base_path('app'),
                base_path('resources'),
                base_path('routes'),
            ])
            ->name(['*.php', '*.blade.php'])
            ->notPath('vendor')
            ->files();

        foreach ($finder as $file) {
            $content = $file->getContents();

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $content, $matches);

                if (! empty($matches[1])) {
                    $keys = array_merge($keys, $matches[1]);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Find __(), trans(), @lang() calls with raw English strings instead of dot-notation keys.
     *
     * @return list<array{string: string, file: string, line: int}>
     */
    private function extractHardcodedStrings(): array
    {
        $results = [];

        $patterns = [
            "/__\(\s*'([^']+)'\s*[,)]/",
            '/__\(\s*"([^"]+)"\s*[,)]/',
            "/trans\(\s*'([^']+)'\s*[,)]/",
            '/trans\(\s*"([^"]+)"\s*[,)]/',
            "/@lang\(\s*'([^']+)'\s*\)/",
        ];

        $finder = (new Finder())
            ->in([
                base_path('app'),
                base_path('resources'),
                base_path('routes'),
            ])
            ->name(['*.php', '*.blade.php'])
            ->notPath('vendor')
            ->files();

        foreach ($finder as $file) {
            $lines = explode("\n", $file->getContents());

            foreach ($lines as $lineNum => $line) {
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $line, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $value = $match[1];

                        // Skip keys that look like dot-notation (proper translation keys)
                        if (preg_match('/^[a-z_]+(\.[a-z_0-9]+)+$/i', $value)) {
                            continue;
                        }

                        // Skip single-segment keys used to load full translation arrays (e.g. trans('app'))
                        if (preg_match('/^[a-z_]+$/i', $value)) {
                            continue;
                        }

                        // Skip :attribute-style placeholders that are just validation keys
                        if (preg_match('/^:/', $value)) {
                            continue;
                        }

                        $results[] = [
                            'string' => $value,
                            'file' => $file->getRealPath(),
                            'line' => $lineNum + 1,
                        ];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Load all defined keys from translation files.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function loadDefinedKeys(): array
    {
        $defined = [];

        foreach ($this->languages as $lang) {
            foreach ($this->translationFiles as $file) {
                $path = lang_path("{$lang}/{$file}.php");

                if (File::exists($path)) {
                    $data = include $path;
                    $defined[$lang][$file] = is_array($data) ? Arr::dot($data) : [];
                }
            }
        }

        return $defined;
    }

    /**
     * Add missing keys to translation files with a TODO placeholder.
     *
     * @param  list<string>  $missingKeys
     * @param  array<string, array<string, array<string, mixed>>>  $definedKeys
     */
    private function addMissingKeys(string $lang, array $missingKeys, array $definedKeys): void
    {
        $grouped = collect($missingKeys)->groupBy(fn ($k) => explode('.', $k, 2)[0]);

        foreach ($grouped as $file => $keys) {
            $path = lang_path("{$lang}/{$file}.php");

            if (! File::exists($path)) {
                continue;
            }

            $data = include $path;

            if (! is_array($data)) {
                continue;
            }

            foreach ($keys as $fullKey) {
                $subKey = explode('.', $fullKey, 2)[1];

                // Try to use the English value as a reference
                $englishValue = $definedKeys['en'][$file][$subKey] ?? null;
                $placeholder = $englishValue && $lang !== 'en'
                    ? "TODO: {$englishValue}"
                    : 'TODO: translate';

                Arr::set($data, $subKey, $placeholder);
            }

            $export = var_export($data, true);
            $export = preg_replace('/^(\s*)array\s*\(/m', '$1[', $export);
            $export = preg_replace('/\)$/m', ']', $export);
            $export = str_replace('array (', '[', $export);
            $export = preg_replace('/\)(\s*,?)$/m', ']$1', $export);

            File::put($path, "<?php\n\nreturn {$export};\n");
            $this->info("  → Added ".count($keys)." missing keys to {$lang}/{$file}.php");
        }
    }
}
