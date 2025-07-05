<?php
// Database configuration
define('DB_CONNECTION', 'mysql');
define('DB_HOST', 'sql312.infinityfree.com');
define('DB_PORT', '3306');
define('DB_DATABASE', 'if0_36743786_aviator');
define('DB_USERNAME', 'if0_36743786');
define('DB_PASSWORD', 'G0LuZzbSxQ');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to execute queries
function executeQuery($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// Helper function to fetch single row
function fetchSingle($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetch() : false;
}

// Helper function to fetch all rows
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetchAll() : false;
}

// Helper function to get total count
function getCount($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetchColumn() : 0;
}
?>