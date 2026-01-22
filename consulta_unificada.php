<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Validar que se proporcionó un valor para buscar
if(!isset($_GET['valor']) || empty($_GET['valor'])){
    echo json_encode(["error" => "No se proporcionó número de guía u orden"]);
    exit;
}

$valor = trim($_GET['valor']);

// Configuración de la base de datos Azure
$host = 'Jorgeserver.database.windows.net';
$dbname = 'DPL';
$username = 'Jmmc';
$password = 'ChaosSoldier01';
$schema = 'externos';
$table = 'guia_orden';

// Variables para almacenar resultados
$datosAzure = null;
$ordenParaInfor = null;
$guiaParaServientrega = null;
$encontradoEnAzure = false;

// Conexión a SQL Server usando sqlsrv
function connectSQLServer() {
    global $host, $dbname, $username, $password;
    
    try {
        $connectionInfo = array(
            "Database" => $dbname,
            "UID" => $username,
            "PWD" => $password,
            "CharacterSet" => "UTF-8",
            "ReturnDatesAsStrings" => true,
            "MultipleActiveResultSets" => false,
            "TrustServerCertificate" => true // Para Azure
        );
        
        $conn = sqlsrv_connect($host, $connectionInfo);
        
        if ($conn === false) {
            $errors = sqlsrv_errors();
            $error_msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error desconocido';
            return ['success' => false, 'error' => 'Error SQL Server: ' . $error_msg];
        }
        
        return ['success' => true, 'conn' => $conn];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Excepción SQL Server: ' . $e->getMessage()];
    }
}

// Consultar datos en Azure SQL Server
$conexion = connectSQLServer();
if ($conexion['success']) {
    $conn = $conexion['conn'];
    
    // Consulta corregida - buscar por guía O por orden_infor
    $sql = "SELECT * FROM [$schema].[$table] WHERE guia = ? OR orden_infor = ?";
    $params = array($valor, $valor);
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $datosAzure = ["error" => "Error en consulta: " . ($errors[0]['message'] ?? 'Error desconocido')];
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        if($row){
            $encontradoEnAzure = true;
            $datosAzure = $row;
            
            // Determinar qué valor tenemos y qué necesitamos
            $guia = $row['guia'];
            $ordenInfor = $row['orden_infor'];
            
            // Si encontramos el registro, usar los valores de la base de datos
            $ordenParaInfor = $ordenInfor;
            $guiaParaServientrega = $guia;
        } else {
            // No encontrado en Azure - intentaremos consultar Infor directamente
            $ordenParaInfor = $valor; // Usar el valor ingresado como posible orden
        }
        
        sqlsrv_free_stmt($stmt);
    }
    
    sqlsrv_close($conn);
} else {
    // Error de conexión a Azure, pero aún podemos intentar con Infor
    $ordenParaInfor = $valor; // Usar el valor ingresado como posible orden
    $datosAzure = ["error" => $conexion['error']];
}

// Array para almacenar todos los resultados
$resultadosCompletos = [
    'datos_azure' => $datosAzure,
    'infor' => null,
    'servientrega' => null,
    'encontrado_en_azure' => $encontradoEnAzure
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
    // Si no hay orden válida para consultar
    if (empty($ordenParaConsultar) || $ordenParaConsultar === "No disponible" || 
        $ordenParaConsultar === "No se encontró la guía en la base de datos") {
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

    $chToken = curl_init($urlToken);
    curl_setopt($chToken, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chToken, CURLOPT_POST, true);
    curl_setopt($chToken, CURLOPT_POSTFIELDS, http_build_query($dataToken));
    $responseToken = curl_exec($chToken);

    if ($responseToken === false) {
        return ["error" => "Error generando token: " . curl_error($chToken)];
    } else {
        $resultToken = json_decode($responseToken, true);
        
        if (isset($resultToken["access_token"])) {
            $token = $resultToken["access_token"];
            
            // Consultar la API de Infor
            $urlInfor = "https://mingle-ionapi.inforcloudsuite.com/RANSA_PRD/WM/wmwebservice_rest/RANSA_PRD_RANSA_PRD_SCE_PRD_0_wmwhse3/shipments/externorderkey/$ordenParaConsultar";
            
            $chInfor = curl_init($urlInfor);
            curl_setopt($chInfor, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chInfor, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $token",
                "x-infor-tenantID: RANSA_PRD",
                "Accept: application/json"
            ]);
            
            $responseInfor = curl_exec($chInfor);
            $httpCodeInfor = curl_getinfo($chInfor, CURLINFO_HTTP_CODE);
            curl_close($chInfor);
            
            $resultInfor = json_decode($responseInfor, true);
            
            if($httpCodeInfor === 200 && $resultInfor && !isset($resultInfor['fault']['faultstring'])){
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
                return [
                    "error" => "No se encontraron datos en Infor para esta orden",
                    "http_code" => $httpCodeInfor,
                    "debug" => $resultInfor
                ];
            }
        } else {
            return ["error" => "No se pudo generar token para Infor", "debug" => $resultToken];
        }
    }
    curl_close($chToken);
}

