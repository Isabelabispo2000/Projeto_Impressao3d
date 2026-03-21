<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function octoview_db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $host = getenv('OCTOVIEW_DB_HOST') ?: 'localhost';
    $user = getenv('OCTOVIEW_DB_USER') ?: 'root';
    $pass = getenv('OCTOVIEW_DB_PASS') ?: '';
    $preferred = getenv('OCTOVIEW_DB_NAME') ?: 'sistema_impressao3d';
    $candidates = array_values(array_unique([$preferred, 'Sistema_impressao3d', 'impressao3d']));

    $lastError = null;
    foreach ($candidates as $database) {
        try {
            $conn = new mysqli($host, $user, $pass, $database);
            $conn->set_charset('utf8mb4');
            return $conn;
        } catch (mysqli_sql_exception $exception) {
            $lastError = $exception;
        }
    }

    throw new RuntimeException(
        'Nao foi possivel conectar ao banco de dados OctoView. Defina OCTOVIEW_DB_NAME se necessario.',
        previous: $lastError
    );
}

