<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== DEBUG CONSULTA UNIFICADA ===\n\n";

// 1. Verificar extensiones
echo "1. VERIFICANDO EXTENSIONES:\n";
$extensions = ['sqlsrv', 'pdo_sqlsrv', 'curl', 'simplexml', 'mbstring'];
foreach ($extensions as $ext) {
    echo "   $ext: " . (extension_loaded($ext) ? "✓ INSTALADO" : "✗ NO INSTALADO") . "\n";
}

// 2. Verificar funciones SQLSRV
echo "\n2. VERIFICANDO FUNCIONES SQLSRV:\n";
$functions = ['sqlsrv_connect', 'sqlsrv_query', 'sqlsrv_fetch_array', 'sqlsrv_errors'];
foreach ($functions as $func) {
    echo "   $func: " . (function_exists($func) ? "✓ DISPONIBLE" : "✗ NO DISPONIBLE") . "\n";
}

// 3. Probar conexión simple
echo "\n3. PROBANDO CONEXIÓN SQL SERVER:\n";
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
    "TrustServerCertificate" => true,
    "Encrypt" => true
);

echo "   Intentando conectar a: $host\n";
$conn = sqlsrv_connect($host, $connectionInfo);

if ($conn === false) {
    echo "   ✗ ERROR DE CONEXIÓN:\n";
    $errors = sqlsrv_errors();
    foreach ($errors as $error) {
        echo "      - Código: " . $error['code'] . "\n";
        echo "      - Mensaje: " . $error['message'] . "\n";
        echo "      - SQLSTATE: " . $error['SQLSTATE'] . "\n";
    }
} else {
    echo "   ✓ CONEXIÓN EXITOSA\n";
    
    // Probar consulta simple
    echo "\n4. PROBANDO CONSULTA SIMPLE:\n";
    $sql = "SELECT TOP 1 * FROM [externos].[guia_orden]";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        echo "   ✗ ERROR EN CONSULTA:\n";
        $errors = sqlsrv_errors();
        foreach ($errors as $error) {
            echo "      - " . $error['message'] . "\n";
        }
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            echo "   ✓ CONSULTA EXITOSA\n";
            echo "   Registros encontrados: SÍ\n";
            echo "   Columnas: " . implode(', ', array_keys($row)) . "\n";
        } else {
            echo "   ✓ CONSULTA EXITOSA (sin resultados)\n";
        }
        sqlsrv_free_stmt($stmt);
    }
    
    sqlsrv_close($conn);
}

// 5. Probar cURL
echo "\n5. PROBANDO CURL:\n";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "   ✓ cURL instalado\n";
    echo "   Versión: " . $curl_version['version'] . "\n";
    echo "   SSL: " . ($curl_version['features'] & CURL_VERSION_SSL ? "✓ SOPORTADO" : "✗ NO SOPORTADO") . "\n";
} else {
    echo "   ✗ cURL no disponible\n";
}

// 6. Probar SimpleXML
echo "\n6. PROBANDO SIMPLEXML:\n";
$xml_test = '<?xml version="1.0"?><test><item>test</item></test>';
try {
    $xml = simplexml_load_string($xml_test);
    echo "   ✓ SimpleXML funcionando\n";
} catch (Exception $e) {
    echo "   ✗ SimpleXML error: " . $e->getMessage() . "\n";
}

// 7. Verificar zona horaria
echo "\n7. ZONA HORARIA:\n";
echo "   Zona horaria actual: " . date_default_timezone_get() . "\n";
echo "   Fecha actual: " . date('Y-m-d H:i:s') . "\n";

echo "\n=== FIN DEBUG ===\n";
?>