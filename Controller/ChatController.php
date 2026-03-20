<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ChatController extends Controller
{
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
        
        // Debug: log del API key recibido
        error_log('CheckApiKey - Provided: ' . ($provided ?: 'NONE') . ' | Expected: ' . $expected);
        
        return $provided && $provided === $expected;
    }

    // Normaliza el teléfono: elimina todo lo que no sean dígitos
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }

    public function listAction(Request $request)
    {
        if (!$this->checkApiKey($request)) 
        {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $phone = $request->query->get('phone');
        $limit = (int) $request->query->get('limit', 50);
        $limit = max(1, min(100, $limit));

        if (!$phone) {
            return new JsonResponse(['error' => 'phone parameter required'], 400);
        }

        $phone = $this->normalizePhone($phone);
        // También calcular la variante local sin prefijo de país (últimos 9 dígitos)
        $phone_local = (strlen($phone) > 9) ? substr($phone, -9) : $phone;

        $conn = $this->getDoctrine()->getConnection();
        // Buscar tanto por la forma completa como por la forma local (últimos 9 dígitos)
        $variants = array_values(array_unique(array_filter([$phone, $phone_local])));

        // Crear placeholders nombrados dinámicamente (:p0, :p1, ...)
        $placeholders = [];
        $params = [];
        foreach ($variants as $i => $v) {
            $ph = ':p' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $v;
        }

        $sql = 'SELECT id, phone_number, role, text, timestamp FROM chat_history WHERE phone_number IN (' . implode(',', array_keys($params)) . ') ORDER BY timestamp DESC LIMIT :limit';
        $stmt = $conn->prepare($sql);

        // Bind dinámico de parámetros
        foreach ($params as $ph => $val) {
            $stmt->bindValue(trim($ph, ':'), $val);
        }
        // bind as integer for LIMIT
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return new JsonResponse(['data' => $rows], 200);
    }

    public function createAction(Request $request)
    {
        // Debug detallado
        $apiKeyHeader = $request->headers->get('X-API-KEY');
        $apiKeyQuery = $request->query->get('api_key');
        $data = json_decode($request->getContent(), true);
        $apiKeyBody = $data['api_key'] ?? null;
        
        error_log('=== DEBUG CREATEACTION ===');
        error_log('Header X-API-KEY: ' . ($apiKeyHeader ?: 'NULL'));
        error_log('Query api_key: ' . ($apiKeyQuery ?: 'NULL'));
        error_log('Body api_key: ' . ($apiKeyBody ?: 'NULL'));
        error_log('Method: ' . $request->getMethod());
        error_log('Content-Type: ' . $request->headers->get('Content-Type'));
        error_log('All Headers: ' . json_encode($request->headers->all()));
        error_log('=== END DEBUG ===');
        
        if (!$this->checkApiKey($request)) 
        {
            return new JsonResponse([
                'error' => 'Unauthorized',
                'debug' => [
                    'header_x_api_key' => $apiKeyHeader,
                    'query_api_key' => $apiKeyQuery,
                    'body_api_key' => $apiKeyBody,
                    'expected' => '123456'
                ]
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'invalid JSON'], 400);
        }

        $phone = $data['phone_number'] ?? null;
        $role = $data['role'] ?? null;
        $role_label = $data['role_label'] ?? null;
        $text = $data['text'] ?? null;

        if (!$phone || !$role || !$text) {
            return new JsonResponse(['error' => 'phone_number, role and text are required'], 400);
        }

        $phone = $this->normalizePhone($phone);
        // Calcular la variante local (sin prefijo) — últimas 9 cifras si el teléfono incluye prefijo
        $phone_local = (strlen($phone) > 9) ? substr($phone, -9) : $phone;

        // Intentar obtener el usuario asociado al teléfono (si existe)
        $user = $this->findUserByPhone($phone);
        $displayName = null;
        if ($user && (!empty($user['nombre']) || !empty($user['apellidos']))) {
            $displayName = trim((string)($user['nombre'] ?? '') . ' ' . (string)($user['apellidos'] ?? ''));
        }

        // Buscar si el teléfono pertenece a un usuario y si tiene expedientes
        $idExpediente = $this->findExpedienteByPhone($phone);

        $conn = $this->getDoctrine()->getConnection();
        // Guardar la versión local (sin prefijo) para facilitar búsquedas que usan el formato sin país
        $conn->insert('chat_history', [
            'phone_number' => $phone_local,
            'role' => $role,
            'role_label' => $role_label,
            'text' => $text,
            'id_expediente' => $idExpediente
        ]);
        $id = $conn->lastInsertId();
        $response = ['id' => $id];
        if ($user) {
            $response['user'] = [
                'id_usuario' => $user['id_usuario'] ?? null,
                'nombre' => $user['nombre'] ?? '',
                'apellidos' => $user['apellidos'] ?? '',
                'display_name' => $displayName
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

    public function usuarioAction(Request $request): JsonResponse
    {
        $provided = $request->headers->get('X-API-KEY') ?: $request->query->get('api_key');
        // Use the same API key check as listAction
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized', 'provided' => $provided], 401);
        }

        $conn = $this->getDoctrine()->getConnection();

        // Obtener el teléfono desde la query (compatibilidad con la forma anterior)
        $phone = $request->query->get('phone');
        if (!$phone) {
            return new JsonResponse(['error' => 'phone parameter required'], 400);
        }

        // Normaliza el teléfono (solo dígitos) usando la función común
        $digits = $this->normalizePhone($phone);

        $variants = array_values(array_unique(array_filter([
            $digits,                          // tal cual
            ltrim($digits, '0'),              // sin ceros a la izquierda
            (strlen($digits) > 9 ? substr($digits, -9) : null), // últimos 9
        ])));

        if (!$variants) {
            return new JsonResponse(['error' => 'Teléfono no válido'], 400);
        }

        // Placeholders (?) según nº de variantes
        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        $sql = "SELECT *
                FROM usuario
                WHERE estado = 1
                AND telefono_movil IN ($placeholders)
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        // Bind posicional (1-indexed)
        foreach ($variants as $i => $v) {
            $stmt->bindValue($i + 1, $v);
        }

        // DBAL 3 devuelve Result; DBAL 2 devuelve el propio statement
        $exec = $stmt->execute();
        if ($exec instanceof \Doctrine\DBAL\Result) {
            $user = $exec->fetchAssociative();
        } else {
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$user) {
            // 204: sin contenido (usuario no encontrado)
            return new JsonResponse(null, 204);
        }

        return new JsonResponse($user, 200);
    }

    // Busca el expediente más reciente de un usuario por su teléfono
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
                // Buscar expediente activo más reciente del usuario (columnas en snake_case)
                $sql = 'SELECT id_expediente FROM expediente 
                        WHERE id_cliente = :userId AND estado > 0 
                        ORDER BY fecha_creacion DESC 
                        LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('userId', $usuario['id_usuario']);
                $stmt->execute();
                $expediente = $stmt->fetch();

                return $expediente ? $expediente['id_expediente'] : null;
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
                error_log('findExpedienteByPhone error: '.$e->getMessage());
            }
            return null;
        }
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

            $sql = 'SELECT id_usuario, nombre, apellidos, telefono_movil FROM usuario WHERE telefono_movil IN (' . implode(', ', array_keys($params)) . ') AND estado = 1 LIMIT 1';
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

    public function gestorAction1(Request $request): JsonResponse
    {
        $apiKey = $this->checkApiKey($request);
        return new JsonResponse([
            'success' => true,
            'api_key' => $apiKey
        ], 200);
    }

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

    public function senderAction(Request $request): JsonResponse
    {
        // Verificar API key
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Obtener datos del body
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'invalid JSON'], 400);
        }

        // Validar campos requeridos
        $idAgencia = $data['IdAgencia'] ?? null;
        $idGestor = $data['IdGestor'] ?? null;
        $telefono = $data['Telefono'] ?? null;
        $version = $data['Version'] ?? null;

        if (!$idGestor || !$telefono) {
            return new JsonResponse(['error' => 'IdGestor and Telefono are required'], 400);
        }

        // Normalizar teléfono
        $telefonoNormalizado = $this->normalizePhone($telefono);

        // Obtener configuraciones (usar 0 como valor por defecto si no están definidas)
        $syncConversaciones = $data['SyncConversaciones'] ?? 0;
        $automatizacionesWhatsapp = $data['AutomatizacionesWhatsapp'] ?? 0;
        $crucesAutomaticos = $data['CrucesAutomaticos'] ?? 0;
        $crucesAutomaticosRGPDExterna = $data['CrucesAutomaticosRGPDExterna'] ?? 0;
        $pilotoAutomatico = $data['PilotoAutomatico'] ?? 0;
        $recordatoriosVisitas = $data['RecordatoriosVisitas'] ?? 0;

        $conn = $this->getDoctrine()->getConnection();

        // INSERT con ON DUPLICATE KEY UPDATE
        // Nota: Para que ON DUPLICATE KEY funcione, necesitas una clave UNIQUE en la tabla
        // Por ejemplo: UNIQUE KEY (IdUsuario, IdAgencia) o UNIQUE KEY (Telefono)
        $sql = "INSERT INTO WhatsappSenders (
                    IdAgencia, IdUsuario, Telefono, FechaUltimaInteraccion, 
                    Version, SyncConversaciones, AutomatizacionesWhatsapp,
                    CrucesAutomaticos, CrucesAutomaticosRGPDExterna, 
                    PilotoAutomatico, RecordatoriosVisitas
                )
                VALUES (
                    :idAgencia, :idUsuario, :telefono, NOW(), :version,
                    :syncConversaciones, :automatizacionesWhatsapp,
                    :crucesAutomaticos, :crucesAutomaticosRGPDExterna,
                    :pilotoAutomatico, :recordatoriosVisitas
                )
                ON DUPLICATE KEY UPDATE 
                    FechaUltimaInteraccion = NOW(),
                    Version = VALUES(Version),
                    Telefono = VALUES(Telefono),
                    SyncConversaciones = VALUES(SyncConversaciones),
                    AutomatizacionesWhatsapp = VALUES(AutomatizacionesWhatsapp),
                    CrucesAutomaticos = VALUES(CrucesAutomaticos),
                    CrucesAutomaticosRGPDExterna = VALUES(CrucesAutomaticosRGPDExterna),
                    PilotoAutomatico = VALUES(PilotoAutomatico),
                    RecordatoriosVisitas = VALUES(RecordatoriosVisitas),
                    ImagenQR = NULL,
                    PathEjecutable = NULL";

        try {
            $stmt = $conn->prepare($sql);
            // Bind idAgencia: si es null usamos PDO::PARAM_NULL para evitar insertar 0
            if ($idAgencia === null) {
                $stmt->bindValue('idAgencia', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue('idAgencia', $idAgencia, \PDO::PARAM_INT);
            }
            $stmt->bindValue('idUsuario', $idGestor);
            $stmt->bindValue('telefono', $telefonoNormalizado);
            $stmt->bindValue('version', $version);
            $stmt->bindValue('syncConversaciones', $syncConversaciones, \PDO::PARAM_INT);
            $stmt->bindValue('automatizacionesWhatsapp', $automatizacionesWhatsapp, \PDO::PARAM_INT);
            $stmt->bindValue('crucesAutomaticos', $crucesAutomaticos, \PDO::PARAM_INT);
            $stmt->bindValue('crucesAutomaticosRGPDExterna', $crucesAutomaticosRGPDExterna, \PDO::PARAM_INT);
            $stmt->bindValue('pilotoAutomatico', $pilotoAutomatico, \PDO::PARAM_INT);
            $stmt->bindValue('recordatoriosVisitas', $recordatoriosVisitas, \PDO::PARAM_INT);
            $stmt->execute();

            $affectedRows = $stmt->rowCount();
            
            return new JsonResponse([
                'success' => true,
                'affected_rows' => $affectedRows,
                'action' => $affectedRows == 1 ? 'inserted' : 'updated',
                'telefono_normalizado' => $telefonoNormalizado
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Database error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAction(Request $request, $id = null)
    {
        if (!$this->checkApiKey($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        if (!$id) {
            // allow id in POST body or query
            $id = $request->request->get('id') ?: $request->query->get('id');
        }
        if (!$id) {
            return new JsonResponse(['error' => 'id required'], 400);
        }

        $conn = $this->getDoctrine()->getConnection();
        $affected = $conn->delete('chat_history', ['id' => $id]);

        return new JsonResponse(['deleted' => $affected]);
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
}
