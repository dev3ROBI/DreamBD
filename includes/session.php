<?php
const DREAM_SESSION_LIFETIME = 86400;

function dream_start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.gc_maxlifetime', (string) DREAM_SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => DREAM_SESSION_LIFETIME,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
?>
