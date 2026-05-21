<?php

namespace AppBundle\Controller;

use AppBundle\Entity\WhatsappSender;
use AppBundle\Entity\WhatsappServidor;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Controller\IArtificalController;

class WhatsappController extends Controller
{
    /**
     * Inyección de IArtificalController para operaciones de IA
     */
    private ?IArtificalController $iaController = null;

    /**
     * Teléfono del sistema para mensajes automatizados
     */
    private string $telefonoSistema = '614257727';

    /**
     * Obtiene instancia de IArtificalController
     */
    private function getIAController(): IArtificalController
    {
        if ($this->iaController === null) {
            $this->iaController = new IArtificalController();
        }
        return $this->iaController;
    }

    /**
     * Registra un mensaje en el log diario
     */
    private function logear($mensaje)
    {
        // Usar la raíz del proyecto
        $logDir = dirname(dirname(dirname(__DIR__))) . '/var/logs/';
        
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . 'whatsapp_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contenido = "[{$timestamp}] {$mensaje}\n";
        
        // Intentar escribir en archivo
        $resultado = @file_put_contents($logFile, $contenido, FILE_APPEND | LOCK_EX);
        
        // Fallback a error_log si no se puede escribir al archivo
        if ($resultado === false) {
            error_log($mensaje);
        }
    }

