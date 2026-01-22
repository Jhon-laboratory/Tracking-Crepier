<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log para debugging
$debug_log = [];

// Validar que se proporcionó un valor para buscar
if(!isset($_GET['valor']) || empty($_GET['valor'])){
    echo json_encode(["error" => "No se proporcionó número de guía u orden"]);
    exit;
}

$valor = trim($_GET['valor']);
$debug_log['input_valor'] = $valor;
$debug_log['timestamp'] = date('Y-m-d H:i:s');
$debug_log['php_version'] = phpversion();

// Configuración de la base de datos Azure
$host = 'Jorgeserver.database.windows.net';
$dbname = 'DPL';
$username = 'Jmmc';
$password = 'ChaosSoldier01';
$puerto = 1433;
$schema = 'externos';
$table = 'guia_orden';

// Variables para almacenar resultados
$datosAzure = null;
$ordenParaInfor = null;
$guiaParaServientrega = null;
$encontradoEnAzure = false;

try {
    $debug_log['azure_connection'] = "Intentando conectar...";
    
    // Conexión a la base de datos Azure
    $dsn = "sqlsrv:Server=$host,$puerto;Database=$dbname";
    $debug_log['azure_dsn'] = $dsn;
    
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $debug_log['azure_connection_success'] = "Conexión exitosa a Azure SQL";
    
    // Consulta corregida - buscar por guía O por orden_infor
    $sql = "SELECT * FROM [$schema].[$table] WHERE guia = :valor OR orden_infor = :valor2";
    $debug_log['azure_sql'] = $sql;
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':valor2', $valor);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($row){
        $encontradoEnAzure = true;
        $datosAzure = $row;
        $debug_log['azure_found'] = "Registro encontrado en Azure";
        $debug_log['azure_data'] = $row;
        
        // Determinar qué valor tenemos y qué necesitamos
        $guia = $row['guia'];
        $ordenInfor = $row['orden_infor'];
        
        // Si encontramos el registro, usar los valores de la base de datos
        $ordenParaInfor = $ordenInfor;
        $guiaParaServientrega = $guia;
        
        $debug_log['orden_para_infor'] = $ordenParaInfor;
        $debug_log['guia_para_servientrega'] = $guiaParaServientrega;
    } else {
        // No encontrado en Azure
        $debug_log['azure_found'] = "NO encontrado en Azure";
        $ordenParaInfor = $valor; // Usar el valor ingresado como posible orden
    }

} catch(PDOException $e) {
    // Error de conexión a Azure
    $debug_log['azure_error'] = "Error PDO: " . $e->getMessage();
    $debug_log['azure_error_code'] = $e->getCode();
    $ordenParaInfor = $valor; // Usar el valor ingresado como posible orden
    $datosAzure = ["error" => "Error de conexión a Azure: " . $e->getMessage()];
}

// Array para almacenar todos los resultados
$resultadosCompletos = [
    'datos_azure' => $datosAzure,
    'infor' => null,
    'servientrega' => null,
    'encontrado_en_azure' => $encontradoEnAzure,
    'debug_log' => $debug_log
];

// ---------- FUNCIÓN PARA RESTAR 4 HORAS ----------
function restarHoras($fechaStr, $horas = 4) {
    if (empty($fechaStr) || $fechaStr === "-") {
        return $fechaStr;
    }
    
    try {
        $fecha = new DateTime($fechaStr);
        $fecha->modify("-$horas hours");
        return $fecha->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $fechaStr;
    }
}

