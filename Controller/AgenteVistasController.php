<?php

namespace AppBundle\Controller;

use AppBundle\Controller\IArtificalController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * AgenteVistasController
 *
 * Agente de Inteligencia Artificial especializado en la gestión de vistas Twig:
 * - Lista las vistas existentes en Resources/views/
 * - Genera nuevas vistas Twig a partir de una descripción en lenguaje natural
 * - Modifica vistas existentes aplicando instrucciones en lenguaje natural
 * - Guarda los ficheros resultantes en la ruta correspondiente
 */
class AgenteVistasController extends Controller
{
    /** Directorio raíz de las vistas de la aplicación */
    private function getViewsDir(): string
    {
        return dirname(__DIR__) . '/Resources/views/';
    }

    /**
     * Listado de vistas disponibles y punto de entrada del agente.
     */
    public function indexAction()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $vistas = $this->listarVistas($this->getViewsDir(), $this->getViewsDir());

        return $this->render('@App/Backoffice/Lista/AgenteVistas.html.twig', [
            'titulo' => 'Agente de Vistas IA',
            'vistas' => $vistas,
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva vista con ayuda de IA.
     * POST: Genera el código con IA y guarda el fichero.
     */
    public function crearAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $error    = null;
        $codigo   = null;
        $guardado = false;

        if ($request->isMethod('POST')) {
            $descripcion = trim($request->request->get('descripcion', ''));
            $rutaRelativa = trim($request->request->get('ruta', ''));
            $accion = $request->request->get('accion', 'generar');

            if ($descripcion === '') {
                $error = 'La descripción no puede estar vacía.';
            } elseif ($rutaRelativa === '') {
                $error = 'Indica la ruta relativa donde guardar la vista (p.ej. Backoffice/Lista/MiVista.html.twig).';
            } else {
                $codigo = $request->request->get('codigo_generado', '');

                if ($accion === 'generar' || $codigo === '') {
                    $codigo = $this->generarVistaTwig($descripcion);
                    if (!$codigo) {
                        $error = 'La IA no pudo generar el código. Verifica la configuración del proveedor IA.';
                    }
                }

                if ($accion === 'guardar' && $codigo !== '') {
                    $resultado = $this->guardarVista($rutaRelativa, $codigo);
                    if ($resultado === true) {
                        $guardado = true;
                        $this->addFlash('success', "Vista «{$rutaRelativa}» creada correctamente.");
                        return $this->redirectToRoute('agente_vistas_index');
                    } else {
                        $error = $resultado;
                    }
                }
            }
        }

        return $this->render('@App/Backoffice/AgregarModificar/AgenteVistas.html.twig', [
            'titulo'    => 'Crear nueva vista con IA',
            'modo'      => 'crear',
            'error'     => $error,
            'codigo'    => $codigo,
            'guardado'  => $guardado,
            'ruta'      => $request->request->get('ruta', ''),
            'descripcion' => $request->request->get('descripcion', ''),
        ]);
    }

    /**
     * Muestra el formulario para modificar una vista existente con ayuda de IA.
     * POST: Aplica las instrucciones con IA y guarda los cambios.
     */
    public function modificarAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $error    = null;
        $codigo   = null;
        $guardado = false;
        $rutaRelativa = trim($request->get('ruta', ''));

        $codigoActual = '';
        if ($rutaRelativa !== '') {
            $codigoActual = $this->leerVista($rutaRelativa);
            if ($codigoActual === null) {
                $error = "No se encontró la vista «{$rutaRelativa}».";
                $codigoActual = '';
            }
        }

        if ($request->isMethod('POST')) {
            $instrucciones = trim($request->request->get('instrucciones', ''));
            $accion = $request->request->get('accion', 'generar');

            if ($rutaRelativa === '') {
                $error = 'Selecciona una vista existente.';
            } elseif ($instrucciones === '') {
                $error = 'Las instrucciones de modificación no pueden estar vacías.';
            } else {
                $codigo = $request->request->get('codigo_generado', '');

                if ($accion === 'generar' || $codigo === '') {
                    $codigo = $this->modificarVistaTwig($codigoActual, $instrucciones);
                    if (!$codigo) {
                        $error = 'La IA no pudo modificar el código. Verifica la configuración del proveedor IA.';
                    }
                }

                if ($accion === 'guardar' && $codigo !== '') {
                    $resultado = $this->guardarVista($rutaRelativa, $codigo);
                    if ($resultado === true) {
                        $guardado = true;
                        $this->addFlash('success', "Vista «{$rutaRelativa}» modificada correctamente.");
                        return $this->redirectToRoute('agente_vistas_index');
                    } else {
                        $error = $resultado;
                    }
                }
            }
        }

        return $this->render('@App/Backoffice/AgregarModificar/AgenteVistas.html.twig', [
            'titulo'       => 'Modificar vista con IA',
            'modo'         => 'modificar',
            'error'        => $error,
            'codigo'       => $codigo,
            'codigoActual' => $codigoActual,
            'guardado'     => $guardado,
            'ruta'         => $rutaRelativa,
            'instrucciones' => $request->request->get('instrucciones', ''),
        ]);
    }

