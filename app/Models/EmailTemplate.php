<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'body',
        'attachments',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'attachments' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (EmailTemplate $template): void {
            $paths = $template->attachmentStoragePaths();

            if ($paths !== []) {
                Storage::disk('local')->delete($paths);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function attachmentStoragePaths(): array
    {
        $paths = is_array($this->attachments) ? $this->attachments : [];

        return collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(EmailSendLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<int, array{key: string, default: string}>
     */
    public function variableDefinitions(): array
    {
        $items = is_array($this->variables) ? $this->variables : [];

        if (is_string($this->variables) && $this->variables !== '') {
            $decoded = json_decode($this->variables, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        return collect($items)
            ->map(function ($item) {
                if (is_string($item)) {
                    $key = trim($item);

                    return $key !== '' ? ['key' => $key, 'default' => ''] : null;
                }

                if (! is_array($item)) {
                    return null;
                }

                $key = trim((string) ($item['key'] ?? ''));

                if ($key === '') {
                    return null;
                }

                $default = trim((string) ($item['default'] ?? ''));

                if ($default === '' && isset($item['label'])) {
                    $legacyLabel = trim((string) $item['label']);

                    if ($legacyLabel !== '' && $legacyLabel !== $key) {
                        $default = $legacyLabel;
                    }
                }

                return [
                    'key' => $key,
                    'default' => $default,
                ];
            })
            ->filter()
            ->unique('key')
            ->values()
            ->all();
    }

    public function prefillValueForDefinition(array $definition): string
    {
        return trim((string) ($definition['default'] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    public function defaultVariableValues(): array
    {
        $values = [];

        foreach ($this->variableDefinitions() as $definition) {
            $value = $this->prefillValueForDefinition($definition);

            if ($value !== '') {
                $values[$definition['key']] = $value;
            }
        }

        return $values;
    }

    public function renderSubject(array $values): string
    {
        return $this->renderText($this->subject, $values);
    }

    public function renderBody(array $values): string
    {
        return static::formatBodyForEmail($this->renderText($this->body, $values));
    }

    public static function formatBodyForEmail(string $body): string
    {
        $text = (new static())->normalizeLineEndings(static::unwrapPlaceholdersFromEditor($body));

        if (! preg_match('/<[^>]+>/', $text)) {
            return static::plainTextToHtml($text);
        }

        return (new static())->convertBareNewlinesToBreaks($text);
    }

    public function bodyExcerpt(array $values, int $limit = 200): string
    {
        return \Illuminate\Support\Str::limit($this->htmlToPlainText($this->renderBody($values)), $limit);
    }

    protected function normalizeLineEndings(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    public static function prepareBodyForEditor(string $body): string
    {
        $body = (new static())->normalizeLineEndings($body);

        if (! preg_match('/<[^>]+>/', $body)) {
            $body = static::plainTextToHtml($body);
        }

        return static::wrapPlaceholdersForEditor($body);
    }

    public static function normalizeBodyFromEditor(string $body): string
    {
        $body = static::unwrapPlaceholdersFromEditor($body);

        return trim($body);
    }

    public static function plainTextToHtml(string $text): string
    {
        $lines = explode("\n", e($text));

        return collect($lines)
            ->map(fn (string $line): string => '<p>'.($line === '' ? '<br>' : $line).'</p>')
            ->implode('');
    }

    public static function wrapPlaceholdersForEditor(string $html): string
    {
        return preg_replace_callback(
            '/\[([a-zA-Z][a-zA-Z0-9_]*)\]/',
            fn (array $matches): string => '<span data-email-placeholder="'.e($matches[1]).'">['.e($matches[1]).']</span>',
            $html,
        ) ?? $html;
    }

    public static function unwrapPlaceholdersFromEditor(string $html): string
    {
        $html = preg_replace(
            '/<span[^>]*data-email-placeholder="([^"]+)"[^>]*>\[[^\]]*\]<\/span>/',
            '[$1]',
            $html,
        ) ?? $html;

        return preg_replace(
            '/<span[^>]*data-email-placeholder=\'([^\']+)\'[^>]*>\[[^\]]*\]<\/span>/',
            '[$1]',
            $html,
        ) ?? $html;
    }

    /** Giữ xuống dòng khi nội dung có HTML lẫn text thuần. */
    protected function convertBareNewlinesToBreaks(string $html): string
    {
        return preg_replace("/\n/", "<br>\n", $html) ?? $html;
    }

    public function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/div>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->normalizeLineEndings($text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    public function renderText(string $text, array $values): string
    {
        $text = static::unwrapPlaceholdersFromEditor($text);
        $merged = array_merge($this->defaultVariableValues(), $values);

        foreach ($this->variableDefinitions() as $definition) {
            $key = $definition['key'];
            $value = (string) ($merged[$key] ?? '');
            $placeholder = '['.$key.']';

            $text = str_replace($placeholder, $value, $text);
            $text = str_replace(urlencode($placeholder), $value, $text);
            $text = str_replace(htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'), $value, $text);
        }

        return $text;
    }

    public static function optionsForSelect(): array
    {
        return static::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
