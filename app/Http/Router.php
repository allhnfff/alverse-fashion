<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Router sederhana: mencocokkan METHOD + path dengan pola seperti /produk/{id}.
 */
final class Router
{
    /** @var list<array{method:string, regex:string, params:list<string>, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-z_]+)\}/', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '([A-Za-z0-9_-]+)';
        }, $pattern);
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'params' => $params, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            array_shift($m);
            $args = array_combine($route['params'], $m) ?: [];
            ($route['handler'])(...array_values($args));
            return;
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405, 'Metode tidak diizinkan.');
        }
        throw new HttpException(404, 'Halaman tidak ditemukan.');
    }
}
