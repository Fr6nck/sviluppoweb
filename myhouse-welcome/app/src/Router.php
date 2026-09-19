<?php
namespace MHW;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    public function get(string $p, callable $h): void  { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }
    public function any(string $p, callable $h): void  { $this->add('*', $p, $h); }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== '*' && $m !== $method) continue;
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $mt)) {
                $args = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($args);
                return;
            }
        }
        http_response_code(404);
        echo View::render('pub/404', [], 'layout/app');
    }
}