// ---------- FUNCIÓN PARA CONSULTAR SERVIENTREGA ----------
function consultarServientrega($guiaParaConsultar) {
    if (empty($guiaParaConsultar)) {
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

    $chServientrega = curl_init("https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php?wsdl");
    curl_setopt($chServientrega, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chServientrega, CURLOPT_HTTPHEADER, [
        "Content-Type: text/xml; charset=ISO-8859-1",
        "Content-Length: " . strlen($xmlServientrega)
    ]);
    curl_setopt($chServientrega, CURLOPT_POST, true);
    curl_setopt($chServientrega, CURLOPT_POSTFIELDS, $xmlServientrega);

    $responseServientrega = curl_exec($chServientrega);
    $httpCodeServientrega = curl_getinfo($chServientrega, CURLINFO_HTTP_CODE);
    $curlErrorServientrega = curl_error($chServientrega);
    curl_close($chServientrega);

    if ($curlErrorServientrega) {
        return ["error" => "Error de conexión a Servientrega: " . $curlErrorServientrega];
    } elseif (empty($responseServientrega)) {
        return ["error" => "La respuesta de Servientrega está vacía", "http_code" => $httpCodeServientrega];
    } else {
        $xmlObj = simplexml_load_string($responseServientrega);
        
        if ($xmlObj === false) {
            return ["error" => "Error al parsear XML de Servientrega"];
        } else {
            $resultados = $xmlObj->xpath('//Result');
            
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
                    }
                    
                    // Determinar si la guía es válida
                    $guiaValida = !$guiaNoExiste;
                    if (empty($dirRemElement[0]) && empty($estadoElement[0]) && empty($fecenvElement[0])) {
                        $guiaValida = false;
                    }
                    
                    // Ajustar fechas de Servientrega (restar 4 horas)
                    $fechaEnvio = isset($fecenvElement[0]) ? restarHoras((string)$fecenvElement[0]) : "";
                    $fechaEntrega = isset($fechaEntregaElement[0]) ? restarHoras((string)$fechaEntregaElement[0]) : "";
                    
                    return [
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
                } else {
                    return ["error" => "Error al parsear XML interno de Servientrega"];
                }
            } else {
                return ["error" => "No se encontró el tag Result en la respuesta de Servientrega"];
            }
        }
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
    // Consultar Infor si tenemos orden
    if (!empty($ordenParaInfor)) {
        $resultadosCompletos['infor'] = consultarInfor($ordenParaInfor);
    }
    
    // Consultar Servientrega si tenemos guía
    if (!empty($guiaParaServientrega)) {
        $resultadosCompletos['servientrega'] = consultarServientrega($guiaParaServientrega);
    }
} 
// FLUJO 2: Si NO encontramos en Azure, consultamos directamente a Infor
else {
    // Intentar consultar directamente a Infor con el valor ingresado
    $resultadosCompletos['infor'] = consultarInfor($valor);
}

// ---------- ASIGNAR FECHAS A ESTADOS ----------
// Solo si tenemos datos de Infor exitosos
if (isset($resultadosCompletos['infor']['success']) && $resultadosCompletos['infor']['success']) {
    $fechasEstados = asignarFechasAEstados(
        $resultadosCompletos['infor'],
        $resultadosCompletos['servientrega'] ?? null
    );
    
    // Agregar las fechas asignadas a la respuesta
    $resultadosCompletos['fechas_estados'] = $fechasEstados;
}

// Verificar si no se encontró nada en ningún lado
$encontradoEnAlgunLado = false;

// Verificar si hay datos en Azure
if ($datosAzure && !isset($datosAzure['error'])) {
    $encontradoEnAlgunLado = true;
}

// Verificar si hay datos en Infor
if ($resultadosCompletos['infor'] && isset($resultadosCompletos['infor']['success']) && $resultadosCompletos['infor']['success']) {
    $encontradoEnAlgunLado = true;
}

// Verificar si hay datos en Servientrega
if ($resultadosCompletos['servientrega'] && !isset($resultadosCompletos['servientrega']['error'])) {
    $encontradoEnAlgunLado = true;
}

// Si no se encontró en ningún lado
if (!$encontradoEnAlgunLado) {
    $resultadosCompletos['error_general'] = "No se encontró el pedido en ninguna base de datos";
}

// Devolver todos los resultados
echo json_encode($resultadosCompletos, JSON_UNESCAPED_UNICODE);
?>