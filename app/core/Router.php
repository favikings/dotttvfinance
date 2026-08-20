<?php

declare(strict_types=1);

class Router
{
    /** @var array<int, array{method: string, pattern: string, paramNames: array<int, string>, handler: string, public: bool}> */
    private array $routes = [];

    public function add(string $method, string $path, string $handler, bool $public = false): void
    {
        $paramNames = [];
        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function (array $m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, rtrim($path, '/') ?: '/');

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => '#^' . $pattern . '$#',
            'paramNames' => $paramNames,
            'handler' => $handler,
            'public' => $public,
        ];
    }

    public function get(string $path, string $handler, bool $public = false): void
    {
        $this->add('GET', $path, $handler, $public);
    }

    public function post(string $path, string $handler, bool $public = false): void
    {
        $this->add('POST', $path, $handler, $public);
    }

    public function put(string $path, string $handler, bool $public = false): void
    {
        $this->add('PUT', $path, $handler, $public);
    }

    public function delete(string $path, string $handler, bool $public = false): void
    {
        $this->add('DELETE', $path, $handler, $public);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';

        if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
            $path = substr($path, strlen(BASE_PATH));
            $path = ($path === '' || $path[0] !== '/') ? '/' . $path : $path;
            $path = rtrim($path, '/') ?: '/';
        }

        $method = strtoupper($method);

        $pathMatchedOtherMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $pathMatchedOtherMethod = true;
                continue;
            }

            if (!$route['public'] && !Auth::check()) {
                $this->redirectToLogin($path);
                return;
            }

            array_shift($matches);
            $params = array_combine($route['paramNames'], $matches) ?: [];

            $this->callHandler($route['handler'], $params);
            return;
        }

        if ($pathMatchedOtherMethod) {
            http_response_code(405);
            echo '405 Method Not Allowed';
            return;
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function redirectToLogin(string $requestedPath): void
    {
        $query = $requestedPath !== '/' ? '?redirect=' . urlencode($requestedPath) : '';
        header('Location: ' . url('/login') . $query);
    }

    private function callHandler(string $handler, array $params): void
    {
        [$controllerName, $action] = array_pad(explode('@', $handler, 2), 2, null);

        if ($controllerName === null || $action === null || !class_exists($controllerName)) {
            http_response_code(500);
            echo 'Router misconfiguration: handler "' . htmlspecialchars($handler) . '" not found';
            return;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            echo 'Router misconfiguration: action "' . htmlspecialchars($action) . '" not found on ' . htmlspecialchars($controllerName);
            return;
        }

        call_user_func_array([$controller, $action], $params);
    }
}