    /**
     * Endpoint AJAX: devuelve el código de una vista existente.
     */
    public function obtenerCodigoAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $ruta = trim($request->query->get('ruta', ''));
        if ($ruta === '') {
            return new JsonResponse(['error' => 'Ruta no indicada.'], 400);
        }

        $codigo = $this->leerVista($ruta);
        if ($codigo === null) {
            return new JsonResponse(['error' => "Vista «{$ruta}» no encontrada."], 404);
        }

        return new JsonResponse(['codigo' => $codigo]);
    }

    // -------------------------------------------------------------------------
    // Métodos privados de ayuda
    // -------------------------------------------------------------------------

    /** Instancia de IArtificalController reutilizada en la misma petición */
    private ?IArtificalController $iaController = null;

    /**
     * Obtiene (o crea) la instancia de IArtificalController con el contenedor inyectado.
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
     * Devuelve la lista de ficheros .twig bajo $baseDir, con ruta relativa a $baseDir.
     */
    private function listarVistas(string $dir, string $baseDir): array
    {
        $resultado = [];
        if (!is_dir($dir)) {
            return $resultado;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fichero) {
            if ($fichero->isFile() && preg_match('/\.twig$/i', $fichero->getFilename())) {
                $rutaRelativa = str_replace($baseDir, '', $fichero->getPathname());
                $rutaRelativa = ltrim(str_replace('\\', '/', $rutaRelativa), '/');
                $resultado[]  = $rutaRelativa;
            }
        }

        sort($resultado);
        return $resultado;
    }

    /**
     * Lee el contenido de una vista dado su path relativo al directorio de vistas.
     * Devuelve null si no existe o si la ruta es inválida.
     */
    private function leerVista(string $rutaRelativa): ?string
    {
        $rutaAbsoluta = $this->resolverRutaSegura($rutaRelativa);
        if ($rutaAbsoluta === null || !is_file($rutaAbsoluta)) {
            return null;
        }

        return file_get_contents($rutaAbsoluta) ?: null;
    }

    /**
     * Guarda $codigo en la ruta indicada (relativa al directorio de vistas).
     * Devuelve true en caso de éxito o un mensaje de error como string.
     *
     * @return true|string
     */
    private function guardarVista(string $rutaRelativa, string $codigo)
    {
        $rutaAbsoluta = $this->resolverRutaSegura($rutaRelativa);
        if ($rutaAbsoluta === null) {
            return 'Ruta no válida o fuera del directorio de vistas permitido.';
        }

        $directorio = dirname($rutaAbsoluta);
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, true)) {
            return "No se pudo crear el directorio «{$directorio}».";
        }

        if (@file_put_contents($rutaAbsoluta, $codigo) === false) {
            return "No se pudo escribir el fichero «{$rutaAbsoluta}». Verifica los permisos.";
        }

        return true;
    }

    /**
     * Resuelve la ruta absoluta de forma segura: asegura que el destino final
     * esté dentro del directorio de vistas de la aplicación.
     */
    private function resolverRutaSegura(string $rutaRelativa): ?string
    {
        $base = realpath($this->getViewsDir());
        if ($base === false) {
            return null;
        }

        // Permitir únicamente caracteres seguros: letras, dígitos, guiones, puntos y barras
        if (!preg_match('#^[a-zA-Z0-9_\-./]+$#', $rutaRelativa)) {
            return null;
        }

        // Eliminar cualquier componente ".." y barras iniciales
        $partes = explode('/', str_replace('\\', '/', $rutaRelativa));
        $partesSanitizadas = [];
        foreach ($partes as $parte) {
            if ($parte === '' || $parte === '.') {
                continue;
            }
            if ($parte === '..') {
                // Ignorar cualquier salto de directorio padre
                return null;
            }
            $partesSanitizadas[] = $parte;
        }

        if (empty($partesSanitizadas)) {
            return null;
        }

        $rutaAbsoluta = $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $partesSanitizadas);

        // Verificar que el directorio padre existe y sigue dentro del directorio base;
        // si no existe todavía (nueva vista), verificar al menos el prefijo de ruta.
        $dirPadre = dirname($rutaAbsoluta);
        $dirPadreReal = is_dir($dirPadre) ? realpath($dirPadre) : null;

        if ($dirPadreReal !== null) {
            // El directorio ya existe: verificar con realpath
            if (strpos($dirPadreReal . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR) !== 0) {
                return null;
            }
        } else {
            // El directorio aún no existe: verificar con prefijo de cadena
            if (strpos($dirPadre . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR) !== 0) {
                return null;
            }
        }

        return $rutaAbsoluta;
    }

    /**
     * Pide a la IA que genere una vista Twig partiendo de una descripción.
     */
    private function generarVistaTwig(string $descripcion): ?string
    {
        $systemPrompt = <<<'PROMPT'
Eres un experto en Symfony y Twig. Tu única misión es generar plantillas Twig válidas para una aplicación Symfony con Bootstrap 4.

REGLAS:
1. Devuelve ÚNICAMENTE el código Twig, sin explicaciones, sin bloques de código Markdown, sin ```twig ni ```.
2. La plantilla debe extender '@App/Backoffice/base.html.twig' salvo que el usuario indique lo contrario.
3. Usa clases de Bootstrap 4 (card, table, form-group, btn, etc.).
4. Respeta la estructura de bloques: {% block body %} para el contenido principal.
5. Escribe el código en español salvo que se pida otro idioma.
PROMPT;

        $mensaje = "Genera una plantilla Twig con la siguiente descripción:\n\n{$descripcion}";

        return $this->llamarIA($mensaje, $systemPrompt);
    }

    /**
     * Pide a la IA que modifique una vista Twig existente según las instrucciones.
     */
    private function modificarVistaTwig(string $codigoActual, string $instrucciones): ?string
    {
        $systemPrompt = <<<'PROMPT'
Eres un experto en Symfony y Twig. Tu única misión es modificar plantillas Twig existentes aplicando las instrucciones del usuario.

REGLAS:
1. Devuelve ÚNICAMENTE el código Twig modificado y completo, sin explicaciones, sin bloques Markdown.
2. Conserva toda la estructura y bloques originales a menos que se pida cambiarlos explícitamente.
3. Usa clases de Bootstrap 4.
4. Aplica SOLO los cambios solicitados; no añadas ni elimines código no relacionado con la petición.
PROMPT;

        $mensaje = "Esta es la vista Twig actual:\n\n{$codigoActual}\n\nInstrucciones de modificación:\n{$instrucciones}";

        return $this->llamarIA($mensaje, $systemPrompt);
    }

    /**
     * Llama al proveedor de IA configurado (Gemini u OpenAI) a través del IArtificalController.
     */
    private function llamarIA(string $mensaje, string $systemPrompt): ?string
    {
        $respuesta = $this->getIAController()->llamarAPIIA($mensaje, $systemPrompt);

        if ($respuesta) {
            // Limpiar posibles bloques Markdown que el modelo pueda incluir
            $respuesta = preg_replace('/^```(?:twig|html)?\s*/i', '', $respuesta);
            $respuesta = preg_replace('/\s*```$/', '', $respuesta);
        }

        return $respuesta ?: null;
    }
}
