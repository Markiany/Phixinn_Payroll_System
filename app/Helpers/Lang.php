<?php

namespace App\Helpers;

class Lang
{
    private static array $strings = [];
    private static string $locale = 'en';
    private static array $available = ['en', 'tl', 'zh'];

    public static function load(): void
    {
        $locale = $_SESSION['locale'] ?? 'en';

        if (!in_array($locale, self::$available, true)) {
            $locale = 'en';
        }

        self::$locale = $locale;

        $file = __DIR__ . '/../../lang/' . $locale . '.php';

        self::$strings = file_exists($file) ? require $file : [];
    }

    /**
     * Kunin ang translated string. Kung walang laman sa kasalukuyang
     * wika, babalik sa English. Kung wala pa rin, ibabalik na lang
     * ang key mismo (para hindi kailanman mawala/blangko ang text).
     */
    public static function get(string $key): string
    {
        if (isset(self::$strings[$key])) {
            return self::$strings[$key];
        }

        // Fallback sa English kung walang laman sa current locale.
        static $englishFallback = null;
        if ($englishFallback === null) {
            $enFile = __DIR__ . '/../../lang/en.php';
            $englishFallback = file_exists($enFile) ? require $enFile : [];
        }

        return $englishFallback[$key] ?? $key;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, self::$available, true)) {
            $_SESSION['locale'] = $locale;
        }
    }
}
