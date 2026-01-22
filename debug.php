<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== DEBUG COMPLETO - CONSULTA UNIFICADA ===\n\n";

// Funci�n para formatear salida
function echoStatus($label, $status, $details = "") {
    $icon = $status ? "✓" : "✗";
    echo "  $icon $label: " . ($status ? "OK" : "FALLO") . "\n";
    if ($details) {
        echo "      $details\n";
    }
}

// ============================================
// 1. INFORMACI�N DEL SISTEMA
// ============================================
echo "1. INFORMACI�N DEL SISTEMA:\n";
echo "  PHP Version: " . phpversion() . "\n";
echo "  Sistema Operativo: " . PHP_OS . "\n";
echo "  SAPI: " . php_sapi_name() . "\n";
echo "  Memory Limit: " . ini_get('memory_limit') . "\n";
echo "  Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "  Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "  Post Max Size: " . ini_get('post_max_size') . "\n";

// ============================================
// 2. VERIFICAR EXTENSIONES
// ============================================
echo "\n2. EXTENSIONES REQUERIDAS:\n";

$required_extensions = [
    'sqlsrv' => 'SQL Server Native Client',
    'pdo_sqlsrv' => 'PDO SQL Server',
    'pdo' => 'PDO',
    'curl' => 'cURL',
    'openssl' => 'OpenSSL',
    'simplexml' => 'SimpleXML',
    'mbstring' => 'Multibyte String',
    'json' => 'JSON',
    'date' => 'Date/Time',
    'dom' => 'DOM',
    'soap' => 'SOAP',
    'xml' => 'XML',
    'xmlreader' => 'XMLReader',
    'xmlwriter' => 'XMLWriter'
];

foreach ($required_extensions as $ext => $desc) {
    $loaded = extension_loaded($ext);
    echoStatus($desc, $loaded);
    
    if ($loaded && in_array($ext, ['curl', 'openssl', 'soap'])) {
        switch ($ext) {
            case 'curl':
                $curl_info = curl_version();
                echo "      Versi�n: " . $curl_info['version'] . "\n";
                echo "      SSL: " . (($curl_info['features'] & CURL_VERSION_SSL) ? "S�" : "No") . "\n";
                echo "      Libz: " . (($curl_info['features'] & CURL_VERSION_LIBZ) ? "S�" : "No") . "\n";
                break;
            case 'openssl':
                echo "      Versi�n: " . OPENSSL_VERSION_TEXT . "\n";
                break;
            case 'soap':
                echo "      Versi�n: " . (defined('SOAP_1_2') ? '1.2' : '1.1') . "\n";
                break;
        }
    }
}

// ============================================
// 3. VERIFICAR FUNCIONES
// ============================================
echo "\n3. FUNCIONES REQUERIDAS:\n";

// SQLSRV
echo "  SQLSRV Functions:\n";
$sqlsrv_funcs = ['sqlsrv_connect', 'sqlsrv_query', 'sqlsrv_fetch_array', 'sqlsrv_errors', 'sqlsrv_close'];
foreach ($sqlsrv_funcs as $func) {
    echoStatus($func, function_exists($func));
}

// PDO
echo "  PDO Functions:\n";
$pdo_funcs = ['PDO', 'PDOStatement'];
$pdo_drivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
echoStatus('PDO Class', class_exists('PDO'));
echo "      Drivers disponibles: " . implode(', ', $pdo_drivers) . "\n";

// cURL
echo "  cURL Functions:\n";
$curl_funcs = ['curl_init', 'curl_setopt', 'curl_exec', 'curl_error', 'curl_close'];
foreach ($curl_funcs as $func) {
    echoStatus($func, function_exists($func));
}

// XML
echo "  XML Functions:\n";
$xml_funcs = ['simplexml_load_string', 'xml_parse', 'DOMDocument'];
foreach ($xml_funcs as $func) {
    if ($func === 'DOMDocument') {
        echoStatus($func, class_exists('DOMDocument'));
    } else {
        echoStatus($func, function_exists($func));
    }
}

// ============================================
// 4. PROBAR CONEXI�N SQL SERVER (SQLSRV)
// ============================================
echo "\n4. PRUEBA CONEXI�N SQL SERVER (SQLSRV):\n";

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
    "Encrypt" => true,
    "LoginTimeout" => 10
);

