<?php
try {
    $dbh = new PDO('mysql:host=mysql;dbname=mysql', 'root', root);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->exec('SET CHARACTER SET utf8mb4');
    echo '<h1>MySQL-PDO连接成功</h1>' . PHP_EOL;
} catch (PDOException $e) {
    die($e->getMessage());
}

//
$redis = new Redis();
$result = $redis->connect('redis', 6379);
if ($result)
    echo '<h1>Redis连接成功</h1>' . PHP_EOL;

//
phpinfo();
