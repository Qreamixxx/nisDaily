<?php
require __DIR__ . '/bootstrap.php';

if (App\Auth::user()) {
    redirect('dashboard.php');
}
render('home', [], ['title' => '', 'active' => 'home']);
