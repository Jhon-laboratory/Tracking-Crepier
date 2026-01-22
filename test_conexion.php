<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'Jorgeserver.database.windows.net';
$dbname = 'DPL';
$username = 'Jmmc';
$password = 'ChaosSoldier01';

$connectionInfo = array(
    "Database" => $dbname,
    "UID" => $username,
    "PWD" => $password,
    "CharacterSet" => "UTF-8",
    "ReturnDatesAsStrings" => true,
    "Encrypt" => true,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($host, $connectionInfo);

if ($conn === false) {
    $errors = sqlsrv_errors();
    echo json_encode([
        "success" => false,
        "error" => $errors[0]['message'] ?? 'Error desconocido',
        "code" => $errors[0]['code'] ?? 0
    ]);
} else {
    echo json_encode(["success" => true]);
    sqlsrv_close($conn);
}
?>