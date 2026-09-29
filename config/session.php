<?php
// Keep authenticated sessions available for one year unless the user logs out.
if (session_status() === PHP_SESSION_NONE) {
    $session_lifetime = 31536000;
    ini_set('session.gc_maxlifetime', (string) $session_lifetime);
    ini_set('session.cookie_lifetime', (string) $session_lifetime);
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
?>
