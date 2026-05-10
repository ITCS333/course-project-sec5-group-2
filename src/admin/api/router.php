<?php
chdir(__DIR__);
require_once __DIR__ . '/db.php';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && $uri !== '/index.php') { return false; }
require __DIR__ . '/index.php';