<?php
namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class InteligenciaArtificialController extends Controller
{
    public function expedienteParseTextoAIAction(Request $request)
    {
        $texto = null;
        // aceptar JSON body o form-data 'texto'
        if ($request->isXmlHttpRequest()) {
            $json = json_decode($request->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['texto'])) {
                $texto = trim((string) $json['texto']);
            }
        }

        if (is_null($texto) || $texto === '') {
            $texto = trim((string) $request->request->get('texto', ''));
        }

        if (is_null($texto) || $texto === '') {
            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'No se ha recibido texto para parsear.'
            ], 400);
        }

        $resultado = [
            'emails' => [],
            'telefonos' => [],
            'importes' => [],
            'fechas' => [],
            'dni' => [],
            'campana' => [],
            'lead_kommo' => [],
            'nombres' => [],
            'direcciones' => [],
            'texto_raw' => $texto
        ];

        // Emails
        if (preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $texto, $m)) {
            foreach (array_unique($m[0]) as $e) {
                $resultado['emails'][] = ['value' => $e, 'confidence' => 0.95];
            }
        }

        // Telefonos (simple): +34, 0034, y 9/6 dígitos
        if (preg_match_all('/(\+?\d[\d \-\.]{6,}\d)/', $texto, $m)) {
            foreach (array_unique($m[0]) as $t) {
                $norm = preg_replace('/[^\d\+]/', '', $t);
                $resultado['telefonos'][] = ['value' => $norm, 'confidence' => 0.9];
            }
        }

        // Importes (ej. 1.200 ?, 1200?, 1200 EUR)
        if (preg_match_all('/(\d{1,3}(?:[\.\s]\d{3})*(?:,\d+)?|\d+(?:[\.,]\d+)?)(\s?(?:?|EUR|EUR\.|euros?))/iu', $texto, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $valor = str_replace([' ', '\u00A0', '?', 'EUR', 'eur', '.'], ['', '', '', '', '', ''], $row[0]);
                $valor = preg_replace('/,/', '.', $valor);
                $resultado['importes'][] = ['value' => trim($row[0]), 'confidence' => 0.88];
            }
        }

        // Fechas (d/m/Y o Y-m-d)
        if (preg_match_all('/(\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b)|(?:(\b\d{4}-\d{2}-\d{2}\b))/u', $texto, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $f = trim($row[0]);
                $resultado['fechas'][] = ['value' => $f, 'confidence' => 0.85];
            }
        }

        // DNI/NIE (ES) simplificado
        if (preg_match_all('/\b(\d{7,8}[A-Za-z])\b|\b([XxYyZz]\d{7}[A-Za-z])\b/', $texto, $m)) {
            $matches = array_filter(array_unique(array_merge($m[1] ?? [], $m[2] ?? [])));
            foreach ($matches as $d) {
                if ($d) $resultado['dni'][] = ['value' => $d, 'confidence' => 0.9];
            }
        }

        // Campaña: buscar 'Campaña: ...' o 'campana:'
        if (preg_match('/Camp[aá]na\s*:\s*(.+)/i', $texto, $m)) {
            $resultado['campana'][] = ['value' => trim($m[1]), 'confidence' => 0.9];
        }

        // Lead Kommo: buscar 'Lead Kommo: 12345' o 'Lead: 12345'
        if (preg_match('/Lead\s*(?:Kommo)?\s*[:#]?\s*(\d+)/i', $texto, $m)) {
            $resultado['lead_kommo'][] = ['value' => trim($m[1]), 'confidence' => 0.9];
        }

        // Direcciones: heurística básica por palabras clave
        if (preg_match_all('/\b(Calle|C\/|C\.\s|Avda|Avenida|Plaza|Pza|Paseo)\b[^\n\r\,\;]{1,100}/iu', $texto, $m)) {
            foreach (array_unique($m[0]) as $addr) {
                $resultado['direcciones'][] = ['value' => trim($addr), 'confidence' => 0.6];
            }
        }

        // Nombres: heurística - secuencias de palabras capitalizadas (2-3 palabras)
        if (preg_match_all('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){0,2}))\b/u', $texto, $m)) {
            $names = array_unique($m[1]);
            foreach ($names as $name) {
                // evitar falsos positivos si es solo una palabra muy común (Madrid, Calle, etc.)
                if (strlen($name) > 2) {
                    $resultado['nombres'][] = ['value' => $name, 'confidence' => 0.6];
                }
            }
        }

        return new JsonResponse([
            'ok' => true,
            'fields' => $resultado
        ]);
    }

    // --- Helpers for external IA providers (copied/adapted from LectorDocumentosController)
    private function obtenerConfiguracionIA()
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $sql = "SELECT * FROM ia_config WHERE activo = 1 AND es_proveedor_por_defecto = 1 LIMIT 1";
            $connection = $em->getConnection();
            $statement = $connection->prepare($sql);
            $statement->execute();
            $configDB = $statement->fetch();

            if ($configDB && !empty($configDB['api_key'])) {
                return [
                    'provider' => $configDB['provider'] ?? 'GEMINI',
                    'api_key' => $configDB['api_key'],
                    'model' => $configDB['model'] ?? 'gemini-1.5-flash',
                    'temperature' => $configDB['temperatura'] ?? 0.7,
                    'max_tokens' => $configDB['max_tokens'] ?? 2048
                ];
            }

            // Fallback to env
            $provider = getenv('IA_PROVIDER') ?: 'GEMINI';
            $apiKey = getenv('GEMINI_API_KEY') ?: getenv('OPENAI_API_KEY');

            return [
                'provider' => $provider,
                'api_key' => $apiKey ?: '',
                'model' => getenv('GEMINI_MODEL') ?: getenv('OPENAI_MODEL') ?: 'gemini-1.5-flash',
                'temperature' => 0.7,
                'max_tokens' => 2048
            ];
        } catch (\Exception $e) {
            return [
                'provider' => 'GEMINI',
                'api_key' => '',
                'model' => 'gemini-1.5-flash',
                'temperature' => 0.7,
                'max_tokens' => 2048
            ];
        }
    }

    private function construirPromptParseTexto()
    {
        return "Extrae del siguiente texto los campos: emails, telefonos, importes, fechas, dni, campana, lead_kommo, nombres, direcciones. Devuelve SOLO JSON con claves: emails, telefonos, importes, fechas, dni, campana, lead_kommo, nombres, direcciones. Cada clave debe ser un array de objetos {value, confidence}. Si no hay valor, devuelve array vacío.";
    }

    private function enviarAGeminiTexto($texto, $configIA)
    {
        $prompt = $this->construirPromptParseTexto() . "\n\nTexto:\n" . $texto;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$configIA['model']}:generateContent?key={$configIA['api_key']}";

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => $configIA['temperature'],
                "maxOutputTokens" => $configIA['max_tokens']
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Error en Gemini API: ' . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception('Respuesta inválida de Gemini: ' . substr($response, 0, 500));
        }

        $textoRespuesta = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $datosExtraidos = $textoRespuesta ? json_decode($textoRespuesta, true) : null;
        if (!is_array($datosExtraidos)) {
            // fallback: return raw text
            $datosExtraidos = ['texto' => $textoRespuesta];
        }

        $tokensEntrada = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $tokensSalida = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
        $tokensTotales = $data['usageMetadata']['totalTokenCount'] ?? ($tokensEntrada + $tokensSalida);

        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.9,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }

    private function enviarAOpenAITexto($texto, $configIA)
    {
        $prompt = $this->construirPromptParseTexto() . "\n\nTexto:\n" . $texto;
        $url = "https://api.openai.com/v1/chat/completions";

        $payload = [
            'model' => $configIA['model'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $configIA['max_tokens'],
            'temperature' => $configIA['temperature']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $configIA['api_key']
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Error en OpenAI API: ' . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception('Respuesta inválida OpenAI: ' . substr($response, 0, 500));
        }

        $textoRespuesta = $data['choices'][0]['message']['content'] ?? null;
        $datosExtraidos = $textoRespuesta ? json_decode($textoRespuesta, true) : null;
        if (!is_array($datosExtraidos)) {
            $datosExtraidos = ['texto' => $textoRespuesta];
        }

        $tokensEntrada = $data['usage']['prompt_tokens'] ?? 0;
        $tokensSalida = $data['usage']['completion_tokens'] ?? 0;
        $tokensTotales = $data['usage']['total_tokens'] ?? ($tokensEntrada + $tokensSalida);

        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.9,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }
}
