<?php
session_start();
require_once __DIR__ . '/config/db.php';
session_destroy();
redirect('/login.php');