if (function_exists('sqlsrv_connect')) {
    echo "  Intentando conectar a: $host\n";
    $start = microtime(true);
    $conn = @sqlsrv_connect($host, $connectionInfo);
    $time = round((microtime(true) - $start) * 1000, 2);
    
    if ($conn === false) {
        echo "  ✗ ERROR DE CONEXI�N (" . $time . "ms):\n";
        $errors = sqlsrv_errors();
        foreach ($errors as $error) {
            echo "      - SQLSTATE: " . ($error['SQLSTATE'] ?? 'N/A') . "\n";
            echo "      - Code: " . ($error['code'] ?? 'N/A') . "\n";
            echo "      - Message: " . ($error['message'] ?? 'N/A') . "\n";
        }
    } else {
        echo "  ✓ CONEXI�N EXITOSA (" . $time . "ms)\n";
        
        // Probar consulta
        echo "  Probando consulta...\n";
        $sql = "SELECT TOP 5 * FROM [externos].[guia_orden] ORDER BY id DESC";
        $stmt = @sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            echo "  ✗ ERROR EN CONSULTA:\n";
            $errors = sqlsrv_errors();
            foreach ($errors as $error) {
                echo "      - " . ($error['message'] ?? 'N/A') . "\n";
            }
        } else {
            $row_count = 0;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $row_count++;
                if ($row_count === 1) {
                    echo "  ✓ CONSULTA EXITOSA\n";
                    echo "      Primer registro:\n";
                    foreach ($row as $key => $value) {
                        echo "        $key: " . (is_string($value) ? substr($value, 0, 50) : $value) . "\n";
                    }
                }
            }
            echo "      Total registros: $row_count\n";
            sqlsrv_free_stmt($stmt);
        }
        
        // Verificar estructura de tabla
        echo "  Verificando estructura de tabla...\n";
        $sql_columns = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
                       FROM INFORMATION_SCHEMA.COLUMNS 
                       WHERE TABLE_SCHEMA = 'externos' 
                       AND TABLE_NAME = 'guia_orden'";
        $stmt = @sqlsrv_query($conn, $sql_columns);
        
        if ($stmt) {
            $columns = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $columns[] = $row['COLUMN_NAME'] . " (" . $row['DATA_TYPE'] . ")";
            }
            echo "      Columnas encontradas: " . count($columns) . "\n";
            echo "      Lista: " . implode(', ', $columns) . "\n";
            sqlsrv_free_stmt($stmt);
        }
        
        sqlsrv_close($conn);
    }
} else {
    echo "  ✗ SQLSRV no disponible\n";
}

// ============================================
// 5. PROBAR CONEXI�N PDO SQL SERVER
// ============================================
echo "\n5. PRUEBA CONEXI�N PDO SQL SERVER:\n";

if (in_array('sqlsrv', $pdo_drivers)) {
    try {
        $dsn = "sqlsrv:Server=$host;Database=$dbname";
        echo "  Intentando conectar con DSN: $dsn\n";
        
        $start = microtime(true);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
            PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 10
        ]);
        $time = round((microtime(true) - $start) * 1000, 2);
        
        echo "  ✓ CONEXI�N PDO EXITOSA (" . $time . "ms)\n";
        
        // Probar consulta PDO
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM [externos].[guia_orden]");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "      Total registros en tabla: " . $result['total'] . "\n";
        
        // Probar consulta espec�fica
        $test_guia = "S00123456"; // Cambia esto por una gu�a real para probar
        $stmt = $pdo->prepare("SELECT * FROM [externos].[guia_orden] WHERE guia = ? OR orden_infor = ?");
        $stmt->execute([$test_guia, $test_guia]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo "  ✓ Consulta espec�fica exitosa\n";
            echo "      Datos encontrados para $test_guia\n";
        } else {
            echo "  ✓ Consulta espec�fica exitosa (sin resultados para $test_guia)\n";
        }
        
        $pdo = null;
        
    } catch (PDOException $e) {
        echo "  ✗ ERROR PDO: " . $e->getMessage() . "\n";
        echo "      C�digo: " . $e->getCode() . "\n";
    }
} else {
    echo "  ✗ Driver PDO SQLSRV no disponible\n";
}

// ============================================
// 6. PROBAR CONEXI�N HTTP/CURL
// ============================================
echo "\n6. PRUEBA CONEXI�N HTTP/CURL:\n";

