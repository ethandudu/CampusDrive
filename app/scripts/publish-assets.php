<?php

/**
 * Copies front-end assets installed by Composer into the public assets folder.
 * vendor/ is not web-accessible (see .htaccess), so the files served to the
 * browser live in assets/vendor/. Run automatically after composer install/update.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$assets = [
    'vendor/twbs/bootstrap/dist/css/bootstrap.min.css'        => 'assets/vendor/bootstrap/css/bootstrap.min.css',
    'vendor/twbs/bootstrap/dist/css/bootstrap.min.css.map'    => 'assets/vendor/bootstrap/css/bootstrap.min.css.map',
    'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js'     => 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
    'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js.map' => 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js.map',
];

foreach ($assets as $source => $target) {
    $from = $root . '/' . $source;
    $to = $root . '/' . $target;

    if (!is_file($from)) {
        fwrite(STDERR, "Missing asset: {$source}\n");
        exit(1);
    }

    $dir = dirname($to);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create directory: {$dir}\n");
        exit(1);
    }

    if (!copy($from, $to)) {
        fwrite(STDERR, "Cannot copy {$source} to {$target}\n");
        exit(1);
    }

    echo "Published {$target}\n";
}
