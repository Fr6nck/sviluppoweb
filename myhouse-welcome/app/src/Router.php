<?php
namespace MHW;

use MHW\Support;

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
        $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
        // Prima la cartella, poi l'eventuale index.php: cosi' entrambe le forme
        // — /welcomebook/pannello e /welcomebook/index.php/pannello — arrivano
        // alla stessa rotta, che il server sappia riscrivere o no.
        $dir = Support::baseDir();
        if ($dir !== '' && str_starts_with($path, $dir)) $path = substr($path, strlen($dir));
        if (str_starts_with($path, '/index.php')) $path = substr($path, strlen('/index.php'));
        $path = '/' . trim($path, '/');
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
