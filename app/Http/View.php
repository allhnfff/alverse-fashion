<?php

declare(strict_types=1);

namespace App\Http;

use App\App;

/** Merender file PHP di folder views/ ke dalam layout. */
final class View
{
    /**
     * @param array<string,mixed> $data variabel untuk view
     */
    public static function render(string $view, array $data = [], string $layout = 'layout', int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $content = self::capture($view, $data);
        echo self::capture($layout, $data + ['content' => $content]);
    }

    /** @param array<string,mixed> $data */
    public static function capture(string $view, array $data = []): string
    {
        $file = App::root() . '/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View tidak ditemukan: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
