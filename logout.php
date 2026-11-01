<?php
require __DIR__ . '/bootstrap.php';

// Logging out changes state, so it must be a POST with the CSRF token (a plain link could be triggered by any site).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
check_csrf();
App\Auth::logout();
flash('logout.done');
redirect('login.php');
