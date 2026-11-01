<?php
declare(strict_types=1);

namespace App;

/** Translations live in lang/en.php, lang/ru.php, lang/kk.php. Missing keys fall back to English. */
final class I18n
{
    public const LANGS = ['en' => 'EN', 'ru' => 'RU', 'kk' => 'KK'];
    private static array $cache = [];
    private static ?string $forced = null;

    public static function force(?string $lang): void
    {
        self::$forced = $lang;
    }

    public static function current(): string
    {
        if (self::$forced !== null) {
            return self::$forced;
        }
        $user = null;
        try {
            $user = Auth::user();
        } catch (\Throwable) {
        }
        $lang = $user['lang'] ?? ($_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'en'));
        return isset(self::LANGS[$lang]) ? $lang : 'en';
    }

    public static function strings(string $lang): array
    {
        return self::$cache[$lang] ??= (require dirname(__DIR__) . '/lang/' . $lang . '.php');
    }

    public static function t(string $key, array $params = []): string
    {
        $lang = self::current();
        $text = self::strings($lang)[$key] ?? self::strings('en')[$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }
        return $text;
    }
}
