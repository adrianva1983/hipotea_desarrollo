<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Entity\FicheroCampo as FicheroCampoEntidad;
use AppBundle\Entity\CampoHito as CampoHitoEntidad;
use AppBundle\Entity\CampoHitoExpediente as CampoHitoExpedienteEntidad;
use AppBundle\Entity\HitoExpediente as HitoExpedienteEntidad;
use AppBundle\Entity\GrupoHitoExpediente as GrupoHitoExpedienteEntidad;
use AppBundle\Entity\Expediente as ExpedienteEntidad;
use AppBundle\Entity\BelenderHitoExpediente;

class BelenderController extends Controller
{
    /*private $apiBaseUrl = 'https://api.qa.belender.net';
    private $apiPackagesUrl = 'https://api.qa.belender.net/users/{user_id}/pack-documents';
    private $apiPreRequestUrl = 'https://api.qa.belender.net/pre-request';
    private $apiPreRequestUrlSMS = 'https://api.qa.belender.net/pre-request/clave-pin-sms';
    private $apiDocumentsUrl = 'https://api.qa.belender.net/requests/{request_id}/documents';
    private $belenderStatusUrl = 'https://api.qa.belender.net/status/service';
    private $email = 'Hipotea_qa@belender.eu';
    private $password = 'wWt?yM0B1';
    private $passwordCirbe = 'OkaP@ssw0rd';
    private $package_id_ICI = '2695904c-5f3e-4ede-8b0f-71f3bfd10ed3';*/
    private $apiBaseUrl = 'https://api.belender.net';
    private $apiPackagesUrl = 'https://api.belender.net/users/{user_id}/pack-documents';
    private $apiPreRequestUrl = 'https://api.belender.net/pre-request/';
    private $apiPreRequestUrlSMS = 'https://api.belender.net/pre-request/clave-pin-sms';
    private $apiDocumentsUrl = 'https://api.belender.net/requests/{request_id}/documents';
    private $belenderStatusUrl = 'https://api.belender.net/status/service';
    private $email = 'adrian.verdecia@semillaproyectos.com';
    private $password = 'P4st0r1t4.2016';
    private $passwordCirbe = 'Hipotea@@@';
    private $package_id_ICI = 'fe3bcae7-18aa-4c95-9754-f105b4153f6c';
    private $timeout = 50;
    
    // Secret HMAC para verificar webhooks de Belender (temporalmente en el controlador)
    // Cambia este valor por un secret seguro en producción
    private $belender_webhook_secret = 'b7f3c4d6a9e8f1c2b3d4e5f60718293a';
    // Últimos detalles de llamada a Gemini (para debug y respuestas al frontend)
    private $last_gemini_error = null;
    private $last_gemini_response = null;
    private $last_gemini_http_code = null;

    private function httpRequest($url, $method = 'GET', $data = [], $token = null, $downloadPath = null)
    {
        $ch = curl_init();
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer $token";
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($downloadPath) {
            $fp = fopen($downloadPath, 'w+');
            if (!$fp) {
                curl_close($ch);
                return ['error' => 'No se pudo abrir archivo para escritura'];
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
        }

        $response = curl_exec($ch);

        if ($downloadPath) {
            fclose($fp);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 200) {
                return true;
            } else {
                return ['error' => "Error en descarga, HTTP code: $httpCode"];
            }
        }

        if (curl_errno($ch)) {
            $errorMessage = curl_error($ch);
            curl_close($ch);
            return ['error' => 'Error cURL: ' . $errorMessage];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        // Log HTTP code para debugging
        if ($httpCode >= 400) {
            error_log("⚠️ HTTP $httpCode en respuesta de API: " . substr($response, 0, 500));
            // Agregar HTTP code a la respuesta si es error
            if (is_array($decoded)) {
                $decoded['_http_code'] = $httpCode;
            }
        }
        
        return $decoded;
    }

    private function login()
    {
        $url = $this->apiBaseUrl . '/login';
        $data = ['email' => $this->email, 'password' => $this->password];
        return $this->httpRequest($url, 'POST', $data);
    }

    private function getPackages($userId, $token)
    {
        $url = str_replace('{user_id}', $userId, $this->apiPackagesUrl);
        return $this->httpRequest($url, 'GET', [], $token);
    }

    private function preRequest($userId, $packageId, $dniCliente, $token)
    {
        return $this->httpRequest($this->apiPreRequestUrl, 'POST', [
            'user_id' => $userId,
            'package_id' => $packageId,
            'document_number' => $dniCliente
        ], $token);
    }

    private function preRequestSMS($userId, $package_id_clave_pin, $package_id_sms, $dniCliente, $token, $doc_expiration_date)
    {
        return $this->httpRequest($this->apiPreRequestUrlSMS, 'POST', [
            'user_id' => $userId,
            'package_id_clave_pin' => $package_id_clave_pin,
            'package_id_sms' => $package_id_sms,
            'document_number' => $dniCliente,
            "document_expiration_date" => $doc_expiration_date,
        ], $token);
    }
    private function preRequestSMS1($userId, $packageIdClavePinSms, $packageIdSms, $csrfToken, $tags = [])
    {
        $url = $this->apiPreRequestUrlSMS;

        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-CSRF-TOKEN: ' . $csrfToken,
        ];

        $data = [
            'user_id' => $userId,
            'package_id_clave_pin' => $packageIdClavePinSms,
            'package_id_sms' => $packageIdSms,
        ];

        // Agregar tags si se proporcionan
        if (!empty($tags) && is_array($tags)) {
            $data['tags'] = $tags;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            curl_close($ch);

            return [
                'success' => false,
                'error'   => $curlError,
            ];
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        return [
            'response' => $decodedResponse,
            'http_code' => $httpCode,
        ];
    }

    private function claivePinRequest(string $csrfToken, array $data): array
    {
        $url = $this->apiBaseUrl.'/requests/clave-pin';

        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            //'Authorization: Bearer ' . $csrfToken,
            'X-CSRF-TOKEN: ' . $csrfToken,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            curl_close($ch);

            return [
                'success' => false,
                'error'   => $curlError,
            ];
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        return [
            'url' => $url,
            'response' => $decodedResponse,
            'http_code' => $httpCode,
        ];
    }

    private function getDocuments($requestId, $token)
    {
        $url = str_replace('{request_id}', $requestId, $this->apiDocumentsUrl);
        return $this->httpRequest($url, 'GET', [], $token);
    }



    private function saveDocumentFromBase64($base64String, $filePath)
    {
        $pdfData = base64_decode($base64String);
        if ($pdfData === false) {
            return ['error' => 'Error al decodificar el documento PDF.'];
        }
        if (file_put_contents($filePath, $pdfData) === false) {
            return ['error' => 'Error al guardar el documento PDF.'];
        }
        return ['success' => 'Documento guardado en ' . $filePath];
    }

    private function saveDocument($json, $filePath)
    {
        if ($json === false) {
            return ['error' => 'Error al decodificar el documento JSON.'];
        }
        if (is_array($json) || is_object($json)) {
            $jsonString = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($jsonString === false) {
                return ['error' => 'Error al codificar el documento como JSON.'];
            }
        } else {
            $jsonString = $json;
        }
        if (file_put_contents($filePath, $jsonString) === false) {
            return ['error' => 'Error al guardar el documento JSON.'];
        }
        return ['success' => 'Documento guardado en ' . $filePath];
    }

    /**
     * Convierte URL de widget a formato sms estándar, quitando siempre el parámetro del medio
     * y normalizando prefijos smspin/sms aunque vengan duplicados.
     *
     * Resultado:
     * - https://widget.belender.net/widget-boxed/sms/{user_id}/{package_id}?request_id=...
     */
    private function convertSmsPinUrlToSms($url)
    {
        if (!$url || !is_string($url)) {
            return $url;
        }

        // Quita espacios y comillas típicas que rompen parse_url
        $url = trim($url);
        $url = trim($url, " \t\n\r\0\x0B\"'");

        $urlParts = parse_url($url);

        // Si parse_url falla, no intentes transformar
        if (!is_array($urlParts)) {
            return $url;
        }

        $path   = $urlParts['path'] ?? '';
        $query  = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
        $scheme = $urlParts['scheme'] ?? 'https';
        $host   = $urlParts['host'] ?? 'widget.belender.net';

        if (strpos($path, '/widget-boxed/') === false) {
            return $url;
        }

        // Segmentos del path (reindexados)
        $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));

        $boxedIndex = array_search('widget-boxed', $segments, true);
        if ($boxedIndex === false) {
            return $url;
        }

        $afterBoxed = array_slice($segments, $boxedIndex + 1);

        // Quitar TODOS los prefijos smspin/sms repetidos
        while (!empty($afterBoxed) && in_array($afterBoxed[0], ['smspin', 'sms'], true)) {
            array_shift($afterBoxed);
        }

        // Necesitamos al menos user_id y package_id
        if (count($afterBoxed) < 2) {
            return $url;
        }

        $userId    = $afterBoxed[0];
        $packageId = $afterBoxed[count($afterBoxed) - 1]; // último segmento

        $newPath = '/widget-boxed/sms/' . $userId . '/' . $packageId;

