<?php
namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BotController extends Controller
{
    private $perfilActual = 'hipotecas';
    private $cache = [];              // Caché en memoria
    private $fileModTimes = [];       // Timestamps de ficheros
    
    /**
     * Obtiene la ruta base de prompts - intenta múltiples ubicaciones
     */
    private function obtenerRutaBase()
    {
        // Opción 1: Directamente en la misma carpeta que BotController
        $ruta1 = __DIR__ . '/prompts/' . $this->perfilActual;
        if (is_dir($ruta1)) {
            error_log('✅ [BOT] Usando ruta 1: ' . $ruta1);
            return $ruta1;
        }
        
        // Opción 2: En una carpeta hermana (config/prompts)
        $ruta2 = dirname(__DIR__) . '/config/prompts/' . $this->perfilActual;
        if (is_dir($ruta2)) {
            error_log('✅ [BOT] Usando ruta 2: ' . $ruta2);
            return $ruta2;
        }
        
        // Opción 3: En la raíz del proyecto/prompts
        $ruta3 = dirname(dirname(__DIR__)) . '/prompts/' . $this->perfilActual;
        if (is_dir($ruta3)) {
            error_log('✅ [BOT] Usando ruta 3: ' . $ruta3);
            return $ruta3;
        }
        
        // Si nada funciona, loguear el error
        error_log('❌ [BOT] No se encontró la carpeta de prompts en ninguna ubicación:');
        error_log('  Ruta 1: ' . $ruta1 . ' (existe: ' . (is_dir($ruta1) ? 'SÍ' : 'NO') . ')');
        error_log('  Ruta 2: ' . $ruta2 . ' (existe: ' . (is_dir($ruta2) ? 'SÍ' : 'NO') . ')');
        error_log('  Ruta 3: ' . $ruta3 . ' (existe: ' . (is_dir($ruta3) ? 'SÍ' : 'NO') . ')');
        error_log('  __DIR__: ' . __DIR__);
        
        // Devolver la primera opción por defecto (aunque no exista, así sabemos dónde buscar)
        return $ruta1;
    }
    
    /**
     * Lee un fichero .md y lo cachea inteligentemente
     */
    private function leerFichero($nombreFichero)
    {
        $ruta = $this->obtenerRutaBase() . '/' . $nombreFichero . '.md';
        
        if (!file_exists($ruta)) {
            error_log('⚠️ [BOT] Fichero no encontrado: ' . $ruta);
            return '';
        }
        
        $modTime = filemtime($ruta);
        $cacheKey = $nombreFichero;
        
        // Si está en caché y NO cambió, devolverlo
        if (isset($this->cache[$cacheKey]) && isset($this->fileModTimes[$cacheKey])) {
            if ($this->fileModTimes[$cacheKey] === $modTime) {
                error_log('✅ [BOT] Usando caché: ' . $nombreFichero);
                return $this->cache[$cacheKey];
            }
        }
        
        // Fichero cambió o es nuevo → recargar
        error_log('🔄 [BOT] Recargando: ' . $nombreFichero . ' (cambio detectado)');
        $contenido = file_get_contents($ruta);
        
        // Guardar en caché con su timestamp
        $this->cache[$cacheKey] = $contenido;
        $this->fileModTimes[$cacheKey] = $modTime;
        
        return $contenido;
    }
    
    /**
     * Extrae secciones de markdown
     */
    private function extraerSeccion($contenido, $titulo = '')
    {
        if ($titulo === '') {
            return trim($contenido);  // Todo el fichero
        }
        
        $lineas = explode("\n", $contenido);
        $resultado = [];
        $encontrado = false;
        
        foreach ($lineas as $linea) {
            // Detectar encabezado
            if (preg_match('/^#\s+/', $linea)) {
                if ($encontrado) break;  // Fin de sección
                if (stripos($linea, $titulo) !== false) {
                    $encontrado = true;
                    continue;
                }
            }
            
            if ($encontrado && trim($linea) !== '') {
                $resultado[] = trim($linea);
            }
        }
        
        return implode("\n", $resultado);
    }
    
    /**
     * Lee configuración y devuelve array clave => valor
     */
    public function obtenerConfiguracion()
    {
        $contenido = $this->leerFichero('config');
        $config = [];
        
        foreach (explode("\n", $contenido) as $linea) {
            if (preg_match('/^([a-z_]+):\s*(.+)$/', trim($linea), $matches)) {
                $config[trim($matches[1])] = trim($matches[2]);
            }
        }
        
        return $config;
    }
    
    /**
     * Construye prompt completo desde ficheros .md
     */
    public function construirPromptDinamico($conversacion, $nombreCliente)
    {
        error_log('📦 [BOT] Construyendo prompt dinámico...');
        
        // Leer todas las secciones (detecta cambios automáticamente)
        $config = $this->obtenerConfiguracion();
        $personalidad = $this->leerFichero('personalidad');
        $instrucciones = $this->leerFichero('instrucciones');
        $reglas = $this->leerFichero('reglas');
        $ejemplos = $this->extraerSeccion($this->leerFichero('ejemplos'), 'EJEMPLO');
        //$baseConocimiento = $this->leerFichero('base_conocimiento');
        // $flujos = $this->leerFichero('flujos_corto');  // TEMPORALMENTE DESHABILITADO para evitar JSON roto
        
        // Preparar conversación
        $conversacionTexto = implode("\n", $conversacion);
        $maxCaracteres = $config['max_caracteres'] ?? '250';
        
        // Construir prompt final CON base de conocimiento inyectada
        $prompt = <<<PROMPT
        {$personalidad}

        CONTEXTO
        Cliente: {$nombreCliente}
        Canal: Kommo (chat en línea)

        CONVERSACIÓN RECIENTE
        {$conversacionTexto}

        INSTRUCCIONES
        {$instrucciones}

        RESTRICCIONES
        {$reglas}

       

        

        LÍMITE: Máximo {$maxCaracteres} caracteres

        EJEMPLO
        {$ejemplos}

        Responde SOLO el texto de la sugerencia, sin explicaciones. Máximo {$maxCaracteres} caracteres:
        PROMPT;

        error_log('✅ [BOT] Prompt construido desde ficheros .md (incluyendo base de conocimiento)');
        return $prompt;
    }
}
?>