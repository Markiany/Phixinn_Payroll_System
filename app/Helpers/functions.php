<?php
// WALANG "namespace" declaration dito - dapat talagang nasa
// GLOBAL scope ang function na ito para magamit kahit saan
// (views, controllers, kahit anong file) na walang namespace prefix.

if (!function_exists('__')) {
    function __(string $key): string
    {
        return \App\Helpers\Lang::get($key);
    }
}