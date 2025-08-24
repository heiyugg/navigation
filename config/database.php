<?php
$db_config = [
    'host' => 'localhost',
    'username' => 'jjj',
    'password' => 'jjjjjj',
    'database' => 'jjj',
    'charset' => 'utf8mb4'
];

$pdo = null;

function getDatabase() {
    global $pdo, $db_config;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['database']};charset={$db_config['charset']}";
            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}
?>