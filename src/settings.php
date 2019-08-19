<?php
use ParagonIE\HiddenString\HiddenString;
if (!is_readable(CRYPTO_ROOT . '/local/cache-key')) {
    $cacheKey = sodium_crypto_shorthash_keygen();
    file_put_contents(
        CRYPTO_ROOT . '/local/cache-key',
        sodium_bin2hex($cacheKey)
    );
} else {
    $cacheKey = sodium_hex2bin(file_get_contents(CRYPTO_ROOT . '/local/cache-key'));
}

// Tie cache key to git commit hash
$commitHash = trim(shell_exec('git rev-parse HEAD'));
$cacheKey = sodium_crypto_generichash(
    $commitHash . $cacheKey,
    '',
    SODIUM_CRYPTO_SHORTHASH_KEYBYTES
);

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        'cache-key' => new HiddenString($cacheKey),

        'ignore-cache' => ($_SERVER['HTTP_HOST'] === 'localhost:8080'),

        'twig' => [
            'basedir' => dirname(__DIR__) . '/doc'
        ]
    ],
];
