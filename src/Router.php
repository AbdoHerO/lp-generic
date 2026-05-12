<?php
class Router {
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $action): void  { $this->routes['GET'][$path]  = $action; }
    public function post(string $path, array $action): void { $this->routes['POST'][$path] = $action; }

    public function dispatch(): void {
        global $CONFIG;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Strip base_url prefix
        $base = rtrim($CONFIG['app']['base_url'], '/');
        if ($base && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        if ($uri === '' || $uri === false) $uri = '/';
        $uri = '/' . ltrim($uri, '/');
        // Remove trailing slash (except root)
        if ($uri !== '/' && substr($uri, -1) === '/') $uri = rtrim($uri, '/');

        $routes = $this->routes[$method] ?? [];
        foreach ($routes as $pattern => $action) {
            $regex = $this->compile($pattern);
            if (preg_match($regex, $uri, $m)) {
                array_shift($m);
                [$class, $methodName] = $action;
                $controller = new $class();
                $controller->$methodName(...$m);
                return;
            }
        }
        not_found();
    }

    private function compile(string $pattern): string {
        $r = preg_replace('#\{([a-zA-Z_]+)\}#', '([^/]+)', $pattern);
        return '#^' . $r . '$#u';
    }
}
