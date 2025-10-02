<?php

namespace Esikat\Helper;

class Lang
{
    protected static string $default = 'id-ID';
    protected static string $current;
    protected static array $translations = [];
    protected static array $defaultTranslations = [];
    protected static string $langPath = __DIR__ . '/../lang';

    public static function setLangPath(string $path): void
    {
        self::$langPath = rtrim($path, '/\\');
    }

    public static function setDefault(string $lang): void
    {
        self::$default = $lang;
    }

    public static function setCurrent(string $lang): void
    {
        $available = self::available();
        self::$current = in_array($lang, $available) ? $lang : self::$default;
    }

    public static function init(): void
    {
        if (!isset(self::$current)) {
            self::$current = self::$default;
        }

        self::$translations = self::load(self::$current);
        self::$defaultTranslations = self::load(self::$default);
    }

    protected static function load(string $lang): array
    {
        $file = self::$langPath . '/' . $lang . '.json';
        return file_exists($file)
            ? json_decode(file_get_contents($file), true) ?? []
            : [];
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::getNested(self::$translations, $key)
               ?? self::getNested(self::$defaultTranslations, $key);

        return is_string($value) ? $value : ($default ?: $key);
    }

    protected static function getNested(array $data, string $key): mixed
    {
        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function available(): array
    {
        if (!is_dir(self::$langPath)) {
            return [];
        }

        $files = glob(self::$langPath . '/*.json');
        return array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), $files);
    }
}
