<?php
// Test simple de conexión SOAP
try {
    $client = new SoapClient(
        "https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php?wsdl",
        [
            'trace' => 1,
            'exceptions' => 1,
            'connection_timeout' => 15,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        ]
    );
    
    echo "SOAP Client creado exitosamente<br>";
    
    // Ver funciones disponibles
    echo "<pre>";
    print_r($client->__getFunctions());
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error SOAP: " . $e->getMessage() . "<br>";
    echo "Detalles: " . $e->getTraceAsString();
}

// Test cURL directo
echo "<h2>Test cURL:</h2>";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php?wsdl",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10
]);

$result = curl_exec($ch);
if(curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    echo "cURL exitoso. Tamaño respuesta: " . strlen($result) . " bytes<br>";
    echo "<pre>" . htmlspecialchars(substr($result, 0, 500)) . "...</pre>";
}
curl_close($ch);