<?php

declare(strict_types=1);

// POST-redirect-GET flash messages, set by controllers as
// $_SESSION['flash_success'] / $_SESSION['flash_error']. Usage at the top
// of a content area: echo flash_messages();. Rendered once, then cleared.
if (!function_exists('flash_messages')) {
    function flash_messages(): string
    {
        $html = '';
        $map = [
            'error'   => 'bg-error-container text-on-error-container',
            'success' => 'bg-success-container text-success',
        ];

        foreach ($map as $type => $classes) {
            $key = 'flash_' . $type;
            if (!empty($_SESSION[$key])) {
                $html .= '<div class="mb-6 rounded border border-outline-variant px-3 py-2 text-sm ' . $classes . '">'
                    . htmlspecialchars((string) $_SESSION[$key], ENT_QUOTES, 'UTF-8') . '</div>';
                unset($_SESSION[$key]);
            }
        }

        return $html;
    }
}