// ---------- FUNCIÓN PARA CONSULTAR INFOR ----------
function consultarInfor($ordenParaConsultar) {
    global $debug_log;
    $debug_log['infor_start'] = "Iniciando consulta Infor para: " . $ordenParaConsultar;
    
    // Si no hay orden válida para consultar
    if (empty($ordenParaConsultar) || $ordenParaConsultar === "No disponible" || 
        $ordenParaConsultar === "No se encontró la guía en la base de datos") {
        $debug_log['infor_error'] = "No hay orden válida para consultar Infor";
        return ["error" => "No hay orden válida para consultar Infor"];
    }
    
    // Generar Token para Infor
    $urlToken = "https://mingle-sso.inforcloudsuite.com:443/RANSA_PRD/as/token.oauth2";
    $dataToken = [
        "grant_type" => "password",
        "username" => "RANSA_PRD#MOKINRdXbbD00lZK_lHS_yZbVA0LzN00UB4nSN5kWrsbQ-lohV8eqjuau329XpqRFWc7Njaro_GmYJg1Sv9eyQ",
        "password" => "xWU0qhiUWucTns-GQWPLAG9DGwIFpezHmEr1Opslt3FMZ6MZ39jkSjg_2JjRNVmgUkzLPbPvsyOSgGrJE1sAGg",
        "client_id" => "RANSA_PRD~pjoQpgw_5-hD4-u0xG3tlmUWyhrVnq7uwSuvbgo6dZg",
        "client_secret" => "fQSXR0FtOVgGBSBSj9CAcMrQonRZXOAb0sQQLncClxD2AKVnPMKqx2JnPkmRC6AF1nN-_ANZCokwAe6woFnxYQ"
    ];
    
    $debug_log['infor_token_url'] = $urlToken;
    
    $chToken = curl_init($urlToken);
    curl_setopt($chToken, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chToken, CURLOPT_POST, true);
    curl_setopt($chToken, CURLOPT_POSTFIELDS, http_build_query($dataToken));
    curl_setopt($chToken, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chToken, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($chToken, CURLOPT_TIMEOUT, 30);
    
    // Debug cURL
    curl_setopt($chToken, CURLOPT_VERBOSE, true);
    $verboseToken = fopen('php://temp', 'w+');
    curl_setopt($chToken, CURLOPT_STDERR, $verboseToken);
    
    $responseToken = curl_exec($chToken);
    $httpCodeToken = curl_getinfo($chToken, CURLINFO_HTTP_CODE);
    $curlErrorToken = curl_error($chToken);
    
    $debug_log['infor_token_response_code'] = $httpCodeToken;
    $debug_log['infor_token_curl_error'] = $curlErrorToken;
    
    if ($responseToken === false) {
        $debug_log['infor_token_error'] = "Error generando token: " . $curlErrorToken;
        rewind($verboseToken);
        $debug_log['infor_token_verbose'] = stream_get_contents($verboseToken);
        fclose($verboseToken);
        curl_close($chToken);
        return ["error" => "Error generando token: " . $curlErrorToken];
    } else {
        $debug_log['infor_token_response_raw'] = substr($responseToken, 0, 200);
        $resultToken = json_decode($responseToken, true);
        $debug_log['infor_token_decoded'] = $resultToken;
        
        if (isset($resultToken["access_token"])) {
            $token = $resultToken["access_token"];
            $debug_log['infor_token_success'] = "Token obtenido exitosamente";
            
            // Consultar la API de Infor
            $urlInfor = "https://mingle-ionapi.inforcloudsuite.com/RANSA_PRD/WM/wmwebservice_rest/RANSA_PRD_RANSA_PRD_SCE_PRD_0_wmwhse3/shipments/externorderkey/$ordenParaConsultar";
            $debug_log['infor_api_url'] = $urlInfor;
            
            $chInfor = curl_init($urlInfor);
            curl_setopt($chInfor, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chInfor, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $token",
                "x-infor-tenantID: RANSA_PRD",
                "Accept: application/json"
            ]);
            curl_setopt($chInfor, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chInfor, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($chInfor, CURLOPT_TIMEOUT, 30);
            
            // Debug cURL para Infor
            curl_setopt($chInfor, CURLOPT_VERBOSE, true);
            $verboseInfor = fopen('php://temp', 'w+');
            curl_setopt($chInfor, CURLOPT_STDERR, $verboseInfor);
            
            $responseInfor = curl_exec($chInfor);
            $httpCodeInfor = curl_getinfo($chInfor, CURLINFO_HTTP_CODE);
            $curlErrorInfor = curl_error($chInfor);
            
            $debug_log['infor_api_response_code'] = $httpCodeInfor;
            $debug_log['infor_api_curl_error'] = $curlErrorInfor;
            $debug_log['infor_api_response_raw'] = substr($responseInfor, 0, 500);
            
            curl_close($chInfor);
            
            rewind($verboseInfor);
            $debug_log['infor_api_verbose'] = stream_get_contents($verboseInfor);
            fclose($verboseInfor);
            
            $resultInfor = json_decode($responseInfor, true);
            
            if($httpCodeInfor === 200 && $resultInfor && !isset($resultInfor['fault']['faultstring'])){
                $debug_log['infor_api_success'] = "Datos Infor obtenidos exitosamente";
                
                // Restar 4 horas a las fechas
                $adddate = isset($resultInfor['adddate']) ? restarHoras($resultInfor['adddate']) : '';
                $editdate = isset($resultInfor['editdate']) ? restarHoras($resultInfor['editdate']) : '';
                $actualshipdate = isset($resultInfor['actualshipdate']) ? restarHoras($resultInfor['actualshipdate']) : '';
                
                return [
                    "success" => true,
                    "adddate" => formatearFechaInfor($adddate),
                    "editdate" => formatearFechaInfor($editdate),
                    "actualshipdate" => formatearFechaInfor($actualshipdate),
                    "ccompany" => $resultInfor['ccompany'] ?? '',
                    "orderkey" => $resultInfor['orderkey'] ?? '',
                    "ccity" => $resultInfor['ccity'] ?? '',
                    "type" => $resultInfor['type'] ?? '',
                    "status" => $resultInfor['status'] ?? '',
                    "straddress1" => $resultInfor['straddress1'] ?? '',
                    "straddress2" => $resultInfor['straddress2'] ?? '',
                    "direccion" => ($resultInfor['straddress1'] ?? '') . ' ' . ($resultInfor['straddress2'] ?? ''),
                    // Datos crudos para lógica interna
                    "raw_adddate" => $adddate,
                    "raw_editdate" => $editdate,
                    "raw_actualshipdate" => $actualshipdate
                ];
            } else {
                $debug_log['infor_api_error'] = "Error en respuesta Infor";
                $debug_log['infor_api_result'] = $resultInfor;
                return [
                    "error" => "No se encontraron datos en Infor para esta orden",
                    "http_code" => $httpCodeInfor,
                    "debug" => $resultInfor
                ];
            }
        } else {
            $debug_log['infor_token_error'] = "No se pudo generar token para Infor";
            return ["error" => "No se pudo generar token para Infor", "debug" => $resultToken];
        }
    }
    curl_close($chToken);
}

// ---------- FUNCIÓN PARA CONSULTAR SERVIENTREGA ----------
function consultarServientrega($guiaParaConsultar) {
    global $debug_log;
    $debug_log['servientrega_start'] = "Iniciando consulta Servientrega para: " . $guiaParaConsultar;
    
    if (empty($guiaParaConsultar)) {
        $debug_log['servientrega_error'] = "No hay guía válida para consultar Servientrega";
        return ["error" => "No hay guía válida para consultar Servientrega"];
    }
    
    $xmlServientrega = '
    <soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                      xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                      xmlns:ws="https://servientrega-ecuador.appsiscore.com/app/ws/">
      <soapenv:Header/>
      <soapenv:Body>
        <ws:ConsultarGuiaImagen soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
          <guia xsi:type="xsd:string">'.$guiaParaConsultar.'</guia>
        </ws:ConsultarGuiaImagen>
      </soapenv:Body>
    </soapenv:Envelope>';

    $debug_log['servientrega_xml_length'] = strlen($xmlServientrega);
    $debug_log['servientrega_xml_preview'] = substr($xmlServientrega, 0, 200) . "...";
    
    $url = "https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php?wsdl";
    $debug_log['servientrega_url'] = $url;

    // PRIMERO: Intentar con cURL
    $debug_log['servientrega_method'] = "Usando cURL";
    
    $chServientrega = curl_init($url);
    curl_setopt($chServientrega, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chServientrega, CURLOPT_HTTPHEADER, [
        "Content-Type: text/xml; charset=ISO-8859-1",
        "Content-Length: " . strlen($xmlServientrega),
        "SOAPAction: \"\""
    ]);
    curl_setopt($chServientrega, CURLOPT_POST, true);
    curl_setopt($chServientrega, CURLOPT_POSTFIELDS, $xmlServientrega);
    
    // Configuración para PHP 8+
    curl_setopt($chServientrega, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chServientrega, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($chServientrega, CURLOPT_TIMEOUT, 30);
    curl_setopt($chServientrega, CURLOPT_CONNECTTIMEOUT, 15);
    
    // Para debugging
    curl_setopt($chServientrega, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($chServientrega, CURLOPT_STDERR, $verbose);

    $responseServientrega = curl_exec($chServientrega);
    $httpCodeServientrega = curl_getinfo($chServientrega, CURLINFO_HTTP_CODE);
    $curlErrorServientrega = curl_error($chServientrega);
    
    $debug_log['servientrega_http_code'] = $httpCodeServientrega;
    $debug_log['servientrega_curl_error'] = $curlErrorServientrega;
    $debug_log['servientrega_response_length'] = strlen($responseServientrega);
    
    // Obtener verbose log
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    $debug_log['servientrega_curl_verbose'] = $verboseLog;
    
    curl_close($chServientrega);

    if ($curlErrorServientrega) {
        $debug_log['servientrega_fallback'] = "Intentando con SoapClient...";
        
        // SEGUNDO: Intentar con SoapClient como fallback
        try {
            $soapOptions = [
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
            
            $debug_log['servientrega_soap_options'] = $soapOptions;
            
            $client = new SoapClient($url, $soapOptions);
            $debug_log['servientrega_soap_client'] = "SoapClient creado exitosamente";
            
            $result = $client->ConsultarGuiaImagen(['guia' => $guiaParaConsultar]);
            $debug_log['servientrega_soap_result'] = "Resultado Soap recibido";
            
            $responseServientrega = $client->__getLastResponse();
            $debug_log['servientrega_soap_response_length'] = strlen($responseServientrega);
            
        } catch (SoapFault $e) {
            $debug_log['servientrega_soap_error'] = "SoapFault: " . $e->getMessage();
            return ["error" => "Error SOAP: " . $e->getMessage()];
        } catch (Exception $e) {
            $debug_log['servientrega_soap_exception'] = "Exception: " . $e->getMessage();
            return ["error" => "Error general: " . $e->getMessage()];
        }
    } 
    
    if (empty($responseServientrega)) {
        $debug_log['servientrega_error'] = "Respuesta completamente vacía";
        return ["error" => "La respuesta de Servientrega está vacía", "http_code" => $httpCodeServientrega];
    }
    
    $debug_log['servientrega_response_preview'] = substr($responseServientrega, 0, 500);
    
    // Procesar la respuesta XML
    try {
        // Intentar diferentes encodings
        $xmlObj = simplexml_load_string($responseServientrega);
        
        if ($xmlObj === false) {
            // Intentar con encoding diferente
            $responseServientrega = mb_convert_encoding($responseServientrega, 'UTF-8', 'ISO-8859-1');
            $xmlObj = simplexml_load_string($responseServientrega);
            
            if ($xmlObj === false) {
                $debug_log['servientrega_xml_error'] = "Error al parsear XML incluso con conversion";
                $debug_log['servientrega_response_hex'] = bin2hex(substr($responseServientrega, 0, 100));
                return ["error" => "Error al parsear XML de Servientrega"];
            }
        }
        
        $debug_log['servientrega_xml_parsed'] = "XML parseado exitosamente";
        
        // Buscar el resultado
        $resultados = $xmlObj->xpath('//Result');
        $debug_log['servientrega_xpath_results'] = count($resultados);
        
        if(isset($resultados[0])){
            $xmlInterno = simplexml_load_string((string)$resultados[0]);
            
            if($xmlInterno){
                // Extraer datos de Servientrega
                $dirRemElement = $xmlInterno->xpath('//DirRem');
                $estadoElement = $xmlInterno->xpath('//Est');
                $fecenvElement = $xmlInterno->xpath('//FecEnv');
                $fechaEntregaElement = $xmlInterno->xpath('//FecEnt');
                $quirecElement = $xmlInterno->xpath('//quienrecibe');
                $dirDestinoElement = $xmlInterno->xpath('//DirDes');
                $placaoElement = $xmlInterno->xpath('//Placa');
                $infoMovElements = $xmlInterno->xpath('//InformacionMov');
                $imagenElement = $xmlInterno->xpath('//MensajeBuscarImagen');
                
                // Debug de elementos encontrados
                $debug_log['servientrega_elements'] = [
                    'DirRem_found' => !empty($dirRemElement),
                    'Est_found' => !empty($estadoElement),
                    'FecEnv_found' => !empty($fecenvElement),
                    'FecEnt_found' => !empty($fechaEntregaElement),
                    'Movimientos_found' => count($infoMovElements)
                ];
                
                // Detectar si la guía no existe
                $fecEnvValue = isset($fecenvElement[0]) ? (string)$fecenvElement[0] : "";
                $fecEntValue = isset($fechaEntregaElement[0]) ? (string)$fechaEntregaElement[0] : "";
                $guiaNoExiste = (empty($fecEnvValue) && empty($fecEntValue));
                
                // Procesar movimientos
                $movimientos = [];
                $fechasMov = [];
                
                if (!empty($infoMovElements)) {
                    $movimientosSinFormatear = [];
                    
                    foreach ($infoMovElements as $infoMov) {
                        $nomMovElement = $infoMov->xpath('.//NomMov');
                        $fecMovElement = $infoMov->xpath('.//FecMov');
                        
                        if (isset($nomMovElement[0]) && isset($fecMovElement[0])) {
                            $nomMov = (string)$nomMovElement[0];
                            $fecMov = (string)$fecMovElement[0];
                            
                            if (!empty($nomMov) && !empty($fecMov)) {
                                // Restar 4 horas a la fecha del movimiento
                                $fecMovAjustada = restarHoras($fecMov);
                                
                                $movimientosSinFormatear[] = [
                                    "NomMov" => $nomMov,
                                    "FecMov" => $fecMovAjustada,
                                    "FecMovFormateada" => formatearFechaServientrega($fecMovAjustada)
                                ];
                                $fechasMov[] = $fecMovAjustada;
                            }
                        }
                    }
                    
                    // Ordenar movimientos cronológicamente
                    usort($movimientosSinFormatear, function($a, $b) {
                        return strtotime($a['FecMov']) - strtotime($b['FecMov']);
                    });
                    
                    usort($fechasMov, function($a, $b) {
                        return strtotime($a) - strtotime($b);
                    });
                    
                    $fechasMov = array_map('formatearFechaServientrega', $fechasMov);
                    
                    foreach ($movimientosSinFormatear as $mov) {
                        $movimientos[] = [
                            "NomMov" => $mov["NomMov"],
                            "FecMov" => $mov["FecMovFormateada"]
                        ];
                    }
                }
                
                // Procesar imagen
                $imagenBase64 = "";
                if(isset($imagenElement[0])){
                    $imagenBase64 = (string)$imagenElement[0];
                    $imagenBase64 = preg_replace('/<[^>]*>/', '', $imagenBase64);
                    $debug_log['servientrega_image_found'] = "Imagen encontrada, tamaño base64: " . strlen($imagenBase64);
                }
                
                // Determinar si la guía es válida
                $guiaValida = !$guiaNoExiste;
                if (empty($dirRemElement[0]) && empty($estadoElement[0]) && empty($fecenvElement[0])) {
                    $guiaValida = false;
                }
                
                // Ajustar fechas de Servientrega (restar 4 horas)
                $fechaEnvio = isset($fecenvElement[0]) ? restarHoras((string)$fecenvElement[0]) : "";
                $fechaEntrega = isset($fechaEntregaElement[0]) ? restarHoras((string)$fechaEntregaElement[0]) : "";
                
                $resultado = [
                    "guia" => $guiaParaConsultar,
                    "valida" => $guiaValida,
                    "no_existe" => $guiaNoExiste,
                    "direccion_remitente" => isset($dirRemElement[0]) ? (string)$dirRemElement[0] : "",
                    "estado" => isset($estadoElement[0]) ? (string)$estadoElement[0] : "",
                    "fecha_envio" => !empty($fechaEnvio) ? formatearFechaServientrega($fechaEnvio) : "",
                    "fecha_entrega" => !empty($fechaEntrega) ? formatearFechaServientrega($fechaEntrega) : "",
                    "nombre_receptor" => isset($quirecElement[0]) ? (string)$quirecElement[0] : "",
                    "direccion_receptor" => isset($dirDestinoElement[0]) ? (string)$dirDestinoElement[0] : "",
                    "placa" => isset($placaoElement[0]) ? (string)$placaoElement[0] : "",
                    "fechas_movimiento" => $fechasMov,
                    "movimientos" => $movimientos,
                    "imagen_base64" => $imagenBase64,
                    // Datos crudos para lógica interna
                    "raw_fecha_envio" => $fechaEnvio,
                    "raw_fecha_entrega" => $fechaEntrega
                ];
                
                $debug_log['servientrega_success'] = "Consulta exitosa";
                return $resultado;
                
            } else {
                $debug_log['servientrega_error'] = "Error al parsear XML interno de Servientrega";
                return ["error" => "Error al parsear XML interno de Servientrega"];
            }
        } else {
            $debug_log['servientrega_error'] = "No se encontró el tag Result en la respuesta";
            $debug_log['servientrega_response_sample'] = substr($responseServientrega, 0, 300);
            return ["error" => "No se encontró el tag Result en la respuesta de Servientrega"];
        }
    } catch (Exception $e) {
        $debug_log['servientrega_exception'] = "Exception: " . $e->getMessage();
        return ["error" => "Excepción al procesar respuesta: " . $e->getMessage()];
    }
}

// ---------- FUNCIONES DE FORMATEO DE FECHAS ----------
function formatearFechaInfor($fechaIso){
    if(empty($fechaIso) || $fechaIso === "-") return "";
    try {
        $fecha = DateTime::createFromFormat(DateTime::ISO8601, $fechaIso);
        
        if (!$fecha) {
            $fecha = new DateTime($fechaIso);
        }
        
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $dia = $fecha->format('d');
        $mes = $meses[(int)$fecha->format('m')];
        $anio = $fecha->format('Y');
        $hora = $fecha->format('H:i');
        
        return "$dia/$mes/$anio $hora";
        
    } catch (Exception $e) {
        return $fechaIso;
    }
}

function formatearFechaServientrega($fechaStr) {
    if(empty($fechaStr) || $fechaStr === "-") return "";
    try {
        $fecha = DateTime::createFromFormat('Y-m-d H:i:s', $fechaStr);
        
        if (!$fecha) {
            $fecha = new DateTime($fechaStr);
        }
        
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $dia = $fecha->format('d');
        $mes = $meses[(int)$fecha->format('m')];
        $anio = $fecha->format('Y');
        $hora = $fecha->format('H:i');
        
        return "$dia/$mes/$anio $hora";
        
    } catch (Exception $e) {
        return $fechaStr;
    }
}

// ---------- FUNCIÓN PARA ASIGNAR FECHAS A ESTADOS ----------
function asignarFechasAEstados($datosInfor, $datosServientrega) {
    $fechasEstados = [
        'pedido_registrado' => '',
        'recibido_almacen' => '',
        'preparacion' => '',
        'despacho' => '',
        'esperando_recoleccion' => '',
        'recogido_origen' => '',
        'ruta_entrega' => '',
        'entrega' => ''
    ];
    
    // 1. Pedido Registrado - siempre usar adddate de Infor
    if (!empty($datosInfor['raw_adddate'])) {
        $fechasEstados['pedido_registrado'] = $datosInfor['adddate'];
    }
    
    // 2. Recibido en Almacén - usar adddate si existe
    if (!empty($datosInfor['raw_adddate'])) {
        $fechasEstados['recibido_almacen'] = $datosInfor['adddate'];
    }
    
    // 3. En Preparación - usar editdate si existe
    if (!empty($datosInfor['raw_editdate'])) {
        $fechasEstados['preparacion'] = $datosInfor['editdate'];
    }
    
    // 4. Listo para Despacho - usar editdate o actualshipdate
    if (!empty($datosInfor['raw_editdate'])) {
        $fechasEstados['despacho'] = $datosInfor['editdate'];
    } elseif (!empty($datosInfor['raw_actualshipdate'])) {
        $fechasEstados['despacho'] = $datosInfor['actualshipdate'];
    }
    
    // 5. Estados de Servientrega
    if ($datosServientrega && isset($datosServientrega['movimientos'])) {
        foreach ($datosServientrega['movimientos'] as $movimiento) {
            $nomMov = $movimiento['NomMov'] ?? '';
            
            // Esperando Recolección
            if (stripos($nomMov, 'Generado Cliente Corporativo') !== false || 
                stripos($nomMov, 'Ingresando de Recoleccion') !== false) {
                $fechasEstados['esperando_recoleccion'] = $movimiento['FecMov'] ?? '';
            }
            
            // Recogido en Origen
            if (stripos($nomMov, 'Ingreso de recoleccion') !== false) {
                $fechasEstados['recogido_origen'] = $movimiento['FecMov'] ?? '';
            }
            
            // En Ruta de Entrega
            if (stripos($nomMov, 'En Distribucion a Cliente') !== false) {
                $fechasEstados['ruta_entrega'] = $movimiento['FecMov'] ?? '';
            }
            
            // Entregado
            if (stripos($nomMov, 'Reportado Entregado en App') !== false ||
                stripos($nomMov, 'Certificacion de Prueba de Entrega') !== false ||
                stripos($nomMov, 'Entrega Digitalizada') !== false) {
                $fechasEstados['entrega'] = $movimiento['FecMov'] ?? '';
            }
        }
    }
    
    // 6. Si hay fecha de entrega de Servientrega, usarla para "Entregado"
    if (empty($fechasEstados['entrega']) && 
        !empty($datosServientrega['raw_fecha_entrega']) &&
        $datosServientrega['fecha_entrega']) {
        $fechasEstados['entrega'] = $datosServientrega['fecha_entrega'];
    }
    
    // 7. Si no hay fecha para "Listo para Despacho" pero hay fecha de envío, usarla
    if (empty($fechasEstados['despacho']) && 
        !empty($datosServientrega['raw_fecha_envio']) &&
        $datosServientrega['fecha_envio']) {
        $fechasEstados['despacho'] = $datosServientrega['fecha_envio'];
    }
    
    return $fechasEstados;
}

// ---------- LÓGICA PRINCIPAL DE CONSULTAS ----------

// FLUJO 1: Si encontramos en Azure
if ($encontradoEnAzure && $datosAzure) {
    $debug_log['flow'] = "FLUJO 1: Encontrado en Azure";
    
    // Consultar Infor si tenemos orden
    if (!empty($ordenParaInfor)) {
        $debug_log['calling_infor'] = "Llamando a Infor con orden: " . $ordenParaInfor;
        $resultadosCompletos['infor'] = consultarInfor($ordenParaInfor);
        $debug_log['infor_result_type'] = isset($resultadosCompletos['infor']['success']) ? "success" : "error";
    } else {
        $debug_log['calling_infor'] = "No hay orden para consultar Infor";
    }
    
    // Consultar Servientrega si tenemos guía
    if (!empty($guiaParaServientrega)) {
        $debug_log['calling_servientrega'] = "Llamando a Servientrega con guía: " . $guiaParaServientrega;
        $resultadosCompletos['servientrega'] = consultarServientrega($guiaParaServientrega);
        $debug_log['servientrega_result_type'] = isset($resultadosCompletos['servientrega']['error']) ? "error" : "success";
    } else {
        $debug_log['calling_servientrega'] = "No hay guía para consultar Servientrega";
    }
} 
// FLUJO 2: Si NO encontramos en Azure, consultamos directamente a Infor
else {
    $debug_log['flow'] = "FLUJO 2: No encontrado en Azure, consultando Infor directamente";
    
    // Intentar consultar directamente a Infor con el valor ingresado
    $resultadosCompletos['infor'] = consultarInfor($valor);
    $debug_log['infor_result_type'] = isset($resultadosCompletos['infor']['success']) ? "success" : "error";
}

// ---------- ASIGNAR FECHAS A ESTADOS ----------
// Solo si tenemos datos de Infor exitosos
if (isset($resultadosCompletos['infor']['success']) && $resultadosCompletos['infor']['success']) {
    $debug_log['assigning_dates'] = "Asignando fechas a estados";
    $fechasEstados = asignarFechasAEstados(
        $resultadosCompletos['infor'],
        $resultadosCompletos['servientrega'] ?? null
    );
    
    // Agregar las fechas asignadas a la respuesta
    $resultadosCompletos['fechas_estados'] = $fechasEstados;
    $debug_log['fechas_asignadas'] = $fechasEstados;
} else {
    $debug_log['assigning_dates'] = "No se pueden asignar fechas (Infor no success)";
}

// Verificar si no se encontró nada en ningún lado
$encontradoEnAlgunLado = false;

// Verificar si hay datos en Azure
if ($datosAzure && !isset($datosAzure['error'])) {
    $encontradoEnAlgunLado = true;
    $debug_log['found_in_azure'] = true;
}

// Verificar si hay datos en Infor
if ($resultadosCompletos['infor'] && isset($resultadosCompletos['infor']['success']) && $resultadosCompletos['infor']['success']) {
    $encontradoEnAlgunLado = true;
    $debug_log['found_in_infor'] = true;
}

// Verificar si hay datos en Servientrega
if ($resultadosCompletos['servientrega'] && !isset($resultadosCompletos['servientrega']['error'])) {
    $encontradoEnAlgunLado = true;
    $debug_log['found_in_servientrega'] = true;
}

// Si no se encontró en ningún lado
if (!$encontradoEnAlgunLado) {
    $resultadosCompletos['error_general'] = "No se encontró el pedido en ninguna base de datos";
    $debug_log['not_found_anywhere'] = true;
}

// Limpiar debug log si es muy grande
if (isset($debug_log['servientrega_curl_verbose'])) {
    $debug_log['servientrega_curl_verbose'] = substr($debug_log['servientrega_curl_verbose'], 0, 1000) . "...";
}

if (isset($debug_log['infor_api_verbose'])) {
    $debug_log['infor_api_verbose'] = substr($debug_log['infor_api_verbose'], 0, 1000) . "...";
}

// Devolver todos los resultados
echo json_encode($resultadosCompletos, JSON_PRETTY_PRINT);

// También escribir a log file para debugging
file_put_contents('debug_log_' . date('Y-m-d') . '.txt', 
    date('Y-m-d H:i:s') . " - Valor: $valor\n" . 
    print_r($debug_log, true) . "\n\n", 
    FILE_APPEND);
?>