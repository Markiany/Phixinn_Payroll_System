<?php

namespace App\Controllers;

use App\Helpers\Lang;

class LanguageController
{
    /**
     * Palitan ang wika ng system tapos bumalik sa dating pinuntahang page.
     *
     * URL: /lang/{code}
     */
    public function switch(string $code): void
    {
        Lang::setLocale($code);

        $back = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        header('Location: ' . $back);
        exit;
    }
}
