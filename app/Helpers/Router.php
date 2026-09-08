<?php

namespace App\Helpers;

/**
 * Router
 * ------
 * Simpleng router na sumusuporta sa GET/POST routes, kasama ang
 * dynamic segments gaya ng {id} (hal. /employees/{id}/edit).
 *
 * Paggamit (sa routes/web.php):
 *   $router->post('/attendance/import/preview', [AttendanceController::class, 'previewImport']);
 *   $router->post('/employees/{id}', [EmployeeController::class, 'update']);
 *
 * Pag-dispatch (sa public/index.php):
 *   $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, paramNames:array<int,string>, handler:array}> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void
    {
        [$regex, $paramNames] = $this->compilePath($path);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $path,
            'regex'      => $regex,
            'paramNames' => $paramNames,
            'handler'    => $handler,
        ];
    }

    /**
     * Convert a route pattern like '/employees/{id}/edit' into a regex,
     * at ibalik din ang listahan ng param names sa tamang pagkakasunod-sunod.
     *
     * @return array{0:string, 1:array<int,string>}
     */
    private function compilePath(string $path): array
    {
        $paramNames = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $path
        );

        $regex = '#^' . rtrim($regex, '/') . '/?$#';

        return [$regex, $paramNames];
    }

    /**
     * Hanapin at tawagin ang tamang handler base sa HTTP method at URI.
     * Kinukuha lang ang path (hinihiwalay ang query string) bago i-match.
     */
    public function dispatch(string $method, string $requestUri): void
    {
        $method = strtoupper($method);

        // Alisin ang query string, kunin lang ang path
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches); // alisin ang full match

                $params = [];
                foreach ($route['paramNames'] as $i => $name) {
                    $params[$name] = $matches[$i] ?? null;
                }

                [$class, $methodName] = $route['handler'];
                $controller = new $class();

                call_user_func_array([$controller, $methodName], array_values($params));
                return;
            }
        }

        http_response_code(404);
        echo '404 - Page not found';
    }
}