if (function_exists('curl_init')) {
    // Test 1: Conectar a Servientrega
    echo "  Test 1: Conectar a Servientrega...\n";
    $url = "https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php?wsdl";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    $start = microtime(true);
    $result = curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000, 2);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    if ($error) {
        echo "  ✗ ERROR CURL: $error\n";
    } else {
        echo "  ✓ HTTP Code: $http_code (" . $time . "ms)\n";
        
        if ($http_code == 200) {
            echo "      ✓ Servicio Servientrega accesible\n";
            
            // Test 2: Descargar WSDL completo
            echo "  Test 2: Descargar WSDL completo...\n";
            curl_setopt($ch, CURLOPT_NOBODY, false);
            $start = microtime(true);
            $wsdl_content = curl_exec($ch);
            $time = round((microtime(true) - $start) * 1000, 2);
            $size = strlen($wsdl_content);
            
            echo "      Tama�o WSDL: " . $size . " bytes (" . $time . "ms)\n";
            
            if ($size > 0) {
                echo "      ✓ WSDL descargado exitosamente\n";
                
                // Verificar si es XML v�lido
                if (simplexml_load_string($wsdl_content)) {
                    echo "      ✓ WSDL es XML v�lido\n";
                } else {
                    echo "      ✗ WSDL no es XML v�lido\n";
                    echo "      Primeros 200 chars:\n" . substr($wsdl_content, 0, 200) . "\n";
                }
            }
        }
    }
    
    curl_close($ch);
    
    // Test 3: Conectar a Infor
    echo "\n  Test 3: Conectar a Infor CloudSuite...\n";
    $url_infor = "https://mingle-sso.inforcloudsuite.com:443/RANSA_PRD/as/token.oauth2";
    
    $ch_infor = curl_init();
    curl_setopt_array($ch_infor, [
        CURLOPT_URL => $url_infor,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $start = microtime(true);
    $result_infor = curl_exec($ch_infor);
    $time = round((microtime(true) - $start) * 1000, 2);
    $http_code_infor = curl_getinfo($ch_infor, CURLINFO_HTTP_CODE);
    $error_infor = curl_error($ch_infor);
    
    if ($error_infor) {
        echo "  ✗ ERROR CURL Infor: $error_infor\n";
    } else {
        echo "  ✓ HTTP Code Infor: $http_code_infor (" . $time . "ms)\n";
    }
    
    curl_close($ch_infor);
    
} else {
    echo "  ✗ cURL no disponible\n";
}

// ============================================
// 7. PROBAR SOAP CLIENT
// ============================================
echo "\n7. PRUEBA SOAP CLIENT:\n";

if (class_exists('SoapClient')) {
    try {
        $wsdl_url = "https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php?wsdl";
        echo "  Creando SoapClient para: $wsdl_url\n";
        
        $options = [
            'trace' => 1,
            'exceptions' => 1,
            'connection_timeout' => 15,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        ];
        
        $start = microtime(true);
        $soap_client = new SoapClient($wsdl_url, $options);
        $time = round((microtime(true) - $start) * 1000, 2);
        
        echo "  ✓ SoapClient creado exitosamente (" . $time . "ms)\n";
        
        // Verificar funciones disponibles
        $functions = $soap_client->__getFunctions();
        echo "      Funciones disponibles:\n";
        foreach ($functions as $func) {
            echo "        - " . $func . "\n";
        }
        
        // Probar llamada al m�todo
        echo "  Probando m�todo ConsultarGuiaImagen...\n";
        try {
            $result = $soap_client->__soapCall('ConsultarGuiaImagen', [['guia' => 'TEST123']]);
            echo "      ✓ M�todo ejecutado\n";
        } catch (SoapFault $f) {
            echo "      ✗ SoapFault: " . $f->getMessage() . "\n";
            echo "      (Esto puede ser normal si la gu�a no existe)\n";
        }
        
    } catch (Exception $e) {
        echo "  ✗ ERROR SoapClient: " . $e->getMessage() . "\n";
        echo "      C�digo: " . $e->getCode() . "\n";
    }
} else {
    echo "  ✗ SoapClient no disponible\n";
}

// ============================================
// 8. PROBAR XML PARSING
// ============================================
echo "\n8. PRUEBA XML PARSING:\n";

// Test SimpleXML
echo "  Test SimpleXML:\n";
$xml_test = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ConsultarGuiaImagenResponse>
      <Result>&lt;xml&gt;&lt;guia&gt;TEST123&lt;/guia&gt;&lt;status&gt;ACTIVA&lt;/status&gt;&lt;/xml&gt;</Result>
    </ConsultarGuiaImagenResponse>
  </soap:Body>
</soap:Envelope>';

try {
    $xml = simplexml_load_string($xml_test);
    if ($xml) {
        echo "  ✓ SimpleXML carga exitosa\n";
        
        // Probar XPath
        $result = $xml->xpath('//Result');
        if (!empty($result)) {
            echo "  ✓ XPath funcionando\n";
            
            // Probar XML interno
            $inner_xml = simplexml_load_string((string)$result[0]);
            if ($inner_xml) {
                echo "  ✓ XML interno parseado\n";
            }
        }
    }
} catch (Exception $e) {
    echo "  ✗ SimpleXML error: " . $e->getMessage() . "\n";
}

// Test DOMDocument
echo "\n  Test DOMDocument:\n";
if (class_exists('DOMDocument')) {
    $dom = new DOMDocument();
    if (@$dom->loadXML($xml_test)) {
        echo "  ✓ DOMDocument carga exitosa\n";
    } else {
        echo "  ✗ DOMDocument error de carga\n";
    }
}

// ============================================
// 9. PROBAR CONEXI�N A INTERNET
// ============================================
echo "\n9. PRUEBA CONEXI�N A INTERNET:\n";

$test_urls = [
    'Google DNS' => 'https://8.8.8.8',
    'Google' => 'https://www.google.com',
    'Microsoft Azure' => 'https://azure.microsoft.com',
    'Servientrega' => 'https://servientrega-ecuador.appsiscore.com',
    'Infor Cloud' => 'https://mingle-sso.inforcloudsuite.com'
];

foreach ($test_urls as $name => $url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $start = microtime(true);
        @curl_exec($ch);
        $time = round((microtime(true) - $start) * 1000, 2);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $status = ($http_code > 0);
        echoStatus($name . " ($url)", $status, "HTTP $http_code (" . $time . "ms)");
    }
}

