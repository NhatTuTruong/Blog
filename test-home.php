<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $controller = new App\Http\Controllers\HomeController();
    $request = Illuminate\Http\Request::create('/', 'GET');
    $view = $controller->index($request);
    $html = $view->render();
    echo 'OK render length=' . strlen($html) . PHP_EOL;
    echo 'bh-cat-sidebar: ' . substr_count($html, 'bh-cat-sidebar') . PHP_EOL;
    echo 'bh-hero: ' . substr_count($html, 'bh-hero') . PHP_EOL;
    echo 'Featured Story: ' . substr_count($html, 'Featured Story') . PHP_EOL;
    echo 'Raw @if/@endif: ' . (substr_count($html, '@if') + substr_count($html, '@endif')) . PHP_EOL;
    echo 'whoops: ' . (substr_count($html, 'Whoops') + substr_count($html, 'Exception')) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}