    private function normalizeWhatsappBase64(?string $base64): ?string
    {
        if (!$base64 || !is_string($base64)) {
            return null;
        }

        $normalizedBase64 = trim($base64);
        if (preg_match('/^data:image\/[a-zA-Z+.-]+;base64,(.+)$/i', $normalizedBase64, $matches)) {
            $normalizedBase64 = $matches[1];
        }

        $normalizedBase64 = str_replace(["\r", "\n", "\t", ' '], '', $normalizedBase64);
        $normalizedBase64 = strtr($normalizedBase64, '-_', '+/');

        $padding = strlen($normalizedBase64) % 4;
        if ($padding !== 0) {
            $normalizedBase64 .= str_repeat('=', 4 - $padding);
        }

        if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $normalizedBase64)) {
            return null;
        }

        return $normalizedBase64;
    }

    private function detectMimeTypeFromBinary(string $binaryContent): ?string
    {
        $signatures = [
            "\xFF\xD8\xFF"      => 'image/jpeg',
            "\x89PNG\r\n\x1A\n" => 'image/png',
            'GIF87a'            => 'image/gif',
            'GIF89a'            => 'image/gif',
            'RIFF'              => 'image/webp',
            'BM'                => 'image/bmp',
        ];

        foreach ($signatures as $signature => $mimeType) {
            if (substr($binaryContent, 0, strlen($signature)) === $signature) {
                return $mimeType;
            }
        }

        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMimeType = $finfo->buffer($binaryContent);
            if ($detectedMimeType && strpos($detectedMimeType, 'image/') === 0) {
                return $detectedMimeType;
            }
        }

        return null;
    }

    private function getValidatedImageMimeType(string $binaryContent, ?string $preferredMimeType = null): ?string
    {
        if ($binaryContent === '') {
            return null;
        }

        if (function_exists('getimagesizefromstring')) {
            $imageInfo = @getimagesizefromstring($binaryContent);
            if (is_array($imageInfo) && !empty($imageInfo['mime']) && strpos($imageInfo['mime'], 'image/') === 0) {
                return $imageInfo['mime'];
            }
        }

        $detectedMimeType = $this->detectMimeTypeFromBinary($binaryContent);
        if ($detectedMimeType && strpos($detectedMimeType, 'image/') === 0) {
            return $detectedMimeType;
        }

        if ($preferredMimeType && strpos($preferredMimeType, 'image/') === 0) {
            return null;
        }

        return null;
    }

    private function guessExtensionFromMimeType(?string $mimeType): string
    {
        $extensionMap = [
            'image/jpeg'    => 'jpg',
            'image/jpg'     => 'jpg',
            'image/png'     => 'png',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/bmp'     => 'bmp',
            'image/svg+xml' => 'svg',
        ];

        return $extensionMap[strtolower((string) $mimeType)] ?? 'jpg';
    }

    private function getWhatsappImagesDirectory(): string
    {
        $imagesDirectory = rtrim($this->getParameter('files_directory'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'whatsapp_images';
        if (!is_dir($imagesDirectory)) {
            @mkdir($imagesDirectory, 0777, true);
        }

        return $imagesDirectory;
    }

    private function buildWhatsappPublicPath(string $absolutePath): string
    {
        $normalizedAbsolutePath = str_replace('\\', '/', $absolutePath);
        $normalizedFilesDirectory = rtrim(str_replace('\\', '/', $this->getParameter('files_directory')), '/');

        if (strpos($normalizedAbsolutePath, $normalizedFilesDirectory) === 0) {
            return '/uploads/' . ltrim(substr($normalizedAbsolutePath, strlen($normalizedFilesDirectory)), '/');
        }

        $projectWebDirectory = rtrim(str_replace('\\', '/', $this->get('kernel')->getProjectDir() . '/web'), '/');
        if (strpos($normalizedAbsolutePath, $projectWebDirectory) === 0) {
            return substr($normalizedAbsolutePath, strlen($projectWebDirectory));
        }

        return '/uploads/whatsapp_images/' . basename($normalizedAbsolutePath);
    }

    private function saveWhatsappImageBinary(string $binaryContent, ?string $preferredMimeType = null): ?array
    {
        if ($binaryContent === '') {
            return null;
        }

        $mimeType = $this->getValidatedImageMimeType($binaryContent, $preferredMimeType);
        if (!$mimeType) {
            $this->logear('⚠️ Se rechazó un binario de WhatsApp porque no es una imagen válida');
            return null;
        }

        $fileName = 'img_' . md5(uniqid('', true)) . '.' . $this->guessExtensionFromMimeType($mimeType);
        $absolutePath = $this->getWhatsappImagesDirectory() . DIRECTORY_SEPARATOR . $fileName;

        if (@file_put_contents($absolutePath, $binaryContent) === false) {
            $this->logear('❌ No se pudo guardar la imagen de WhatsApp en disco: ' . $absolutePath);
            return null;
        }

        return [
            'filepath' => $this->buildWhatsappPublicPath($absolutePath),
            'mime_type' => $mimeType,
            'absolute_path' => $absolutePath,
        ];
    }

    private function saveWhatsappImageFromBase64(string $base64Content, ?string $preferredMimeType = null): ?array
    {
        $normalizedBase64 = $this->normalizeWhatsappBase64($base64Content);
        if (!$normalizedBase64) {
            $this->logear('⚠️ Base64 de imagen WhatsApp inválido o corrupto');
            return null;
        }

        $binaryContent = base64_decode($normalizedBase64, true);
        if ($binaryContent === false) {
            $this->logear('⚠️ No se pudo decodificar la imagen WhatsApp desde base64');
            return null;
        }

        return $this->saveWhatsappImageBinary($binaryContent, $preferredMimeType);
    }

    private function extractIncomingWhatsappImagePayload(array $data): array
    {
        $mimeType = null;
        foreach (['mime_type', 'mimetype', 'imageMimeType'] as $mimeKey) {
            if (!empty($data[$mimeKey]) && is_string($data[$mimeKey]) && strpos($data[$mimeKey], 'image/') === 0) {
                $mimeType = $data[$mimeKey];
                break;
            }
        }

        $base64Content = null;
        foreach (['content', 'base64', 'imageBase64', 'fileData', 'data'] as $contentKey) {
            if (!empty($data[$contentKey]) && is_string($data[$contentKey])) {
                $normalizedBase64 = $this->normalizeWhatsappBase64($data[$contentKey]);
                if ($normalizedBase64) {
                    $base64Content = $normalizedBase64;
                    break;
                }
            }
        }

        $url = null;
        foreach (['url', 'mediaUrl', 'body'] as $urlKey) {
            if (!empty($data[$urlKey]) && is_string($data[$urlKey]) && filter_var($data[$urlKey], FILTER_VALIDATE_URL)) {
                $url = $data[$urlKey];
                break;
            }
        }

        $text = null;
        foreach (['text', 'caption'] as $textKey) {
            if (isset($data[$textKey]) && is_string($data[$textKey]) && trim($data[$textKey]) !== '') {
                $text = trim($data[$textKey]);
                break;
            }
        }

        return [
            'mime_type' => $mimeType,
            'base64' => $base64Content,
            'url' => $url,
            'text' => $text,
        ];
    }

    private function normalizeWhatsappMessageForComparison($message, string $messageType): string
    {
        if (!is_string($message)) {
            return '';
        }

        $normalizedMessage = trim($message);
        if ($normalizedMessage === '') {
            return '';
        }

        $decodedMessage = json_decode($normalizedMessage, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMessage)) {
            if ($messageType === 'text') {
                return trim((string) ($decodedMessage['content'] ?? $decodedMessage['text'] ?? ''));
            }

            if ($messageType === 'image') {
                return trim((string) ($decodedMessage['filepath'] ?? $decodedMessage['url'] ?? $decodedMessage['content'] ?? ''));
            }

            return trim((string) ($decodedMessage['content'] ?? $decodedMessage['url'] ?? $decodedMessage['text'] ?? ''));
        }

        return $normalizedMessage;
    }

    private function normalizeWhatsappPhoneForComparison(?string $phone): string
    {
        if (!is_string($phone) || trim($phone) === '') {
            return '';
        }

        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        if ($normalizedPhone === '') {
            return '';
        }

        return strlen($normalizedPhone) > 9 ? substr($normalizedPhone, -9) : $normalizedPhone;
    }

    private function hasRecentOutgoingMessageDuplicate($conn, ?int $idExpediente, ?string $fromPhone, ?string $toPhone, string $messageType, string $messageToCompare): bool
    {
        $normalizedCandidate = $this->normalizeWhatsappMessageForComparison($messageToCompare, $messageType);
        $normalizedFromPhone = $this->normalizeWhatsappPhoneForComparison($fromPhone);
        $normalizedToPhone = $this->normalizeWhatsappPhoneForComparison($toPhone);

        if ($normalizedCandidate === '' || $normalizedFromPhone === '') {
            return false;
        }

        $sql = 'SELECT message
                     , from_phone
                     , to_phone
                FROM chat_history
                WHERE direction = :direction
                  AND role = :role
                  AND message_type = :messageType';

        $params = [
            'direction' => 'enviado',
            'role' => 'assistant',
            'messageType' => $messageType,
            'recentLimit' => date('Y-m-d H:i:s', time() - 120),
        ];

        if ($idExpediente !== null) {
            $sql .= ' AND id_expediente = :idExpediente';
            $params['idExpediente'] = $idExpediente;
        }

        $sql .= ' AND timestamp >= :recentLimit ORDER BY timestamp DESC LIMIT 5';

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $recentMessages = $stmt->fetchAll();

        foreach ($recentMessages as $recentMessageRow) {
            $recentFromPhone = $this->normalizeWhatsappPhoneForComparison($recentMessageRow['from_phone'] ?? null);
            $recentToPhone = $this->normalizeWhatsappPhoneForComparison($recentMessageRow['to_phone'] ?? null);

            if ($recentFromPhone !== $normalizedFromPhone) {
                continue;
            }

            if ($normalizedToPhone !== '' && $recentToPhone !== '' && $recentToPhone !== $normalizedToPhone) {
                continue;
            }

            $recentNormalized = $this->normalizeWhatsappMessageForComparison((string) ($recentMessageRow['message'] ?? ''), $messageType);
            if ($recentNormalized !== '' && $recentNormalized === $normalizedCandidate) {
                return true;
            }
        }

        return false;
    }

    private function downloadMediaToWhatsappUpload(string $url): ?array
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HipoteaBot/1.0)',
                CURLOPT_HTTPHEADER     => [
                    'Accept: image/*,*/*;q=0.8',
                    'ngrok-skip-browser-warning: true',
                ],
                CURLOPT_BUFFERSIZE     => 1024 * 1024,
            ]);

            $rawBinary = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || $httpCode !== 200 || !$rawBinary) {
                $this->logear("⚠️ downloadMediaToWhatsappUpload: HTTP={$httpCode}, error={$curlError}");
                return null;
            }

            if (strlen($rawBinary) > 10 * 1024 * 1024) {
                $this->logear("⚠️ downloadMediaToWhatsappUpload: archivo demasiado grande (" . strlen($rawBinary) . " bytes)");
                return null;
            }

            if ($contentType && strpos($contentType, 'image/') !== 0) {
                $this->logear('⚠️ downloadMediaToWhatsappUpload: content-type no válido para imagen: ' . $contentType);
            }

            return $this->saveWhatsappImageBinary($rawBinary, $contentType ?: null);
        } catch (\Exception $e) {
            $this->logear('❌ downloadMediaToWhatsappUpload excepción: ' . $e->getMessage());
            return null;
        }
    }

    private function isTrustedWhatsappMediaUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return (bool) preg_match('/(^|\.)ngrok-free\.dev$/', strtolower($parts['host']));
    }

    public function proxyWhatsappMediaAction(Request $request)
    {
        $url = trim((string) $request->query->get('url', ''));
        if (!$this->isTrustedWhatsappMediaUrl($url)) {
            return new Response('URL de media no permitida', 400);
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HipoteaBot/1.0)',
                CURLOPT_HTTPHEADER     => [
                    'Accept: image/*,*/*;q=0.8',
                    'ngrok-skip-browser-warning: true',
                ],
                CURLOPT_BUFFERSIZE     => 1024 * 1024,
            ]);

            $rawBinary = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || $httpCode !== 200 || !$rawBinary) {
                $this->logear("⚠️ proxyWhatsappMediaAction: HTTP={$httpCode}, error={$curlError}, url={$url}");
                return new Response('No se pudo recuperar la imagen', 502);
            }

            if (strlen($rawBinary) > 10 * 1024 * 1024) {
                $this->logear('⚠️ proxyWhatsappMediaAction: archivo demasiado grande para url ' . $url);
                return new Response('La imagen es demasiado grande', 413);
            }

            $mimeType = $this->getValidatedImageMimeType($rawBinary, $contentType ?: null);
            if (!$mimeType) {
                $this->logear('⚠️ proxyWhatsappMediaAction: contenido no válido como imagen para url ' . $url);
                return new Response('El recurso no es una imagen válida', 415);
            }

            $fileName = basename((string) parse_url($url, PHP_URL_PATH));
            if ($fileName === '' || $fileName === '/' || $fileName === '.') {
                $fileName = 'whatsapp-media.' . $this->guessExtensionFromMimeType($mimeType);
            }

            return new Response($rawBinary, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
                'Cache-Control' => 'private, max-age=300',
            ]);
        } catch (\Exception $e) {
            $this->logear('❌ proxyWhatsappMediaAction excepción: ' . $e->getMessage());
            return new Response('Error al recuperar la imagen', 500);
        }
    }

    /**
     * Muestra la página de Auto WhatsApp con conexiones activas
     */
    public function autoWhatsappAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');

        // Obtener conexiones sin ImagenQR asignada
        $qb = $senderRepo->createQueryBuilder('ws');
        $conexiones = $qb
            ->where('ws.imagenQR IS NULL')
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('@App/Backoffice/Lista/auto-whatsapp.html.twig', [
            'titulo' => 'Auto WhatsApp',
            'conexiones' => $conexiones,
        ]);
    }

    /**
     * Muestra la conexión personal del usuario autenticado
     */
    public function miConexionWhatsappAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        
        if (!$usuario) {
            return $this->redirectToRoute('login');
        }

        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
        
        // Obtener la conexión del usuario actual
        $qb = $senderRepo->createQueryBuilder('ws');
        $conexion = $qb
            ->where('ws.idUsuario = :idUsuario')
            ->andWhere('ws.imagenQR IS NULL')
            ->setParameter('idUsuario', $usuario->getIdUsuario())
            ->getQuery()
            ->getOneOrNullResult();

        // Obtener roles del usuario
        $roles = $usuario->getRoles();
        $rolUsuario = !empty($roles) ? $roles[0] : 'ROLE_USER';
        $telefonoUsuario = $usuario->getTelefonoMovil() ?: '';

        return $this->render('@App/Backoffice/Lista/mi-conexion-whatsapp.html.twig', [
            'titulo' => 'Mi Conexión WhatsApp',
            'conexion' => $conexion,
            'usuarioLogueado' => $usuario,
            'rolUsuario' => $rolUsuario,
            'telefonoUsuario' => $telefonoUsuario,
        ]);
    }

    /**
     * Muestra la página para agregar o modificar una conexión de WhatsApp
     */
    public function agregarModificarConexionAction(Request $request)
    {
        $id_sender = $request->get('id');
        
        $fecha = date('Y-m-d');
        $hash  = $this->generarHashWhatsapp($fecha);
        $ip    = $this->obtenerServidorParaSender($id_sender);
        $base  = $this->baseHostWhatsapp($ip);
        $externalUrl = $base . "/?new=true&hash={$hash}&date={$fecha}";
        
        return $this->render('@App/Backoffice/AgregarModificar/whatsapp-redirect.html.twig', [
            'externalUrl' => $externalUrl,
        ]);
    }

    /**
     * Edita una conexión existente (toggle switches y configuraciones)
     */
    public function editarConexionAction(Request $request)
    {
        $idSender = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
        $usuario = $this->getUser();

        // Obtener el sender
        $sender = $senderRepo->find((int)$idSender);
        if (!$sender) {
            throw $this->createNotFoundException('Conexión no encontrada');
        }

        // Validar permisos: admin puede editar cualquiera, usuario normal solo la suya
        $esAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COMERCIAL') || $this->isGranted('ROLE_TECNICO');
        if (!$esAdmin && $usuario && $sender->getIdUsuario() !== $usuario->getIdUsuario()) {
            throw $this->createAccessDeniedException('No tienes permiso para editar esta conexión');
        }

        // Si es POST, procesar los cambios
        if ($request->isMethod('POST')) {
            // Actualizar los campos booleanos (solo los que existen en la entidad)
            $sender->setCrucesAutomaticos($request->request->has('crucesAutomaticos'));
            $sender->setCrucesAutomaticosRGPDExterna($request->request->has('crucesAutomaticosRGPDExterna'));
            $sender->setAutomatizacionesWhatsapp($request->request->has('automatizacionesWhatsapp'));
            $sender->setSyncConversaciones($request->request->has('syncConversaciones'));
            $sender->setRecordatoriosVisitas($request->request->has('recordatoriosVisitas'));
            $sender->setPilotoAutomatico($request->request->has('pilotoAutomatico'));

            // Si hay system prompt para piloto automático
            $systemPrompt = $request->request->get('pilotoAutomaticoSystemPrompt');
            if ($systemPrompt) {
                $sender->setPilotoAutomaticoSystemPrompt($systemPrompt);
            }

            // Guardar cambios
            $em->flush();

            $this->addFlash('success', 'Conexión actualizada correctamente');
            return $this->redirectToRoute('auto_whatsapp');
        }

        return $this->render('@App/Backoffice/AgregarModificar/editar-conexion.html.twig', [
            'titulo' => 'Editar Conexión WhatsApp',
            'sender' => $sender,
        ]);
    }

    /**
     * Marca una conexión para actualizar su ImagenQR
     */
    public function eliminarConexionAction(Request $request)
    {
        $idSender = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
        $usuario = $this->getUser();

        // Obtener el sender
        $sender = $senderRepo->find((int)$idSender);
        if (!$sender) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Conexión no encontrada'
            ], 404);
        }

        // Validar permisos: admin puede eliminar cualquiera, usuario normal solo la suya
        $esAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COMERCIAL') || $this->isGranted('ROLE_TECNICO');
        if (!$esAdmin && $usuario && $sender->getIdUsuario() !== $usuario->getIdUsuario()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta conexión'
            ], 403);
        }

        try {
            // Actualizar ImagenQR a "necesario"
            $sender->setImagenQR('necesario');
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'WhatsApp desconectado correctamente'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al actualizar la conexión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener servidor para un sender, asignando uno disponible si no lo tiene
     */
    public function obtenerServidorParaSender($idSender): ?string
    {
        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
        $servidorRepo = $em->getRepository('AppBundle:WhatsappServidor');

        // Obtener el sender
        $sender = $senderRepo->find((int)$idSender);
        if (!$sender) {
            return null;
        }

        // Si el sender ya tiene un servidor asignado
        $servidor = $sender->getServidor();
        if ($servidor !== null && trim($servidor) !== '') {
            return trim($servidor);
        }

        // No tenía servidor: asignar uno disponible
        $ip = $this->seleccionarServidorDisponible();
        if ($ip) {
            $sender->setServidor($ip);
            $em->flush();
            return $ip;
        }

        return null;
    }

    /**
     * Seleccionar un servidor disponible con menos conexiones activas
     */
    private function seleccionarServidorDisponible(): ?string
    {
        $em = $this->getDoctrine()->getManager();
        $servidorRepo = $em->getRepository('AppBundle:WhatsappServidor');

        // Obtener servidor activo
        $servidor = $servidorRepo->findOneBy(['estado' => true]);

        if ($servidor) {
            return $servidor->getIp();
        }

        return null;
    }

    private function generarHashWhatsapp($fecha) 
    {
        $texto = "hipotea_whatsapp_" . $fecha;
        return hash('sha256', $texto);
    }

    /**
     * Obtener la URL base del host WhatsApp
     */
    private function baseHostWhatsapp($ipPreferida = null): string
    {
        $host = $ipPreferida ?: $this->seleccionarServidorDisponible();
        
        if (!$host) {
            throw new \Exception('No hay servidores WhatsApp disponibles');
        }
        
        return "http://{$host}:3000";
    }
    
    // Función para encontrar usuario por teléfono (normalizado)
    public function gestorAction(Request $request): JsonResponse
    {
        // Verificar API key
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Obtener el teléfono desde la query
        $phone = $request->query->get('phone');
        if (!$phone) {
            return new JsonResponse(['error' => 'phone parameter required'], 400);
        }

        // Normalizar el teléfono y generar variantes
        $digits = $this->normalizePhone($phone);
        $variants = array_values(array_unique(array_filter([
            $digits,                          // tal cual
            ltrim($digits, '0'),              // sin ceros a la izquierda
            (strlen($digits) > 9 ? substr($digits, -9) : null), // últimos 9 dígitos
        ])));

        if (!$variants) {
            return new JsonResponse(['error' => 'Teléfono no válido'], 400);
        }

        $conn = $this->getDoctrine()->getConnection();

        // Crear placeholders para la consulta IN
        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        // Consulta adaptada a tu esquema: tabla usuario con campos snake_case
        // Ajusta los nombres de columnas según tu base de datos real
        // Usamos subconsultas correlacionadas para obtener el último registro de WhatsappSenders
        // correspondiente al usuario e inmobiliaria (NULL-safe con <=>). Esto evita que, si
        // existen varias filas en WhatsappSenders para el mismo usuario, se devuelva una fila
        // arbitraria por el LEFT JOIN.
        $sql = "SELECT
                    u.id_usuario as IdGestor,
                    u.id_inmobiliaria as IdAgencia,
                    u.role as NivelAcceso,
                    u.nombre as Nombre,
                    u.apellidos as Apellidos,
                    (SELECT ws.SyncConversaciones FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS SyncConversaciones,
                    (SELECT ws.AutomatizacionesWhatsapp FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS AutomatizacionesWhatsapp,
                    (SELECT ws.CrucesAutomaticos FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS CrucesAutomaticos,
                    (SELECT ws.CrucesAutomaticosRGPDExterna FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS CrucesAutomaticosRGPDExterna,
                    (SELECT ws.PilotoAutomatico FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS PilotoAutomatico,
                    (SELECT ws.RecordatoriosVisitas FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS RecordatoriosVisitas
                FROM usuario u
                WHERE u.estado = 1
                AND u.telefono_movil IN ($placeholders)
                ORDER BY u.id_usuario ASC
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        // Bind posicional (1-indexed)
        foreach ($variants as $i => $v) {
            $stmt->bindValue($i + 1, $v);
        }

        // Ejecutar consulta
        $exec = $stmt->execute();
        if ($exec instanceof \Doctrine\DBAL\Result) {
            $gestor = $exec->fetchAssociative();
        } else {
            $gestor = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$gestor) {
            // 204: sin contenido (gestor no encontrado)
            return new JsonResponse(null, 204);
        }

        return new JsonResponse($gestor, 200);
    }

    // Comprueba API key en header X-API-KEY o ?api_key= o en body JSON
    private function checkApiKey(Request $request)
    {
        // Intentar obtener API key desde diferentes fuentes
        $provided = $request->headers->get('X-API-KEY');
        
        // Si no está en header, buscar en query
        if (!$provided) {
            $provided = $request->query->get('api_key');
        }
        
        // Si no está en query, buscar en body JSON (para POST)
        if (!$provided && in_array($request->getMethod(), ['POST', 'PUT'])) {
            $data = json_decode($request->getContent(), true);
            $provided = $data['api_key'] ?? null;
        }
        
        $expected = '123456';
        $isValid = $provided && $provided === $expected;
        
        // Log solo si la API key es inválida
        if (!$isValid && $this->container->has('logger')) {
            $this->container->get('logger')->warning('Invalid API key attempt', [
                'provided' => $provided ?: 'NONE',
                'ip' => $request->getClientIp()
            ]);
        }
        
        return $isValid;
    }

    /**
	 * Endpoint para ejecutar consultas SQL directas
	 * ADVERTENCIA: Solo para usuarios autenticados con API key
	 */
	public function ejecutarConsultaSQLAction(Request $request)
	{
		// Verificar API key
		if (!$this->checkApiKey($request)) {
			return new JsonResponse(['error' => 'Unauthorized'], 401);
		}

		// Obtener la consulta SQL del request
		$data = json_decode($request->getContent(), true);
		$sql = isset($data['query']) ? trim($data['query']) : null;
		$params = isset($data['params']) ? $data['params'] : [];

		// Validar que se proporcionó una consulta
		if (empty($sql)) {
			return new JsonResponse([
				'success' => false,
				'error' => 'No se proporcionó ninguna consulta SQL'
			], 400);
		}

		// Lista negra de operaciones peligrosas
		$operacionesPeligrosas = ['DROP', 'TRUNCATE', 'DELETE FROM usuario', 'ALTER TABLE', 'CREATE TABLE', 'GRANT', 'REVOKE'];
		foreach ($operacionesPeligrosas as $operacion) {
			if (stripos($sql, $operacion) !== false) {
				return new JsonResponse([
					'success' => false,
					'error' => 'Operación no permitida: ' . $operacion
				], 403);
			}
		}

		try {
			$connection = $this->getDoctrine()->getConnection();
			
			// Determinar si es SELECT u otra operación
			$isSelect = preg_match('/^\s*SELECT/i', $sql);
			
			// Preparar y ejecutar la consulta
			$stmt = $connection->prepare($sql);
			
			// Vincular parámetros si existen
			if (!empty($params)) {
				foreach ($params as $key => $value) {
					$stmt->bindValue($key, $value);
				}
			}
			
			$stmt->execute();
			
			// Logging de la consulta ejecutada
			if ($this->container->has('logger')) {
				$logger = $this->container->get('logger');
				$logger->warning('SQL Query ejecutada via API Chat', [
					'query' => $sql,
					'params' => $params,
					'ip' => $request->getClientIp(),
					'timestamp' => date('Y-m-d H:i:s')
				]);
			}
			
			if ($isSelect) {
				// Para SELECT: devolver resultados
				$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				
				return new JsonResponse([
					'success' => true,
					'type' => 'SELECT',
					'data' => $results,
					'count' => count($results),
					'query' => $sql
				]);
			} else {
				// Para INSERT, UPDATE, DELETE: devolver filas afectadas
				$rowCount = $stmt->rowCount();
				
				return new JsonResponse([
					'success' => true,
					'type' => 'MODIFY',
					'affected_rows' => $rowCount,
					'message' => "Consulta ejecutada correctamente. Filas afectadas: {$rowCount}",
					'query' => $sql
				]);
			}
			
		} catch (\Exception $e) {
			// Logging del error
			if ($this->container->has('logger')) {
				$logger = $this->container->get('logger');
				$logger->error('Error ejecutando SQL Query via API Chat', [
					'query' => $sql,
					'error' => $e->getMessage(),
					'ip' => $request->getClientIp()
				]);
			}
			
			return new JsonResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'query' => $sql
			], 500);
		}
	}

    // Normaliza el teléfono: elimina todo lo que no sean dígitos
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }

    // 
    public function createAction(Request $request)
    {
        if (!$this->checkApiKey($request)) 
        {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'invalid JSON'], 400);
        }

        $phone = $data['phone_origen'] ?? null;
        $phoneDestination = $data['phone_destination'] ?? null;
        $idExpediente = $data['id_expediente'] ?? null;
        $role_label = $data['role_label'] ?? null;
        $text = $data['text'] ?? null;
        $direction = $data['direccion'] ?? 'enviado';

        if (!$phone || !$text) {
            return new JsonResponse(['error' => 'phone_origen and text are required'], 400);
        }

        // Parsear el texto: puede ser un JSON con estructura de imagen o texto plano
        $imageData = null;
        $imageType = null;
        $storedImagePath = null;
        $textContent = $text;
        $isImage = false;

        // Intentar parsear como JSON (el campo text puede contener JSON serializado con imagen)
        $parsedText = json_decode($text, true);
        if ($parsedText && is_array($parsedText) && isset($parsedText['type'])) {
            if ($parsedText['type'] === 'image') {
                $isImage = true;
                $imageType = $parsedText['mime_type'] ?? 'image/jpeg';
                $textContent = $parsedText['text'] ?? null; // Descripción de la imagen

                if (!empty($parsedText['filepath'])) {
                    $storedImagePath = $parsedText['filepath'];
                } elseif (!empty($parsedText['url'])) {
                    $storedImagePath = $parsedText['url'];
                } elseif (!empty($parsedText['content'])) {
                    $imageData = $this->normalizeWhatsappBase64($parsedText['content']);
                    if (!$imageData) {
                        return new JsonResponse([
                            'error' => 'Imagen en formato inválido'
                        ], 400);
                    }

                    $decodedImage = base64_decode($imageData, true);
                    if ($decodedImage === false) {
                        return new JsonResponse([
                            'error' => 'No se pudo decodificar la imagen'
                        ], 400);
                    }

                    // Validar tamaño máximo de imagen (5MB)
                    $imageSizeInBytes = strlen($decodedImage);
                    $maxSizeInBytes = 5 * 1024 * 1024; // 5MB

                    if ($imageSizeInBytes > $maxSizeInBytes) {
                        return new JsonResponse([
                            'error' => 'Imagen demasiado grande. Máximo 5MB',
                            'size' => $imageSizeInBytes,
                            'max_size' => $maxSizeInBytes
                        ], 400);
                    }

                    error_log("Imagen detectada: $imageType, tamaño: $imageSizeInBytes bytes\n");
                }
            } else {
                // JSON pero no es imagen, tratarlo como texto
                $textContent = $text;
            }
        }
        // Si no es un JSON válido, es texto plano
        // $textContent ya está asignado a $text por defecto

        $phone = $this->normalizePhone($phone);
        // Calcular la variante local (sin prefijo) — últimas 9 cifras si el teléfono incluye prefijo
        $phone_local = (strlen($phone) > 9) ? substr($phone, -9) : $phone;

        // Intentar obtener el usuario asociado al teléfono de origen (comercial/técnico)
        $user = $this->findUserByPhone($phone);
        $displayName = null;
        if ($user && (!empty($user['nombre']) || !empty($user['apellidos']))) {
            $displayName = trim((string)($user['nombre'] ?? '') . ' ' . (string)($user['apellidos'] ?? ''));
        }
        
        // role_label será el nombre del usuario de origen
        $role_label = $displayName;

        // Opción 1: Si proporciona id_expediente directamente, validarlo
        if ($idExpediente) {
            $conn = $this->getDoctrine()->getConnection();
            $sql = 'SELECT e.id_expediente, c.nombre AS cliente_nombre, c.apellidos AS cliente_apellidos, c.nif AS cliente_nif 
                    FROM expediente e 
                    LEFT JOIN usuario c ON e.id_cliente = c.id_usuario 
                    WHERE e.id_expediente = :id AND e.estado > 0 LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('id', (int)$idExpediente);
            $stmt->execute();
            $expediente = $stmt->fetch();
            
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'El expediente ' . $idExpediente . ' no existe o no está activo'
                ], 400);
            }
        } else {
            // Opción 2: Buscar por ambos teléfonos (phone_origen y phone_destination)
            if ($phoneDestination) {
                $phoneDestinationNorm = $this->normalizePhone($phoneDestination);
                
                // Buscar expediente que tenga ambos teléfonos en cualquier orden
                $idExpediente = $this->findExpedienteByBothPhones($phone, $phoneDestinationNorm);
                
                if (!$idExpediente) {
                    // Si no encuentra por ambos, intentar phone_origen como técnico/comercial
                    $idExpediente = $this->findExpedienteByCommercialPhone($phone);
                }
                
                if (!$idExpediente) {
                    // Si no encuentra, intentar phone_origen como cliente
                    $idExpediente = $this->findExpedienteByClientPhone($phone);
                }
                
                if (!$idExpediente) {
                    // Si aún no encuentra, intentar phone_destination como técnico/comercial
                    $idExpediente = $this->findExpedienteByCommercialPhone($phoneDestinationNorm);
                }
                
                if (!$idExpediente) {
                    // Si aún no encuentra, intentar phone_destination como cliente
                    $idExpediente = $this->findExpedienteByClientPhone($phoneDestinationNorm);
                }
            } else {
                // Buscar solo por teléfono de origen
                // Primero buscar si phone_origen es técnico/comercial
                $idExpediente = $this->findExpedienteByCommercialPhone($phone);
                
                // Si no encuentra, buscar si phone_origen es cliente
                if (!$idExpediente) {
                    $idExpediente = $this->findExpedienteByClientPhone($phone);
                }
            }

            // Recuperar datos del expediente incluyendo información del cliente
            if ($idExpediente) {
                try {
                    $conn = $this->getDoctrine()->getConnection();
                    $sql = 'SELECT e.id_expediente, c.nombre AS cliente_nombre, c.apellidos AS cliente_apellidos, c.nif AS cliente_nif 
                            FROM expediente e 
                            LEFT JOIN usuario c ON e.id_cliente = c.id_usuario 
                            WHERE e.id_expediente = :id AND e.estado > 0 LIMIT 1';
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue('id', (int)$idExpediente);
                    $stmt->execute();
                    $expediente = $stmt->fetch();
                    
                    if (!$expediente) {
                        $this->logear('⚠ Advertencia: No se obtuvieron datos del expediente ' . $idExpediente);
                        $expediente = ['id_expediente' => $idExpediente, 'cliente_nombre' => '', 'cliente_apellidos' => '', 'cliente_nif' => ''];
                    }
                } catch (\Exception $e) {
                    $this->logear('⚠ Error recuperando datos del expediente: ' . $e->getMessage());
                    $expediente = ['id_expediente' => $idExpediente, 'cliente_nombre' => '', 'cliente_apellidos' => '', 'cliente_nif' => ''];
                }
            } else {
                $expediente = ['id_expediente' => null, 'cliente_nombre' => '', 'cliente_apellidos' => '', 'cliente_nif' => ''];
            }

            // Validar que se encontró un expediente
            if (!$idExpediente) {
                // Construir información de debug
                $debug = [];
                
                // Info del usuario por phone_origen
                if ($user) {
                    $debug[] = "Usuario origen: {$displayName} (ID: {$user['id_usuario']})";
                    
                    // Buscar expedientes donde el usuario es técnico/comercial
                    $conn2 = $this->getDoctrine()->getConnection();
                    $sqlDebug = 'SELECT id_expediente FROM expediente WHERE (id_tecnico = :id OR id_comercial = :id) AND estado > 0 LIMIT 5';
                    $stmtDebug = $conn2->prepare($sqlDebug);
                    $stmtDebug->bindValue('id', $user['id_usuario']);
                    $stmtDebug->execute();
                    $expUserOriginTechComm = $stmtDebug->fetchAll();
                    if ($expUserOriginTechComm) {
                        $debug[] = "Expedientes (como técnico/comercial): " . implode(', ', array_map(function($e) { return $e['id_expediente']; }, $expUserOriginTechComm));
                    } else {
                        $debug[] = "Expedientes como técnico/comercial: NINGUNO";
                    }
                    
                    // Buscar expedientes donde el usuario es cliente
                    $sqlDebug2 = 'SELECT id_expediente FROM expediente WHERE id_cliente = :id AND estado > 0 LIMIT 5';
                    $stmtDebug2 = $conn2->prepare($sqlDebug2);
                    $stmtDebug2->bindValue('id', $user['id_usuario']);
                    $stmtDebug2->execute();
                    $expUserOriginClient = $stmtDebug2->fetchAll();
                    if ($expUserOriginClient) {
                        $debug[] = "Expedientes (como cliente): " . implode(', ', array_map(function($e) { return $e['id_expediente']; }, $expUserOriginClient));
                    } else {
                        $debug[] = "Expedientes como cliente: NINGUNO";
                    }
                } else {
                    $debug[] = "No se encontró usuario con teléfono origen: $phone";
                }
                
                // Info del usuario por phone_destination si existe
                if ($phoneDestination) {
                    $userDest = $this->findUserByPhone($this->normalizePhone($phoneDestination));
                    if ($userDest) {
                        $nameDest = trim((string)($userDest['nombre'] ?? '') . ' ' . (string)($userDest['apellidos'] ?? ''));
                        $debug[] = "Usuario destino: $nameDest (ID: {$userDest['id_usuario']})";
                        
                        // Buscar expedientes del usuario destino
                        $conn3 = $this->getDoctrine()->getConnection();
                        $sqlDebug2 = 'SELECT id_expediente, id_tecnico, id_comercial, id_cliente FROM expediente WHERE id_cliente = :id AND estado > 0 LIMIT 5';
                        $stmtDebug2 = $conn3->prepare($sqlDebug2);
                        $stmtDebug2->bindValue('id', $userDest['id_usuario']);
                        $stmtDebug2->execute();
                        $expUserDest = $stmtDebug2->fetchAll();
                        if ($expUserDest) {
                            $debug[] = "Expedientes del usuario destino (como cliente): " . implode(', ', array_map(function($e) { return $e['id_expediente']; }, $expUserDest));
                        } else {
                            $debug[] = "El usuario destino NO es cliente de ningún expediente";
                        }
                    } else {
                        $debug[] = "No se encontró usuario con teléfono destino: $phoneDestination";
                    }
                }
                
                $errorMsg = 'No se encontró expediente asociado. Debug: ' . implode('; ', $debug);
                
                return new JsonResponse([
                    'success' => false,
                    'error' => $errorMsg,
                    'debug' => $debug
                ], 400);
            }
        }

        $conn = $this->getDoctrine()->getConnection();
        
        try {
            // Determinar el role SIEMPRE desde phone_origen
            $finalRole = 'user'; // por defecto
            
            // Buscar usuario por phone_origen
            $usuarioOrigen = $this->findUserByPhone($phone);
            if ($usuarioOrigen) {
                $roleUsuario = $usuarioOrigen['role'] ?? null;
                
                // Si el usuario es técnico, comercial o admin, asignar 'assistant'
                if (in_array($roleUsuario, ['ROLE_TECNICO', 'ROLE_COMERCIAL', 'ROLE_ADMIN', 'technician', 'comercial', 'admin'])) {
                    $finalRole = 'assistant';
                } else {
                    $finalRole = 'user';
                }
            }
            
            // Determinar qué teléfono guardar: siempre el del técnico
            $phoneGuardar = $phone_local;  // Por defecto phone_origen
            
            // Si phone_origen no es técnico, pero phone_destination sí lo es, usar phone_destination como phone_number
            // pero mantener el role del phone_origen
            if ($usuarioOrigen && $finalRole === 'user' && $phoneDestination) {
                $phoneDestinationNorm = $this->normalizePhone($phoneDestination);
                $phoneDestinationLocal = (strlen($phoneDestinationNorm) > 9) ? substr($phoneDestinationNorm, -9) : $phoneDestinationNorm;
                
                $usuarioDestino = $this->findUserByPhone($phoneDestinationNorm);
                if ($usuarioDestino) {
                    $roleUsuarioDestino = $usuarioDestino['role'] ?? null;
                    if (in_array($roleUsuarioDestino, ['ROLE_TECNICO', 'ROLE_COMERCIAL', 'ROLE_ADMIN', 'technician', 'comercial', 'admin'])) {
                        $phoneGuardar = $phoneDestinationLocal;
                        // NO cambiar finalRole, mantiene el del phone_origen
                    }
                }
            }

            
            // Preparar JSON estructurado para guardar en BD
            if ($isImage) {
                if (!$storedImagePath && $imageData) {
                    $savedImage = $this->saveWhatsappImageFromBase64($imageData, $imageType);
                    if (!$savedImage) {
                        return new JsonResponse([
                            'success' => false,
                            'error' => 'No se pudo guardar la imagen en uploads'
                        ], 500);
                    }

                    $storedImagePath = $savedImage['filepath'];
                    $imageType = $savedImage['mime_type'];
                }

                $messageData = [
                    'type' => 'image',
                    'filepath' => $storedImagePath,
                    'mime_type' => $imageType ?: 'image/jpeg',
                    'text' => $textContent  // Descripción/caption opcional
                ];
            } else {
                // Solo texto
                $messageData = [
                    'type' => 'text',
                    'content' => $textContent ?: $text
                ];
            }


            
            $conn->insert('chat_history', [
                'id_expediente' => $idExpediente,
                'from_phone' => $phone,
                'to_phone' => $phoneDestination ? $this->normalizePhone($phoneDestination) : null,
                'message' => json_encode($messageData),
                'role' => $finalRole,
                'direction' => ($direction === 'recibido') ? 'recibido' : 'enviado',
                'message_type' => $isImage ? 'image' : 'text',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            $id = $conn->lastInsertId();
            $this->logear("Mensaje guardado en chat_history con ID $id para expediente $idExpediente, direciion: $direction");

            $mensajeGenerado = null;

            if ($direction === 'recibido') 
            {
                // Verificar si el usuario vinculado tiene PilotoAutomatico activo
                $usuarioVinculado = $data['usuario_vinculado'] ?? null;
                
                $this->logear('Entro11111: ' . ($usuarioVinculado && isset($usuarioVinculado['telefono']) ? $usuarioVinculado['telefono'] : 'telefono no disponible'));
                
                // Si no hay usuario vinculado o no tiene teléfono, usar el sistema
                if (!$usuarioVinculado || !isset($usuarioVinculado['telefono']) || empty($usuarioVinculado['telefono'])) 
                {
                    $this->logear('DEBUG: No hay usuario vinculado con teléfono válido, usando sistema');
                    $telefonoParaBot = $this->telefonoSistema;
                    $pilotoAutomaticoActivo = true; // Asumir que el sistema siempre está activo
                    
                    // Inicializar variables de teléfono para envío de mensajes
                    $phoneDestinoNorm = $this->normalizePhone($phone);
                    $phoneDestinoConPrefijo = $this->normalizePhonenWithPrefix($phoneDestinoNorm);
                    $telefonoUsuarioVinculadoConPrefijo = $this->normalizePhonenWithPrefix($this->telefonoSistema);
                    $hash = $this->generarHashWhatsapp(date('Y-m-d'));
                    $fecha = date('Y-m-d');
                    
                    // Ejecutar el flujo de análisis de mensajes para sistema
                    $this->logear('✓ El teléfono para bot es el del sistema, analizando mensaje entrante');
                    
                    // IMPORTANTE: Obtener datosFase1 PRIMERO
                    $this->logear('DEBUG: Obteniendo datosFase1 para aplicar condiciones antes de IA...');
                    $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $this->getDoctrine()->getConnection());
                    
                    // Obtener campos requeridos y metadatos
                    $camposRequeridos = $this->getIAController()->obtenerCamposRequeridos();
                    $metadatosCampos = !empty($camposRequeridos) ? $this->getIAController()->obtenerMetadatosCampos($camposRequeridos) : null;
                    
                    // Analizar mensaje
                    try {
                        $this->logear('DEBUG: Llamando a analizarMensajeParaDatos() con sistema...');
                        $datosExtraidos = $this->getIAController()->analizarMensajeParaDatos($textContent ?: $text, $idExpediente, null, $datosFase1);
                        $this->logear('DEBUG: analizarMensajeParaDatos() retornó - campos_encontrados count=' . (isset($datosExtraidos['campos_encontrados']) ? count($datosExtraidos['campos_encontrados']) : 'null'));
                    } catch (\Exception $e) {
                        $this->logear('⚠ EXCEPCIÓN en analizarMensajeParaDatos: ' . $e->getMessage());
                        $datosExtraidos = null;
                    }
                    
                    if ($datosExtraidos && !empty($datosExtraidos['campos_encontrados'])) {
                        $this->logear('✓ Datos extraídos: ' . json_encode($datosExtraidos['campos_encontrados']));
                        
                        // Guardar datos
                        $nombreClienteParaSalvar = $expediente['cliente_nombre'] ?? 'Cliente';
                        $nifClienteParaSalvar = $expediente['cliente_nif'] ?? '';
                        $resultadoGuardar = $this->getIAController()->guardarDatosEnExpediente($idExpediente, $datosExtraidos, $phone, $nombreClienteParaSalvar, $nifClienteParaSalvar);
                        
                        // Limpiar cache
                        $em = $this->getDoctrine()->getManager();
                        $em->clear();
                        
                        // Obtener proxima parte
                        $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $em->getConnection());
                        $resultadoParte = $this->getIAController()->obtenerProximaParteYCamposFaltantes($idExpediente, $datosFase1);
                        $camposFaltantesActuales = $resultadoParte['campos_faltantes'];
                        $mensajeSegmentado = $resultadoParte['mensaje_completo'] ?? '';
                        
                        if (!empty($camposFaltantesActuales)) {
                            if (!empty($mensajeSegmentado)) {
                                $this->llamarBotWhatsApp(
                                    $this->normalizePhonenWithPrefix($this->telefonoSistema),
                                    $phoneDestinoConPrefijo,
                                    $mensajeSegmentado,
                                    $hash,
                                    $fecha
                                );
                                $this->logear('✓ Mensaje segmentado enviado al cliente (sistema)');
                            }
                        }
                    }
                } 
                else if ($usuarioVinculado && isset($usuarioVinculado['telefono'])) 
                {
                    $this->logear('Entro22222');
                    $telefonoVinculado = $this->normalizePhone($usuarioVinculado['telefono']);
                    
                    // Si no hay teléfono vinculado válido, usar el teléfono del sistema
                    if (empty($telefonoVinculado)) {
                        $this->logear('DEBUG: No hay teléfono vinculado válido, usando teléfono del sistema');
                        $telefonoVinculado = $this->telefonoSistema;
                    }
                    
                    $telefonoVinculadoLocal = (strlen($telefonoVinculado) > 9) ? substr($telefonoVinculado, -9) : $telefonoVinculado;
                    
                    // Buscar en WhatsappSenders si el usuario tiene PilotoAutomatico activo
                    $pilotoAutomaticoActivo = $this->verificarPilotoAutomatico($telefonoVinculadoLocal);
                    $this->logear('Entro22222: ' . ($pilotoAutomaticoActivo ? 'activo' : 'no activo'));
                    //$pilotoAutomaticoActivo = true;
                    if ($pilotoAutomaticoActivo) 
                    {
                        $this->logear('Entro33333');
                        // Enviar mensaje de prueba si piloto automático está activo
                        $telefonoUsuarioVinculado = $this->normalizePhone($telefonoVinculado);
                        $telefonoUsuarioVinculadoConPrefijo = $this->normalizePhonenWithPrefix($telefonoUsuarioVinculado);

                        // Comparar si el teléfono vinculado es el del sistema
                        if ($telefonoUsuarioVinculadoConPrefijo === $this->normalizePhonenWithPrefix($this->telefonoSistema) || $telefonoUsuarioVinculadoConPrefijo === $this->normalizePhonenWithPrefix($this->telefonoSistema)) 
                        {
                            $this->logear('✓ El teléfono vinculado es el del sistema, analizando mensaje entrante');
                            
                            // IMPORTANTE: Obtener datosFase1 PRIMERO para que analizarMensajeParaDatos() pueda aplicar condiciones
                            $this->logear('DEBUG: Obteniendo datosFase1 para aplicar condiciones antes de IA...');
                            $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $this->getDoctrine()->getConnection());
                            
                            // Obtener campos requeridos (array manual) y sus metadatos (dinámicos)
                            $camposRequeridos = $this->getIAController()->obtenerCamposRequeridos();
                            $metadatosCampos = !empty($camposRequeridos) ? $this->getIAController()->obtenerMetadatosCampos($camposRequeridos) : null;
                            
                            $this->logear('DEBUG: Antes de analizarMensajeParaDatos - textContent="' . ($textContent ?: 'null') . '", idExpediente=' . $idExpediente . ', metadatos count=' . (is_array($metadatosCampos) ? count($metadatosCampos) : 'null') . ', datosFase1=' . ($datosFase1 ? 'SÍ' : 'NO'));
                            
                            try {
                                $this->logear('DEBUG: Llamando a analizarMensajeParaDatos()...');
                                // Analizar el mensaje para extraer datos del expediente (AHORA pasando datosFase1 para aplicar condiciones ANTES de IA)
                                $datosExtraidos = $this->getIAController()->analizarMensajeParaDatos($textContent ?: $text, $idExpediente, null, $datosFase1);
                                $this->logear('DEBUG: analizarMensajeParaDatos() retornó - metodo=' . ($datosExtraidos['metodo'] ?? 'null') . ', campos_encontrados count=' . (isset($datosExtraidos['campos_encontrados']) ? count($datosExtraidos['campos_encontrados']) : 'null'));
                            } catch (\Exception $e) {
                                $this->logear('⚠ EXCEPCIÓN en analizarMensajeParaDatos: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
                                $datosExtraidos = null;
                            }
                            
                            // Inicializar variables de teléfono para envío de mensajes (necesarias en toda la rama)
                            $phoneDestinoNorm = $this->normalizePhone($phone);
                            $phoneDestinoConPrefijo = $this->normalizePhonenWithPrefix($phoneDestinoNorm);
                            $hash = $this->generarHashWhatsapp(date('Y-m-d'));
                            $fecha = date('Y-m-d');
                            
                            if ($datosExtraidos && !empty($datosExtraidos['campos_encontrados'])) {
                                $this->logear('✓ Datos extraídos del mensaje: ' . json_encode($datosExtraidos['campos_encontrados']));
                                $this->logear('DEBUG: Estructura completa de datosExtraidos:');
                                $this->logear(json_encode($datosExtraidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                
                                // Obtener nombre completo del cliente para guardar en campo 192
                                $nombreClienteParaSalvar = trim(($usuarioVinculado['nombre'] ?? '') . ' ' . ($usuarioVinculado['apellidos'] ?? ''));
                                if (empty($nombreClienteParaSalvar)) {
                                    // Intentar con nombre del cliente (usuario vinculado al expediente)
                                    $nombreClienteParaSalvar = trim(($expediente['cliente_nombre'] ?? '') . ' ' . ($expediente['cliente_apellidos'] ?? ''));
                                }
                                
                                // Obtener NIF para campo 194: primero de usuarioVinculado, luego del expediente
                                $nifClienteParaSalvar = $usuarioVinculado['nif'] ?? '';
                                if (empty($nifClienteParaSalvar)) {
                                    // Si no viene en usuarioVinculado, intentar obtener desde BD usando el teléfono
                                    if (!empty($usuarioVinculado['telefono'])) {
                                        try {
                                            $telefonoVinc = $this->normalizePhone($usuarioVinculado['telefono']);
                                            $connNif = $this->getDoctrine()->getConnection();
                                            $sqlNif = 'SELECT nif FROM usuario WHERE telefono_movil LIKE :telefono LIMIT 1';
                                            $stmtNif = $connNif->prepare($sqlNif);
                                            $stmtNif->bindValue('telefono', '%' . $telefonoVinc . '%');
                                            $stmtNif->execute();
                                            $resultNif = $stmtNif->fetch();
                                            if ($resultNif && !empty($resultNif['nif'])) {
                                                $nifClienteParaSalvar = $resultNif['nif'];
                                                $this->logear('✓ NIF obtenido desde BD usando teléfono: ' . $nifClienteParaSalvar);
                                            }
                                        } catch (\Exception $e) {
                                            $this->logear('⚠ Error obteniendo NIF desde BD: ' . $e->getMessage());
                                        }
                                    }
                                    // Si aún no hay NIF, intentar con el del expediente
                                    if (empty($nifClienteParaSalvar)) {
                                        $nifClienteParaSalvar = $expediente['cliente_nif'] ?? '';
                                    }
                                }
                                
                                $this->logear('DEBUG: usuarioVinculado completo: ' . json_encode($usuarioVinculado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                $this->logear('DEBUG: expediente completo: ' . json_encode($expediente, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                
                                // Guardar los datos extraídos en el expediente (incluyendo campo 192 y 194)
                                $resultadoGuardar = $this->getIAController()->guardarDatosEnExpediente($idExpediente, $datosExtraidos, $phone, $nombreClienteParaSalvar, $nifClienteParaSalvar);
                                $this->logear('DEBUG: Resultado de guardarDatosEnExpediente: ' . json_encode($resultadoGuardar, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                
                                // LIMPIAR CACHE DE DOCTRINE después de guardar datos con SQL directo
                                $em = $this->getDoctrine()->getManager();
                                $em->clear();
                                $this->logear('DEBUG: Cache de Doctrine limpiado para obtener datos frescos');
                                
                                // VERIFICAR si aún quedan campos faltantes después de guardar
                                $this->logear('DEBUG: Verificando campos faltantes después de guardar...');
                                // Obtener nombre del cliente: primero de usuarioVinculado, luego del cliente/usuario, finalmente fallback a 'Roberto'
                                $nombreClienteVerif = trim(($usuarioVinculado['nombre'] ?? '') . ' ' . ($usuarioVinculado['apellidos'] ?? ''));
                                if (empty($nombreClienteVerif)) {
                                    // Intentar con nombre del cliente (usuario vinculado al expediente)
                                    $nombreClienteVerif = trim(($expediente['cliente_nombre'] ?? '') . ' ' . ($expediente['cliente_apellidos'] ?? ''));
                                    if (empty($nombreClienteVerif)) {
                                        $nombreClienteVerif = 'Roberto';
                                    }
                                }
                                
                                // OBTENER PROXIMA PARTE A SOLICITAR (calculo dinamico)
                                $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $em->getConnection());
                                $resultadoParte = $this->getIAController()->obtenerProximaParteYCamposFaltantes($idExpediente, $datosFase1);
                                $numeroParte = $resultadoParte['numero_parte'];
                                $numeroParteAnterior = $resultadoParte['numero_parte_anterior'];
                                $camposFaltantesActuales = $resultadoParte['campos_faltantes'];
                                $mensajeSegmentado = $resultadoParte['mensaje_completo'] ?? '';
                                
                                $this->logear('DEBUG: Proxima parte a solicitar: ' . $numeroParte . ', campos faltantes: ' . count($camposFaltantesActuales));
                                
                                // Si aun hay campos faltantes, pedir mas datos
                                if (!empty($camposFaltantesActuales)) {
                                    $this->logear('⚠ Aun quedan ' . count($camposFaltantesActuales) . ' campos faltantes, pidiendo mas datos...');
                                    
                                    // Usar el mensaje segmentado generado automáticamente
                                    if (!empty($mensajeSegmentado)) {
                                        // El mensaje ya está completamente formado y segmentado - SIEMPRE usar bot del sistema
                                        $this->llamarBotWhatsApp(
                                            $this->normalizePhonenWithPrefix($this->telefonoSistema),
                                            $phoneDestinoConPrefijo,
                                            $mensajeSegmentado,
                                            $hash,
                                            $fecha
                                        );
                                        $this->logear('✓ Mensaje segmentado enviado al cliente');
                                    } else {
                                        // Fallback: usar el método antiguo si no hay mensaje segmentado
                                        $tieneHistorico = $this->getIAController()->tieneConversacionReciente($idExpediente, 10);
                                        $esNuevaParte = ($numeroParte > $numeroParteAnterior);
                                        $mensajeUnificado = $this->getIAController()->construirMensajeUnificado($nombreClienteVerif, $camposFaltantesActuales, $tieneHistorico, $esNuevaParte);
                                        
                                        $this->llamarBotWhatsApp(
                                            $this->normalizePhonenWithPrefix($this->telefonoSistema),
                                            $phoneDestinoConPrefijo,
                                            $mensajeUnificado,
                                            $hash,
                                            $fecha
                                        );
                                        $this->logear('✓ Mensaje de campos faltantes enviado al cliente (fallback - usando bot)');
                                    }
                                } else {
                                    // Todos los campos están completos, generar mensaje de finalización
                                    $this->logear('✓ Todos los campos están completos, generando mensaje final...');
                                    
                                    // Extraer primer nombre del cliente
                                    $nombres = explode(' ', trim($nombreClienteVerif));
                                    $primerNombre = $nombres[0] ?? 'Cliente';
                                    
                                    // Mensaje de finalización agradeciendo y notificando que procesaremos los datos
                                    $mensajeFinal = "¡Perfecto, $primerNombre! 🎉\n\n";
                                    $mensajeFinal .= "Hemos recibido toda la información necesaria para tu expediente.\n\n";
                                    $mensajeFinal .= "Ahora procesaremos tus datos y nos pondremos en contacto contigo en breve para continuar avanzando con tu solicitud.\n\n";
                                    $mensajeFinal .= "¡Gracias por confiar en nosotros! 💙";
                                    
                                    // Enviar mensaje de finalización - SIEMPRE usar bot del sistema
                                    $this->llamarBotWhatsApp(
                                        $this->normalizePhonenWithPrefix($this->telefonoSistema),
                                        $phoneDestinoConPrefijo,
                                        $mensajeFinal,
                                        $hash,
                                        $fecha
                                    );
                                    $this->logear('✓ Mensaje de finalización enviado al cliente (desde bot)');
                                }
                            } else {
                                $this->logear('✗ No se encontraron datos útiles en el mensaje para completar el expediente');
                                $this->logear('DEBUG: datosExtraidos: ' . json_encode($datosExtraidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            }
                        } 

                        // LA GENERACIÓN DE MENSAJES SE HACE DENTRO DEL BLOQUE DE DATOS EXTRAÍDOS
                        // AQUÍ NO SE GENERA NADA PORQUE YA SE VERIFICA SI FALTAN DATOS O NO
                        // (Esta sección antiguo se comenta para evitar generar mensajes sin verificar campos)
                    }
                }
            }
        } 
        catch (\Exception $e) 
        {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al guardar el mensaje: ' . $e->getMessage()
            ], 500);
        }
        $response = ['id' => $id];
        if ($user) {
            $response['user'] = [
                'id_usuario' => $user['id_usuario'] ?? null,
                'nombre' => $user['nombre'] ?? '',
                'apellidos' => $user['apellidos'] ?? '',
                'display_name' => $displayName,
                'mensajeGenerado' => $mensajeGenerado,
                'pilotoAutomaticoActivo' => $pilotoAutomaticoActivo,
                'telefonoVinculadoLocal' => $telefonoVinculadoLocal,
            ];
        }
        if ($idExpediente) {
            $response['expediente_id'] = $idExpediente;
            $response['linked'] = true;
        } else {
            $response['linked'] = false;
        }

        // Información adicional para diagnóstico: teléfono original normalizado y guardado
        $response['phone_normalized'] = $phone;
        $response['phone_stored'] = $phone_local;

        return new JsonResponse($response, 201);
    }

    // Busca un usuario por teléfono (devuelve fila de usuario o null)
    private function findUserByPhone($phone)
    {
        $conn = $this->getDoctrine()->getConnection();
        try {
            $variants = array_unique(array_filter([
                $phone,
                ltrim($phone, '0'),
                (strlen($phone) > 9 ? substr($phone, -9) : null)
            ]));

            if (count($variants) === 0) {
                return null;
            }

            $params = [];
            foreach ($variants as $i => $v) {
                $params[':p' . $i] = $v;
            }

            $sql = 'SELECT id_usuario, nombre, apellidos, telefono_movil, role FROM usuario WHERE telefono_movil IN (' . implode(', ', array_keys($params)) . ') AND estado = 1 LIMIT 1';
            $stmt = $conn->prepare($sql);
            foreach ($params as $ph => $val) {
                $stmt->bindValue(trim($ph, ':'), $val);
            }
            $stmt->execute();
            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('findUserByPhone error: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Busca expediente que tenga ambos teléfonos
     * phone_origen puede ser técnico/comercial o cliente
     * phone_destination puede ser técnico/comercial o cliente
     * Los roles pueden intercambiarse
     */
    private function findExpedienteByBothPhones($phoneOrigen, $phoneDestino)
    {
        $conn = $this->getDoctrine()->getConnection();

        try {
            // Obtener variantes de ambos teléfonos
            $variantsOrigen = array_unique(array_filter([
                $phoneOrigen,
                ltrim($phoneOrigen, '0'),
                (strlen($phoneOrigen) > 9 ? substr($phoneOrigen, -9) : null)
            ]));

            $variantsDestino = array_unique(array_filter([
                $phoneDestino,
                ltrim($phoneDestino, '0'),
                (strlen($phoneDestino) > 9 ? substr($phoneDestino, -9) : null)
            ]));

            if (count($variantsOrigen) === 0 || count($variantsDestino) === 0) {
                return null;
            }

            // Buscar usuarios con estos teléfonos
            $placeholdersOrigen = [];
            $placeholdersDestino = [];
            $params = [];
            
            foreach ($variantsOrigen as $i => $v) {
                $ph = ':origen' . $i;
                $placeholdersOrigen[] = $ph;
                $params[$ph] = $v;
            }
            
            foreach ($variantsDestino as $i => $v) {
                $ph = ':destino' . $i;
                $placeholdersDestino[] = $ph;
                $params[$ph] = $v;
            }

            $sql = 'SELECT id_usuario FROM usuario 
                    WHERE telefono_movil IN (' . implode(',', $placeholdersOrigen) . ') 
                    AND estado = 1 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $val) {
                if (strpos($key, 'origen') === 1) {
                    $stmt->bindValue(trim($key, ':'), $val);
                }
            }
            $stmt->execute();
            $usuarioOrigen = $stmt->fetch();

            $sql = 'SELECT id_usuario FROM usuario 
                    WHERE telefono_movil IN (' . implode(',', $placeholdersDestino) . ') 
                    AND estado = 1 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $val) {
                if (strpos($key, 'destino') === 1) {
                    $stmt->bindValue(trim($key, ':'), $val);
                }
            }
            $stmt->execute();
            $usuarioDestino = $stmt->fetch();

            if (!$usuarioOrigen || !$usuarioDestino) {
                return null;
            }

            $idUsuarioOrigen = $usuarioOrigen['id_usuario'];
            $idUsuarioDestino = $usuarioDestino['id_usuario'];

            // Búsqueda 1: Expediente donde origen es técnico/comercial y destino es cliente
            $sql = 'SELECT id_expediente FROM expediente 
                    WHERE (id_tecnico = :origen OR id_comercial = :origen) 
                    AND id_cliente = :destino 
                    AND estado > 0 
                    ORDER BY fecha_creacion DESC 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('origen', $idUsuarioOrigen);
            $stmt->bindValue('destino', $idUsuarioDestino);
            $stmt->execute();
            $expediente = $stmt->fetch();
            
            if ($expediente) {
                return $expediente['id_expediente'];
            }

            // Búsqueda 2: Expediente donde destino es técnico/comercial y origen es cliente
            $sql = 'SELECT id_expediente FROM expediente 
                    WHERE (id_tecnico = :destino OR id_comercial = :destino) 
                    AND id_cliente = :origen 
                    AND estado > 0 
                    ORDER BY fecha_creacion DESC 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('origen', $idUsuarioOrigen);
            $stmt->bindValue('destino', $idUsuarioDestino);
            $stmt->execute();
            $expediente = $stmt->fetch();
            
            if ($expediente) {
                return $expediente['id_expediente'];
            }

            // Búsqueda 3: Ambos son técnico/comercial en el mismo expediente
            $sql = 'SELECT id_expediente FROM expediente 
                    WHERE ((id_tecnico = :origen AND id_comercial = :destino) 
                    OR (id_tecnico = :destino AND id_comercial = :origen))
                    AND estado > 0 
                    ORDER BY fecha_creacion DESC 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('origen', $idUsuarioOrigen);
            $stmt->bindValue('destino', $idUsuarioDestino);
            $stmt->execute();
            $expediente = $stmt->fetch();
            
            if ($expediente) {
                return $expediente['id_expediente'];
            }

            return null;
        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('findExpedienteByBothPhones error: ' . $e->getMessage());
            }
            return null;
        }
    }

    // Busca el expediente más reciente de un usuario por su teléfono
    // Busca expediente por teléfono del CLIENTE (phone_destination)
    // El cliente está en la tabla usuario con su teléfono_movil
    // El expediente tiene id_cliente que referencia a ese usuario
    private function findExpedienteByClientPhone($phone)
    {
        $conn = $this->getDoctrine()->getConnection();

        try {
            // Preparar variantes del teléfono
            $variants = array_unique(array_filter([
                $phone,
                ltrim($phone, '0'),
                (strlen($phone) > 9 ? substr($phone, -9) : null)
            ]));

            if (count($variants) === 0) {
                return null;
            }

            // 1. Buscar usuario (cliente) con ese teléfono
            $placeholders = [];
            $params = [];
            foreach ($variants as $i => $v) {
                $ph = ':p' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $v;
            }

            $sql = 'SELECT id_usuario FROM usuario 
                    WHERE telefono_movil IN (' . implode(',', array_keys($params)) . ') 
                    AND estado = 1 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            foreach ($params as $ph => $val) {
                $stmt->bindValue(trim($ph, ':'), $val);
            }
            $stmt->execute();
            $usuario = $stmt->fetch();

            if (!$usuario) {
                return null;
            }

            // 2. Buscar expediente donde este usuario es id_cliente
            $sql = 'SELECT id_expediente FROM expediente 
                    WHERE id_cliente = :clientId AND estado > 0 
                    ORDER BY fecha_creacion DESC 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('clientId', $usuario['id_usuario']);
            $stmt->execute();
            $expediente = $stmt->fetch();

            return $expediente ? $expediente['id_expediente'] : null;
        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('findExpedienteByClientPhone error: '.$e->getMessage());
            }
            return null;
        }
    }

    // Busca expediente por teléfono del COMERCIAL/TÉCNICO (phone_number)
    // El comercial/técnico está en la tabla usuario
    // El expediente tiene id_comercial o id_tecnico que referencia a ese usuario
    private function findExpedienteByCommercialPhone($phone)
    {
        $conn = $this->getDoctrine()->getConnection();

        try {
            // Preparar variantes del teléfono
            $variants = array_unique(array_filter([
                $phone,
                ltrim($phone, '0'),
                (strlen($phone) > 9 ? substr($phone, -9) : null)
            ]));

            if (count($variants) === 0) {
                return null;
            }

            // 1. Buscar usuario (comercial/técnico) con ese teléfono
            $placeholders = [];
            $params = [];
            foreach ($variants as $i => $v) {
                $ph = ':p' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $v;
            }

            $sql = 'SELECT id_usuario FROM usuario 
                    WHERE telefono_movil IN (' . implode(',', array_keys($params)) . ') 
                    AND estado = 1 
                    LIMIT 1';
            $stmt = $conn->prepare($sql);
            foreach ($params as $ph => $val) {
                $stmt->bindValue(trim($ph, ':'), $val);
            }
            $stmt->execute();
            $usuario = $stmt->fetch();

            if ($usuario) {
                // 2. Buscar expediente donde este usuario es comercial o técnico
                $sql = 'SELECT id_expediente FROM expediente 
                        WHERE (id_comercial = :userId OR id_tecnico = :userId) AND estado > 0 
                        ORDER BY fecha_creacion DESC 
                        LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('userId', $usuario['id_usuario']);
                $stmt->execute();
                $expediente = $stmt->fetch();

                if ($expediente) {
                    return $expediente['id_expediente'];
                }
            }

            // Si no hay expediente como comercial/técnico, buscar en campos personalizados
            // por compatibilidad con expedientes que tengan el teléfono en campo_hito_expediente
            $sql = 'SELECT che.id_expediente, che.valor AS valor, e.fecha_creacion AS fecha_creacion, e.estado AS estado
                    FROM campo_hito_expediente che
                    LEFT JOIN expediente e ON che.id_expediente = e.id_expediente
                    WHERE che.id_campo_hito = 408';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $matchedExpedienteId = null;
            $matchedExpedienteTime = 0;
            foreach ($rows as $r) {
                if (empty($r['valor'])) {
                    continue;
                }
                $valorNormalizado = preg_replace('/\D+/', '', $r['valor']);
                foreach ($variants as $v) {
                    if ($v === $valorNormalizado) {
                        if (isset($r['estado']) && (int)$r['estado'] > 0) {
                            $ts = 0;
                            if (!empty($r['fecha_creacion'])) {
                                $ts = strtotime($r['fecha_creacion']);
                            }
                            if ($ts > $matchedExpedienteTime) {
                                $matchedExpedienteTime = $ts;
                                $matchedExpedienteId = $r['id_expediente'];
                            }
                        }
                        break 2;
                    }
                }
            }

            return $matchedExpedienteId;
        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('findExpedienteByCommercialPhone error: '.$e->getMessage());
            }
            return null;
        }
    }

    private function findExpedienteByPhone($phone)
    {
        $conn = $this->getDoctrine()->getConnection();

        try {
            // Preparar variantes del teléfono para buscar en la tabla usuario.
            // En la BD los teléfonos de usuarios suelen guardarse sin prefijo de país (ej: +34XXXXXXXXX -> XXXXXXXXX)
            $variants = array_unique(array_filter([
                $phone,
                ltrim($phone, '0'),
                (strlen($phone) > 9 ? substr($phone, -9) : null)
            ]));

            $usuario = null;
            if (count($variants) > 0) {
                // Crear placeholders dinámicos para la consulta IN
                $placeholders = [];
                $params = [];
                foreach ($variants as $i => $v) {
                    $ph = ':p' . $i;
                    $placeholders[] = $ph;
                    $params[$ph] = $v;
                }
                $sql = 'SELECT id_usuario, telefono_movil FROM usuario WHERE telefono_movil IN (' . implode(',', array_keys($params)) . ') AND estado = 1 LIMIT 1';
                $stmt = $conn->prepare($sql);
                foreach ($params as $ph => $val) {
                    $stmt->bindValue(trim($ph, ':'), $val);
                }
                $stmt->execute();
                $usuario = $stmt->fetch();
            }

            if ($usuario) {
                // Buscar expedientes donde este usuario (comercial/técnico) es responsable
                // Busca el expediente más reciente donde es id_comercial O id_tecnico
                $sql = 'SELECT id_expediente FROM expediente 
                        WHERE (id_comercial = :userId OR id_tecnico = :userId) AND estado > 0 
                        ORDER BY fecha_creacion DESC 
                        LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('userId', $usuario['id_usuario']);
                $stmt->execute();
                $expediente = $stmt->fetch();

                if ($expediente) {
                    return $expediente['id_expediente'];
                }
                // Si no hay expediente como comercial/técnico, continuar buscando en campo_hito_expediente
            }

            // Si no hay usuario, buscar en campos personalizados (campo_hito_expediente)
            // id_campo_hito = 408 corresponde al teléfono en los formularios web
            $sql = 'SELECT che.id_expediente, che.valor AS valor, e.fecha_creacion AS fecha_creacion, e.estado AS estado
                    FROM campo_hito_expediente che
                    LEFT JOIN expediente e ON che.id_expediente = e.id_expediente
                    WHERE che.id_campo_hito = 408';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $matchedExpedienteId = null;
            $matchedExpedienteTime = 0;
            // preparar variantes del teléfono para comparar (incluyendo última 9 cifras)
            $variants = array_unique(array_filter([
                $phone,
                ltrim($phone, '0'),
                (strlen($phone) > 9 ? substr($phone, -9) : null)
            ]));
            foreach ($rows as $r) {
                if (empty($r['valor'])) {
                    continue;
                }
                $valorNormalizado = preg_replace('/\D+/', '', $r['valor']);
                foreach ($variants as $v) {
                    if ($v === $valorNormalizado) {
                        // verificar estado del expediente (mayor que 0 = activo)
                        if (isset($r['estado']) && (int)$r['estado'] > 0) {
                            $ts = 0;
                            if (!empty($r['fecha_creacion'])) {
                                $ts = strtotime($r['fecha_creacion']);
                            }
                            if ($ts > $matchedExpedienteTime) {
                                $matchedExpedienteTime = $ts;
                                $matchedExpedienteId = $r['id_expediente'];
                            }
                        }
                        break 2; // encontramos una coincidencia exacta, salir
                    }
                }
            }

            return $matchedExpedienteId ? $matchedExpedienteId : null;
        } catch (\Exception $e) {
            // Registrar el error y continuar sin vincular
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('findExpedienteByPhone error: '.$e->getMessage());
            } else {
                $this->logear('findExpedienteByPhone error: '.$e->getMessage());
            }
            return null;
        }
    }

    /**
     * Obtiene los mensajes de WhatsApp para un expediente específico
     * GET /API/WhatsApp/messages/{id}
     */
    public function getMessagesAction($id)
    {
        $conn = $this->getDoctrine()->getConnection();
        error_log("getMessagesAction called with id: " . $id);

        try {
            // Validar que el id_expediente existe
            $sql = 'SELECT id_expediente FROM expediente WHERE id_expediente = :id LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('id', (int)$id);
            $stmt->execute();
            $expediente = $stmt->fetch();

            if (!$expediente) {
                return new JsonResponse([
                    'error' => 'Expediente no encontrado'
                ], 404);
            }

            // Obtener los mensajes ordenados por timestamp
            $sql = 'SELECT id, from_phone, to_phone, message, role, direction, message_type, timestamp, id_expediente
                    FROM chat_history
                    WHERE id_expediente = :idExpediente
                    ORDER BY timestamp ASC';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('idExpediente', (int)$id);
            $stmt->execute();
            $messages = $stmt->fetchAll();

            return new JsonResponse($messages, 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error al obtener los mensajes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los IDs de expedientes que tienen mensajes de WhatsApp
     * GET /API/WhatsApp/expedientes-con-mensajes
     */
    public function getExpedientesConMensajesAction(Request $request)
    {
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $conn = $this->getDoctrine()->getConnection();

        try {
            // Obtener IDs únicos de expedientes que tienen mensajes en chat_history
            $sql = 'SELECT DISTINCT id_expediente FROM chat_history WHERE id_expediente IS NOT NULL ORDER BY id_expediente';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll();

            $expedienteIds = array_map(function($row) {
                return $row['id_expediente'];
            }, $results);

            return new JsonResponse([
                'success' => true,
                'expedientes_con_mensajes' => $expedienteIds,
                'count' => count($expedienteIds)
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener expedientes con mensajes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía un mensaje a través del bot de WhatsApp y lo guarda en chat_history
     * POST /API/WhatsApp/send-message
     */
    public function sendMessageAction(Request $request)
    {
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'invalid JSON'], 400);
        }

        $idExpediente = $data['id_expediente'] ?? null;
        $phoneOrigen = $data['phone_number'] ?? null; // Opcional
        $texto = $data['text'] ?? null;

        if (!$idExpediente || !$texto) {
            return new JsonResponse([
                'error' => 'id_expediente and text are required'
            ], 400);
        }

        $conn = $this->getDoctrine()->getConnection();
        $em = $this->getDoctrine()->getManager();

        try {
            // 1. Validar que el expediente existe
            $sql = 'SELECT id_expediente, id_cliente FROM expediente WHERE id_expediente = :id AND estado > 0 LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('id', (int)$idExpediente);
            $stmt->execute();
            $expediente = $stmt->fetch();

            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'El expediente no existe o no está activo'
                ], 400);
            }

            // 2. Obtener el teléfono del cliente (destino)
            $phoneDestino = null;
            if ($expediente['id_cliente']) {
                $sql = 'SELECT telefono_movil FROM usuario WHERE id_usuario = :id LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('id', $expediente['id_cliente']);
                $stmt->execute();
                $cliente = $stmt->fetch();
                if ($cliente && $cliente['telefono_movil']) {
                    $phoneDestino = $this->normalizePhonenWithPrefix($cliente['telefono_movil']);
                }
            }

            // 3. Si no encontró teléfono en usuario, buscar en campos personalizados
            if (!$phoneDestino) {
                $sql = 'SELECT valor FROM campo_hito_expediente 
                        WHERE id_expediente = :id AND id_campo_hito = 408 
                        ORDER BY id_campo_hito_expediente DESC LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('id', (int)$idExpediente);
                $stmt->execute();
                $campo = $stmt->fetch();
                if ($campo && $campo['valor']) {
                    $phoneDestino = $this->normalizePhonenWithPrefix($campo['valor']);
                }
            }

            if (!$phoneDestino) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No se encontró teléfono de destino para este expediente'
                ], 400);
            }

            // 4. Obtener la sesión activa del usuario logueado
            $usuario = $this->getUser();
            if (!$usuario) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $idUsuarioLogueado = $usuario->getIdUsuario();
            
            // Buscar en WhatsappSender la sesión activa del usuario
            $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
            $senderQuery = $senderRepo->createQueryBuilder('ws')
                ->where('ws.idUsuario = :idUsuario')
                ->andWhere('ws.imagenQR IS NULL')  // Sesión activa (no necesita escanear QR)
                ->setParameter('idUsuario', $idUsuarioLogueado)
                ->getQuery();
            
            $sender = $senderQuery->getOneOrNullResult();
            
            if (!$sender) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No tienes una sesión de WhatsApp activa. Por favor, conecta tu WhatsApp primero.'
                ], 400);
            }

            // 5. Usar sessionId de WhatsappSender (es el UUID correcto de la sesión en el bot)
            $sessionId = $sender->getSessionId() ?: 'ComercialPrueba';
            
            $this->logear("=== CONFIGURACIÓN DE ENVÍO ===");
            $this->logear("Usuario: {$idUsuarioLogueado}");
            $this->logear("SessionId: {$sessionId}");
            $this->logear("Teléfono destino: {$phoneDestino}");
            $this->logear("Expediente: {$idExpediente}");

            $botResponse = $this->llamarBotWhatsApp(
                $sessionId,
                $phoneDestino,
                $texto
            );

            if (!$botResponse['success']) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Error al enviar mensaje al bot: ' . ($botResponse['message'] ?? 'Unknown error')
                ], 500);
            }

            // 5. Guardar el mensaje en chat_history si se envió correctamente al bot
            try {
                $fromPhone = $phoneOrigen ? $this->normalizePhone($phoneOrigen) : ($sender ? $this->normalizePhone($sender->getTelefono()) : null);
                $messageData = [
                    'type' => 'text',
                    'content' => $texto
                ];
                
                $conn->insert('chat_history', [
                    'id_expediente' => $idExpediente,
                    'from_phone' => $fromPhone,
                    'to_phone' => $this->normalizePhone($phoneDestino),
                    'message' => json_encode($messageData),
                    'role' => 'assistant',
                    'direction' => 'enviado',
                    'message_type' => 'text',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                // Log del error pero no bloquear la respuesta
                if ($this->container->has('logger')) {
                    $this->container->get('logger')->warning('Error al guardar mensaje en chat_history: ' . $e->getMessage());
                }
            }

            return new JsonResponse([
                'success' => true,
                'bot_response' => $botResponse,
                'expediente_id' => $idExpediente,
                'message' => 'Mensaje enviado y guardado correctamente'
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al enviar mensaje: ' . $e->getMessage(),
                'trace' => get_class($e)
            ], 500);
        }
    }

    /**
     * Llama al bot de WhatsApp para enviar un mensaje
     * Usa la API correcta: POST /api/messages/send
     */
    private function llamarBotWhatsApp($sessionId, $telefono, $mensaje)
    {
        $this->logear("=== ENVÍO A BOT WHATSAPP ===");
        $this->logear("sessionId: {$sessionId}");
        $this->logear("to (telefono): {$telefono}");
        $this->logear("body (mensaje): {$mensaje}");
        
        try {
            $url = "https://punchiest-irremediably-suzette.ngrok-free.dev/api/messages/send";

            $payload = json_encode([
                'sessionId' => $sessionId,
                'to' => $telefono,
                'body' => $mensaje
            ]);
            
            $this->logear("Payload JSON: {$payload}");

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: 1234567890',
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception('cURL Error: ' . $error);
            }

            $responseData = json_decode($response, true);
            
            $this->logear("HTTP Code: {$httpCode}");
            $this->logear("Response: " . json_encode($responseData));

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message' => 'Mensaje enviado al bot',
                    'http_code' => $httpCode,
                    'response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error del bot: HTTP ' . $httpCode,
                    'http_code' => $httpCode,
                    'response' => $responseData
                ];
            }
        } catch (\Exception $e) {
            $this->logear("EXCEPCIÓN: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Normaliza el teléfono agregando prefijo de país si es necesario
     */
    private function normalizePhonenWithPrefix($phone)
    {
        if ($phone === 'Sistema') $phone = $this->telefonoSistema;
        // Remover caracteres no numéricos
        $phone = preg_replace('/\D+/', '', $phone);

        // Si es un número corto (9 dígitos), asumir que es España (34)
        if (strlen($phone) === 9) {
            return '34' . $phone;
        }

        // Si ya tiene prefijo (11+ dígitos), devolverlo tal cual
        if (strlen($phone) >= 11) {
            return $phone;
        }

        // Si empieza con 0, removerlo y agregar 34
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
            if (strlen($phone) === 9) {
                return '34' . $phone;
            }
        }

        // Por defecto, devolver con prefijo 34
        return '34' . $phone;
    }

    /**
     * Obtiene los datos del expediente (técnico, comercial y teléfono del cliente)
     * GET /admin/WhatsApp/expediente-datos?id={id}
     */
    public function getExpedienteDatosAction(Request $request)
    {
        $id = $request->query->get('id');
        
        if (!$id) {
            return new JsonResponse([
                'error' => 'ID de expediente no proporcionado'
            ], 400);
        }

        $conn = $this->getDoctrine()->getConnection();

        try {
            // Obtener los datos del expediente con el teléfono del cliente
            $sql = 'SELECT e.id_expediente, e.id_tecnico, e.id_comercial, e.id_cliente, u.telefono_movil AS telefono_cliente
                    FROM expediente e
                    LEFT JOIN usuario u ON e.id_cliente = u.id_usuario
                    WHERE e.id_expediente = :id LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('id', (int)$id);
            $stmt->execute();
            $expediente = $stmt->fetch();

            if (!$expediente) {
                return new JsonResponse([
                    'error' => 'Expediente no encontrado'
                ], 404);
            }

            return new JsonResponse([
                'id_expediente' => $expediente['id_expediente'],
                'id_tecnico' => $expediente['id_tecnico'],
                'id_comercial' => $expediente['id_comercial'],
                'id_cliente' => $expediente['id_cliente'],
                'telefono_cliente' => $expediente['telefono_cliente'] ?: 'N/A'
            ], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error al obtener datos del expediente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene expedientes con mensajes para el usuario autenticado (técnico/comercial)
     * GET /admin/WhatsApp/mis-expedientes-con-mensajes
     */
    public function getMisExpedientesConMensajesAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse([
                'error' => 'Usuario no autenticado'
            ], 401);
        }

        $idUsuario = $usuario->getIdUsuario();
        $conn = $this->getDoctrine()->getConnection();

        try {
            // Obtener expedientes donde el usuario es técnico o comercial y tienen mensajes
            $sql = 'SELECT DISTINCT 
                        e.id_expediente,
                        e.id_cliente,
                        c.nombre AS cliente_nombre,
                        c.apellidos AS cliente_apellidos,
                        c.telefono_movil AS cliente_telefono,
                        (SELECT MAX(timestamp) FROM chat_history WHERE id_expediente = e.id_expediente) AS ultimo_mensaje_fecha,
                        (SELECT message FROM chat_history WHERE id_expediente = e.id_expediente ORDER BY timestamp DESC LIMIT 1) AS ultimo_mensaje_texto,
                        (SELECT COUNT(*) FROM chat_history WHERE id_expediente = e.id_expediente) AS total_mensajes
                    FROM expediente e
                    LEFT JOIN usuario c ON e.id_cliente = c.id_usuario
                    WHERE (e.id_tecnico = :usuarioId OR e.id_comercial = :usuarioId)
                    AND e.id_expediente IN (SELECT DISTINCT id_expediente FROM chat_history WHERE id_expediente IS NOT NULL)
                    AND e.estado > 0
                    ORDER BY ultimo_mensaje_fecha DESC';
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('usuarioId', $idUsuario);
            $stmt->execute();
            $expedientes = $stmt->fetchAll();

            return new JsonResponse([
                'success' => true,
                'expedientes' => $expedientes,
                'count' => count($expedientes)
            ], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener expedientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Completa datos faltantes de un expediente
     * POST /API/datos_expediente/{id}
     * Identifica campos faltantes, solicita la información vía WhatsApp y usa IA para procesarla
     */
    public function datosExpedienteAction(Request $request, $id)
    {
        $this->logear('=== INICIO de datosExpedienteAction para expediente ID: ' . $id . ' ===');
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $idExpediente = (int)$id;

        if (!$idExpediente) {
            return new JsonResponse(['error' => 'ID de expediente inválido'], 400);
        }

        $conn = $this->getDoctrine()->getConnection();

        try {
            // 1. Obtener datos del expediente
            $sql = 'SELECT e.*, c.telefono_movil, c.nombre AS cliente_nombre, c.apellidos AS cliente_apellidos, c.nif AS cliente_nif 
                    FROM expediente e
                    LEFT JOIN usuario c ON e.id_cliente = c.id_usuario
                    WHERE e.id_expediente = :idExpediente';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('idExpediente', (int)$idExpediente);
            $stmt->execute();
            $expediente = $stmt->fetch();

            $this->logear('=== Paso 11111111111111111111 ===');

            if (!$expediente) {
                return new JsonResponse(['error' => 'Expediente no encontrado'], 404);
            }

            // ✅ Validar si WhatsApp automático está habilitado
            if (!$expediente['whatsapp_automatico']) {
                $this->logear('⚠️ WhatsApp automático DESACTIVADO para expediente ' . $idExpediente);
                return new JsonResponse([
                    'success' => false,
                    'message' => 'WhatsApp automático está desactivado para este expediente',
                    'expediente_id' => $idExpediente,
                    'whatsapp_automatico' => (bool)$expediente['whatsapp_automatico']
                ], 403);
            }

            // ✅ NUEVO: Validar si YA FUE ENVIADO (evitar duplicados)
            /*if ($expediente['whatsapp_automatico_enviado']) {
                $this->logear('⏭️ YA ENVIADO - Expediente ' . $idExpediente . ' - Intento de re-envío bloqueado');
                return new JsonResponse([
                    'success' => false,
                    'message' => 'WhatsApp automático ya fue enviado para este expediente',
                    'expediente_id' => $idExpediente,
                    'whatsapp_automatico_enviado' => (bool)$expediente['whatsapp_automatico_enviado']
                ], 403);
            }*/

            $this->logear('✓ WhatsApp automático ACTIVO - Procediendo a enviar mensaje para expediente ' . $idExpediente);

            // 2. Obtener datos de la Fase 1 (tipo = 0)
            $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $conn);

            // Validar que obtenerDatosFase1 retornó datos válidos
            if (isset($datosFase1['error'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Error obteniendo Fase 1: ' . $datosFase1['error'],
                    'expediente_id' => $idExpediente
                ], 500);
            }

            // 3. Calcular DINAMICAMENTE cual es la siguiente parte incompleta (no siempre Parte 1)
            $resultadoParte = $this->getIAController()->obtenerProximaParteYCamposFaltantes($idExpediente, $datosFase1);
            $numeroParteActual = $resultadoParte['numero_parte'];
            $numeroParteAnterior = $resultadoParte['numero_parte_anterior'];
            $camposFaltantes = $resultadoParte['campos_faltantes'];
            $mensajeSegmentado = $resultadoParte['mensaje_completo'] ?? '';
            
            // Si no hay parte incompleta, todas están completas
            if ($numeroParteActual === 0 || empty($camposFaltantes)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Expediente completo - Todas las partes han sido completadas',
                    'expediente_id' => $idExpediente,
                    'campos_faltantes' => []
                ], 200);
            }
            
            // Obtener metadatos dinámicamente para los campos faltantes
            $camposRequeridos = array_map(function($campo) {
                return $campo['id_campo_hito'] ?? $campo['campo_id'] ?? 0;
            }, $camposFaltantes);
            
            $metadatosCampos = $this->getIAController()->obtenerMetadatosCampos($camposRequeridos);
            $this->logear('Metadatos cargados para ' . count($metadatosCampos) . ' tipos de campos');
            
            $this->logear('=== Detectada Parte ' . $numeroParteActual . ' incompleta con ' . count($camposFaltantes) . ' campos faltantes ===');

            // 4. Obtener teléfono del cliente
            $phoneDestino = $expediente['telefono_movil'];
            if (!$phoneDestino) {
                return new JsonResponse([
                    'error' => 'No se encontró teléfono del cliente para este expediente'
                ], 400);
            }

            $nombreCliente = $expediente['cliente_nombre'] ?? 'Cliente';
            
            // ✅ Verificar si hay historial reciente de conversación
            $tieneHistorico = $this->tieneConversacionReciente($idExpediente, 10); // Últimos 10 minutos
            $esNuevaParte = ($numeroParteAnterior > 0 && $numeroParteAnterior !== $numeroParteActual); // Cambio de parte
            
            $this->logear('Contexto: tieneHistorico=' . ($tieneHistorico ? 'true' : 'false') . ' | esNuevaParte=' . ($esNuevaParte ? 'true' : 'false'));
            
            // Obtener solo los primeros campos del segmento actual
            $primerSegmento = array_slice($camposFaltantes, 0, 2);
            
            // Construir mensaje contextualizado
            /*if (!$tieneHistorico) {
                // PRIMERA vez: saludar con nombre
                $mensajeUnificado = $this->getIAController()->generarMensajeInicial($nombreCliente);
                $mensajeSegmentadoCampos = $this->getIAController()->generarMensajeSegmentado($primerSegmento);
                $mensajeUnificado = $mensajeUnificado . $mensajeSegmentadoCampos;
            } else {
                // Ya hay conversación: NO saludar, solo mostrar continuación
                if ($esNuevaParte) {
                    // Nueva parte
                    $mensajeUnificado = "¡Perfecto333! Gracias por esos datos. ✓\n\n";
                    $mensajeUnificado .= "📋 Ahora necesitamos que completes esta información:\n\n";
                } else {
                    // Continuación de la misma parte
                    $mensajeUnificado = "Gracias por tu respuesta. ✓\n\n";
                    $mensajeUnificado .= "📋 Necesitamos que completes lo siguiente:\n\n";
                }
                
                // Agregar campos
                foreach ($primerSegmento as $campo) {
                    $nombreCampo = $campo['nombre'] ?? $campo['campo_hito'] ?? 'Campo';
                    $mensajeUnificado .= "* " . $nombreCampo . "\n";
                }
                
                $mensajeUnificado .= "\nCuando 3333 puedas, nos lo haces saber. ¡Muchas gracias! 😊";
            }*/
            $mensajeUnificado = $this->getIAController()->generarMensajeInicial($nombreCliente);
            $mensajeSegmentadoCampos = $this->getIAController()->generarMensajeSegmentado($primerSegmento);
            $mensajeUnificado = $mensajeUnificado . $mensajeSegmentadoCampos;
        
            $mensajes = [
                [
                    'tipo' => 'unificado',
                    'mensaje' => $mensajeUnificado,
                    'campos' => $primerSegmento  // Los primeros 2 campos solicitados
                ]
            ];

            // 4. Enviar mensaje unificado vía WhatsApp
            $phoneOrigenFull = $this->normalizePhonenWithPrefix('Sistema');
            $phoneDestinoFull = $this->normalizePhonenWithPrefix($phoneDestino);
            $fecha = date('Y-m-d');

            $mensajeEnviado = false;
            $respuestaBot = null;
            $this->logear('=== Paso 22222222222222222222222 ===');
            // Enviar el mensaje unificado
            try {
                $hash = $this->generarHashWhatsapp($fecha);
                error_log('Enviando mensaje unificado a: ' . $phoneDestinoFull);
                error_log('Contenido del mensaje: ' . $mensajes[0]['mensaje']);
                
                $botResponse = $this->llamarBotWhatsApp(
                    $phoneOrigenFull,
                    $phoneDestinoFull,
                    $mensajes[0]['mensaje'],
                    $hash,
                    $fecha
                );

                $respuestaBot = $botResponse;
                
                if ($botResponse['success']) {
                    $mensajeEnviado = true;
                    error_log('✓ Mensaje unificado enviado correctamente al cliente');
                    
                    // ⭐️ CRÍTICO: Marcar como enviado en BD para evitar duplicados (SECCIÓN 9)
                    try {
                        $expedienteEntidad = $this->getDoctrine()
                            ->getRepository('AppBundle:Expediente')
                            ->findOneBy(['idExpediente' => (int)$idExpediente]);
                        
                        if ($expedienteEntidad) {
                            // Actualizar el flag de "ya enviado"
                            $expedienteEntidad->setWhatsappAutomaticoEnviado(true);
                            
                            // Guardar cambios en BD
                            $em = $this->getDoctrine()->getManager();
                            $em->persist($expedienteEntidad);
                            $em->flush();
                            
                            $this->logear('✅ whatsapp_automatico_enviado = 1 (actualizado en BD para expediente ' . $idExpediente . ')');
                            
                            // ⭐️ GUARDAR MENSAJE EN chat_history PARA QUE FUTURAS LLAMADAS VEAN EL HISTORIAL
                            try {
                                $messageData = [
                                    'type' => 'text',
                                    'content' => $mensajeUnificado
                                ];
                                $conn->insert('chat_history', [
                                    'id_expediente' => $idExpediente,
                                    'from_phone' => $this->telefonoSistema,
                                    'to_phone' => $this->normalizePhone($phoneDestino),
                                    'message' => json_encode($messageData),
                                    'role' => 'assistant',
                                    'direction' => 'enviado',
                                    'message_type' => 'text',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                                $this->logear('✅ Mensaje guardado en chat_history para expediente ' . $idExpediente);
                            } catch (\Exception $e) {
                                $this->logear('⚠️ Error al guardar en chat_history: ' . $e->getMessage());
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logear('⚠️ Error al marcar como enviado en BD: ' . $e->getMessage());
                    }
                } else {
                    error_log('✗ Error del bot: ' . ($botResponse['message'] ?? 'Desconocido'));
                }
            } catch (\Exception $e) {
                error_log('✗ Excepción al enviar mensaje: ' . $e->getMessage());
                $respuestaBot = ['success' => false, 'error' => $e->getMessage()];
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Campos faltantes identificados y mensaje enviado',
                'expediente_id' => $idExpediente,
                'mensaje_unificado' => $mensajes[0]['mensaje'],
                'campos_solicitados' => $mensajes[0]['campos'],
                'enviado_whatsapp' => $mensajeEnviado,
                'respuesta_bot' => $respuestaBot,
                'telefono_destino' => $phoneDestinoFull
            ], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function obtenerDatosFase1PruebaAction(Request $request, $id)
    {
        $idExpediente = (int)$id;

        if (!$idExpediente) {
            return new JsonResponse([
                'success' => false,
                'error' => 'ID de expediente inválido'
            ], 400);
        }

        try 
        {
            $em = $this->getDoctrine()->getManager();

            // Validar que el expediente existe
            $expediente = $em->getRepository('AppBundle:Expediente')->findOneBy([
                'idExpediente' => $idExpediente
            ]);

            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Expediente no encontrado',
                    'expediente_id' => $idExpediente
                ], 404);
            }

            // Obtain full Fase 1 data
            $conn = $this->getDoctrine()->getConnection();
            $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $conn);

            if (isset($datosFase1['error'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $datosFase1['error'],
                    'expediente_id' => $idExpediente
                ], 500);
            }
            //$hito15 = $datosFase1['fase']['hitos'][0] ?? null; // Solo para prueba, obtener el primer hito
            foreach ($datosFase1['fase']['hitos'] as $hito) 
            {
                if ($hito['id_hito'] == 15) 
                {
                    $camposHito15 = [];
                    foreach ($hito['grupos'] as $grupo) 
                    {
                        foreach ($grupo['campos'] as $campo) 
                        {
                            $camposHito15[] = $campo;
                        }
                    }
                    break;
                }
            }

            return new JsonResponse([
                'success' => true,
                'expediente_id' => $idExpediente,
                'camposHito15' => $camposHito15
            ], 200);
        } 
        catch (\Exception $e) 
        {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener datos de la fase: ' . $e->getMessage(),
                'expediente_id' => $idExpediente
            ], 500);
        }
    }

    /**
     * Obtiene los logs del día actual
     * GET /API/logs
     * @return JsonResponse
     */
    public function misLogsAction()
    {
        try {
            $logDir = dirname(dirname(dirname(__DIR__))) . '/var/logs/';
            $logFile = $logDir . 'whatsapp_' . date('Y-m-d') . '.log';
            
            if (!file_exists($logFile)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No hay logs para hoy',
                    'logs' => []
                ], 200);
            }
            
            $contenido = file_get_contents($logFile);
            $lineas = explode("\n", trim($contenido));
            
            $logs = array_map(function($linea) {
                return trim($linea);
            }, array_filter($lineas));
            
            return new JsonResponse([
                'success' => true,
                'fecha' => date('Y-m-d'),
                'archivo' => basename($logFile),
                'cantidad' => count($logs),
                'logs' => $logs
            ], 200);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construye un mensaje personalizado para solicitar un campo específico
     * @param string $nombreCliente Nombre del cliente
     * @param string $nombreCampo Nombre del campo
     * @param int $tipo Tipo de campo
     * @return string Mensaje personalizado
     */
    private function construirMensajeParaCampo($nombreCliente, $nombreCampo, $tipo)
    {
        // Extraer primer nombre del cliente
        $nombres = explode(' ', trim($nombreCliente));
        $primerNombre = $nombres[0] ?? 'Cliente';

        // Normalizar nombre de campo para búsqueda
        $nombreLower = strtolower($nombreCampo);

        // Mensajes por tipo de campo
        switch ($tipo) {
            case 6: // Fecha
                if (strpos($nombreLower, 'nacimiento') !== false) {
                    return "Hola $primerNombre, necesitamos tu fecha de nacimiento para completar tu expediente. ¿Puedes compartirla?";
                } else if (strpos($nombreLower, 'fecha') !== false) {
                    return "Hola $primerNombre, necesitamos que nos indiques la fecha para completar tu expediente. ¿Cuál es?";
                }
                break;

            case 4: // Teléfono
                return "Hola $primerNombre, ¿cuál es tu número de teléfono para completar tu expediente?";

            case 5: // Email
                return "Hola $primerNombre, ¿cuál es tu correo electrónico para completar tu expediente?";

            case 3: // DNI/Passport
                if (strpos($nombreLower, 'dni') !== false || strpos($nombreLower, 'nif') !== false) {
                    return "Hola $primerNombre, necesitamos tu DNI/NIF para completar tu expediente. ¿Puedes compartirlo?";
                } else if (strpos($nombreLower, 'passport') !== false || strpos($nombreLower, 'pasaporte') !== false) {
                    return "Hola $primerNombre, necesitamos tu número de pasaporte para completar tu expediente. ¿Puedes compartirlo?";
                }
                break;

            case 1: // Texto
                if (strpos($nombreLower, 'ciudad') !== false || strpos($nombreLower, 'residencia') !== false) {
                    return "Hola $primerNombre, ¿en qué ciudad resides actualmente? Necesitamos esta información para tu expediente.";
                } else if (strpos($nombreLower, 'nombre') !== false && strpos($nombreLower, 'completo') !== false) {
                    return "Hola $primerNombre, necesitamos tu nombre completo para completar tu expediente. ¿Cuál es?";
                } else if (strpos($nombreLower, 'apellido') !== false) {
                    return "Hola $primerNombre, necesitamos tus apellidos para completar tu expediente. ¿Cuáles son?";
                } else if (strpos($nombreLower, 'empresa') !== false || strpos($nombreLower, 'empleador') !== false) {
                    return "Hola $primerNombre, ¿en qué empresa trabajas? Necesitamos esta información para tu expediente.";
                }
                break;
        }

        // Mensaje genérico por defecto
        return "Hola $primerNombre, necesitamos que nos proporciones: $nombreCampo para completar tu expediente. ¿Puedes compartirlo?";
    }

    /**
     * Construye un mensaje unificado y claro para todos los campos faltantes
     * @param string $nombreCliente Nombre del cliente
     * @param array $camposFaltantes Array de campos faltantes
     * @return string Mensaje unificado
     */
    private function tieneConversacionReciente(int $idExpediente, int $minutosAtras = 10): bool
    {
        try {
            $conn = $this->getDoctrine()->getConnection();
            $fechaLimite = date('Y-m-d H:i:s', time() - ($minutosAtras * 60));
            
            $sql = 'SELECT COUNT(*) as total FROM chat_history 
                    WHERE id_expediente = :idExp AND timestamp > :fechaLimite';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('idExp', $idExpediente);
            $stmt->bindValue('fechaLimite', $fechaLimite);
            $stmt->execute();
            $resultado = $stmt->fetch();
            
            return (int)($resultado['total'] ?? 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Construye un mensaje unificado para todos los campos faltantes
     * Mensaje simple y directo, sin placeholders ni reemplazos
     * 
     * @param string $nombreCliente Nombre del cliente
     * @param array $camposFaltantes Array de campos faltantes
     * @param bool $tieneHistorico True si hay conversacion reciente
     * @param bool $esNuevaParte True si es una nueva parte (no continuacion de la misma)
     * @return string Mensaje completo listo para enviar
     */
    private function construirMensajeUnificado($nombreCliente, $camposFaltantes, $tieneHistorico = false, $esNuevaParte = false)
    {
        // Extraer primer nombre del cliente
        $nombres = explode(' ', trim($nombreCliente));
        $primerNombre = $nombres[0] ?? 'Cliente';

        // Contar campos
        $totalCampos = count($camposFaltantes);
        
        if ($totalCampos === 0) {
            return "¡Ya tienes todo completado! Muchas gracias por tu información.";
        }

        // Construir lista de campos de forma legible, incluyendo opciones si existen
        $listaCampos = [];
        foreach ($camposFaltantes as $campo) {
            $texto = "• " . $campo['nombre'];
            
            // Si el campo tiene opciones, agregarlas
            if (isset($campo['id_campo_hito'])) {
                $opciones = $this->obtenerOpcionesFormateadas($campo['id_campo_hito']);
                if ($opciones) {
                    $texto .= $opciones;
                }
            }
            
            $listaCampos[] = $texto;
        }
        $textoLista = implode("\n", $listaCampos);

        // Construir mensaje según contexto - ORDEN CORRECTO: saludo → contexto → campos → cierre
        if (!$tieneHistorico) {
            // PRIMERA SOLICITUD
            $mensaje = "¡Hola $primerNombre! 👋\n\n";
            $mensaje .= "Te escribo desde Hipotea para dar seguimiento a tu trámite de hipoteca que iniciaste con nosotros.\n\n";
            $mensaje .= "📋 Para poder avanzar, necesitamos que completes esta información:\n\n";
            $mensaje .= $textoLista . "\n\n";
            $mensaje .= "Cuando puedas, nos lo haces saber. ¡Muchas gracias! 😊";
        } elseif ($esNuevaParte) {
            // NUEVA PARTE - cambio a Parte 2, 3, etc
            $mensaje = "¡Perfecto! Gracias por compartir esos datos. ✓\n\n";
            $mensaje .= "📋 Ahora necesitamos que completes esta información:\n\n";
            $mensaje .= $textoLista . "\n\n";
            $mensaje .= "Cuando tengas un momento. ¡Muchas gracias! 😊";
        } else {
            // CONTINUACIÓN MISMA PARTE - más campos de la misma parte
            $mensaje = "Gracias por tu respuesta. ✓\n\n";
            $mensaje .= "📋 Necesitamos que completes lo siguiente:\n\n";
            $mensaje .= $textoLista . "\n\n";
            $mensaje .= "¡Muchas gracias por tu ayuda! 😊";
        }

        $this->logear("✓ Mensaje construido: " . strlen($mensaje) . " caracteres | tieneHistorico=$tieneHistorico | esNuevaParte=$esNuevaParte");
        return $mensaje;
    }

    /**
     * Verifica si un usuario tiene PilotoAutomatico activo en la tabla WhatsappSenders
     * @param string $telefono Teléfono del usuario (normalizado sin prefijo)
     * @return bool True si PilotoAutomatico está activo, false en caso contrario
     */
    private function verificarPilotoAutomatico($telefono): bool
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $conn = $em->getConnection();
            
            // Preparar variantes del teléfono para la búsqueda
            $variants = array_unique(array_filter([
                $telefono,
                ltrim($telefono, '0'),
                (strlen($telefono) > 9 ? substr($telefono, -9) : null)
            ]));
            
            if (count($variants) === 0) {
                return false;
            }
            
            // Crear placeholders para la búsqueda IN
            $placeholders = [];
            $params = [];
            foreach ($variants as $i => $v) {
                $ph = ':p' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $v;
            }
            
            // Buscar en WhatsappSenders por teléfono
            // Nota: Ajusta el nombre de la columna según tu esquema actual (puede ser 'telefono', 'phone', etc.)
            $sql = 'SELECT PilotoAutomatico FROM WhatsappSenders  
                    WHERE telefono IN (' . implode(',', array_keys($params)) . ') 
                    ORDER BY FechaUltimaInteraccion DESC  
                    LIMIT 1';
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $ph => $val) {
                $stmt->bindValue(trim($ph, ':'), $val);
            }
            $stmt->execute();
            $result = $stmt->fetch();
            
            // Retornar true si PilotoAutomatico es 1 o true
            return $result && ($result['PilotoAutomatico'] == 1 || $result['PilotoAutomatico'] === true);
            
        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('verificarPilotoAutomatico error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Guarda los datos extraídos de un mensaje en el expediente
     * Inserta o actualiza registros en CampoHitoExpediente usando Doctrine ORM
     * 
     * @param int $idExpediente ID del expediente
     * @param array $datosExtraidos Array con datos extraídos (resultado de analizarMensajeParaDatos)
     * @param string $telefonoOrigen Teléfono del cliente que envió el mensaje
     * @param string $nombreCliente Nombre del cliente para auto-poblar campo 192
     * @param string $nifCliente NIF/DNI del cliente para auto-poblar campo 194
     * @return array Resultado con información de campos guardados
     */
    private function guardarDatosEnExpediente(int $idExpediente, array $datosExtraidos, string $telefonoOrigen, string $nombreCliente = '', string $nifCliente = '')
    {
        $this->logear('=== INICIO guardarDatosEnExpediente ===');
        $this->logear('ID Expediente: ' . $idExpediente);
        $this->logear('Nombre Cliente: ' . $nombreCliente);
        $this->logear('Datos a guardar: ' . json_encode($datosExtraidos['campos_encontrados']));
        
        $conn = $this->getDoctrine()->getConnection();
        $camposGuardados = 0;
        $camposError = 0;

        // Agregar campo 192 (Nombre y Apellidos) y campo 194 (DNI) si no vienen en los datos extraídos
        if (is_array($datosExtraidos['campos_encontrados'])) {
            // Verificar si ya existen en los datos extraídos
            $campo192Existe = false;
            $campo194Existe = false;
            foreach ($datosExtraidos['campos_encontrados'] as $campo) {
                if (isset($campo['campo_id']) && $campo['campo_id'] == 192) {
                    $campo192Existe = true;
                }
                if (isset($campo['campo_id']) && $campo['campo_id'] == 194) {
                    $campo194Existe = true;
                }
            }
            
            // Agregar campo 192 si no existe y el nombre no está vacío
            if (!$campo192Existe && !empty($nombreCliente)) {
                $datosExtraidos['campos_encontrados'][] = [
                    'tipo' => 'nombre_apellidos',
                    'nombre_campo' => 'Nombre y Apellidos',
                    'campo_id' => 192,
                    'valor' => $nombreCliente
                ];
                $this->logear('✓ Campo 192 agregado automáticamente: ' . $nombreCliente);
            }
            
            // Agregar campo 194 si no existe y el NIF no está vacío
            if (!$campo194Existe && !empty($nifCliente)) {
                $datosExtraidos['campos_encontrados'][] = [
                    'tipo' => 'dni',
                    'nombre_campo' => 'DNI, NIE, Tarjeta Residencia',
                    'campo_id' => 194,
                    'valor' => $nifCliente
                ];
                $this->logear('✓ Campo 194 agregado automáticamente: ' . $nifCliente);
            }
        }

        if (empty($datosExtraidos['campos_encontrados'])) {
            $this->logear('✗ No hay campos para guardar');
            return ['exito' => false, 'guardados' => 0];
        }

        try {
            $timestamp = date('Y-m-d H:i:s');
            
            // Obtener mapeo de opciones para campos que las tienen
            $opcionesMapeo = $this->obtenerOpcionesCampos();
            
            // Procesar cada campo encontrado
            foreach ($datosExtraidos['campos_encontrados'] as $campo) {
                try {
                    $idCampoHito = $campo['campo_id'];
                    $valor = trim($campo['valor']);
                    $nombreCampo = $campo['nombre_campo'];
                    $idOpcional = null; // Campo para almacenar id_opciones_campo

                    $this->logear("→ Guardando: {$nombreCampo} (ID: {$idCampoHito}) = '{$valor}'");

                    if (empty($valor)) {
                        $this->logear('✗ Valor vacío, saltando');
                        continue;
                    }
                    
                    // MAPEO DE OPCIONES: Si el campo tiene opciones configuradas, mapear el valor
                    if (isset($opcionesMapeo[$idCampoHito])) {
                        $valorNormalizado = strtolower(trim($valor));
                        $valorMapeado = null;
                        
                        foreach ($opcionesMapeo[$idCampoHito] as $opcionUsuario => $opcionBD) {
                            if (strpos($valorNormalizado, strtolower($opcionUsuario)) !== false) {
                                $valorMapeado = $opcionBD;
                                $idOpcional = $valorMapeado; // Guardar el ID de la opción en id_opciones_campo
                                $this->logear("  → Mapeado: '{$valor}' → opción ID '{$valorMapeado}'");
                                $this->logear("  → Guardando en id_opciones_campo");
                                // NO sobrescribir $valor - mantener el valor original
                                break;
                            }
                        }
                        
                        if (!$valorMapeado) {
                            $this->logear("  ⚠ Valor '{$valor}' no coincide con opciones. Guardando como valor texto.");
                        }
                    }

                    // Usar SQL directo para máxima compatibilidad
                    // Primero verificar si existe y obtener su valor actual
                    $sql = 'SELECT id_campo_hito_expediente, valor, id_opciones_campo FROM campo_hito_expediente 
                            WHERE id_expediente = :idExp AND id_campo_hito = :idCampo LIMIT 1';
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue('idExp', $idExpediente);
                    $stmt->bindValue('idCampo', $idCampoHito);
                    $stmt->execute();
                    $resultado = $stmt->fetch();

                    if ($resultado) {
                        // El campo ya existe - verificar si ya tiene valor
                        $valorActual = trim($resultado['valor'] ?? '');
                        $tieneOpcional = !empty($resultado['id_opciones_campo']);
                        
                        // Detectar si el valor es corrupto (formato: campo_hito_XXXX_opcion_YYYY)
                        $esValorCorrupto = preg_match('/^campo_hito_\d+_opcion_\d+$/', $valorActual);
                        
                        // Si tiene opción asignada (válida), NO actualizar
                        if ($tieneOpcional && !$esValorCorrupto) {
                            $this->logear('⚠ CAMPO YA TIENE OPCIÓN ASIGNADA: ' . $nombreCampo . ' = opción ID: ' . $resultado['id_opciones_campo'] . ' (no se actualiza)');
                            continue;
                        }
                        
                        // Si tiene valor válido (no corrupto), NO actualizar
                        if (!empty($valorActual) && !$esValorCorrupto) {
                            $this->logear('⚠ CAMPO YA TIENE VALOR: ' . $nombreCampo . ' = "' . $valorActual . '" (no se actualiza)');
                            continue;
                        }
                        
                        // Si el valor es corrupto, permitir sobrescribir
                        if ($esValorCorrupto) {
                            $this->logear('⚠ VALOR CORRUPTO DETECTADO: ' . $valorActual . ' - SOBRESCRIBIENDO CON: ' . $valor);
                        }
                        
                        // El campo existe pero está vacío, proceder a actualizar
                        $sqlUpdate = 'UPDATE campo_hito_expediente 
                                      SET valor = :valor, id_opciones_campo = :idOpcional, fecha_modificacion = :timestamp
                                      WHERE id_expediente = :idExp AND id_campo_hito = :idCampo';
                        $stmt = $conn->prepare($sqlUpdate);
                        $stmt->bindValue('valor', $valor);
                        $stmt->bindValue('idOpcional', $idOpcional);
                        $stmt->bindValue('timestamp', $timestamp);
                        $stmt->bindValue('idExp', $idExpediente);
                        $stmt->bindValue('idCampo', $idCampoHito);
                        $stmt->execute();
                        $this->logear('✓ ACTUALIZADO: ' . $nombreCampo);
                    } else {
                        // Insertar
                        $sqlInsert = 'INSERT INTO campo_hito_expediente 
                                      (id_expediente, id_campo_hito, valor, id_opciones_campo, fecha_modificacion, obligatorio)
                                      VALUES (:idExp, :idCampo, :valor, :idOpcional, :timestamp, 0)';
                        $stmt = $conn->prepare($sqlInsert);
                        $stmt->bindValue('idExp', $idExpediente);
                        $stmt->bindValue('idCampo', $idCampoHito);
                        $stmt->bindValue('valor', $valor);
                        $stmt->bindValue('idOpcional', $idOpcional);
                        $stmt->bindValue('timestamp', $timestamp);
                        $stmt->execute();
                        $this->logear('✓ INSERTADO: ' . $nombreCampo);
                    }

                    $camposGuardados++;

                } catch (\Exception $e) {
                    $this->logear('✗ Error: ' . $e->getMessage());
                    $camposError++;
                }
            }

            $this->logear("=== FIN guardarDatosEnExpediente: {$camposGuardados} guardados, {$camposError} errores ===");
            
            return [
                'exito' => $camposGuardados > 0,
                'guardados' => $camposGuardados,
                'errores' => $camposError
            ];

        } catch (\Exception $e) {
            $this->logear('✗ EXCEPCIÓN FATAL: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return [
                'exito' => false,
                'guardados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Webhook para recibir eventos de WhatsApp (Baileys ↔ Symfony)
     * POST /API/webhook_whatsapp
     * 
     * Eventos soportados:
     * - PHONE_CONNECTED: Validar teléfono y devolver GestorData
     * - SESSION_CREATED: Guardar sesión activa
     * - MESSAGE: Procesar mensaje entrante
     * - DISCONNECTED: Marcar sesión como inactiva
     */
    public function webhookWhatsappAction(Request $request): JsonResponse
    {
        try {
            // Obtener datos del webhook
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'error' => 'Invalid JSON'
                ], 400);
            }

            $status = $data['status'] ?? null;

            // Registrar webhook en log
            $this->logear('🔔 WEBHOOK BAILEYS: ' . ($status ?? 'unknown') . ' | ' . json_encode($data));

            // Procesar diferentes tipos de eventos
            switch ($status) {
                case 'PHONE_CONNECTED':
                    return $this->handlePhoneConnected($data);
                    
                case 'SESSION_CREATED':
                    return $this->handleSessionCreated($data);
                    
                case 'MESSAGE':
                    return $this->handleMessage($data);
                    
                case 'DISCONNECTED':
                    return $this->handleDisconnected($data);
                    
                default:
                    $this->logear('⚠️ Status desconocido: ' . ($status ?? 'null'));
                    return new JsonResponse([
                        'error' => 'Unknown status'
                    ], 400);
            }

        } catch (\Exception $e) {
            $this->logear('❌ ERROR en webhook_whatsapp: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return new JsonResponse([
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * PHONE_CONNECTED: Validar teléfono y devolver datos del gestor
     * 
     * Request: {status: "PHONE_CONNECTED", sessionId, phone}
     * Response: {IdGestor, IdAgencia, NivelAcceso, Nombre, Apellidos, ...}
     */
    private function handlePhoneConnected(array $data): JsonResponse
    {
        try {
            $phone = $data['phone'] ?? null;
            $sessionId = $data['sessionId'] ?? null;

            if (!$phone) {
                return new JsonResponse([
                    'error' => 'phone field required'
                ], 400);
            }

            // Normalizar el teléfono y generar variantes
            $digits = $this->normalizePhone($phone);
            $variants = array_values(array_unique(array_filter([
                $digits,                          // tal cual
                ltrim($digits, '0'),              // sin ceros a la izquierda
                (strlen($digits) > 9 ? substr($digits, -9) : null), // últimos 9 dígitos
            ])));

            if (!$variants) {
                return new JsonResponse([
                    'error' => 'Invalid phone format'
                ], 400);
            }

            $conn = $this->getDoctrine()->getConnection();
            $placeholders = implode(',', array_fill(0, count($variants), '?'));

            // Consulta para obtener datos del gestor por teléfono
            $sql = "SELECT
                        u.id_usuario as IdGestor,
                        u.id_inmobiliaria as IdAgencia,
                        u.role as NivelAcceso,
                        u.nombre as Nombre,
                        u.apellidos as Apellidos,
                        (SELECT ws.SyncConversaciones FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS SyncConversaciones,
                        (SELECT ws.AutomatizacionesWhatsapp FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS AutomatizacionesWhatsapp,
                        (SELECT ws.CrucesAutomaticos FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS CrucesAutomaticos,
                        (SELECT ws.CrucesAutomaticosRGPDExterna FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS CrucesAutomaticosRGPDExterna,
                        (SELECT ws.PilotoAutomatico FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS PilotoAutomatico,
                        (SELECT ws.RecordatoriosVisitas FROM WhatsappSenders ws WHERE ws.IdUsuario = u.id_usuario AND ws.IdAgencia <=> u.id_inmobiliaria ORDER BY ws.FechaUltimaInteraccion DESC LIMIT 1) AS RecordatoriosVisitas
                    FROM usuario u
                    WHERE u.estado = 1
                    AND u.telefono_movil IN ($placeholders)
                    ORDER BY u.id_usuario ASC
                    LIMIT 1";

            $stmt = $conn->prepare($sql);

            // Bind posicional (1-indexed)
            foreach ($variants as $i => $v) {
                $stmt->bindValue($i + 1, $v);
            }

            // Ejecutar consulta
            $exec = $stmt->execute();
            if ($exec instanceof \Doctrine\DBAL\Result) {
                $gestor = $exec->fetchAssociative();
            } else {
                $gestor = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            if (!$gestor || !$gestor['IdGestor']) {
                $this->logear('⚠️ PHONE_CONNECTED: Gestor no encontrado para: ' . $phone);
                // Node.js espera IdGestor null para rechazar
                return new JsonResponse([
                    'IdGestor' => null
                ], 200);
            }

            $this->logear('✓ PHONE_CONNECTED: Gestor encontrado - ' . $gestor['IdGestor'] . ' (' . $gestor['Nombre'] . ')');

            return new JsonResponse($gestor, 200);

        } catch (\Exception $e) {
            $this->logear('❌ Error en PHONE_CONNECTED: ' . $e->getMessage());
            return new JsonResponse([
                'IdGestor' => null,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * SESSION_CREATED: Guardar sesión activa con datos del gestor
     * 
     * Request: {status: "SESSION_CREATED", sessionId, phone, IdGestor, IdAgencia, ...}
     * Response: {}
     */
    private function handleSessionCreated(array $data): JsonResponse
    {
        try {
            $phone = $data['phone'] ?? null;
            $sessionId = $data['sessionId'] ?? null;
            $sessionName = $data['sessionName'] ?? null;
            $idGestor = $data['IdGestor'] ?? null;

            if (!$phone || !$sessionId) {
                return new JsonResponse([], 400);
            }

            $em = $this->getDoctrine()->getManager();
            $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
            
            $sender = null;
            
            // Primero intentar buscar por sessionName (nuevo flujo)
            if ($sessionName) {
                $sender = $senderRepo->findOneBy(['sessionName' => $sessionName]);
            }
            
            // Si no lo encuentra por sessionName, buscar por teléfono (flujo antiguo)
            if (!$sender) {
                $phoneNorm = $this->normalizePhone($phone);
                $sender = $senderRepo->findOneBy(['telefono' => $phoneNorm]);
            }

            if (!$sender) {
                // Crear nuevo sender si no existe
                $senderClass = 'AppBundle\Entity\WhatsappSender';
                $sender = new $senderClass();
                $sender->setTelefono($this->normalizePhone($phone));
                $sender->setVersion(1);
                $em->persist($sender);
            }

            // Actualizar datos de sesión
            $sender->setSessionId($sessionId);
            $sender->setSessionName($sessionName);
            $sender->setTelefono($this->normalizePhone($phone));
            $sender->setImagenQR(null); // Conexión exitosa
            $sender->setIdUsuario($idGestor);
            $sender->setFechaUltimaInteraccion(new \DateTime());

            // Guardar datos opcionales si vienen en la request
            if (isset($data['IdAgencia'])) {
                $sender->setIdAgencia($data['IdAgencia']);
            }
            if (isset($data['AutomatizacionesWhatsapp'])) {
                $sender->setAutomatizacionesWhatsapp($data['AutomatizacionesWhatsapp']);
            }
            if (isset($data['SyncConversaciones'])) {
                $sender->setSyncConversaciones($data['SyncConversaciones']);
            }
            if (isset($data['CrucesAutomaticos'])) {
                $sender->setCrucesAutomaticos($data['CrucesAutomaticos']);
            }
            if (isset($data['RecordatoriosVisitas'])) {
                $sender->setRecordatoriosVisitas($data['RecordatoriosVisitas']);
            }
            if (isset($data['PilotoAutomatico'])) {
                $sender->setPilotoAutomatico($data['PilotoAutomatico']);
            }

            $em->flush();

            $this->logear('✓ SESSION_CREATED: ' . $phone . ' [SID: ' . $sessionId . '] [SessionName: ' . ($sessionName ?? 'N/A') . ']');

            return new JsonResponse([], 200);

        } catch (\Exception $e) {
            $this->logear('❌ Error en SESSION_CREATED: ' . $e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * MESSAGE: Procesar mensaje entrante desde WhatsApp
     * 
     * Request: {status: "MESSAGE", sessionId, from, body, type}
     * Response: {}
     * 
     * Tipos: text, image, document, audio, video, other
     */
    private function handleMessage(array $data): JsonResponse
    {
        try {
            $sessionId = $data['sessionId'] ?? null;
            $from = $data['from'] ?? null;
            $to = $data['to'] ?? null;  // Puede venir del webhook o ser deducido
            $body = is_scalar($data['body'] ?? null) ? (string) $data['body'] : '';
            $type = $data['type'] ?? 'text';

            $this->logear("📨 WEBHOOK MESSAGE RAW: from={$from}, to={$to}, sessionId={$sessionId}, body=" . substr($body, 0, 30));

            if (!$sessionId || !$from) {
                return new JsonResponse([], 400);
            }

            $em = $this->getDoctrine()->getManager();
            $conn = $em->getConnection();

            // Normalizar teléfono del remitente
            $fromNorm = $this->normalizePhone($from);
            $fromLocal = (strlen($fromNorm) > 9) ? substr($fromNorm, -9) : $fromNorm;

            // Normalizar teléfono del destinatario (si viene)
            $toNorm = null;
            $toLocal = null;
            if ($to) {
                $toNorm = $this->normalizePhone($to);
                $toLocal = (strlen($toNorm) > 9) ? substr($toNorm, -9) : $toNorm;
            }

            // Buscar la sesión por sessionId para obtener el teléfono del comercial
            $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
            $qb = $senderRepo->createQueryBuilder('ws');
            $sender = $qb->where('ws.sessionId = :sessionId')
                ->setParameter('sessionId', $sessionId)
                ->getQuery()
                ->getOneOrNullResult();

            // Si no encuentra por sessionId, buscar por teléfono
            if (!$sender && $toLocal) {
                $this->logear("⚠️ Sesión no encontrada por sessionId, buscando por teléfono {$toLocal}");
                $qb2 = $senderRepo->createQueryBuilder('ws');
                $sender = $qb2->where('ws.telefono LIKE :tel')
                    ->setParameter('tel', '%' . $toLocal . '%')
                    ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($sender) {
                    $this->logear("✓ Sesión encontrada por teléfono: " . $sender->getTelefono());
                }
            }

            // Determinar si el mensaje es enviado o recibido
            $direction = 'recibido';      // Default (mensaje del cliente)
            $role = 'user';               // Default
            $fromPhone = $fromLocal;      // Quién envía
            $toPhone = $toLocal;          // Quién recibe (del webhook)
            $idExpediente = null;         // Id del expediente asociado
            $idCliente = null;            // Id del cliente

            if ($sender) {
                // Obtener teléfono del comercial
                $telefonoComercial = $sender->getTelefono();
                $telefonoComercialLocal = (strlen($telefonoComercial) > 9) ? substr($telefonoComercial, -9) : $telefonoComercial;

                // Si el mensaje viene del teléfono del comercial, es ENVIADO
                if ($fromLocal === $telefonoComercialLocal) {
                    $direction = 'enviado';   // Mensaje enviado por el comercial
                    $role = 'assistant';      // El comercial es asistente
                    $this->logear("✓ Mensaje ENVIADO por comercial: {$fromLocal} → {$toLocal}");
                } else {
                    // Mensaje RECIBIDO de un cliente
                    $this->logear("✓ Mensaje RECIBIDO de cliente: {$fromLocal} → {$telefonoComercialLocal}");
                    if (!$toPhone) {
                        $toPhone = $telefonoComercialLocal;
                    }
                }
            } else {
                // No encontró sesión: usar lo que viene del webhook
                $this->logear("⚠️ No se encontró sesión para {$fromLocal}, usando valores del webhook");
            }

            // Buscar expediente asociado al mensaje
            if (!$idExpediente && $sender) {
                if ($direction === 'enviado') {
                    // MENSAJE ENVIADO: Buscar por to_phone (cliente destino)
                    $this->logear("🔍 Buscando expediente ENVIADO para to={$toLocal}, técnico={$sender->getIdUsuario()}");
                    
                    $sqlBuscaExp = "SELECT e.id_expediente FROM expediente e 
                                   INNER JOIN usuario u_cliente ON e.id_cliente = u_cliente.id_usuario
                                   WHERE u_cliente.telefono_movil LIKE :toPhone
                                   AND (e.id_comercial = :tecnicoId OR e.id_tecnico = :tecnicoId)
                                   AND e.estado > 0
                                   ORDER BY e.id_expediente DESC LIMIT 1";
                    
                    $stmtBuscaExp = $conn->prepare($sqlBuscaExp);
                    $stmtBuscaExp->execute([
                        ':toPhone' => '%' . $toLocal . '%',
                        ':tecnicoId' => $sender->getIdUsuario()
                    ]);
                    $expResult = $stmtBuscaExp->fetch();
                    
                    if ($expResult && $expResult['id_expediente']) {
                        $idExpediente = $expResult['id_expediente'];
                        $this->logear("✓ Expediente encontrado (ENVIADO): {$idExpediente}");
                    }
                } else {
                    // MENSAJE RECIBIDO: Buscar por from_phone (cliente remitente)
                    $this->logear("🔍 Buscando expediente RECIBIDO para from={$fromLocal}, técnico={$sender->getIdUsuario()}");
                    
                    $sqlBuscaExp = "SELECT e.id_expediente FROM expediente e 
                                   INNER JOIN usuario u_cliente ON e.id_cliente = u_cliente.id_usuario
                                   WHERE u_cliente.telefono_movil LIKE :fromPhone
                                   AND (e.id_comercial = :tecnicoId OR e.id_tecnico = :tecnicoId)
                                   AND e.estado > 0
                                   ORDER BY e.id_expediente DESC LIMIT 1";
                    
                    $stmtBuscaExp = $conn->prepare($sqlBuscaExp);
                    $stmtBuscaExp->execute([
                        ':fromPhone' => '%' . $fromLocal . '%',
                        ':tecnicoId' => $sender->getIdUsuario()
                    ]);
                    $expResult = $stmtBuscaExp->fetch();
                    
                    if ($expResult && $expResult['id_expediente']) {
                        $idExpediente = $expResult['id_expediente'];
                        $this->logear("✓ Expediente encontrado (RECIBIDO): {$idExpediente}");
                    } else {
                        // Si no encuentra con técnico, buscar solo por cliente
                        $this->logear("⚠️ No encontrado con técnico, buscando solo por cliente...");
                        $sqlBuscaExp2 = "SELECT e.id_expediente FROM expediente e 
                                       INNER JOIN usuario u_cliente ON e.id_cliente = u_cliente.id_usuario
                                       WHERE u_cliente.telefono_movil LIKE :fromPhone
                                       AND e.estado > 0
                                       ORDER BY e.id_expediente DESC LIMIT 1";
                        
                        $stmtBuscaExp2 = $conn->prepare($sqlBuscaExp2);
                        $stmtBuscaExp2->execute([
                            ':fromPhone' => '%' . $fromLocal . '%'
                        ]);
                        $expResult2 = $stmtBuscaExp2->fetch();
                        
                        if ($expResult2 && $expResult2['id_expediente']) {
                            $idExpediente = $expResult2['id_expediente'];
                            $this->logear("✓ Expediente encontrado (solo cliente): {$idExpediente}");
                        }
                    }
                }
            }

            // Guardar mensaje en tabla de mensajes
            $now = new \DateTime();
            
            // Validación final: asegurar que toPhone no sea null ni igual a fromPhone
            if (!$toPhone) {
                $this->logear("⚠️ toPhone vacío para mensaje de {$fromPhone}");
            }
            if ($toPhone === $fromPhone) {
                $this->logear("⚠️ toPhone igual a fromPhone: {$fromPhone}, intentando corregir...");
                $toPhone = null; // Dejar null si no se puede determinar
            }

            // Para mensajes de imagen recibidos: descargar y convertir a base64
            $messageToSave = $body;
            if ($type === 'image') {
                $imagePayload = $this->extractIncomingWhatsappImagePayload($data);
                $imageMimeType = $imagePayload['mime_type'] ?? 'image/jpeg';
                $savedImage = null;

                if (!empty($imagePayload['base64'])) {
                    $this->logear('📥 Guardando imagen recibida en base64 desde webhook WhatsApp');
                    $savedImage = $this->saveWhatsappImageFromBase64($imagePayload['base64'], $imageMimeType);
                }

                if ($savedImage === null && !empty($imagePayload['url'])) {
                    $this->logear("📥 Descargando imagen desde URL: " . substr($imagePayload['url'], 0, 80) . "...");
                    $savedImage = $this->downloadMediaToWhatsappUpload($imagePayload['url']);
                }

                if ($savedImage !== null) {
                    $messageToSave = json_encode([
                        'type'      => 'image',
                        'filepath'  => $savedImage['filepath'],
                        'mime_type' => $savedImage['mime_type'],
                        'text'      => $imagePayload['text'],
                    ]);
                    $this->logear('✓ Imagen descargada y guardada en uploads: ' . $savedImage['filepath']);
                } else {
                    // Si falla la descarga, guardar la URL como referencia
                    $messageToSave = json_encode([
                        'type'      => 'image',
                        'filepath'  => null,
                        'mime_type' => $imageMimeType,
                        'url'       => $imagePayload['url'],
                        'text'      => $imagePayload['text'],
                    ]);
                    $this->logear('⚠️ No se pudo guardar la imagen en uploads; revisar si Baileys está enviando solo una URL temporal');
                }
            }

            if ($direction === 'enviado' && $this->hasRecentOutgoingMessageDuplicate($conn, $idExpediente, $fromPhone, $toPhone, $type, $messageToSave)) {
                $this->logear('⚠️ Se omitió un eco duplicado del webhook para expediente ' . ($idExpediente ?? 'null') . ' y teléfono ' . $fromPhone);
                return new JsonResponse([], 200);
            }

            $conn->insert('chat_history', [
                'id_expediente' => $idExpediente,   // ✅ Id del expediente (si se encuentra)
                'from_phone' => $fromPhone,         // ✅ Quién envía
                'to_phone' => $toPhone,             // ✅ Quién recibe
                'message' => $messageToSave,
                'role' => $role,
                'direction' => $direction,
                'message_type' => $type,
                'timestamp' => $now
            ], [
                'timestamp' => 'datetime'
            ]);

            $this->logear('✓ MESSAGE GUARDADO: from=' . $fromPhone . ', to=' . $toPhone . ', direction=' . $direction . ', expediente=' . $idExpediente . ' | ' . substr($body, 0, 50));

            return new JsonResponse([], 200);

        } catch (\Exception $e) {
            $this->logear('❌ Error en MESSAGE: ' . $e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * Descarga un archivo multimedia desde una URL y lo devuelve en base64.
     * Retorna null si la descarga falla o el archivo es demasiado grande.
     */
    private function downloadMediaAsBase64(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HipoteaBot/1.0)',
                // Limitar a 10 MB
                CURLOPT_BUFFERSIZE     => 1024 * 1024,
            ]);

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || $httpCode !== 200 || !$raw) {
                $this->logear("⚠️ downloadMediaAsBase64: HTTP={$httpCode}, error={$curlError}");
                return null;
            }

            // Rechazar archivos > 10 MB
            if (strlen($raw) > 10 * 1024 * 1024) {
                $this->logear("⚠️ downloadMediaAsBase64: archivo demasiado grande (" . strlen($raw) . " bytes)");
                return null;
            }

            return base64_encode($raw);

        } catch (\Exception $e) {
            $this->logear("❌ downloadMediaAsBase64 excepción: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Detecta el MIME type a partir de los primeros bytes de un base64.
     */
    private function detectMimeTypeFromBase64(string $base64): ?string
    {
        $raw = base64_decode(substr($base64, 0, 16));
        if ($raw === false) {
            return null;
        }

        $signatures = [
            "\xFF\xD8\xFF"       => 'image/jpeg',
            "\x89PNG\r\n\x1A\n"  => 'image/png',
            'GIF87a'             => 'image/gif',
            'GIF89a'             => 'image/gif',
            'RIFF'               => 'image/webp',
            "\x00\x00\x00"       => 'image/mp4',  // mp4/mov genérico
        ];

        foreach ($signatures as $sig => $mime) {
            if (substr($raw, 0, strlen($sig)) === $sig) {
                return $mime;
            }
        }

        return null;
    }

    /**
     * DISCONNECTED: Marcar sesión como inactiva
     * 
     * Request: {status: "DISCONNECTED", sessionId}
     * Response: {}
     */
    private function handleDisconnected(array $data): JsonResponse
    {
        try {
            $sessionId = $data['sessionId'] ?? null;

            if (!$sessionId) {
                return new JsonResponse([], 400);
            }

            $em = $this->getDoctrine()->getManager();
            $senderRepo = $em->getRepository('AppBundle:WhatsappSender');

            // Buscar sender por sessionId
            $qb = $senderRepo->createQueryBuilder('ws');
            $sender = $qb->where('ws.sessionId = :sessionId')
                ->setParameter('sessionId', $sessionId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($sender) {
                $sender->setSessionId(null);
                $sender->setFechaUltimaInteraccion(new \DateTime());
                $em->flush();

                $this->logear('⚠️ DISCONNECTED: ' . $sender->getTelefono() . ' [SID: ' . $sessionId . ']');
            }

            return new JsonResponse([], 200);

        } catch (\Exception $e) {
            $this->logear('❌ Error en DISCONNECTED: ' . $e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * Enviar mensaje a WhatsApp via Node.js Baileys
     * 
     * POST http://localhost:3000/api/messages/send
     * Body: {sessionId, to, body}
     * Headers: x-api-key, Content-Type
     */
    private function sendToWhatsApp(string $sessionId, string $to, string $body): bool
    {
        try {
            $nodeUrl = 'http://localhost:3000/api/messages/send';
            $nodeApiKey = getenv('NODE_API_KEY') ?: '1234567890';

            // Usar cURL para enviar
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $nodeUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $nodeApiKey
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'sessionId' => $sessionId,
                    'to' => $to,
                    'body' => $body
                ])
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 201) {
                $this->logear('✓ sendToWhatsApp: Mensaje enviado a ' . $to);
                return true;
            } else {
                $this->logear('⚠️ sendToWhatsApp: HTTP ' . $httpCode . ' - ' . $response);
                return false;
            }

        } catch (\Exception $e) {
            $this->logear('❌ sendToWhatsApp error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Endpoint SSE para escuchar nuevos mensajes de un expediente
     * GET /api/whatsapp/listen/{expedienteId}
     * 
     * Mantiene una conexión abierta y notifica al cliente cuando hay mensajes nuevos
     */

    /**
     * Muestra la configuración personal de WhatsApp del usuario logueado
     * GET /Mi/Configuracion/WhatsApp
     */
    public function miConfiguracionWhatsappAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return $this->redirectToRoute('login');
        }

        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');

        // Obtener la conexión del usuario actual
        $conexion = $senderRepo->createQueryBuilder('ws')
            ->where('ws.idUsuario = :idUsuario')
            ->setParameter('idUsuario', $usuario->getIdUsuario())
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Determinar si tiene sesión activa
        $tieneConexion = $conexion && $conexion->getSessionId() && !$conexion->getImagenQR();

        // Preparar datos para la vista
        $datosConexion = [];
        if ($conexion) {
            $datosConexion = [
                'id' => $conexion->getId(),
                'telefono' => $conexion->getTelefono(),
                'tieneConexion' => $tieneConexion,
                'ultimaInteraccion' => $conexion->getFechaUltimaInteraccion(),
                'opciones' => [
                    'syncConversaciones' => $conexion->getSyncConversaciones(),
                    'automatizacionesWhatsapp' => $conexion->getAutomatizacionesWhatsapp(),
                    'pilotoAutomatico' => $conexion->getPilotoAutomatico(),
                ],
            ];
        }

        return $this->render('@App/Backoffice/Lista/whatsapp-config.html.twig', [
            'titulo' => 'Configuración de WhatsApp',
            'usuario' => $usuario,
            'datosConexion' => $datosConexion,
            'tieneConexion' => $tieneConexion,
        ]);
    }

    /**
     * Cambia el estado de una opción de WhatsApp
     * POST /API/whatsapp/toggle-opcion
     * Body: {opcion: 'SyncConversaciones', estado: true}
     */
    public function toggleOpcionWhatsappAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $opcion = $data['opcion'] ?? null;
        $estado = $data['estado'] ?? null;

        if (!$opcion || $estado === null) {
            return new JsonResponse(['error' => 'Opción y estado requeridos'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');

        // Obtener la conexión del usuario
        $conexion = $senderRepo->createQueryBuilder('ws')
            ->where('ws.idUsuario = :idUsuario')
            ->setParameter('idUsuario', $usuario->getIdUsuario())
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$conexion) {
            return new JsonResponse(['error' => 'No hay sesión WhatsApp'], 404);
        }

        // Cambiar opción según el parámetro
        switch ($opcion) {
            case 'SyncConversaciones':
                $conexion->setSyncConversaciones((bool)$estado);
                break;
            case 'AutomatizacionesWhatsapp':
                $conexion->setAutomatizacionesWhatsapp((bool)$estado);
                break;
            case 'PilotoAutomatico':
                $conexion->setPilotoAutomatico((bool)$estado);
                break;
            default:
                return new JsonResponse(['error' => 'Opción desconocida'], 400);
        }

        try {
            $em->flush();
            $this->logear("✓ Opción {$opcion} cambiada a " . ($estado ? 'ON' : 'OFF') . " para usuario {$usuario->getIdUsuario()}");
            return new JsonResponse([
                'success' => true,
                'opcion' => $opcion,
                'estado' => $estado,
                'mensaje' => 'Opción actualizada correctamente'
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desconecta la sesión actual de WhatsApp del usuario
     * POST /API/whatsapp/desconectar
     */
    public function desconectarWhatsappAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');

        // Obtener la conexión del usuario
        $conexion = $senderRepo->createQueryBuilder('ws')
            ->where('ws.idUsuario = :idUsuario')
            ->setParameter('idUsuario', $usuario->getIdUsuario())
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$conexion) {
            return new JsonResponse(['error' => 'No hay sesión WhatsApp'], 404);
        }

        try {
            $sessionId = $conexion->getSessionId();
            $telefono = $conexion->getTelefono();

            // 1. Notificar al servidor Node.js para desconectar la sesión
            if ($sessionId) {
                $this->desconectarEnServidorWhatsApp($sessionId);
            }

            // 2. Marcar como desconectado en BD: sessionId = NULL, imagenQR = placeholder (para re-escaneo)
            $conexion->setSessionId(null);
            $conexion->setImagenQR('ESPERANDO_NUEVO_QR');
            $conexion->setFechaUltimaInteraccion(new \DateTime());
            
            $em->persist($conexion);
            $em->flush();
            $em->detach($conexion);
            $em->clear();
            
            $this->logear("✓ Sesión desconectada para usuario {$usuario->getIdUsuario()} teléfono {$telefono}");
            
            return new JsonResponse([
                'success' => true,
                'mensaje' => 'Sesión desconectada correctamente'
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al desconectar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notifica al servidor Node.js para desconectar una sesión de WhatsApp
     * POST https://punchiest-irremediably-suzette.ngrok-free.dev/api/sessions/disconnect
     * 
     * @param string $sessionId UUID de la sesión
     * @return bool True si se desconectó exitosamente
     */
    private function desconectarEnServidorWhatsApp(string $sessionId): bool
    {
        try {
            // Obtener URL del servidor desde BD
            $em = $this->getDoctrine()->getManager();
            $servidorRepo = $em->getRepository('AppBundle:WhatsappServidor');
            $servidor = $servidorRepo->findOneBy(['estado' => true]);
            
            if (!$servidor) {
                $this->logear('❌ No hay servidor WhatsApp configurado');
                return false;
            }

            $url = rtrim($servidor->getUrl(), '/') . '/api/sessions/disconnect';
            $apiKey = '1234567890';

            $payload = json_encode(['sessionId' => $sessionId]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey
                ],
                CURLOPT_POSTFIELDS => $payload
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 204) {
                $this->logear("✓ Sesión desconectada en servidor WhatsApp: {$sessionId}");
                return true;
            } else {
                $this->logear("⚠️ Error al desconectar en servidor WhatsApp (HTTP {$httpCode}): {$response}");
                return false;
            }

        } catch (\Exception $e) {
            $this->logear("❌ Error en desconectarEnServidorWhatsApp: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Solicita un nuevo QR para conectar WhatsApp
     * POST /AJAX/whatsapp/solicitar-qr
     * 
     * Devuelve el sessionName a usar para polling en Node.js
     */
    public function solicitarQRWhatsappAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            // Generar sessionName único: comercial_{idUsuario}
            $sessionName = 'comercial_' . $usuario->getIdUsuario();

            $this->logear("✓ Sesión solicitada: {$sessionName}");

            return new JsonResponse([
                'success' => true,
                'sessionName' => $sessionName,
                'mensaje' => 'Abre el modal para escanear el QR'
            ], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al solicitar QR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Proxy: Crea una sesión en Node.js Baileys
     * POST /whatsapp/session/create
     * 
     * Body: { sessionName: "comercial_2282" }
     * Response: { success: true, message: "..." } o { error: "..." }
     */
    public function createSessionAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $sessionName = $data['sessionName'] ?? null;

        if (!$sessionName) {
            return new JsonResponse(['error' => 'sessionName requerido'], 400);
        }

        try {
            // Obtener URL del servidor desde BD
            $em = $this->getDoctrine()->getManager();
            $servidorRepo = $em->getRepository('AppBundle:WhatsappServidor');
            $servidor = $servidorRepo->findOneBy(['estado' => true]);
            
            if (!$servidor) {
                $this->logear('❌ No hay servidor WhatsApp configurado');
                return new JsonResponse(['error' => 'Servidor no configurado'], 503);
            }

            // Guardar sessionName en WhatsappSender para este usuario
            $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
            $sender = $senderRepo->findOneBy(['idUsuario' => $usuario->getIdUsuario()]);
            
            if (!$sender) {
                $sender = new \AppBundle\Entity\WhatsappSender();
                $sender->setIdUsuario($usuario->getIdUsuario());
                $sender->setVersion(1);
                $em->persist($sender);
            }
            
            $sender->setSessionName($sessionName);
            $sender->setSessionId(null);
            $sender->setImagenQR('ESCANEAR_QR');
            $sender->setFechaUltimaInteraccion(new \DateTime());
            $em->flush();

            $nodeUrl = rtrim($servidor->getUrl(), '/') . '/api/sessions/create';
            $nodeApiKey = '1234567890';

            $payload = json_encode(['sessionName' => $sessionName]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $nodeUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $nodeApiKey
                ],
                CURLOPT_POSTFIELDS => $payload
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$response) {
                $this->logear("❌ Error curl en createSession: {$curlError}");
                return new JsonResponse(['error' => 'Node.js no disponible'], 503);
            }

            $result = json_decode($response, true);

            if ($httpCode === 200) {
                $this->logear("✓ Sesión creada: {$sessionName}");
                return new JsonResponse($result, 200);
            } elseif ($httpCode === 400) {
                // Sesión ya existe
                return new JsonResponse($result, 400);
            } else {
                $this->logear("⚠️ createSession HTTP {$httpCode}: {$response}");
                return new JsonResponse(['error' => 'Error del servidor Node.js'], $httpCode);
            }

        } catch (\Exception $e) {
            $this->logear("❌ Exception en createSession: " . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Proxy: Obtiene el QR actual de una sesión (para polling)
     * GET /whatsapp/session/qr/{sessionName}
     * 
     * Response: { status: "waiting|qr_ready|connected", qr: "data:image/png;base64,..." }
     */
    public function getQrAction($sessionName)
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            // Obtener URL del servidor desde BD
            $em = $this->getDoctrine()->getManager();
            $servidorRepo = $em->getRepository('AppBundle:WhatsappServidor');
            $servidor = $servidorRepo->findOneBy(['estado' => true]);
            
            if (!$servidor) {
                $this->logear('❌ No hay servidor WhatsApp configurado');
                return new JsonResponse(['error' => 'Servidor no configurado'], 503);
            }

            $nodeUrl = rtrim($servidor->getUrl(), '/') . '/api/sessions/qr/' . urlencode($sessionName);
            $nodeApiKey = '1234567890';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $nodeUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'x-api-key: ' . $nodeApiKey
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$response) {
                $this->logear("❌ Error curl en getQr: {$curlError}");
                return new JsonResponse(['error' => 'Node.js no disponible'], 503);
            }

            $result = json_decode($response, true);

            if ($httpCode === 200) {
                return new JsonResponse($result, 200);
            } elseif ($httpCode === 404) {
                return new JsonResponse(['error' => 'Sesión no encontrada'], 404);
            } else {
                $this->logear("⚠️ getQr HTTP {$httpCode}: {$response}");
                return new JsonResponse(['error' => 'Error del servidor Node.js'], $httpCode);
            }

        } catch (\Exception $e) {
            $this->logear("❌ Exception en getQr: " . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Lista todas las conexiones WhatsApp activas (solo para ADMIN)
     * GET /Admin/Lista/ConexionesWhatsApp
     */
    public function listaConexionesAdminAction(Request $request)
    {
        $usuario = $this->getUser();
        if (!$usuario || !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Solo admins pueden acceder');
        }

        $em = $this->getDoctrine()->getManager();
        $senderRepo = $em->getRepository('AppBundle:WhatsappSender');
        $usuarioRepo = $em->getRepository('AppBundle:Usuario');
        
        // Obtener todas las conexiones con sessionId (activas)
        $conexiones = $senderRepo->createQueryBuilder('ws')
            ->where('ws.sessionId IS NOT NULL')
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->getQuery()
            ->getResult();

        // Mapear datos con información del usuario
        $datosConexiones = [];
        foreach ($conexiones as $conexion) {
            $usuarioProp = $usuarioRepo->find($conexion->getIdUsuario());
            $datosConexiones[] = [
                'conexion' => $conexion,
                'usuario' => $usuarioProp
            ];
        }

        return $this->render('@App/Backoffice/Lista/conexiones-admin.html.twig', [
            'conexiones' => $datosConexiones
        ]);
    }

    /**
     * Ver conversaciones de una conexión específica (agrupadas por expediente)
     * GET /Admin/WhatsApp/conversaciones/{idSender}
     */
    public function conversacionesAdminAction($idSender)
    {
        $this->logear("=== INICIO conversacionesAdminAction, idSender: {$idSender} ===");
        
        $usuario = $this->getUser();
        if (!$usuario || !$this->isGranted('ROLE_ADMIN')) {
            $this->logear("❌ No autorizado o no es admin");
            throw $this->createAccessDeniedException('Solo admins pueden acceder');
        }

        $em = $this->getDoctrine()->getManager();
        
        // Obtener el sender
        $sender = $em->getRepository('AppBundle:WhatsappSender')->find($idSender);
        if (!$sender) {
            $this->logear("❌ Sender no encontrado: {$idSender}");
            throw $this->createNotFoundException('Conexión no encontrada');
        }
        
        $this->logear("✓ Sender encontrado - ID: {$idSender}, Teléfono: " . ($sender->getTelefono() ?? 'NULL'));

        // Obtener el usuario propietario
        $usuarioPropietario = $em->getRepository('AppBundle:Usuario')->find($sender->getIdUsuario());
        $this->logear("✓ Usuario propietario: " . ($usuarioPropietario ? $usuarioPropietario->getUsername() : 'NO ENCONTRADO'));

        // Obtener expedientes con conversaciones agrupadas
        $conn = $em->getConnection();
        $phone = $sender->getTelefono();
        
        $this->logear("📞 Teléfono sin procesar: " . ($phone ?? 'NULL'));
        
        $expedientes = [];
        if ($phone) {
            // Normalizar el teléfono: remover + y espacios
            $phoneNorm = preg_replace('/[^0-9]/', '', $phone);
            
            // También crear versión sin prefijo 34
            $phoneWithout34 = $phoneNorm;
            if (strpos($phoneNorm, '34') === 0) {
                $phoneWithout34 = substr($phoneNorm, 2);
            }
            
            $this->logear("📞 Buscando: {$phoneNorm} o {$phoneWithout34}");
            
            // Obtener expedientes con count y última fecha (búsqueda flexible)
            $sql = "SELECT 
                        id_expediente,
                        COUNT(*) as count,
                        MAX(timestamp) as ultima_fecha
                    FROM chat_history 
                    WHERE (from_phone LIKE :phone1 OR from_phone LIKE :phone2
                           OR to_phone LIKE :phone1 OR to_phone LIKE :phone2)
                    GROUP BY id_expediente
                    ORDER BY ultima_fecha DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':phone1' => '%' . $phoneNorm . '%',
                ':phone2' => '%' . $phoneWithout34 . '%'
            ]);
            $expedientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logear("✓ Expedientes encontrados: " . count($expedientes));
        }

        $this->logear("=== FIN conversacionesAdminAction - Renderizando vista ===");
        
        return $this->render('@App/Backoffice/Lista/conversaciones-admin.html.twig', [
            'sender' => $sender,
            'usuarioPropietario' => $usuarioPropietario,
            'expedientes' => $expedientes
        ]);
    }

    /**
     * API: Obtener conversaciones de un expediente específico
     * POST /AJAX/whatsapp/conversaciones-expediente/{idSender}
     */
    public function conversacionesExpedienteAction(Request $request, $idSender)
    {
        $usuario = $this->getUser();
        if (!$usuario || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'error' => 'No autorizado'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? $request->request->all();
        $idExpediente = $data['expediente'] ?? null;

        if (!$idExpediente) {
            return new JsonResponse(['success' => false, 'error' => 'Expediente requerido'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        
        // Obtener el sender
        $sender = $em->getRepository('AppBundle:WhatsappSender')->find($idSender);
        if (!$sender) {
            return new JsonResponse(['success' => false, 'error' => 'Sender no encontrado'], 404);
        }

        $phone = $sender->getTelefono();
        $mensajes = [];
        
        if ($phone) {
            // Normalizar el teléfono
            $phoneNorm = preg_replace('/[^0-9]/', '', $phone);
            $phoneWithout34 = $phoneNorm;
            if (strpos($phoneNorm, '34') === 0) {
                $phoneWithout34 = substr($phoneNorm, 2);
            }
            
            $this->logear("🔍 Buscando en expediente {$idExpediente} - Teléfono: {$phoneNorm} o {$phoneWithout34}");
            
            $conn = $em->getConnection();
            
            // Búsqueda flexible con LIKE para ambos campos (from_phone Y to_phone)
            $sql = "SELECT * FROM chat_history 
                    WHERE id_expediente = :expediente
                      AND (from_phone LIKE :phone1 OR from_phone LIKE :phone2
                           OR to_phone LIKE :phone1 OR to_phone LIKE :phone2)
                    ORDER BY timestamp ASC
                    LIMIT 200";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':expediente' => $idExpediente,
                ':phone1' => '%' . $phoneNorm . '%',
                ':phone2' => '%' . $phoneWithout34 . '%'
            ]);
            $mensajes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logear("✓ Mensajes encontrados en expediente: " . count($mensajes));
            
            // Debug: Ver dirección de mensajes
            $directionCount = ['enviado' => 0, 'recibido' => 0];
            foreach ($mensajes as $msg) {
                $dir = $msg['direction'] ?? 'undefined';
                if (isset($directionCount[$dir])) {
                    $directionCount[$dir]++;
                }
                $this->logear("  📨 {$dir} | from: " . substr($msg['from_phone'], -10) . " | to: " . substr($msg['to_phone'], -10) . " | msg: " . substr($msg['message'], 0, 30));
            }
            $this->logear("📊 Resumen: enviado=" . $directionCount['enviado'] . ", recibido=" . $directionCount['recibido']);
        }

        return new JsonResponse([
            'success' => true,
            'mensajes' => $mensajes
        ]);
    }
    
}

