<?php
session_start();
session_unset();
session_destroy();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
redirect('login.php');