<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') redirect('/admin/dashboard.php');
    else redirect('/dashboard.php');
}
redirect('/login.php');
