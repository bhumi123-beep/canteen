<?php
require_once __DIR__ . '/config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}
if (isAdmin()) {
    redirect('views/admin/dashboard.php');
} else {
    redirect('views/customer/home.php');
}
