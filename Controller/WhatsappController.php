<?php

namespace AppBundle\Controller;

use AppBundle\Entity\WhatsappSender;
use AppBundle\Entity\WhatsappServidor;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Controller\IArtificalController;
use AppBundle\Entity\Expediente as ExpedienteEntidad;

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
            $this->iaController->setContainer($this->container);
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

        // Obtener servidor activo con menos conexiones
        $servidor = $servidorRepo->findServidorConMenosConexiones();

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

        // 📊 LOGUEAR TODO LO QUE LLEGA EN $data
        $this->logear("=== INICIO createAction ===");
        $this->logear("📨 TODOS LOS PARÁMETROS RECIBIDOS:");
        $this->logear(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->logear("phone_origen: " . ($data['phone_origen'] ?? 'NO VIENE'));
        $this->logear("phone_destination: " . ($data['phone_destination'] ?? 'NO VIENE'));
        $this->logear("id_expediente: " . ($data['id_expediente'] ?? ($data['expediente_id'] ?? 'NO VIENE')));
        $this->logear("text: " . (strlen($data['text'] ?? '') > 100 ? substr($data['text'], 0, 100) . '...' : ($data['text'] ?? 'NO VIENE')));
        $this->logear("direccion: " . ($data['direccion'] ?? 'NO VIENE'));
        $this->logear("role_label: " . ($data['role_label'] ?? 'NO VIENE'));

        $phone = $data['phone_origen'] ?? null;
        $phoneDestination = $data['phone_destination'] ?? null;
        $idExpediente = $data['expediente_id'] ?? null;
        $role_label = $data['role_label'] ?? null;
        $text = $data['text'] ?? null;
        $direction = $data['direccion'] ?? 'enviado11';

        

        if (!$phone || !$text) {
            return new JsonResponse(['error' => 'phone_origen and text are required'], 400);
        }

        // Parsear el texto: puede ser un JSON con estructura de imagen o texto plano
        $imageData = null;
        $imageType = null;
        $textContent = $text;
        $isImage = false;

        // Intentar parsear como JSON (el campo text puede contener JSON serializado con imagen)
        $parsedText = json_decode($text, true);
        if ($parsedText && is_array($parsedText) && isset($parsedText['type'])) {
            if ($parsedText['type'] === 'image' && isset($parsedText['content'])) {
                // ✅ ES UNA IMAGEN EN BASE64
                $isImage = true;
                $imageData = $parsedText['content'];
                $imageType = $parsedText['mime_type'] ?? 'image/jpeg';
                $textContent = $parsedText['text'] ?? null; // Descripción de la imagen

                // Validar tamaño máximo de imagen (5MB)
                $imageSizeInBytes = strlen(base64_decode($imageData));
                $maxSizeInBytes = 5 * 1024 * 1024; // 5MB
                
                if ($imageSizeInBytes > $maxSizeInBytes) {
                    return new JsonResponse([
                        'error' => 'Imagen demasiado grande. Máximo 5MB',
                        'size' => $imageSizeInBytes,
                        'max_size' => $maxSizeInBytes
                    ], 400);
                }
                
                error_log("Imagen detectada: $imageType, tamaño: $imageSizeInBytes bytes\n");
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

                $this->logear("direction111111111111111111111: " . ($direction));
                $this->logear("idExpediente111111111111111111: " . ($idExpediente));

                if ($direction == 'recibido') 
                {
                    // Si aún no encuentra, intentar phone_destination como cliente                    
                    $idExpediente = $this->findExpedienteByClientPhone($phone);                    
                } 
                else
                {
                    // Si no encuentra, intentar phone_origen como cliente
                    $idExpediente = $this->findExpedienteByClientPhone($phoneDestinationNorm);
                }
                $this->logear("idExpediente222222222222222222: " . ($idExpediente));
                
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
            if ($isImage && $imageData) {
                // Si hay imagen, guardar con metadatos
                $messageData = [
                    'type' => 'image',
                    'content' => $imageData,  // Base64 de la imagen
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
                'phone_number' => $phoneGuardar,
                'role' => $finalRole,
                'role_label' => $role_label,
                'text' => json_encode($messageData),
                'id_expediente' => $idExpediente,
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
                                    $fecha,
                                    $idExpediente
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
                                            $fecha,
                                            $idExpediente
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
                                            $fecha,
                                            $idExpediente
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
                                        $fecha,
                                        $idExpediente
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
            $sql = 'SELECT id, phone_number, role, role_label, text, id_expediente, timestamp
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
        $phoneOrigen = $data['phone_number'] ?? null;
        $texto = $data['text'] ?? null;

        if (!$idExpediente || !$phoneOrigen || !$texto) {
            return new JsonResponse([
                'error' => 'id_expediente, phone_number and text are required'
            ], 400);
        }

        $conn = $this->getDoctrine()->getConnection();

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

            // 4. Generar hash y date para el bot
            $fecha = date('Y-m-d');
            $hash = $this->generarHashWhatsapp($fecha);

            // 5. Llamar al bot de WhatsApp
            $phoneOrigenFull = $this->normalizePhonenWithPrefix($phoneOrigen);
            $phoneDestinoFull = $this->normalizePhonenWithPrefix($phoneDestino);

            $botResponse = $this->llamarBotWhatsApp(
                $phoneOrigenFull,
                $phoneDestinoFull,
                $texto,
                $hash,
                $fecha,
                $idExpediente
            );

            if (!$botResponse['success']) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Error al enviar mensaje al bot: ' . ($botResponse['message'] ?? 'Unknown error')
                ], 500);
            }

            // 6. Guardar el mensaje en chat_history si se envió correctamente al bot
            /*try {
                // Obtener el nombre del usuario que envía
                $usuarioEnvia = $this->findUserByPhone($phoneOrigen);
                $roleLabel = $usuarioEnvia ? trim(($usuarioEnvia['nombre'] ?? '') . ' ' . ($usuarioEnvia['apellidos'] ?? '')) : 'Técnico';
                
                $conn->insert('chat_history', [
                    'phone_number' => $phoneOrigen,  // Guardar el teléfono normalizado sin prefijo
                    'role' => 'assistant',  // El que envía es técnico/comercial (assistant)
                    'role_label' => $roleLabel,
                    'text' => $texto,
                    'id_expediente' => $idExpediente,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                // Log del error pero no bloquear la respuesta
                if ($this->container->has('logger')) {
                    $this->container->get('logger')->warning('Error al guardar mensaje en chat_history: ' . $e->getMessage());
                }
            }*/

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
     */
    private function llamarBotWhatsApp($telefonoOrigen, $telefonoDestino, $mensaje, $hash, $fecha, $idExpediente = 111)
    {
        $this->logear("Llamando al bot de WhatsApp con hash: {$hash} y fecha: {$fecha}, mensaje: {$mensaje}, desde: {$telefonoOrigen} hacia: {$telefonoDestino}");
        try {
            $url = "https://crabbedly-unpersonalized-angelique.ngrok-free.dev/api/send-message?hash={$hash}&date={$fecha}";
            //$url = seleccionarServidorDisponible();

            $payload = json_encode([
                'telefono_origen' => $telefonoOrigen,
                'telefono_destino' => $telefonoDestino,
                'mensaje' => $mensaje, 
                'id_expediente' => $idExpediente
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
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
                        (SELECT text FROM chat_history WHERE id_expediente = e.id_expediente ORDER BY timestamp DESC LIMIT 1) AS ultimo_mensaje_texto,
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
                    $fecha,
                    $idExpediente
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
                                $conn->insert('chat_history', [
                                    'phone_number' => '61425772',  // Sistema
                                    'role' => 'assistant',
                                    'role_label' => 'Hipotea',
                                    'text' => $mensajeUnificado,
                                    'id_expediente' => $idExpediente,
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

    public function datosExpedientePruebaAction(Request $request, $id)
    {
        $idExpediente = (int)$id;
        $doctrine = $this->getDoctrine();
		$managerEntidad = $doctrine->getManager();
        $expediente = $doctrine->getRepository(ExpedienteEntidad::class)->findOneBy(array(
            'idExpediente' => $idExpediente
        ));
        if (!$expediente) 
        {
            return new JsonResponse([
                'success' => false,
                'message' => 'No existe el expediente',
                'expediente_id' => $idExpediente
            ], 404);
        }else{
            // Serializar la entidad (incluyendo asociaciones) a array
            try {
                $data = $this->serializeEntity($expediente, $managerEntidad, 2, 0);
            } catch (\Exception $e) {
                // Fallback simple
                $data = [];
                if ($this->container->has('serializer')) {
                    try {
                        $data = json_decode($this->get('serializer')->serialize($expediente, 'json'), true);
                    } catch (\Exception $e2) {
                        $data = [];
                    }
                }
            }

            if (isset($data['whatsappAutomatico']) && $data['whatsappAutomatico'] === true) 
            {
                if (isset($data['whatsappAutomaticoEnviado']) && $data['whatsappAutomaticoEnviado'] === false)
                {
                    if (isset($data['idCliente']['telefonoMovil']) && !empty($data['idCliente']['telefonoMovil'])) 
                    {
                        $telefonoCliente = $this->normalizePhonenWithPrefix($data['idCliente']['telefonoMovil']);
                        $nombreCliente = $data['idCliente']['nombre'] ?? 'Cliente';
                        $roleCliente = $data['idCliente']['role'] ?? null;
                        if (isset($data['idComercial']['telefonoMovil']) && !empty($data['idComercial']['telefonoMovil'])) 
                        {
                            $telefonoCRM = $this->normalizePhonenWithPrefix($data['idComercial']['telefonoMovil']);
                            $nombreCRM = $data['idComercial']['nombre'] ?? 'Comercial';
                            $roleCRM = $data['idComercial']['role'] ?? null;
                        }
                        else if (isset($data['idTecnico']['telefonoMovil']) && !empty($data['idTecnico']['telefonoMovil']))
                        {
                            $telefonoCRM = $this->normalizePhonenWithPrefix($data['idTecnico']['telefonoMovil']);
                            $nombreCRM = $data['idTecnico']['nombre'] ?? 'Técnico';
                            $roleCRM = $data['idTecnico']['role'] ?? null;
                        } 
                        else 
                        {
                            $telefonoCRM = $this->normalizePhonenWithPrefix('Sistema');
                            $nombreCRM = 'Sistema';
                            $roleCRM = 'Chat_Bot';
                        }

                        $mensajePrueba = "Hola $nombreCliente, esto es una mensaje de prueba para el expediente $idExpediente";
                        $fecha = date('Y-m-d');
                        $hash = $this->generarHashWhatsapp($fecha);

                        $datosFase1 = $this->getIAController()->obtenerDatosFase1($idExpediente, $this->getDoctrine()->getConnection());

                        /*$botResponse = $this->llamarBotWhatsApp(
                            $telefonoCRM,
                            $telefonoCliente,
                            $mensajePrueba,
                            $hash,
                            $fecha,
                            $idExpediente
                        );*/

                        $respuestaBot = $botResponse;
                        return new JsonResponse([
                            'success' => true,
                            'message' => 'Cliente con teléfono móvil registrado y WhatsApp automático activo sin ser enviado para este expediente',
                            'ClienteTelefonoMovil' => $telefonoCliente ?? null,
                            'nombreCliente' => $nombreCliente ?? null,
                            'roleCliente' => $roleCliente ?? null,
                            'telefonoCRM' => $telefonoCRM ?? null,
                            'nombreCRM' => $nombreCRM ?? null,
                            'roleCRM' => $roleCRM ?? null,
                            'respuestaBot' => $respuestaBot ?? null,
                            'datosFase1' => $datosFase1,
                            'expediente' => $data
                        ], 200);
                    } else {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Cliente sin teléfono móvil registrado',
                            'ClientetelefonoMovil' => $telefonoCliente ?? null,
                        ], 404);
                    }
                    
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'WhatsApp automático activo y sin ser enviado para este expediente',
                        'whatsappAutomatico' => $data['whatsappAutomatico'] ?? null,
                        'whatsappAutomaticoEnviado' => $data['whatsappAutomaticoEnviado'] ?? null,
                        'Cliente' => $data['idCliente']['telefonoMovil'] ?? null,
                        'expediente' => $data
                    ], 200);
                }
                else
                {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'WhatsApp automático activo y pero ya enviado para este expediente',
                        'whatsappAutomatico' => $data['whatsappAutomatico'] ?? null,
                        'whatsappAutomaticoEnviado' => $data['whatsappAutomaticoEnviado'] ?? null,
                        'Cliente' => $data['idCliente']['telefonoMovil']?? null,
                        'expediente' => $data
                    ], 404);
                }
                
            }
            else
            {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'WhatsApp automático no activo para este expediente',
                    'whatsappAutomatico' => $data['whatsappAutomatico'] ?? null,
                    'expediente' => $data
                ], 200);
            }
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
     * Serializa una entidad Doctrine a un array, incluyendo asociaciones hasta una profundidad.
     */
    private function serializeEntity($entity, $manager, $maxDepth = 2, $currentDepth = 0)
    {
        if ($entity === null) {
            return null;
        }
        if (!is_object($entity)) {
            return $entity;
        }
        if ($currentDepth > $maxDepth) {
            return null;
        }

        $data = [];
        try {
            $class = get_class($entity);
            $meta = $manager->getClassMetadata($class);

            // Campos simples
            foreach ($meta->getFieldNames() as $field) {
                $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
                if (method_exists($entity, $getter)) {
                    $value = $entity->$getter();
                } else {
                    $prop = $meta->getReflectionProperty($field);
                    $prop->setAccessible(true);
                    $value = $prop->getValue($entity);
                }
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                $data[$field] = $value;
            }

            // Identificadores (asegurar presencia)
            foreach ($meta->getIdentifierFieldNames() as $idField) {
                if (!array_key_exists($idField, $data)) {
                    $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $idField)));
                    $data[$idField] = method_exists($entity, $getter) ? $entity->$getter() : null;
                }
            }

            // Asociaciones (objeto o colección)
            foreach ($meta->getAssociationNames() as $assoc) {
                $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $assoc)));
                if (method_exists($entity, $getter)) {
                    $assocVal = $entity->$getter();
                } else {
                    try {
                        $prop = $meta->getReflectionProperty($assoc);
                        $prop->setAccessible(true);
                        $assocVal = $prop->getValue($entity);
                    } catch (\Exception $e) {
                        $assocVal = null;
                    }
                }

                if ($assocVal instanceof \Doctrine\Common\Collections\Collection || is_array($assocVal)) {
                    $data[$assoc] = [];
                    foreach ($assocVal as $el) {
                        if ($currentDepth + 1 <= $maxDepth) {
                            $data[$assoc][] = $this->serializeEntity($el, $manager, $maxDepth, $currentDepth + 1);
                        } else {
                            try {
                                $idvals = $manager->getClassMetadata(get_class($el))->getIdentifierValues($el);
                                $data[$assoc][] = $idvals;
                            } catch (\Exception $e) {
                                $data[$assoc][] = null;
                            }
                        }
                    }
                } elseif (is_object($assocVal)) {
                    if ($currentDepth + 1 <= $maxDepth) {
                        $data[$assoc] = $this->serializeEntity($assocVal, $manager, $maxDepth, $currentDepth + 1);
                    } else {
                        try {
                            $data[$assoc] = $manager->getClassMetadata(get_class($assocVal))->getIdentifierValues($assocVal);
                        } catch (\Exception $e) {
                            $data[$assoc] = null;
                        }
                    }
                } else {
                    $data[$assoc] = $assocVal;
                }
            }
        } catch (\Exception $e) {
            return [];
        }

        return $data;
    }

    /**
     * Obtiene los logs del día actual
     * GET /API/logs
     * @return JsonResponse
     */
    public function misLogsAction()
    {
        if (empty($datosFase1['fase']['hitos'])) {
            return null;
        }

        foreach ($datosFase1['fase']['hitos'] as $hito) {
            if (empty($hito['grupos'])) {
                continue;
            }

            foreach ($hito['grupos'] as $grupo) {
                if (empty($grupo['campos'])) {
                    continue;
                }

                foreach ($grupo['campos'] as $campo) {
                    if ($campo['id_campo_hito'] == $idCampo) {
                        // Agregar nombre del hito al campo encontrado
                        $campo['hito'] = $hito['nombre'];
                        return $campo;
                    }
                }
            }
        }

        return null;
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
    
}

