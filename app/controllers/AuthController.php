<?php

declare(strict_types=1);

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . url('/'));
            return;
        }

        $redirect = $_GET['redirect'] ?? '';

        View::render('auth/login', [
            'title' => 'Sign in',
            'error' => $_SESSION['login_error'] ?? null,
            'old_email' => $_SESSION['login_old_email'] ?? '',
            'redirect' => is_string($redirect) ? $redirect : '',
        ], 'layouts/auth');

        unset($_SESSION['login_error'], $_SESSION['login_old_email']);
    }

    public function login(): void
    {
        $redirect = $_POST['redirect'] ?? '';
        $redirectQuery = (is_string($redirect) && $redirect !== '') ? '?redirect=' . urlencode($redirect) : '';

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['login_error'] = 'Your session expired. Please try again.';
            header('Location: ' . url('/login') . $redirectQuery);
            return;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $result = Auth::attempt($email, $password);

        if (!$result['ok']) {
            $_SESSION['login_error'] = $result['error'];
            $_SESSION['login_old_email'] = $email;
            header('Location: ' . url('/login') . $redirectQuery);
            return;
        }

        // Only ever follow a same-app, root-relative redirect — never an
        // absolute or protocol-relative ("//host/...") one, which would be
        // an open-redirect vector.
        $target = (is_string($redirect) && $redirect !== '' && $redirect[0] === '/' && !str_starts_with($redirect, '//'))
            ? $redirect
            : '/';

        header('Location: ' . url($target));
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . url('/login'));
    }
}
