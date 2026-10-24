<?php

// Enable mysqli exception mode for robust error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host     = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$database = getenv('DB_NAME') ?: "student_study_planner";
$port     = (int)(getenv('DB_PORT') ?: 3306);
$sslCa    = getenv('DB_SSL_CA') ?: null;

try {
    $conn = mysqli_init();
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        // TiDB Cloud and remote managed MySQL require SSL
        if ($sslCa && file_exists($sslCa)) {
            $conn->ssl_set(NULL, NULL, $sslCa, NULL, NULL);
        } elseif (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
            // Standard CA bundle on Linux/Render/Docker
            $conn->ssl_set(NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);
        } else {
            $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        }
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->real_connect($host, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);
    } else {
        $conn->real_connect($host, $username, $password, $database, $port);
    }
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Show friendly message while not leaking sensitive credentials
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please ensure MySQL is running and properly configured.");
}