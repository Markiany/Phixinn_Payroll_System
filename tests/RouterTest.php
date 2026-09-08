<?php

namespace Tests;

use App\Helpers\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function test_matches_static_route(): void
    {
        $router = new Router();
        $hit = false;

        $router->get('/ping', function () use (&$hit) {
            $hit = true;
        });

        ob_start();
        $router->dispatch('GET', '/ping');
        ob_end_clean();

        $this->assertTrue($hit);
    }

    public function test_returns_404_for_unknown_route(): void
    {
        $router = new Router();

        ob_start();
        $router->dispatch('GET', '/does-not-exist');
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output);
    }
}
