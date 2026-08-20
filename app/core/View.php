<?php

declare(strict_types=1);

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = APP_ROOT . '/app/views/' . $view . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        $content = self::capture($viewFile, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = APP_ROOT . '/app/views/' . $layout . '.php';

        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout not found: {$layout}");
        }

        echo self::capture($layoutFile, $data + ['content' => $content]);
    }

    public static function renderPartial(string $view, array $data = []): string
    {
        $viewFile = APP_ROOT . '/app/views/' . $view . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        return self::capture($viewFile, $data);
    }

    // Parameter deliberately NOT named $data: extract()'s EXTR_SKIP refuses to
    // overwrite a variable that already exists in the current scope, and a
    // local var named $data existing here would collide with (and silently
    // swallow) any view array that itself has a 'data' key — a natural name
    // for a view's payload, as the Reports suite's controllers use.
    private static function capture(string $file, array $__viewData): string
    {
        extract($__viewData, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