        return "{$scheme}://{$host}{$newPath}{$query}";
    }


    public function preSolicitarDocumentosAction(Request $request)
    {
        $dniCliente = $request->query->get('dni');
        if (!$dniCliente) {
            return new JsonResponse(['error' => 'DNI no proporcionado'], 400);
        }
        $authResponse = $this->login();
        if (!$authResponse || $authResponse['status'] !== 'success') {
            error_log("❌ Error en autenticación: " . json_encode($authResponse));
            return new JsonResponse(['error' => 'Error en autenticación'], 500);
        }
        $token = $authResponse['access_token'];
        $userId = $authResponse['data']['user_id'];

        $packagesResponse = $this->getPackages($userId, $token);
        error_log("📦 Respuesta de paquetes: " . json_encode($packagesResponse));

        if (!$packagesResponse || $packagesResponse['status'] !== 'success' || count($packagesResponse['data']) < 2) {
            return new JsonResponse(['error' => 'Error obteniendo package_id'], 500);
        }

        $packageId = $packagesResponse['data'][1]['package_id'];

        $preRequestResponse['data']['user_id'] = $userId;
        $preRequestResponse['data']['package_id'] = $packageId;
        $preRequestResponse['data']['document_number'] = $dniCliente;
        return new JsonResponse($preRequestResponse['data']);
    }

    public function solicitarDocumentosAction(Request $request)
    {
        $dniCliente = $request->query->get('dni');
        $idHitoExpediente = $request->query->get('id_hito_expediente');
        $docExpirationDate = $request->query->get('doc_expiration_date'); // Formato: YYYY-MM-DD
        $documentSupport = $request->query->get('document_support', '1111111');
        $checkLegal = filter_var($request->query->get('check_legal', 'true'), FILTER_VALIDATE_BOOLEAN);
        $flowControl = filter_var($request->query->get('flow_control', 'true'), FILTER_VALIDATE_BOOLEAN);
        $addCirbe = filter_var($request->query->get('add_cirbe', 'true'), FILTER_VALIDATE_BOOLEAN);
        
        if (!$dniCliente) {
            return new JsonResponse(['error' => 'DNI no proporcionado'], 400);
        }

        // Si no viene doc_expiration_date, devolver error
        if (!$docExpirationDate) {
            return new JsonResponse([
                'error' => 'doc_expiration_date es requerido',
                'ejemplo' => 'GET /solicitar-documentos?dni=12345678X&doc_expiration_date=2030-12-31&id_hito_expediente=123'
            ], 400);
        }

        $authResponse = $this->login();
        if (!$authResponse || $authResponse['status'] !== 'success') {
            error_log("❌ Error en autenticación: " . json_encode($authResponse));
            return new JsonResponse(['error' => 'Error en autenticación'], 500);
        }

        $token = $authResponse['access_token'];
        $userId = $authResponse['data']['user_id'];

        $packagesResponse = $this->getPackages($userId, $token);
        error_log("📦 Respuesta de paquetes: " . json_encode($packagesResponse));

        if (!$packagesResponse || $packagesResponse['status'] !== 'success' || empty($packagesResponse['data'])) {
            return new JsonResponse(['error' => 'Error obteniendo package_id'], 500);
        }

        // Recorrer array de packages para encontrar el de ClavePin
        $packageId = null;
        if (is_array($packagesResponse['data'])) {
            foreach ($packagesResponse['data'] as $package) {
                if (isset($package['flow_name']) && $package['flow_name'] === 'ClavePin') {
                    $packageId = $package['package_id'];
                    break;
                }
            }
        }

        if (!$packageId) {
            return new JsonResponse(['error' => 'No se encontró package de ClavePin'], 500);
        }

        $preRequestResponse = $this->preRequest($userId, $packageId, $dniCliente, $token);
        error_log("🔗 Respuesta del pre-request: " . json_encode($preRequestResponse));

        if (!$preRequestResponse || $preRequestResponse['status'] !== 'success') {
            return new JsonResponse(['error' => 'Error en pre-request'], 500);
        }

        $requestId = $preRequestResponse['data']['request_id'] ?? null;
        
        // 🔐 LLAMAR AL ENDPOINT DE CLAVE PIN
        $claivePinResponse = null;
        if ($requestId) {
            // Construir el payload para Clave PIN
            $claivePinData = [
                'request_id' => $requestId,
                'document_number' => $dniCliente,
                'document_support' => $documentSupport,
                'document_expiration_date' => $docExpirationDate,
                'user_id' => $userId,
                'package_id' => $packageId,
                'check_legal' => $checkLegal,
                'flow_control' => $flowControl,
            ];
            
            // Solo incluir add_cirbe si es true
            if ($addCirbe === true) {
                $claivePinData['add_cirbe'] = true;
            }
            
            $claivePinResponse = $this->claivePinRequest($token, $claivePinData);
        }
        
        // Validar respuesta exitosa de Clave PIN
        if (!$claivePinResponse) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'No se recibió respuesta de Clave PIN'
            ], 500);
        }
        
        $httpCode = $claivePinResponse['http_code'] ?? 500;
        if ($httpCode < 200 || $httpCode >= 300) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'Error en Clave PIN',
                'http_code' => $httpCode,
                'claivePinData' => $claivePinData,
                'response' => $claivePinResponse['response'] ?? null,
                'raw' => $claivePinResponse['raw'] ?? null
            ], $httpCode);
        }
        
        // ✅ GUARDAR O ACTUALIZAR EN TABLA belender_hito_expediente (no-blocking)
        if ($requestId && $dniCliente && $idHitoExpediente) {
            try {
                $em = $this->getDoctrine()->getManager();
                $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);
                
                $registroExistente = $repoRegistros->findOneBy([
                    'idHitoExpediente' => (int)$idHitoExpediente
                ]);
                
                if ($registroExistente) {
                    $registroExistente->setDniBelender($dniCliente);
                    $registroExistente->setRequestIdBelender($requestId);
                    $registroExistente->setTipoPeticion('BELENDER_PIN');
                    $registroExistente->setFecha(new \DateTime());
                    $em->persist($registroExistente);
                    $em->flush();
                } else {
                    $belenderRegistro = new BelenderHitoExpediente();
                    $belenderRegistro->setIdHitoExpediente((int)$idHitoExpediente);
                    $belenderRegistro->setDniBelender($dniCliente);
                    $belenderRegistro->setRequestIdBelender($requestId);
                    $belenderRegistro->setTipoPeticion('BELENDER_PIN');
                    $belenderRegistro->setFecha(new \DateTime());
                    
                    $em->persist($belenderRegistro);
                    $em->flush();
                }
            } catch (\Exception $e) {
                $claivePinResponse['data']['error_general'] = $e->getMessage();
                // No detener el flujo si falla el registro
            }
        }

        return new JsonResponse($claivePinResponse['response']['data']);
        /*return new JsonResponse([
            'status' => 'success',
            'data' => $claivePinResponse['response'] ?? []
        ]);*/
    }

    public function solicitarDocumentosSMSAction(Request $request)
    {
        $dniCliente = $request->query->get('dni');
        $idHitoExpediente = $request->query->get('id_hito_expediente');
        $doc_expiration_date = $request->query->get('doc_expiration_date');
        $preRequestResponse = null;
        if (!$dniCliente) {
            return new JsonResponse(['error' => 'DNI no proporcionado'], 400);
        }

        $authResponse = $this->login();
        if (!$authResponse || $authResponse['status'] !== 'success') {
            error_log("❌ Error en autenticación: " . json_encode($authResponse));
            return new JsonResponse(['error' => 'Error en autenticación'], 500);
        }

        $token = $authResponse['access_token'];
        $userId = $authResponse['data']['user_id'];

        $packagesResponse = $this->getPackages($userId, $token);

        if (!$packagesResponse || $packagesResponse['status'] !== 'success' || count($packagesResponse['data']) < 2) 
        {
            return new JsonResponse(['error' => 'Error obteniendo package_id'], 500);
        }

        // Recorrer el array de packages para encontrar SMS y ClavePin
        $packageSMS = null;
        $packageClavePin = null;

        if ($packagesResponse && $packagesResponse['status'] === 'success' && is_array($packagesResponse['data'])) 
        {
            foreach ($packagesResponse['data'] as $package) 
            {
                // Buscar el package con flow SMS
                if (isset($package['flow_name']) && $package['flow_name'] === 'SMS') 
                {
                    $packageSMS = $package['package_id'];
                }
                // Buscar el package con flow ClavePin
                if (isset($package['flow_name']) && $package['flow_name'] === 'ClavePin') 
                {
                    $packageClavePin = $package['package_id'];
                }
            }
        }

        $preRequestResponse = $this->preRequestSMS($userId, $packageClavePin, $packageSMS, $dniCliente, $token, $doc_expiration_date);



        if (!$preRequestResponse || $preRequestResponse['status'] !== 'success') 
        {
            return new JsonResponse(['error' => 'Error en pre-request'], 500);
        }

        $requestId = $preRequestResponse['data']['request_id'] ?? null;
        
        // ✅ GUARDAR O ACTUALIZAR EN TABLA belender_hito_expediente
        if ($requestId && $dniCliente && $idHitoExpediente) 
        { 
            try { 
                $preRequestResponse['data']['Paso'] = 'Pasoo11111';
                
                $em = $this->getDoctrine()->getManager();
                $preRequestResponse['data']['Paso'] = 'Pasoo11111-getManager OK';
                
                $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);
                $preRequestResponse['data']['Paso'] = 'Pasoo222221111 - getRepository OK';
                
                // Buscar si ya existe un registro con el mismo id_hito_expediente y tipo BELENDER
                $registroExistente = $repoRegistros->findOneBy([
                    'idHitoExpediente' => (int)$idHitoExpediente,
                    //'tipoPeticion' => 'BELENDER_PIN'
                ]);
                $preRequestResponse['data']['Paso'] = 'Pasoo222221111 - findOneBy OK';
                
                if ($registroExistente) 
                { 
                    $preRequestResponse['data']['Paso'] = 'Pasoo333333';
                    // ACTUALIZAR: existe un registro anterior, actualizar dni, request_id y fecha
                    $registroExistente->setDniBelender($dniCliente);
                    $registroExistente->setRequestIdBelender($requestId);
                    $registroExistente->setTipoPeticion('BELENDER_SMS');
                    $registroExistente->setFecha(new \DateTime());
                    $em->persist($registroExistente);
                    $em->flush();
                    
                    error_log("🔄 Registro BELENDER actualizado: ID Hito=$idHitoExpediente, DNI=$dniCliente, Request=$requestId");
                } 
                else
                { 
                    $preRequestResponse['data']['Paso'] = 'Pasoo444444';
                    // CREAR: no existe registro anterior
                    $belenderRegistro = new BelenderHitoExpediente();
                    $belenderRegistro->setIdHitoExpediente((int)$idHitoExpediente);
                    $belenderRegistro->setDniBelender($dniCliente);
                    $belenderRegistro->setRequestIdBelender($requestId);
                    $belenderRegistro->setTipoPeticion('BELENDER_SMS');
                    $belenderRegistro->setFecha(new \DateTime());
                    
                    $em->persist($belenderRegistro);
                    $em->flush();
                    
                    error_log("✅ Registro BELENDER creado: ID Hito=$idHitoExpediente, DNI=$dniCliente, Request=$requestId");
                }
            } catch (\Doctrine\Persistence\Mapping\MappingException $e) {
                error_log("❌ Error de mapeo Doctrine: " . $e->getMessage());
                $preRequestResponse['data']['error_doctrine'] = "Mapeo Doctrine: " . $e->getMessage();
            } catch (\Exception $e) {
                error_log("⚠️ Error guardando registro belender_hito_expediente: " . $e->getMessage());
                error_log("⚠️ Tipo de error: " . get_class($e));
                error_log("⚠️ Stack trace: " . $e->getTraceAsString());
                $preRequestResponse['data']['error_general'] = $e->getMessage();
                // No detener el flujo si falla el registro
            }
        }

        $preRequestResponse['data']['widget_url_boxed'] = $this->convertSmsPinUrlToSms($preRequestResponse['data']['widget_url_boxed']);
        return new JsonResponse($preRequestResponse['data']);
    }

    public function solicitarDocumentosSMS1Action(Request $request)
    {
        

        $requestId = $preRequestResponse['data']['request_id'] ?? null;
        
        // ✅ GUARDAR O ACTUALIZAR EN TABLA belender_hito_expediente
        if ($requestId && $dniCliente && $idHitoExpediente) 
        { 
            try { 
                $preRequestResponse['data']['Paso'] = 'Pasoo11111';
                
                $em = $this->getDoctrine()->getManager();
                $preRequestResponse['data']['Paso'] = 'Pasoo11111-getManager OK';
                
                $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);
                $preRequestResponse['data']['Paso'] = 'Pasoo222221111 - getRepository OK';
                
                // Buscar si ya existe un registro con el mismo id_hito_expediente y tipo BELENDER
                $registroExistente = $repoRegistros->findOneBy([
                    'idHitoExpediente' => (int)$idHitoExpediente,
                    //'tipoPeticion' => 'BELENDER_PIN'
                ]);
                $preRequestResponse['data']['Paso'] = 'Pasoo222221111 - findOneBy OK';
                
                if ($registroExistente) 
                { 
                    $preRequestResponse['data']['Paso'] = 'Pasoo333333';
                    // ACTUALIZAR: existe un registro anterior, actualizar dni, request_id y fecha
                    $registroExistente->setDniBelender($dniCliente);
                    $registroExistente->setRequestIdBelender($requestId);
                    $registroExistente->setTipoPeticion('BELENDER_PIN');
                    $registroExistente->setFecha(new \DateTime());
                    $em->persist($registroExistente);
                    $em->flush();
                    
                    error_log("🔄 Registro BELENDER actualizado: ID Hito=$idHitoExpediente, DNI=$dniCliente, Request=$requestId");
                } 
                else
                { 
                    $preRequestResponse['data']['Paso'] = 'Pasoo444444';
                    // CREAR: no existe registro anterior
                    $belenderRegistro = new BelenderHitoExpediente();
                    $belenderRegistro->setIdHitoExpediente((int)$idHitoExpediente);
                    $belenderRegistro->setDniBelender($dniCliente);
                    $belenderRegistro->setRequestIdBelender($requestId);
                    $belenderRegistro->setTipoPeticion('BELENDER_PIN');
                    $belenderRegistro->setFecha(new \DateTime());
                    
                    $em->persist($belenderRegistro);
                    $em->flush();
                    
                    error_log("✅ Registro BELENDER creado: ID Hito=$idHitoExpediente, DNI=$dniCliente, Request=$requestId");
                }
            } catch (\Doctrine\Persistence\Mapping\MappingException $e) {
                error_log("❌ Error de mapeo Doctrine: " . $e->getMessage());
                $preRequestResponse['data']['error_doctrine'] = "Mapeo Doctrine: " . $e->getMessage();
            } catch (\Exception $e) {
                error_log("⚠️ Error guardando registro belender_hito_expediente: " . $e->getMessage());
                error_log("⚠️ Tipo de error: " . get_class($e));
                error_log("⚠️ Stack trace: " . $e->getTraceAsString());
                $preRequestResponse['data']['error_general'] = $e->getMessage();
                // No detener el flujo si falla el registro
            }
        }

        return new JsonResponse($preRequestResponse['data']);
    }

    /**
     * 🔔 WEBHOOK ENDPOINT - Procesa eventos de Belender en tiempo real
     * POST /webhook/belender-eventos
     * 
     * Recibe eventos JSON del API de Belender y actualiza belender_hito_expediente
     * Valida autenticación mediante X-API-KEY o Bearer token
     * 
     * Estructura esperada:
     * {
     *   "event_type": "process|document",
     *   "request_id": "uuid",
     *   "user_id": "uuid",
     *   "status_code": "...",
     *   "status_message": "...",
     *   ...
     * }
     */


    public function webhookBelenderEventosAction(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        error_log("📨 Webhook recibido - Payload completo: " . json_encode($payload));
        
        if (!$payload || !is_array($payload)) {
            error_log("❌ Payload inválido");
            return new JsonResponse([
                'success' => false,
                'error' => 'Payload JSON inválido'
            ], 400);
        }

        $requestId = $payload['request_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $fechaStatus = $payload['fecha_status'] ?? null;

        error_log("📋 Datos extraídos del webhook:");
        error_log("   - request_id: " . ($requestId ?? 'NULL'));
        error_log("   - status_code: " . ($statusCode ?? 'NULL'));
        error_log("   - fecha_status: " . ($fechaStatus ?? 'NULL'));

        if (!$requestId) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Falta request_id'
            ], 400);
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);

            // 🔍 Buscar registro existente por request_id
            $registroExistente = $repoRegistros->findOneBy([
                'requestIdBelender' => $requestId
            ]);

            if (!$registroExistente) {
                error_log("⚠️ Webhook: No se encontró registro para request_id: $requestId");
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                    'request_id' => $requestId
                ], 404);
            }

            // ✅ ACTUALIZAR el registro con el nuevo estado
            error_log("🔄 Antes de actualizar - statusCode actual: " . ($registroExistente->getStatusCode() ?? 'NULL'));
            error_log("🔄 statusCode a asignar: " . ($statusCode ?? 'NULL'));
            
            $registroExistente->setFecha(new \DateTime());
            
            // Si tu entidad tiene estos métodos, usarlos:
            if (method_exists($registroExistente, 'setStatusCode')) {
                error_log("✅ Método setStatusCode EXISTE");
                $registroExistente->setStatusCode($statusCode);
                error_log("✅ setStatusCode asignado. Valor ahora: " . ($registroExistente->getStatusCode() ?? 'NULL'));
            } else {
                error_log("❌ Método setStatusCode NO EXISTE");
            }
            
            if (method_exists($registroExistente, 'setFechaStatus')) {
                error_log("✅ Método setFechaStatus EXISTE");
                $registroExistente->setFechaStatus($fechaStatus ? new \DateTime($fechaStatus) : new \DateTime());
            }

            error_log("🔄 Antes de persist - statusCode: " . ($registroExistente->getStatusCode() ?? 'NULL'));
            $em->persist($registroExistente);
            error_log("🔄 Después de persist");
            $em->flush();
            error_log("✅ Después de flush - statusCode: " . ($registroExistente->getStatusCode() ?? 'NULL'));


            return new JsonResponse([
                'success' => true,
                'message' => 'Webhook procesado correctamente',
                'request_id' => $requestId,
                'status_code' => $statusCode,
                'id_hito_expediente' => $registroExistente->getIdHitoExpediente(),
                'timestamp' => date('Y-m-d H:i:s')
            ], 200);

        } catch (\Exception $e) {
            error_log("❌ Error procesando webhook: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Error procesando webhook',
                'message' => $e->getMessage(),
                'request_id' => $requestId
            ], 500);
        }
    }
    public function webhookBelenderEventosAction1(Request $request)
    {
        // ✅ VALIDAR AUTENTICACIÓN
        

        

        try {
            $em = $this->getDoctrine()->getManager();
            $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);

            // 🔍 Buscar registro existente por request_id
            $registroExistente = $repoRegistros->findOneBy([
                'requestIdBelender' => $requestId
            ]);

            // 📝 Actualizar o crear registro según el evento
            if ($registroExistente) {
                // ACTUALIZAR: existe registro anterior
                
                // Actualizar estado del proceso
                $registroExistente->setTipoPeticion('BELENDER_PIN');
                $registroExistente->setFecha(new \DateTime());
                
                // Guardar estado actual en campo adicional (si existe)
                if (method_exists($registroExistente, 'setStatusCode')) {
                    $registroExistente->setStatusCode($statusCode);
                }
                if (method_exists($registroExistente, 'setStatusMessage')) {
                    $registroExistente->setStatusMessage($statusMessage);
                }
                if (method_exists($registroExistente, 'setEventType')) {
                    $registroExistente->setEventType($eventType);
                }
                
                $em->persist($registroExistente);
                $em->flush();

                return new JsonResponse([
                    'success' => true,
                    'action' => 'updated',
                    'request_id' => $requestId,
                    'status_code' => $statusCode,
                    'message' => 'Registro actualizado exitosamente'
                ]);
            } else {
                // CREATE: No existe registro, crear uno nuevo
                // Nota: Sin DNI de cliente disponible en webhook, se deja vacío
                
                $belenderRegistro = new BelenderHitoExpediente();
                $belenderRegistro->setRequestIdBelender($requestId);
                $belenderRegistro->setTipoPeticion('BELENDER_PIN');
                $belenderRegistro->setFecha(new \DateTime());
                
                // Campos opcionales si existen métodos setter
                if (method_exists($belenderRegistro, 'setStatusCode')) {
                    $belenderRegistro->setStatusCode($statusCode);
                }
                if (method_exists($belenderRegistro, 'setStatusMessage')) {
                    $belenderRegistro->setStatusMessage($statusMessage);
                }
                if (method_exists($belenderRegistro, 'setEventType')) {
                    $belenderRegistro->setEventType($eventType);
                }

                $em->persist($belenderRegistro);
                $em->flush();

                return new JsonResponse([
                    'success' => true,
                    'action' => 'created',
                    'request_id' => $requestId,
                    'status_code' => $statusCode,
                    'message' => 'Registro creado exitosamente'
                ]);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error procesando webhook',
                'exception' => $e->getMessage(),
                'request_id' => $requestId ?? 'unknown'
            ], 500);
        }
    }

    /**
     * Obtener datos de belender_hito_expediente por id_hito_expediente
     * GET /obtener-belender-registro?id_hito_expediente=...
     */
    public function obtenerBelenderRegistroAction(Request $request)
    {
        $idHitoExpediente = $request->query->get('id_hito_expediente');
        $tipoPeticion = $request->query->get('tipo_peticion'); // Sin valor por defecto
        
        if (!$idHitoExpediente) {
            return new JsonResponse(['error' => 'id_hito_expediente no proporcionado'], 400);
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);
            
            // Construir filtro dinámicamente usando QueryBuilder
            $qb = $repoRegistros->createQueryBuilder('r')
                ->where('r.idHitoExpediente = :idHito')
                ->setParameter('idHito', (int)$idHitoExpediente);
            
            // Si se proporciona tipo_peticion, incluirlo en el filtro
            if ($tipoPeticion) {
                $qb->andWhere('r.tipoPeticion LIKE :tipoPeticion')
                   ->setParameter('tipoPeticion', '%' . $tipoPeticion . '%');
            }
            
            $registro = $qb->getQuery()->getOneOrNullResult();
            
            if (!$registro) {
                return new JsonResponse([
                    'found' => false,
                    'message' => 'No se encontró registro para este hito',
                    'tipo_solicitado' => $tipoPeticion ?: 'cualquiera'
                ]);
            }
            
            return new JsonResponse([
                'found' => true,
                'tipo_peticion' => $registro->getTipoPeticion(),
                'request_id' => $registro->getRequestIdBelender(),
                'dni' => $registro->getDniBelender(),
                'status_code' => $registro->getStatusCode(),
                'fecha' => $registro->getFecha()->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("⚠️ Error obteniendo registro belender: " . $e->getMessage());
            return new JsonResponse([
                'error' => $e->getMessage(),
                'found' => false
            ], 500);
        }
    }

    /**
     * MÉTODOS PRIVADOS - MÉTODOS DE HELPER
     * =======================================
     */
    public function cargarDocumentosDescargadosAction(Request $request)
    {
        $dni = $request->query->get('dni');

        if (!$dni) {
            return new JsonResponse(['error' => 'DNI no proporcionado', 'success' => false], 400);
        }

        $filesDirectory = $this->getParameter('files_directory') . '/' . $dni;

        // Verificar si el directorio existe
        if (!is_dir($filesDirectory)) {
            error_log("ℹ️ Directorio de documentos no existe para DNI: $dni");
            return new JsonResponse([
                'success' => true,
                'documentos' => [],
                'mensaje' => 'No hay documentos descargados aún'
            ]);
        }

        $documentos = [];
        $files = scandir($filesDirectory);

        if ($files === false) {
            error_log("❌ Error leyendo directorio: $filesDirectory");
            return new JsonResponse(['error' => 'Error al leer documentos', 'success' => false], 500);
        }

        foreach ($files as $file) {
            // Ignorar . y ..
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $filesDirectory . '/' . $file;

            // Solo procesar archivos, no directorios
            if (!is_file($filePath)) {
                continue;
            }

            $fileSize = filesize($filePath);
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

            // Procesar solo JSON y PDF
            if ($fileExtension === 'json') {
                $jsonContent = file_get_contents($filePath);
                $jsonData = json_decode($jsonContent, true);

                $estado = 'válido';
                if (empty($jsonData) || $jsonData === null) {
                    $estado = 'vacío';
                }

                $documentos[] = [
                    'nombre' => $file,
                    'tipo' => 'json',
                    'tamaño' => $fileSize,
                    'estado' => $estado,
                    'ruta' => '/obtener-documento?dni=' . urlencode($dni) . '&archivo=' . urlencode($file)
                ];
                error_log("✅ JSON cargado: $file");
            } elseif ($fileExtension === 'pdf') {
                $documentos[] = [
                    'nombre' => $file,
                    'tipo' => 'pdf',
                    'tamaño' => $fileSize,
                    'estado' => 'válido',
                    'ruta' => '/obtener-documento?dni=' . urlencode($dni) . '&archivo=' . urlencode($file) . '&download=1'
                ];
                error_log("✅ PDF cargado: $file");
            }
        }

        // Ordenar por nombre
        usort($documentos, function($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });

        error_log("📊 Total documentos cargados para DNI $dni: " . count($documentos));

        return new JsonResponse([
            'success' => true,
            'documentos' => $documentos,
            'total' => count($documentos)
        ]);
    }

    public function descargarDocumentosAction(Request $request)
    {
        $requestId = $request->query->get('request_id');
        $dni = $request->query->get('dni');
        //$dni = '65879943P';

        if (!$requestId || !$dni) {
            return new JsonResponse(['error' => 'DNI y Request ID son requeridos'], 400);
        }

        error_log("🔍 Descarga iniciada para request_id: " . $requestId);

        $authResponse = $this->login();
        if (!$authResponse || !isset($authResponse['access_token'])) {
            return new JsonResponse(['error' => 'Error en autenticación'], 500);
        }

        $token = $authResponse['access_token'];
        $documentsResponse = $this->getDocuments($requestId, $token);

        if (!$documentsResponse || $documentsResponse['status'] !== 'success') {
            return new JsonResponse(['error' => 'Error obteniendo documentos: '.$documentsResponse['status']], 500);
        }

        if (empty($documentsResponse['data'])) {
            return new JsonResponse(['error' => 'No hay documentos disponibles'], 404);
        }

        $filesDirectory = $this->getParameter('files_directory') . '/' . $dni;
        if (!is_dir($filesDirectory)) {
            mkdir($filesDirectory, 0777, true);
        }

        $downloadedFiles = [];
        foreach ($documentsResponse['data'] as $document) {
            // Validar que el documento tenga al menos un nombre
            if (empty($document['document_name'])) {
                error_log("⚠️ Documento sin nombre, omitido");
                continue;
            }

            // Guardar PDF - descargar aunque esté vacío
            if (isset($document['document_pdf'])) {
                $documentName = preg_replace('/[^A-Za-z0-9_-]/', '_', $document['document_name']) . '.pdf';
                $filePath = $filesDirectory . '/' . $documentName;
                $saveResult = $this->saveDocumentFromBase64($document['document_pdf'], $filePath);
                
                if (isset($saveResult['error'])) {
                    error_log("❌ Error guardando PDF: " . $documentName . " - " . $saveResult['error']);
                } else {
                    error_log("✅ PDF guardado: " . $filePath);
                    $downloadedFiles[] = ['name' => $documentName, 'path' => $filePath, 'type' => 'pdf'];
                }
            }

            // Guardar JSON - descargar aunque esté vacío
            if (isset($document['document_json'])) {
                $documentName = preg_replace('/[^A-Za-z0-9_-]/', '_', $document['document_name']) . '.json';
                $filePath = $filesDirectory . '/' . $documentName;
                $saveResult = $this->saveDocument($document['document_json'], $filePath);

                if (isset($saveResult['error'])) {
                    error_log("❌ Error guardando JSON: " . $documentName . " - " . $saveResult['error']);
                } else {
                    error_log("✅ JSON guardado: " . $filePath);
                    $downloadedFiles[] = ['name' => $documentName, 'path' => $filePath, 'type' => 'json'];
                }
            }
        }

        return new JsonResponse([
            'success' => true,
            'files' => $downloadedFiles
        ]);
    }

    public function obtenerDocumentoAction(Request $request)
    {
        $dni = $request->query->get('dni');
        $archivo = $request->query->get('archivo');
        $download = $request->query->get('download', 0);

        if (!$dni || !$archivo) {
            return new JsonResponse(['error' => 'DNI y nombre de archivo son requeridos'], 400);
        }

        $filesDirectory = $this->getParameter('files_directory') . '/' . $dni;
        $filePath = $filesDirectory . '/' . basename($archivo); // basename para evitar directory traversal

        // Validar que el archivo exista y esté dentro del directorio esperado
        if (!file_exists($filePath) || !is_file($filePath)) {
            error_log("❌ Archivo no encontrado: $filePath");
            return new JsonResponse(['error' => 'Archivo no encontrado'], 404);
        }

        // Validar que está dentro del directorio permitido
        $realPath = realpath($filePath);
        $realDirectory = realpath($filesDirectory);
        if (strpos($realPath, $realDirectory) !== 0) {
            error_log("❌ Intento de acceso a archivo fuera del directorio permitido");
            return new JsonResponse(['error' => 'Acceso denegado'], 403);
        }

        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Descargar PDF
        if ($download && $fileExtension === 'pdf') {
            $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($filePath);
            $response->setContentDisposition(
                \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $archivo
            );
            return $response;
        }

        // Ver JSON en el navegador
        if ($fileExtension === 'json') {
            $jsonContent = file_get_contents($filePath);
            $jsonData = json_decode($jsonContent, true);

            return new JsonResponse([
                'success' => true,
                'archivo' => $archivo,
                'tipo' => 'json',
                'datos' => $jsonData,
                'contenido_crudo' => $jsonContent
            ]);
        }

        // Ver PDF (descargar siempre)
        if ($fileExtension === 'pdf') {
            $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($filePath);
            $response->setContentDisposition(
                \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $archivo
            );
            return $response;
        }

        return new JsonResponse(['error' => 'Tipo de archivo no soportado'], 400);
    }

    /**
     * Adjuntar archivos descargados de Belender como entradas en fichero_campo
     *
     * Espera JSON POST con: id_expediente, id_campo_hito, id_campo_hito_expediente, dni, files (array de nombres guardados en files_directory/{dni})
     *
     */
    public function adjuntarArchivosBelenderAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !is_array($data)) {
            return new JsonResponse(['success' => false, 'error' => 'JSON inválido'], 400);
        }

        // Validar parámetros mínimos: dni y files
        $missing = [];
        if (!isset($data['dni']) || !$data['dni']) $missing[] = 'dni';
        if (!isset($data['files']) || !is_array($data['files']) || empty($data['files'])) $missing[] = 'files';
        // id_expediente puede venir en body o en query string
        $idExpediente = $data['id_expediente'] ?? $request->query->get('id_expediente');

        // Si no viene, intentar extraer id_expediente desde el Referer (URL de la vista)
        if (!$idExpediente) {
            $referer = $request->headers->get('referer') ?: $request->server->get('HTTP_REFERER');
            if ($referer) {
                // Intentar extraer un número de expediente en la URL (e.g. .../Expediente/15642)
                if (preg_match('/Expediente\/([0-9]+)/i', $referer, $m)) {
                    $idExpediente = $m[1];
                } else {
                    // fallback: buscar el último segmento numérico
                    $parts = preg_split('/[\/\\]+/', trim(parse_url($referer, PHP_URL_PATH) ?: ''));
                    $parts = array_reverse($parts);
                    foreach ($parts as $p) {
                        if (preg_match('/^[0-9]+$/', $p)) { $idExpediente = $p; break; }
                    }
                }
            }
        }

        if (!$idExpediente) $missing[] = 'id_expediente';

        if (!empty($missing)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Faltan parámetros requeridos',
                'missing' => $missing
            ], 400);
        }

        $dni = $data['dni'];
        $files = $data['files'];
        // id_hito_expediente opcional: permite asociar el fichero al hito-expediente exacto
        $idHitoExpediente = $data['id_hito_expediente'] ?? null;

        $doctrine = $this->getDoctrine();
        $em = $doctrine->getManager();

        $expediente = $doctrine->getRepository(ExpedienteEntidad::class)->findOneBy(array('idExpediente' => $idExpediente));
        if (!$expediente) {
            return new JsonResponse(['success' => false, 'error' => 'Expediente no encontrado'], 404);
        }

        // Map asociativo: keyword (sin tildes, minúscula) => título exacto de hito
        /*$map = [
            // Identidad
            'dni' => 'DNI - NIE - TARJETA RESIDENCIA',
            'nie' => 'DNI - NIE - TARJETA RESIDENCIA',
            'tarjeta' => 'DNI - NIE - TARJETA RESIDENCIA',
            // Contrato
            'contrato' => 'CONTRATO DE TRABAJO',
            'contrato_trabajo' => 'CONTRATO DE TRABAJO',
            // Nóminas
            'nomina' => '3 ÚLTIMAS NÓMINAS',
            'nominas' => '3 ÚLTIMAS NÓMINAS',
            'ultima_nomina' => '3 ÚLTIMAS NÓMINAS',
            // Renta / Fiscal
            'renta' => 'RENTA ÚLTIMO AÑO',
            'certificado_retenciones' => 'CERTIFICADO RETENCIONES ÚLTIMO AÑO',
            'retenciones' => 'CERTIFICADO RETENCIONES ÚLTIMO AÑO',
            // Vida laboral
            'vida' => 'VIDA LABORAL',
            'vida_laboral' => 'VIDA LABORAL',
            // Bancarios
            'movimientos' => 'MOVIMIENTOS BANCARIOS 6 ÚLTIMOS MESES',
            'movimientos_bancarios' => 'MOVIMIENTOS BANCARIOS 6 ÚLTIMOS MESES',
            // Alquiler
            'alquiler' => 'CONTRATO DE ALQUILER',
            'contrato_alquiler' => 'CONTRATO DE ALQUILER',
            // Préstamos
            'recibo_prestamo' => '3 ÚLTIMOS RECIBOS PRÉSTAMOS',
            'recibos_prestamo' => '3 ÚLTIMOS RECIBOS PRÉSTAMOS',
            'cuadro_amortizacion' => 'CUADRO AMORTIZACIÓN PRÉSTAMO',
            'amortizacion' => 'CUADRO AMORTIZACIÓN PRÉSTAMO',
            // Convenio / Sentencia
            'convenio' => 'CONVENIO Y SENTENCIA',
            'sentencia' => 'CONVENIO Y SENTENCIA',
            // Autónomo
            'autonomo' => 'RECIBO AUTÓNOMO',
            'recibo_autonomo' => 'RECIBO AUTÓNOMO',
            // Trimestres IVA/IRPF
            'iva' => 'TRIMESTRES IVA - IRPF',
            'irpf' => 'TRIMESTRES IVA - IRPF',
            'trimestre' => 'TRIMESTRES IVA - IRPF',
            // Estado civil / Pensión
            'certificado_estado_civil' => 'CERTIFICADO ESTADO CIVIL',
            'estado_civil' => 'CERTIFICADO ESTADO CIVIL',
            'pension' => 'CERTIFICADO PENSIÓN',
            'pensión' => 'CERTIFICADO PENSIÓN',
            // Otros
            'otros' => 'OTROS',
            'documentacion_adicional' => 'OTROS'
        ];*/
        /*$map = [
            '100_2022' => '100_2022',
            '100_2023' => '100_2023',
            '100_2024' => '100_2024',
            '111' => '111',
            '130' => '130',
            '131' => '131',
            '190' => 'CERTIFICADO RETENCIONES ÚLTIMO AÑO',
            '303' => 'TRIMESTRES IVA - IRPF',
            '347' => '347',
            '390' => '390',
            'Censo' => 'Censo',
            'Cotizacion_V2' => 'Cotizacion_V2',
            'Pensiones' => 'CERTIFICADO PENSIÓN',
            'Renta' => 'RENTA ÚLTIMO AÑO',
            'Revalorizacion' => 'Revalorizacion',
            'SituacionAEAT' => 'SituacionAEAT',
            'SituacionSS' => 'SituacionSS',
            'Laboral_V2' => 'Laboral_V2',
        ];*/
        $map = [
            '100_2022' => '100_2022',
            '100_2023' => 'PENÚLTIMA RENTA',
            '100_2024' => 'RENTA ÚLTIMO AÑO',
            '111' => '111',
            '130' => 'MODELO TRIMESTRE IRPF AÑO EN CURSO',
            '131' => '131',
            '190' => '190',
            '303' => 'MODELO TRIMESTRE IVA AÑO EN CURSO',
            '347' => '347',
            '390' => 'RESUMEN IVA AÑO ANTERIOR',
            'Censo' => 'Censo',
            'Cotizacion_V2' => 'Cotizacion_V2',
            'Cotizaciones' => 'Cotizacion_V2',
            'Pensiones' => 'CERTIFICADO PENSIÓN',
            'Renta' => 'RENTA ÚLTIMO AÑO',
            'Revalorizacion' => 'Revalorizacion',
            'SituacionAEAT' => 'SituacionAEAT',
            'SituacionSS' => 'SituacionSS',
            'Laboral_V2' => 'TRAYECTORIA PROFESIONAL',
            'Laboral' => 'TRAYECTORIA PROFESIONAL',
            'CIRBE' => 'CIRBE',
            'CIRBE_ICI' => 'CIRBE',
        ];

        // Normalizador: quita tildes y convierte a minúsculas para matching robusto
        $normalize = function($s) {
            $s = (string)$s;
            // translitera acentos (si está disponible)
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
            if ($converted !== false) $s = $converted;
            $s = strtolower($s);
            $s = preg_replace('/[^a-z0-9_\- ]+/', '', $s);
            return $s;
        };

        // Preparar mapa normalizado (clave normalizada => título) y ordenar por longitud de clave descendente
        $mapNormalized = [];
        foreach ($map as $keyword => $titulo) {
            $k = $normalize($keyword);
            if ($k !== '') $mapNormalized[$k] = $titulo;
        }
        // Orden keys por longitud para priorizar coincidencias más específicas
        $mapKeys = array_keys($mapNormalized);
        usort($mapKeys, function($a, $b) { return strlen($b) - strlen($a); });

        $repoCampoHito = $doctrine->getRepository(CampoHitoEntidad::class);
        $repoCampoHitoExp = $doctrine->getRepository(CampoHitoExpedienteEntidad::class);

        $filesDirectoryDni = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $dni;
        $filesDirectoryRoot = rtrim($this->getParameter('files_directory'), '\\/');

        if (!is_dir($filesDirectoryDni)) {
            return new JsonResponse(['success' => false, 'error' => 'No existen archivos para el DNI proporcionado'], 404);
        }

        $created = [];
        $errors = [];

        // Log: id_expediente detectado y origen
        try {
            $origin = isset($data['id_expediente']) && $data['id_expediente'] ? 'body' : ($request->query->get('id_expediente') ? 'query' : 'referer');
            error_log("ℹ️ adjuntarArchivosBelenderAction - id_expediente usado: $idExpediente (origen: $origin)");
        } catch (Exception $e) {}

        foreach ($files as $fileName) {
            $safeName = basename($fileName);
            $origPath = $filesDirectoryDni . DIRECTORY_SEPARATOR . $safeName;
            if (!file_exists($origPath) || !is_file($origPath)) {
                $errors[] = "Archivo no encontrado: $safeName";
                continue;
            }

            // Determinar título del hito a partir del nombre de fichero usando PRIMERO el mapa normalizado
            $hitoTitulo = null;
            $matchedMapKey = null;
            $normSafe = $normalize($safeName);

            // Intentar coincidencia con mapa normalizado (priorizar claves largas/específicas)
            foreach ($mapKeys as $mkey) {
                if ($mkey === '') continue;
                if (strpos($normSafe, $mkey) !== false) {
                    $hitoTitulo = $mapNormalized[$mkey];
                    $matchedMapKey = $mkey;
                    break;
                }
            }

            // Fallback adicional: comprobar nombre sin extensión y tokens separados por guiones/underscores/espacios
            if (!$hitoTitulo) {
                $baseNameNoExt = pathinfo($safeName, PATHINFO_FILENAME);
                $normBase = $normalize($baseNameNoExt);
                foreach ($mapKeys as $mkey) {
                    if ($mkey === '') continue;
                    if (strpos($normBase, $mkey) !== false) {
                        $hitoTitulo = $mapNormalized[$mkey];
                        $matchedMapKey = $mkey;
                        break;
                    }
                    // token exact match
                    $tokens = preg_split('/[_\-\s]+/', $normBase);
                    if (in_array($mkey, $tokens, true)) {
                        $hitoTitulo = $mapNormalized[$mkey];
                        $matchedMapKey = $mkey;
                        break;
                    }
                    // word boundary match
                    if (preg_match('/\b' . preg_quote($mkey, '/') . '\b/', $normBase)) {
                        $hitoTitulo = $mapNormalized[$mkey];
                        $matchedMapKey = $mkey;
                        break;
                    }
                }
            }

            // heurísticas antiguas como último recurso
            if (!$hitoTitulo) {
                if (stripos($safeName, 'renta') !== false) {
                    $hitoTitulo = 'RENTA ÚLTIMO AÑO';
                } else {
                    $lower = strtolower($safeName);
                    if (strpos($lower, 'nomina') !== false || strpos($lower, 'nominas') !== false) {
                        $hitoTitulo = '3 ÚLTIMAS NÓMINAS';
                    }
                }
            }

            // Debug: si no se ha encontrado hito, volcar info para rastrear por qué falló
            if (!$hitoTitulo) {
                try {
                    error_log("🔍 Belender mapping failed for file='$safeName' normSafe='$normSafe' normBase='" . ($normBase ?? '') . "'");
                    error_log('🔑 Map keys sample: ' . implode(',', array_slice($mapKeys, 0, 30)) );
                } catch (\Exception $e) {}
            }

            if (!$hitoTitulo) {
                $errors[] = "No se pudo mapear el fichero '$safeName' a un hito";
                continue;
            }

            // Buscar id_campo_hito por LIKE (tomar el más reciente)
            $qb = $repoCampoHito->createQueryBuilder('c')
                ->where('c.nombre LIKE :titulo')
                ->setParameter('titulo', '%' . $hitoTitulo . '%')
                ->orderBy('c.idCampoHito', 'DESC')
                ->setMaxResults(1);
            $campo = $qb->getQuery()->getOneOrNullResult();

            if (!$campo) {
                $errors[] = "No se encontró campo_hito para título '$hitoTitulo' (archivo: $safeName)";
                continue;
            }

            // Buscar campo_hito_expediente para ese id_campo_hito e id_expediente
            $qb2 = $repoCampoHitoExp->createQueryBuilder('ce')
                ->where('ce.idCampoHito = :idCampo')
                ->andWhere('ce.idExpediente = :idExpediente')
                ->setParameter('idCampo', $campo->getIdCampoHito())
                ->setParameter('idExpediente', $idExpediente);

            // NUEVO: Filtrar también por id_hito_expediente si viene dado
            if ($idHitoExpediente) {
                $qb2->andWhere('ce.idHitoExpediente = :idHitoExpediente')
                    ->setParameter('idHitoExpediente', $idHitoExpediente);
            }

            $qb2->orderBy('ce.idCampoHitoExpediente', 'DESC')
                ->setMaxResults(1);
            
            $campoExp = $qb2->getQuery()->getOneOrNullResult();

            if (!$campoExp) 
            {
                $fileDebug[] = "❌ CampoHitoExpediente no encontrado, intentando crear uno...";
                
                // Obtener el Hito del CampoHito
                $hito = $campo->getIdGrupoCamposHito()->getIdHito();
                
                // Buscar o crear HitoExpediente
                $hitoExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findOneBy([
                    'idHito' => $hito,
                    'idExpediente' => $expediente
                ]);
                
                if (!$hitoExpediente) {
                    $hitoExpediente = (new HitoExpedienteEntidad())
                        ->setIdHito($hito)
                        ->setIdExpediente($expediente)
                        ->setFechaModificacion(new \DateTime())
                        ->setEstado(0);
                    $em->persist($hitoExpediente);
                    $em->flush();
                    $fileDebug[] = "✅ HitoExpediente creado: ID={$hitoExpediente->getIdHitoExpediente()}";
                } else {
                    $fileDebug[] = "✅ HitoExpediente encontrado: ID={$hitoExpediente->getIdHitoExpediente()}";
                }
                
                // Buscar o crear GrupoHitoExpediente
                $grupoHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findOneBy([
                    'idHitoExpediente' => $hitoExpediente,
                    'idGrupoCamposHito' => $campo->getIdGrupoCamposHito()
                ]);
                
                if (!$grupoHitoExpediente) {
                    $grupoHitoExpediente = (new GrupoHitoExpedienteEntidad())
                        ->setIdHitoExpediente($hitoExpediente)
                        ->setIdGrupoCamposHito($campo->getIdGrupoCamposHito());
                    $em->persist($grupoHitoExpediente);
                    $em->flush();
                    $fileDebug[] = "✅ GrupoHitoExpediente creado: ID={$grupoHitoExpediente->getIdGrupoHitoExpediente()}";
                } else {
                    $fileDebug[] = "✅ GrupoHitoExpediente encontrado: ID={$grupoHitoExpediente->getIdGrupoHitoExpediente()}";
                }
                
                // Crear el CampoHitoExpediente
                if ($hitoExpediente && $grupoHitoExpediente) {
                    $campoExp = (new CampoHitoExpedienteEntidad())
                        ->setIdCampoHito($campo)
                        ->setIdHitoExpediente($hitoExpediente)
                        ->setIdGrupoHitoExpediente($grupoHitoExpediente)
                        ->setIdExpediente($expediente)
                        ->setFechaModificacion(new \DateTime())
                        ->setObligatorio(0)
                        ->setSolicitarAlColaborador(0)
                        ->setAvisarColaborador(0)
                        ->setParaFirmar(0)
                        ->setFirmado(0)
                        ->setEnviarAlCliente(0)
                        ->setEnviarAlColaborador(0);
                    
                    // El valor se establecerá con el nombre del fichero
                    $campoExp->setValor(pathinfo($safeName, PATHINFO_FILENAME));
                    
                    $em->persist($campoExp);
                    $em->flush();
                    $fileDebug[] = "✅ CampoHitoExpediente creado automáticamente: ID={$campoExp->getIdCampoHitoExpediente()}";
                } else {
                    $msg = "❌ No se pudo crear CampoHitoExpediente: hitoExpediente=" . ($hitoExpediente ? 'OK' : 'NULL') . ", grupoHitoExpediente=" . ($grupoHitoExpediente ? 'OK' : 'NULL');
                    $fileDebug[] = $msg;
                    $errors[] = $msg . " (archivo: $safeName)";
                    $debug[] = implode(" | ", $fileDebug);
                    continue;
                }
            } else {
                $fileDebug[] = "✅ CampoHitoExpediente encontrado: ID={$campoExp->getIdCampoHitoExpediente()}";
                // Actualizar el valor del campo con el nombre del fichero
                $campoExp->setValor(pathinfo($safeName, PATHINFO_FILENAME));
                $em->persist($campoExp);
                $em->flush();

            }

            $ext = pathinfo($safeName, PATHINFO_EXTENSION);
            $newName = md5(uniqid('', true)) . '.' . $ext;
            $newPath = $filesDirectoryRoot . DIRECTORY_SEPARATOR . $newName;

            // Copiar al directorio principal de uploads (sin eliminar original)
            if (!@copy($origPath, $newPath)) {
                $errors[] = "No se pudo copiar $safeName";
                continue;
            }

            // Leer JSON asociado si existe
            $contenidoJson = null;
            $jsonPath = pathinfo($origPath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . 
                        pathinfo($origPath, PATHINFO_FILENAME) . '.json';
            
            error_log("🔍 DEBUG JSON - Archivo: $safeName, Buscando JSON en: $jsonPath");
            
            if (file_exists($jsonPath) && is_file($jsonPath)) {
                error_log("✅ JSON encontrado en: $jsonPath");
                $jsonContent = @file_get_contents($jsonPath);
                if ($jsonContent !== false) {
                    error_log("📄 JSON leído, tamaño: " . strlen($jsonContent) . " bytes");
                    // Validar que sea JSON válido
                    json_decode($jsonContent);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $contenidoJson = $jsonContent;
                        error_log("✅ JSON válido guardado en contenidoJson");
                    } else {
                        error_log("❌ JSON inválido: " . json_last_error_msg());
                    }
                } else {
                    error_log("❌ No se pudo leer el archivo JSON");
                }
            } else {
                error_log("❌ JSON no encontrado en: $jsonPath");
            }

            // Buscar si ya existe un FicheroCampo con los mismos datos (upsert)
            $existingFichero = $doctrine->getRepository(FicheroCampoEntidad::class)->findOneBy([
                'idCampoHito' => $campo->getIdCampoHito(),
                'idCampoHitoExpediente' => $campoExp->getIdCampoHitoExpediente(),
                'idExpediente' => $idExpediente
            ]);

            if ($existingFichero) {
                // Actualizar: eliminar archivo antiguo y actualizar nombre
                $oldName = $existingFichero->getNombreFichero();
                $oldPath = $filesDirectoryRoot . DIRECTORY_SEPARATOR . $oldName;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
                $existingFichero->setNombreFichero($newName);
                if ($contenidoJson !== null) {
                    error_log("📝 Asignando contenidoJson a FicheroCampo actualizado (tamaño: " . strlen($contenidoJson) . " bytes)");
                    $existingFichero->setContenidoJson($contenidoJson);
                } else {
                    error_log("⚠️ contenidoJson es NULL, no asignando nada");
                }
                if (method_exists($existingFichero, 'setFechaActualizacion')) {
                    $existingFichero->setFechaActualizacion(new \DateTime());
                }
                $em->persist($existingFichero);
                $em->flush();
                error_log("✅ FicheroCampo actualizado en BD");
                
                $created[] = [
                    'original' => $safeName,
                    'stored' => $newName,
                    'id_fichero_campo' => $existingFichero->getIdFicheroCampo(),
                    'id_campo_hito' => $campo->getIdCampoHito(),
                    'id_campo_hito_expediente' => $campoExp->getIdCampoHitoExpediente(),
                    'action' => 'updated'
                ];
            } else {
                // Crear nuevo FicheroCampo
                $ficheroCampo = new FicheroCampoEntidad();
                $ficheroCampo->setIdCampoHito($campo);
                $ficheroCampo->setIdCampoHitoExpediente($campoExp);
                $ficheroCampo->setIdExpediente($expediente);
                $ficheroCampo->setNombreFichero($newName);
                if ($contenidoJson !== null) {
                    error_log("📝 Asignando contenidoJson a FicheroCampo nuevo (tamaño: " . strlen($contenidoJson) . " bytes)");
                    $ficheroCampo->setContenidoJson($contenidoJson);
                } else {
                    error_log("⚠️ contenidoJson es NULL, no asignando nada");
                }
                if (method_exists($ficheroCampo, 'setFechaCreacion')) {
                    $ficheroCampo->setFechaCreacion(new \DateTime());
                }

                $em->persist($ficheroCampo);
                $em->flush();
                error_log("✅ FicheroCampo creado en BD");

                $created[] = [
                    'original' => $safeName,
                    'stored' => $newName,
                    'id_fichero_campo' => $ficheroCampo->getIdFicheroCampo(),
                    'id_campo_hito' => $campo->getIdCampoHito(),
                    'id_campo_hito_expediente' => $campoExp->getIdCampoHitoExpediente(),
                    'action' => 'created'
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'created' => $created,
            'errors' => $errors
        ]);
    }

    
    public function procesarDocumentosBelenderAction(Request $request)
    {
        // Habilitar reporte de errores para debugging
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        
        $debug = [];
        try {
            $debug[] = "INICIO procesarDocumentosBelenderAction";
            error_log("=== INICIO procesarDocumentosBelenderAction ===");
            
            $dni = $request->query->get('dni');
            $debug[] = "DNI recibido: " . $dni;
            error_log("DNI recibido: " . $dni);
            
            if (!$dni) {
                return new JsonResponse(['error' => 'DNI no proporcionado', 'debug' => $debug], 400);
            }

            $filesDirectory = $this->getParameter('files_directory') . '/' . $dni;
            $debug[] = "Directorio: " . $filesDirectory;
            $debug[] = "¿Existe directorio?: " . (is_dir($filesDirectory) ? 'SI' : 'NO');
            error_log("Directorio de archivos: " . $filesDirectory);
            
            if (!is_dir($filesDirectory)) {
                return new JsonResponse(['error' => 'No hay documentos para este DNI', 'debug' => $debug], 404);
            }

            // Intentar consolidar con IA, si falla usar datos básicos
            $debug[] = "Llamando a consolidar_documentos_belender...";
            error_log("Llamando a consolidar_documentos_belender...");
            $datosConsolidados = $this->consolidar_documentos_belender($filesDirectory, $dni);
            
            if (!$datosConsolidados) {
                $debug[] = "⚠️ La IA no pudo consolidar, generando estructura básica...";
                error_log("⚠️ La IA no pudo consolidar, generando estructura básica...");
                
                // Intentar obtener el último error de los logs
                $errorDetails = "Revisar logs del servidor para más detalles";
                
                // Fallback: crear estructura básica sin IA
                $archivosJson = glob($filesDirectory . '/*.json');
                $archivosJson = array_filter($archivosJson, function($archivo) {
                    return basename($archivo) !== 'datos_consolidados.json';
                });
                
                $todosLosDatos = [];
                foreach ($archivosJson as $archivoJson) {
                    $contenido = file_get_contents($archivoJson);
                    $datos = json_decode($contenido, true);
                    if ($datos) {
                        $nombreDocumento = basename($archivoJson, '.json');
                        $todosLosDatos[$nombreDocumento] = $datos;
                    }
                }
                
                $datosConsolidados = [
                    'estado' => 'consolidacion_basica',
                    'mensaje' => 'No se pudo usar IA. Datos sin procesar.',
                    'motivo_fallo_ia' => $errorDetails,
                    'documentos_originales' => $todosLosDatos,
                    'total_documentos' => count($todosLosDatos),
                    'dni' => $dni,
                    'fecha_consolidacion' => date('Y-m-d H:i:s')
                ];
                
                $debug[] = "✅ Estructura básica generada con " . count($todosLosDatos) . " documentos";
            } else {
                $debug[] = "✅ Consolidación con IA completada exitosamente";
            }
            
            $debug[] = "Todo OK - Guardando archivo...";
        } catch (\Exception $e) {
            $debug[] = "EXCEPCIÓN: " . $e->getMessage();
            $debug[] = "Línea: " . $e->getLine();
            $debug[] = "Archivo: " . $e->getFile();
            error_log("EXCEPCIÓN en procesarDocumentosBelenderAction: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return new JsonResponse(['error' => 'Error interno: ' . $e->getMessage(), 'debug' => $debug, 'trace' => $e->getTraceAsString()], 500);
        }

        // Guardar el JSON consolidado
        $jsonConsolidadoPath = $filesDirectory . '/datos_consolidados.json';
        file_put_contents($jsonConsolidadoPath, json_encode($datosConsolidados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $debug[] = "Archivo guardado en: " . $jsonConsolidadoPath;

        // Devolver los datos consolidados directamente (con estado y documentos_originales si aplica)
        $datosConsolidados['archivo_guardado'] = $jsonConsolidadoPath;
        $datosConsolidados['debug'] = $debug;
        
        return new JsonResponse($datosConsolidados);
    }

    public function compararDatosExpedienteAction(Request $request)
    {
        $dni = $request->query->get('dni');
        $datosExpedienteRaw = $request->request->get('datos_expediente'); // Datos que ingresó el usuario (JSON string o array)

        // Normalizar: si se recibe una cadena JSON, decodificarla
        $datosExpediente = $datosExpedienteRaw;
        if (is_string($datosExpedienteRaw)) {
            $decoded = json_decode($datosExpedienteRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datosExpediente = $decoded;
            } else {
                // Mantener la cadena si no es JSON válido
                $datosExpediente = $datosExpedienteRaw;
            }
        }
        
        if (!$dni) {
            return new JsonResponse(['error' => 'DNI no proporcionado'], 400);
        }

        $filesDirectory = $this->getParameter('files_directory') . '/' . $dni;
        $jsonConsolidadoPath = $filesDirectory . '/datos_consolidados.json';

        if (!file_exists($jsonConsolidadoPath)) {
            // Si no existe, generarlo
            $datosConsolidados = $this->consolidar_documentos_belender($filesDirectory, $dni);
            if ($datosConsolidados) {
                file_put_contents($jsonConsolidadoPath, json_encode($datosConsolidados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            $datosConsolidados = json_decode(file_get_contents($jsonConsolidadoPath), true);
        }

        if (!$datosConsolidados) {
            return new JsonResponse(['error' => 'No se pudieron obtener datos consolidados'], 500);
        }

        // Comparar usando IA
        $resultadoComparacion = $this->comparar_con_ia($datosExpediente, $datosConsolidados);

        if (is_array($resultadoComparacion) && isset($resultadoComparacion['error'])) {
            // Incluir motivo del fallo si tenemos información del servicio de IA
            $motivo = $resultadoComparacion['error'];
            if ($this->last_gemini_error) {
                $motivo .= ' | detalle_ia: ' . $this->last_gemini_error;
            }
            $response = ['error' => $motivo];
            if ($this->last_gemini_http_code) {
                $response['motivo_fallo_ia_http_code'] = $this->last_gemini_http_code;
            }
            if ($this->last_gemini_response) {
                $response['motivo_fallo_ia_respuesta_raw'] = substr($this->last_gemini_response, 0, 2000);
            }
            return new JsonResponse($response, 500);
        }

        return new JsonResponse([
            'success' => true,
            'datos_expediente' => $datosExpediente,
            'datos_oficiales' => $datosConsolidados,
            'discrepancias' => isset($resultadoComparacion['discrepancias']) ? $resultadoComparacion['discrepancias'] : [],
            'coincidencias' => isset($resultadoComparacion['coincidencias']) ? $resultadoComparacion['coincidencias'] : [],
            'resumen' => isset($resultadoComparacion['resumen']) ? $resultadoComparacion['resumen'] : []
        ]);
    }

    public function cargarJsonAction(Request $request)
    {
        $ruta = "../100_2021.json";
        $this->procesar_json_belender($ruta);
        return new JsonResponse("OK");
    }

    private function llamar_gemini_api($mensaje, $contexto_conversacion = '', $modelo = 'gemini-2.5-flash', $system_prompt = '')
    {
        error_log("=== INICIO llamar_gemini_api ===");
        
        // API key para Google Generative Language (configúrela según entorno)
        // NOTA: cambiar por la key que usted utiliza en frontend si corresponde.
        $google_ai_studio_api_key = "AIzaSyB2QjeyKlwcZ2aYdwFJ5wvLpFqErbURkVc";
        error_log("🔑 API Key presente: " . (empty($google_ai_studio_api_key) ? 'NO' : 'SI'));
        error_log("🔑 API Key longitud: " . strlen($google_ai_studio_api_key));

        if (empty($google_ai_studio_api_key)) {
            error_log("❌ [Gemini] API key no configurada");
            return null;
        }

        // NO sobrescribir los parámetros recibidos
        // Construir prompt completo
        $prompt = !empty($system_prompt)
            ? $system_prompt . "\n\nMENSAJE:\n" . $mensaje . (!empty($contexto_conversacion) ? "\n\nContexto:\n" . $contexto_conversacion : "")
            : $mensaje;

        error_log("📝 Longitud del prompt: " . strlen($prompt) . " caracteres");
        error_log("📝 Modelo: " . $modelo);

        // Usar el modelo recibido en el parámetro $modelo (por defecto 'gemini-2.5-flash')
        $modelo_sanitizado = urlencode($modelo);
        $url = "https://generativelanguage.googleapis.com/v1/models/" . $modelo_sanitizado . ":generateContent?key=" . $google_ai_studio_api_key;
        error_log("🌐 URL final Gemini: " . substr($url, 0, 200));
        error_log("🌐 URL: " . substr($url, 0, 100) . "...");

        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8000,
            ]
        ];

        error_log("📤 Enviando petición a Gemini...");
        $httpResult = $this->hacer_peticion_http($url, $data, [
            "Content-Type: application/json",
            "User-Agent: IAGestion-WhatsApp/1.0"
        ]);

        if (!$httpResult || !isset($httpResult['ok']) || $httpResult['ok'] === false) {
            $this->last_gemini_error = isset($httpResult['error']) ? $httpResult['error'] : 'No se recibió respuesta HTTP';
            $this->last_gemini_response = isset($httpResult['body']) ? $httpResult['body'] : null;
            $this->last_gemini_http_code = isset($httpResult['httpCode']) ? $httpResult['httpCode'] : null;
            error_log("❌ [Gemini] No se recibió respuesta HTTP o error: " . $this->last_gemini_error);
            return null;
        }

        error_log("📥 Respuesta recibida, decodificando JSON...");
        $response = $httpResult['body'];
        $result = json_decode($response, true);
        // Guardar raw response y http code para debugging
        $this->last_gemini_response = $response;
        $this->last_gemini_http_code = $httpResult['httpCode'];
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_gemini_error = 'Error al decodificar JSON: ' . json_last_error_msg();
            error_log("❌ [Gemini] " . $this->last_gemini_error);
            error_log("📄 Respuesta raw (primeros 500 chars): " . substr($response, 0, 500));
            return null;
        }

        error_log("✅ JSON decodificado correctamente");
        error_log("🔍 Estructura recibida: " . json_encode(array_keys($result)));

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $texto = trim($result['candidates'][0]['content']['parts'][0]['text']);
            error_log("✅ [Gemini] Respuesta exitosa, longitud: " . strlen($texto) . " caracteres");
            return $texto;
        }

        if (isset($result['error'])) {
            $this->last_gemini_error = 'API error: ' . json_encode($result['error']);
            error_log("❌ [Gemini] Error de API: " . json_encode($result['error']));
        } else {
            $this->last_gemini_error = 'Respuesta inesperada de Gemini';
            error_log("⚠️ [Gemini] Respuesta inesperada (primeros 1000 chars): " . substr($response, 0, 1000));
        }

        return null;
    }

    private function hacer_peticion_http($url, $data, $headers, $timeout = 30)
    {
        try {
            error_log("🌐 [HTTP] Iniciando petición a: " . $url);
            
            // Usar cURL en lugar de file_get_contents
            $ch = curl_init($url);
            
            if ($ch === false) {
                error_log("❌ [HTTP] No se pudo inicializar cURL");
                return null;
            }
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);

                curl_close($ch);

                if ($response === false) {
                    error_log("❌ [HTTP] Error cURL: " . $curlError);
                    return ['ok' => false, 'error' => $curlError, 'httpCode' => $httpCode, 'body' => null];
                }

                error_log("✅ [HTTP] Respuesta recibida: " . strlen($response) . " bytes (HTTP $httpCode)");

                if ($httpCode >= 400) {
                    error_log("⚠️ [HTTP] Código de error: $httpCode");
                    error_log("Respuesta: " . substr($response, 0, 500));
                }

                return ['ok' => true, 'body' => $response, 'httpCode' => $httpCode];
            
        } catch (\Exception $e) {
            error_log("❌ [HTTP] Excepción: " . $e->getMessage());
            return null;
        }
    }

    private function consolidar_documentos_belender($filesDirectory, $dni)
    {
        try {
            error_log("=== INICIO consolidar_documentos_belender ===");
            error_log("Directorio: $filesDirectory");
            error_log("DNI: $dni");
            
            // Obtener todos los archivos JSON
            $archivosJson = glob($filesDirectory . '/*.json');
            
            if (empty($archivosJson)) {
                error_log("❌ No se encontraron archivos JSON en: $filesDirectory");
                return null;
            }

            // Excluir el archivo de consolidación si existe
            $archivosJson = array_filter($archivosJson, function($archivo) {
                return basename($archivo) !== 'datos_consolidados.json';
            });

            error_log("📄 Archivos JSON encontrados: " . count($archivosJson));

            // Leer todos los documentos
            $todosLosDatos = [];
            foreach ($archivosJson as $archivoJson) {
                $contenido = file_get_contents($archivoJson);
                $datos = json_decode($contenido, true);
                
                if ($datos) {
                    $nombreDocumento = basename($archivoJson, '.json');
                    $todosLosDatos[$nombreDocumento] = $datos;
                    error_log("✅ JSON leído: $nombreDocumento");
                }
            }

            if (empty($todosLosDatos)) {
                error_log("❌ No se pudo leer ningún documento JSON");
                return null;
            }

            // Crear prompt para Gemini
            $systemPrompt = $this->obtener_system_prompt_consolidacion();
            $mensaje = "DOCUMENTOS A CONSOLIDAR:\n\n" . json_encode($todosLosDatos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            error_log("🤖 Enviando " . count($todosLosDatos) . " documentos a Gemini para consolidación...");

            // Llamar a Gemini para consolidar
            $respuestaGemini = $this->llamar_gemini_api($mensaje, '', 'gemini-2.5-flash', $systemPrompt);

            if (!$respuestaGemini) {
                error_log("❌ No se recibió respuesta de Gemini");
                return null;
            }

            // Extraer JSON de la respuesta (puede venir con markdown)
            $jsonConsolidado = $this->extraer_json_de_respuesta($respuestaGemini);

            if ($jsonConsolidado) {
                error_log("✅ Datos consolidados exitosamente");
                return $jsonConsolidado;
            }

            error_log("❌ No se pudo extraer JSON válido de la respuesta de Gemini");
            return null;
            
        } catch (\Exception $e) {
            error_log("❌ EXCEPCIÓN en consolidar_documentos_belender: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
            return null;
        }
    }

    private function obtener_system_prompt_consolidacion()
    {
        return <<<PROMPT
        Eres un asistente especializado en procesar documentos oficiales españoles (IRPF, catastro, vida laboral, etc.) provenientes de la API de Belender.

        TAREA: Consolidar múltiples documentos JSON en UN SOLO JSON estructurado con los siguientes campos:

        {
            "datos_personales": {
                "nombre_completo": "",
                "dni": "",
                "fecha_nacimiento": "",
                "estado_civil": "",
                "numero_hijos": 0,
                "nacionalidad": ""
            },
            "domicilio": {
                "direccion_completa": "",
                "codigo_postal": "",
                "municipio": "",
                "provincia": ""
            },
            "situacion_laboral": {
                "situacion": "empleado|autonomo|pensionista|desempleado",
                "empresa": "",
                "antiguedad": "",
                "tipo_contrato": "indefinido|temporal|autonomo",
                "ocupacion": ""
            },
            "ingresos": {
                "base_liquidable_general": 0,
                "base_liquidable_ahorro": 0,
                "ingresos_brutos_anuales": 0,
                "ingresos_netos_mensuales": 0,
                "otras_rentas": 0
            },
            "patrimonio": {
                "inmuebles": [
                    {
                        "referencia_catastral": "",
                        "direccion": "",
                        "valor_catastral": 0,
                        "tipo": "vivienda|local|garaje|trastero",
                        "porcentaje_propiedad": 100
                    }
                ],
                "vehiculos": [],
                "cuentas_bancarias": [],
                "inversiones": []
            },
            "cargas_financieras": {
                "hipotecas": [],
                "prestamos": [],
                "tarjetas_credito": [],
                "total_deuda_mensual": 0
            },
            "informacion_fiscal": {
                "ejercicio_fiscal": "",
                "cuota_liquida": 0,
                "retenciones": 0,
                "deducciones": []
            },
            "metadatos": {
                "documentos_procesados": [],
                "fecha_consolidacion": "",
                "confianza": "alta|media|baja"
            }
        }

        INSTRUCCIONES:
        1. Analiza TODOS los documentos proporcionados
        2. Extrae información relevante de cada uno
        3. Consolida en la estructura indicada
        4. Si un dato está en varios documentos, usa el más reciente
        5. Si un campo no se puede determinar, déjalo vacío o en 0
        6. En "metadatos.documentos_procesados" lista qué documentos usaste
        7. Devuelve SOLO el JSON válido, sin markdown ni explicaciones

        IMPORTANTE: Responde ÚNICAMENTE con el JSON, sin texto adicional.
        PROMPT;
    }

    private function extraer_json_de_respuesta($respuesta)
    {
        // Intentar extraer JSON de bloques markdown
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $respuesta, $matches)) {
            $jsonString = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $respuesta, $matches)) {
            $jsonString = $matches[1];
        } else {
            $jsonString = $respuesta;
        }

        // Limpiar
        $jsonString = trim($jsonString);

        // Intentar decodificar
        $datos = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $datos;
        }

        error_log("❌ Error JSON: " . json_last_error_msg());
        error_log("📄 Respuesta recibida: " . substr($respuesta, 0, 500));
        
        return null;
    }

    private function comparar_con_ia($datosExpediente, $datosOficiales)
    {
        $systemPrompt = <<<PROMPT
        Eres un auditor financiero especializado en detectar discrepancias entre datos declarados y documentación oficial.

        TAREA: Compara los datos del expediente con los datos oficiales consolidados y devuelve un JSON con las discrepancias encontradas.

        FORMATO DE RESPUESTA:
        {
            "discrepancias": [
                {
                    "campo": "nombre del campo",
                    "valor_declarado": "lo que dijo el usuario",
                    "valor_oficial": "lo que dice la documentación",
                    "gravedad": "alta|media|baja",
                    "descripcion": "explicación breve"
                }
            ],
            "coincidencias": [
                {
                    "campo": "nombre del campo",
                    "valor": "valor coincidente"
                }
            ],
            "resumen": {
                "total_discrepancias": 0,
                "discrepancias_graves": 0,
                "porcentaje_coincidencia": 0
            }
        }

        CRITERIOS DE GRAVEDAD:
        - ALTA: Diferencias superiores al 20% en ingresos, patrimonio o deudas
        - MEDIA: Diferencias entre 10-20% o datos contradictorios menores
        - BAJA: Diferencias mínimas o de formato

        Responde ÚNICAMENTE con el JSON, sin texto adicional.
        PROMPT;

        $mensaje = "DATOS DECLARADOS EN EXPEDIENTE:\n" . json_encode($datosExpediente, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . 
                   "\n\nDATOS OFICIALES CONSOLIDADOS:\n" . json_encode($datosOficiales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $respuestaGemini = $this->llamar_gemini_api($mensaje, '', 'gemini-2.5-flash', $systemPrompt);

        if (!$respuestaGemini) {
            return ['error' => 'No se pudo realizar la comparación'];
        }

        $resultado = $this->extraer_json_de_respuesta($respuestaGemini);

        return $resultado ?: ['error' => 'Respuesta inválida de IA'];
    }

    private function procesar_json_belender($ruta_archivo_json)
    {
        if (!file_exists($ruta_archivo_json)) {
            error_log("Archivo JSON no encontrado: $ruta_archivo_json");
            echo "Archivo JSON no encontrado: $ruta_archivo_json";
            return null;
        }

        $contenido_json = file_get_contents($ruta_archivo_json);
        $data_belener = json_decode($contenido_json, true);

        if (!$data_belener) {
            error_log("Error al decodificar JSON de Belener");
            return null;
        }

        $mensaje = "Datos recibidos de Belener: ";

        foreach ($data_belener as $clave => $valor) {
            if (is_array($valor)) {
                $valor = json_encode($valor);
            }
            $mensaje .= "$clave: $valor; ";
        }

        $respuesta_gemini = $this->llamar_gemini_api($mensaje);

        if ($respuesta_gemini) {
            echo "Respuesta de Gemini: " . $respuesta_gemini;
            return $respuesta_gemini;
        }

        error_log("No se recibió respuesta válida de Gemini");
        return null;
    }

    public function guardarDatosConsolidadosAction(Request $request)
    {
        try {
            error_log("=== INICIO guardarDatosConsolidadosAction ===");
            error_log("Content-Type: " . $request->getContentType());
            
            // Obtener datos del cuerpo JSON (no FormData)
            $data = json_decode($request->getContent(), true);
            
            error_log("📨 Datos recibidos: " . json_encode($data));
            
            if (!$data) {
                error_log("❌ JSON inválido en el body");
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Datos JSON inválidos en el cuerpo de la petición'
                ], 400);
            }

            $idExpediente = $data['id_expediente'] ?? null;
            $idCliente = $data['id_cliente'] ?? null;
            $datosJson = $data['datos_json'] ?? null;
            $datosJsonOriginal = $data['datos_json_original'] ?? null;
            
            error_log("ID Expediente: $idExpediente");
            error_log("ID Cliente: $idCliente");
            error_log("Datos JSON presente: " . (isset($data['datos_json']) ? 'SI' : 'NO'));
            error_log("Datos JSON Original presente: " . (isset($data['datos_json_original']) ? 'SI' : 'NO'));
            
            if (!$idExpediente || !$idCliente || !$datosJson) {
                error_log("❌ Parámetros faltantes: expediente=$idExpediente, cliente=$idCliente, datos=" . (isset($datosJson) ? 'SI' : 'NO'));
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Faltan campos requeridos: id_expediente, id_cliente, datos_json'
                ], 400);
            }

            // Validar que datos_json sea un JSON válido (si viene como string)
            if (is_string($datosJson)) {
                $datosJsonDecoded = json_decode($datosJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("❌ JSON inválido en datos_json: " . json_last_error_msg());
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'datos_json no es un JSON válido: ' . json_last_error_msg()
                    ], 400);
                }
            }

            // Validar que datos_json_original sea un JSON válido si está presente
            if ($datosJsonOriginal && is_string($datosJsonOriginal)) {
                $datosJsonOriginalDecoded = json_decode($datosJsonOriginal, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("⚠️ JSON inválido en datos_json_original: " . json_last_error_msg());
                    $datosJsonOriginal = null;
                }
            }

            // Obtener el EntityManager
            $em = $this->getDoctrine()->getManager();

            // Intentar encontrar un registro existente para el mismo expediente y cliente
            $repo = $em->getRepository('AppBundle:DatosConsolidadosBelender');
            $datosConsolidados = $repo->findOneBy([
                'idExpediente' => (int)$idExpediente,
                'idCliente' => (int)$idCliente
            ]);

            $action = 'created';
            if ($datosConsolidados) {
                // Actualizar registro existente
                $action = 'updated';
                $datosConsolidados->setDatosJson(is_array($datosJson) ? json_encode($datosJson) : $datosJson);
                if ($datosJsonOriginal) {
                    $datosConsolidados->setDatosJsonOriginal(is_array($datosJsonOriginal) ? json_encode($datosJsonOriginal) : $datosJsonOriginal);
                    error_log("✅ JSON original actualizado: " . strlen(is_array($datosJsonOriginal) ? json_encode($datosJsonOriginal) : $datosJsonOriginal) . " bytes");
                }
                $datosConsolidados->setFechaActualizacion(new \DateTime());
            } else {
                // Crear nueva entidad
                $datosConsolidados = new \AppBundle\Entity\DatosConsolidadosBelender();
                $datosConsolidados->setIdExpediente((int)$idExpediente);
                $datosConsolidados->setIdCliente((int)$idCliente);
                $datosConsolidados->setDatosJson(is_array($datosJson) ? json_encode($datosJson) : $datosJson);
                if ($datosJsonOriginal) {
                    $datosConsolidados->setDatosJsonOriginal(is_array($datosJsonOriginal) ? json_encode($datosJsonOriginal) : $datosJsonOriginal);
                    error_log("✅ JSON original guardado: " . strlen(is_array($datosJsonOriginal) ? json_encode($datosJsonOriginal) : $datosJsonOriginal) . " bytes");
                }
                $datosConsolidados->setFechaCreacion(new \DateTime());
                $datosConsolidados->setFechaActualizacion(new \DateTime());
            }

            // Persistir y guardar (Doctrine actualizará o insertará según corresponda)
            $em->persist($datosConsolidados);
            $em->flush();

            $idRegistro = $datosConsolidados->getId();
            error_log("✅ Datos consolidados guardados: ID $idRegistro");

            // Preparar diagnóstico para verificar tamaños/contenido recibido
            $storedDatosJson = $datosConsolidados->getDatosJson();
            $storedDatosJsonOriginal = $datosConsolidados->getDatosJsonOriginal();
            $lenDatosJson = $storedDatosJson ? strlen($storedDatosJson) : 0;
            $lenDatosJsonOriginal = $storedDatosJsonOriginal ? strlen($storedDatosJsonOriginal) : 0;
            error_log("ℹ️ Len datos_json: $lenDatosJson bytes");
            error_log("ℹ️ Len datos_json_original: $lenDatosJsonOriginal bytes");

            return new JsonResponse([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'id' => $idRegistro,
                'id_expediente' => (int)$idExpediente,
                'id_cliente' => (int)$idCliente,
                'fecha_creacion' => $datosConsolidados->getFechaCreacion()->format('Y-m-d H:i:s'),
                'con_json_original' => $datosJsonOriginal ? true : false,
                'len_datos_json' => $lenDatosJson,
                'len_datos_json_original' => $lenDatosJsonOriginal,
                'sample_datos_json_original' => $storedDatosJsonOriginal ? substr($storedDatosJsonOriginal, 0, 200) : null
            ], 200);

        } catch (\Exception $e) {
            error_log("❌ EXCEPCIÓN en guardarDatosConsolidadosAction: " . $e->getMessage());
            error_log("📍 Línea: " . $e->getLine());
            error_log("📍 Archivo: " . $e->getFile());
            error_log("Stack: " . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recuperar datos consolidados guardados para un expediente.
     * GET /admin/obtener-datos-consolidados?expediente={id}
     */
    public function obtenerDatosConsolidadosAction(Request $request)
    {
        $idExpediente = $request->query->get('expediente');
        if (!$idExpediente) {
            return new JsonResponse(['success' => false, 'error' => 'Parámetro expediente requerido'], 400);
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository('AppBundle:DatosConsolidadosBelender');
            $registro = $repo->findOneBy(['idExpediente' => (int)$idExpediente]);

            if (!$registro) {
                return new JsonResponse(['success' => false, 'error' => 'No se encontraron datos consolidados para el expediente'], 404);
            }

            $datosJsonRaw = $registro->getDatosJson();
            $datosJson = null;
            if ($datosJsonRaw) {
                $decoded = json_decode($datosJsonRaw, true);
                $datosJson = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $datosJsonRaw;
            }

            // Filtrar/extraer solo los datos generales del contribuyente antes de enviarlo a la vista
            $datosJsonFiltrado = $this->extraerDatosGeneralesDelContribuyente($datosJson);

            return new JsonResponse([
                'success' => true,
                'id' => $registro->getId(),
                'id_expediente' => $registro->getIdExpediente(),
                'id_cliente' => $registro->getIdCliente(),
                'datos_json' => $datosJsonFiltrado,
                'datos_json_raw' => $datosJsonRaw,
                'fecha_creacion' => $registro->getFechaCreacion() ? $registro->getFechaCreacion()->format('Y-m-d H:i:s') : null,
                'fecha_actualizacion' => $registro->getFechaActualizacion() ? $registro->getFechaActualizacion()->format('Y-m-d H:i:s') : null,
            ], 200);
        } 
        catch (\Exception $e) 
        {
            error_log('❌ EXCEPCIÓN obtenerDatosConsolidadosAction: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint webhook Belender (GET sin firma HMAC)
     * Ruta: /webhook/belender?request_id=REQ-123&status_code=pending&type=new_request
     * 
     * Parámetros GET:
     * - request_id: ID de la solicitud en Belender
     * - status_code: Estado actual (pending, processing, all_documents_downloaded, failed, etc)
     * - type: Tipo de evento (new_request, status_change, document, status_system)
     * - user_id: ID del usuario (opcional)
     * - funder_id: ID del funder (opcional)
     * - document_id: ID del documento si type=document (opcional)
     * - document_name: Nombre del documento (opcional)
     */
    public function webhookBelenderAction(Request $request)
    {
        // Extraer parámetros GET
        $requestId = $request->query->get('request_id');
        $statusCode = $request->query->get('status_code');
        $type = $request->query->get('type');
        $userId = $request->query->get('user_id');
        $funderId = $request->query->get('funder_id');
        $documentId = $request->query->get('document_id');
        $documentName = $request->query->get('document_name');
        
        // Log de la petición recibida
        error_log('📬 [Webhook Belender GET] Recibida notificación' .
                    ' | Request ID: ' . $requestId .
                    ' | Status: ' . $statusCode .
                    ' | Type: ' . $type .
                    ' | User ID: ' . $userId .
                    ' | Funder ID: ' . $funderId .
                    ' | Document ID: ' . $documentId .
                    ' | Document Name: ' . $documentName);
        
        // Validar que al menos request_id esté presente
        if (!$requestId) {
            error_log('⚠️ [Webhook Belender] Parámetro request_id faltante');
            return new JsonResponse(['status' => 'error', 'message' => 'request_id requerido'], 400);
        }
        
        // Procesar según el tipo de evento
        if ($type) {
            switch ($type) {
                case 'new_request':
                    $this->procesarWebhookNuevaSolicitud([
                        'request_id' => $requestId,
                        'status_code' => $statusCode,
                        'user_id' => $userId,
                        'funder_id' => $funderId
                    ]);
                    break;
                
                case 'status_change':
                    $this->procesarWebhookCambioEstado([
                        'request_id' => $requestId,
                        'status_code' => $statusCode,
                        'user_id' => $userId,
                        'funder_id' => $funderId
                    ]);
                    break;
                
                case 'document':
                    $this->procesarWebhookDocumento([
                        'request_id' => $requestId,
                        'document_id' => $documentId,
                        'document_name' => $documentName,
                        'status_code' => $statusCode,
                        'user_id' => $userId,
                        'funder_id' => $funderId
                    ]);
                    break;
                
                default:
                    error_log('⚠️ [Webhook Belender] Tipo de evento desconocido: ' . $type);
            }
        }
        
        // Siempre devolver 200 OK (Belender espera esto)
        return new JsonResponse(['status' => 'ok', 'request_id' => $requestId], 200);
    }

        
    /**
     * Verificar estado del servicio Belender
     * GET /admin/check-belender-status
     * 
     * Retorna JSON con disponibilidad del servicio Belender
     * Usado por JavaScript antes de inicializar el widget
     */
    public function checkBelenderStatusAction(Request $request)
    {
        try {
            error_log("🔍 Verificando estado de Belender: " . $this->belenderStatusUrl);
            
            // Usar httpRequest para conectar a Belender status
            $statusResponse = $this->httpRequest($this->belenderStatusUrl, 'GET', [], null);
            
            // Verificar si hay error en la respuesta
            if (isset($statusResponse['error'])) {
                error_log("⚠️ Error conectando a Belender status: " . $statusResponse['error']);
                return new JsonResponse([
                    'available' => false,
                    'message' => 'No se pudo conectar con el servicio Belender',
                    'error' => $statusResponse['error']
                ], 503);
            }
            
            // Validar que la respuesta sea un array válido
            if (!is_array($statusResponse)) {
                error_log("⚠️ Respuesta de Belender inválida (no es array)");
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Respuesta inválida del servicio Belender'
                ], 503);
            }
            
            // ✅ Servicio disponible
            error_log("✅ Servicio Belender disponible");
            return new JsonResponse([
                'available' => true,
                'message' => 'Servicio Belender disponible',
                'data' => $statusResponse
            ], 200);
            
        } catch (\Exception $e) {
            error_log("❌ Excepción verificando status Belender: " . $e->getMessage());
            return new JsonResponse([
                'available' => false,
                'message' => 'Error verificando estado del servicio',
                'error' => $e->getMessage()
            ], 503);
        }
    }

    /**
     * Solicitar informe CIRBE ICI
     * POST /solicitar-cirbe
     * 
     * Body: {
     *   "dni": "12345678A",
     *   "nombre": "Juan",
     *   "apellido1": "García",
     *   "apellido2": "López"
     * }
     */
    public function solicitarCirbeAction(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validar datos requeridos
            $dni = $data['dni'] ?? null;
            $nombre = $data['nombre'] ?? null;
            $apellido1 = $data['apellido1'] ?? null;
            $apellido2 = $data['apellido2'] ?? '';
            $idHitoExpediente = $data['idHitoExpediente'] ?? null;
            $credentialPassword = $this->passwordCirbe;

            if (!$dni || !$nombre || !$apellido1) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Parámetros requeridos: dni, nombre, apellido1'
                ], 400);
            }

            // Hacer login para obtener token y funder_id
            $authResponse = $this->login();

            if (!$authResponse || !isset($authResponse['access_token'])) 
            {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Error en autenticación con Belender'
                ], 500);
            }

            $token = $authResponse['access_token'];
            $userId = $authResponse['data']['user_id'] ?? null;
            $funderId = $authResponse['data']['funder_id'] ?? null;

            if (!$userId || !$funderId) 
            {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'No se pudo obtener user_id o funder_id'
                ], 500);
            }

            $packagesResponse = $this->getPackages($userId, $token);

            if (!$packagesResponse || empty($packagesResponse['data'])) 
            {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'No se pudieron obtener los packages disponibles'
                ], 500);
            }

            // Buscar package CIRBE
            $packageId = null;
            foreach ($packagesResponse['data'] as $pkg) 
            {
                $flowName = $pkg['flow_name'] ?? '';
                if (stripos($flowName, 'cirbe') !== false) 
                {
                    $packageId = $pkg['package_id'];
                    break;
                }
            }

            if (!$packageId) 
            {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'No se encontró el package CIRBE disponible'
                ], 500);
            }

            // Construir payload para solicitud CIRBE
            $cirbePayload = [
                'package_id' => $this->package_id_ICI,
                'name' => $nombre,
                'first_surname' => $apellido1,
                'second_surname' => $apellido2,
                'document_number' => $dni,
                'credential_password' => $credentialPassword,
                'user_id' => $userId
            ];

            // Hacer solicitud CIRBE usando cURL directamente
            $url = $this->apiBaseUrl . "/funders/$funderId/requests/cirbe";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_POSTFIELDS     => json_encode($cirbePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            
            $response = curl_exec($ch);

            if ($response === false) 
            {
                $err = curl_error($ch);
                curl_close($ch);
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'cURL error: ' . $err
                ], 500);
            }

            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $jsonResponse = json_decode($response, true);
            $jsonOk = (json_last_error() === JSON_ERROR_NONE);

            if ($httpCode !== 201) 
            {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Error al crear solicitud CIRBE',
                    'http_code' => $httpCode,
                    'details' => $jsonResponse ?: $response
                ], 500);
            }

            $requestId = $jsonResponse['request_id'] ?? $jsonResponse['data']['request_id'] ?? null;

            if (!$requestId) 
            {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'No se recibió request_id en la respuesta'
                ], 500);
            }

            // ✅ GUARDAR O ACTUALIZAR EN TABLA belender_hito_expediente
            if ($requestId && $dni && $idHitoExpediente) 
            { 
                try { 
                    $em = $this->getDoctrine()->getManager();
                    $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);
                    
                    // Buscar si ya existe un registro con el mismo id_hito_expediente y tipo CIRBE
                    $registroExistente = $repoRegistros->findOneBy([
                        'idHitoExpediente' => (int)$idHitoExpediente,
                        'tipoPeticion' => 'CIRBE'
                    ]);
                    
                    if ($registroExistente) 
                    { 
                        // ACTUALIZAR: existe un registro anterior
                        $registroExistente->setDniBelender($dni);
                        $registroExistente->setRequestIdBelender($requestId);
                        $registroExistente->setTipoPeticion('CIRBE');
                        $registroExistente->setFecha(new \DateTime());
                        $em->persist($registroExistente);
                        $em->flush();
                        
                        error_log("🔄 Registro CIRBE actualizado: ID Hito=$idHitoExpediente, DNI=$dni, Request=$requestId");
                    } 
                    else
                    { 
                        // CREAR: no existe registro anterior
                        $belenderRegistro = new BelenderHitoExpediente();
                        $belenderRegistro->setIdHitoExpediente((int)$idHitoExpediente);
                        $belenderRegistro->setDniBelender($dni);
                        $belenderRegistro->setRequestIdBelender($requestId);
                        $belenderRegistro->setTipoPeticion('CIRBE');
                        $belenderRegistro->setFecha(new \DateTime());
                        
                        $em->persist($belenderRegistro);
                        $em->flush();
                        
                        error_log("✅ Registro CIRBE creado: ID Hito=$idHitoExpediente, DNI=$dni, Request=$requestId");
                    }
                } 
                catch (\Exception $e) 
                {
                    error_log("⚠️ Error guardando registro belender_hito_expediente: " . $e->getMessage());
                    // No detener el flujo si falla el registro
                }
            }

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Solicitud CIRBE creada correctamente',
                'request_id' => $requestId,
                'funder_id' => $funderId,
                'user_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s')
            ], 201);
            
        } 
        catch (\Exception $e) 
        {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }   
        


    public function checkClavePinServiceAction(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validar datos requeridos
            $documentNumber = $data['document_number'] ?? null;
            $documentExpirationDate = $data['document_expiration_date'] ?? null;
            $idHitoExpediente = $data['id_hito_expediente'] ?? null;

            if (!$documentNumber || !$documentExpirationDate) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Parámetros requeridos: document_number, document_expiration_date'
                ], 400);
            }

            // Hacer login para obtener token, user_id y CSRF
            $authResponse = $this->login();
            if (!$authResponse || !isset($authResponse['access_token'])) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Error en autenticación con Belender'
                ], 500);
            }

            $token = $authResponse['access_token'];
            $userId = $authResponse['data']['user_id'] ?? null;
            
            if (!$userId) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'No se pudo obtener user_id de la autenticación'
                ], 500);
            }
            
            // Obtener CSRF token de la respuesta si está disponible
            $csrfToken = $authResponse['csrf_token'] ?? $authResponse['data']['csrf_token'] ?? null;

            // Construir payload para comprobar Clave PIN
            $payload = [
                'user_id' => $userId,
                'document_number' => $documentNumber,
                'document_expiration_date' => $documentExpirationDate
            ];

            // URL del endpoint
            $url = $this->apiBaseUrl . '/requests/clave-pin/check-service';

            // Preparar headers
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ];
            
            if ($csrfToken) {
                $headers[] = 'X-CSRF-TOKEN: ' . $csrfToken;
            }

            // Hacer petición cURL
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                error_log("❌ cURL error al comprobar Clave PIN: " . $curlError);
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Error de conectividad: ' . $curlError
                ], 500);
            }

            $jsonResponse = json_decode($response, true);
            $jsonOk = (json_last_error() === JSON_ERROR_NONE);

            error_log("ℹ️ Check Clave PIN - HTTP Code: $httpCode, JSON OK: " . ($jsonOk ? 'SI' : 'NO'));

            // Determinar disponibilidad
            $disponible = false;
            if ($httpCode === 200 || $httpCode === 201) {
                error_log("✅ Servicio Clave PIN disponible");
                $disponible = true;
            } elseif ($httpCode === 404 || $httpCode === 403 || $httpCode === 401) {
                error_log("⚠️ Servicio Clave PIN no disponible (HTTP $httpCode)");
                $disponible = false;
            } else {
                error_log("⚠️ Respuesta inesperada del servicio Clave PIN (HTTP $httpCode)");
                $disponible = false;
            }

            // ✅ GUARDAR O ACTUALIZAR EN TABLA belender_hito_expediente
            if ($idHitoExpediente && $documentNumber) {
                try {
                    $em = $this->getDoctrine()->getManager();
                    $repoRegistros = $em->getRepository(BelenderHitoExpediente::class);
                    
                    // Buscar si ya existe un registro con el mismo id_hito_expediente y tipo CLAVE_PIN
                    $registroExistente = $repoRegistros->findOneBy([
                        'idHitoExpediente' => (int)$idHitoExpediente,
                        //'tipoPeticion' => 'CLAVE_PIN'
                    ]);
                    
                    if ($registroExistente) {
                        // ACTUALIZAR: existe un registro anterior
                        $registroExistente->setDniBelender($documentNumber);
                        $registroExistente->setRequestIdBelender($disponible ? 'AVAILABLE' : 'NOT_AVAILABLE');
                        $registroExistente->setTipoPeticion('CLAVE_PIN');
                        $registroExistente->setFecha(new \DateTime());
                        $em->persist($registroExistente);
                        $em->flush();
                        
                        error_log("🔄 Registro Clave PIN actualizado: ID Hito=$idHitoExpediente, DNI=$documentNumber, Disponible=$disponible");
                    } else {
                        // CREAR: no existe registro anterior
                        $belenderRegistro = new BelenderHitoExpediente();
                        $belenderRegistro->setIdHitoExpediente((int)$idHitoExpediente);
                        $belenderRegistro->setDniBelender($documentNumber);
                        $belenderRegistro->setRequestIdBelender($disponible ? 'AVAILABLE' : 'NOT_AVAILABLE');
                        $belenderRegistro->setTipoPeticion('CLAVE_PIN');
                        $belenderRegistro->setFecha(new \DateTime());
                        
                        $em->persist($belenderRegistro);
                        $em->flush();
                        
                        error_log("✅ Registro Clave PIN creado: ID Hito=$idHitoExpediente, DNI=$documentNumber, Disponible=$disponible");
                    }
                } catch (\Exception $e) {
                    error_log("⚠️ Error guardando registro belender_hito_expediente: " . $e->getMessage());
                    // No detener el flujo si falla el registro
                }
            }

            // Códigos de respuesta esperados
            if ($disponible) {
                return new JsonResponse([
                    'available' => true,
                    'message' => 'Servicio Clave PIN disponible',
                    'service_info' => $jsonResponse ?: [],
                    'http_code' => $httpCode
                ], 200);
            } else {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Servicio Clave PIN no disponible para este usuario',
                    'http_code' => $httpCode,
                    'details' => $jsonResponse ?: $response
                ], 200); // Retornar 200 pero con available=false
            }
            
        } catch (\Exception $e) {
            error_log("❌ Excepción en checkClavePin ServiceAction: " . $e->getMessage());
            return new JsonResponse([
                'available' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }
}