// ============================================
// 10. VERIFICAR CONFIGURACIONES INI
// ============================================
echo "\n10. CONFIGURACIONES PHP.INI:\n";

$ini_settings = [
    'allow_url_fopen',
    'allow_url_include',
    'default_socket_timeout',
    'openssl.cafile',
    'openssl.capath',
    'curl.cainfo',
    'soap.wsdl_cache_enabled',
    'soap.wsdl_cache_dir'
];

foreach ($ini_settings as $setting) {
    $value = ini_get($setting);
    echo "  $setting: " . ($value ? $value : '(no configurado)') . "\n";
}

// ============================================
// 11. PRUEBA DE MEMORIA Y PERFORMANCE
// ============================================
echo "\n11. PRUEBA DE MEMORIA:\n";

$memory_start = memory_get_usage();
$test_array = [];
for ($i = 0; $i < 10000; $i++) {
    $test_array[] = str_repeat('test', 100);
}
$memory_used = memory_get_usage() - $memory_start;
echo "  Memoria usada en test: " . round($memory_used / 1024, 2) . " KB\n";

// ============================================
// 12. RESUMEN
// ============================================
echo "\n12. RESUMEN:\n";

$critical_checks = [
    'SQLSRV Extension' => extension_loaded('sqlsrv'),
    'PDO SQLSRV Driver' => in_array('sqlsrv', $pdo_drivers),
    'cURL Extension' => extension_loaded('curl'),
    'OpenSSL' => extension_loaded('openssl'),
    'SimpleXML' => extension_loaded('simplexml'),
    'SOAP' => extension_loaded('soap')
];

$all_ok = true;
foreach ($critical_checks as $check => $status) {
    $icon = $status ? "✓" : "✗";
    echo "  $icon $check\n";
    if (!$status) $all_ok = false;
}

echo "\n" . str_repeat("=", 50) . "\n";
if ($all_ok) {
    echo "✓ TODOS LOS COMPONENTES CR�TICOS EST�N DISPONIBLES\n";
} else {
    echo "✗ FALTAN COMPONENTES CR�TICOS\n";
}
echo str_repeat("=", 50) . "\n";

// ============================================
// 13. RECOMENDACIONES
// ============================================
echo "\n13. RECOMENDACIONES:\n";

if (!extension_loaded('sqlsrv')) {
    echo "  - Instalar extensi�n SQLSRV para PHP 8.5:\n";
    echo "    Para Ubuntu/Debian: sudo apt-get install php8.5-sqlsrv\n";
    echo "    Para Windows: Descargar de https://pecl.php.net/package/sqlsrv\n";
}

if (!in_array('sqlsrv', $pdo_drivers)) {
    echo "  - Instalar driver PDO SQLSRV\n";
}

if (!extension_loaded('soap')) {
    echo "  - Instalar extensi�n SOAP: sudo apt-get install php8.5-soap\n";
}

echo "\n=== FIN DEBUG ===\n";

// Guardar resultado en archivo de log
$log_content = ob_get_contents();
file_put_contents('debug_completo_' . date('Y-m-d_His') . '.txt', $log_content);
?>