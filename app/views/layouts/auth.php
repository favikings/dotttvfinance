<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title ?? 'Sign in') ?> — <?= View::e(APP_NAME) ?></title>
    <meta name="theme-color" content="#000d35">
    <link rel="stylesheet" href="<?= View::e(url('/assets/css/app.css')) ?>?v=<?= filemtime(APP_ROOT . '/public_html/assets/css/app.css') ?>">
    <script defer src="<?= View::e(url('/assets/js/alpine.min.js')) ?>"></script>
</head>
<body class="bg-surface text-on-surface font-sans antialiased">
    <?= $content ?? '' ?>
</body>
</html>
