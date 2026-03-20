<?php

namespace AppBundle\Controller;

use AppBundle\Services\GoogleSheetsService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use DateTime;
use Symfony\Component\Routing\RouterInterface;
use AppBundle\Entity\CampoHitoExpediente;
use AppBundle\Entity\CampoHito;
use AppBundle\Entity\Fase;
use AppBundle\Entity\Hito;
use AppBundle\Entity\GrupoCamposHito as GrupoCampos;
use AppBundle\Entity\CampoHitoExpedienteColaboradores as CampoHitoExpedienteColaboradoresEntidad;
use AppBundle\Entity\FicheroCampo as FicheroCampoEntidad;
use AppBundle\Entity\Expediente;
use AppBundle\Entity\OpcionesCampo;
use AppBundle\Entity\HitoExpediente;
use AppBundle\Entity\GrupoHitoExpediente;
use AppBundle\Entity\FicheroCampo;
use AppBundle\Entity\Notificacion as Notificacion;
use AppBundle\Entity\RegistrarActividad;
use AppBundle\Entity\Usuario;
use AppBundle\Entity\Dispositivo;
use AppBundle\Entity\ImagenFichero;
use AppBundle\Entity\ConceptoSeguimientoExpediente;
use AppBundle\Entity\SincronizacionSheets;
use AppBundle\Entity\VistaComercialesExpedientes;
use AppBundle\Entity\VistaExpedientesRelacionados;
use AppBundle\Utils\UsuariosNombreCompleto;
use DateTimeImmutable;

class SheetsController extends Controller
{
    public function leerHojasAction()
    {
        // GoogleSheetsService inyección desactivada por incompatibilidad
        
        /*
        $sheetsService = $this->get(GoogleSheetsService::class);
        $hojas = $sheetsService->getSheetNames();
        $datos = [];

        $contadorHojas = 0;

        foreach ($hojas as $hoja) {
            $contadorHojas++;
            $datos[$hoja] = $sheetsService->readSheet($hoja);
            // if ($contadorHojas == 2) {
            //     break;
            // }
        }

        return new Response('<pre>' . print_r($datos, true) . '</pre>');
        */
        
        return new Response('Google Sheets está desactivado temporalmente.', 503);
    }

    public function sincronizarVariasHojasAction()
    {
        // GoogleSheetsService inyección desactivada por incompatibilidad
        
        /*
        $em = $this->getDoctrine()->getManager();
        $sheetsService = $this->get(GoogleSheetsService::class);
        $hojas = $sheetsService->getSheetNames();

        $rango = 'A:O';

        $datos = $sheetsService->getFilteredRecordsAndUpdateSyncDate($em, $hojas, $rango);


        foreach ($datos as $clavePrimerNivel => $hoja) {
            foreach ($hoja as $registro) {
                $this->simulacionExpedienteAction($registro, $clavePrimerNivel);
                // Paramos con una ejecución para las pruebas
                // die;
            }
        }

        return new Response('<pre>OK</pre>');
        */
        
        return new Response('Google Sheets está desactivado temporalmente.', 503);
    }

    public function simulacionExpedienteAction($registro = [], $clavePrimerNivel = "")
    {

        $doctrine = $this->getDoctrine();
        // Ahora creamos el expediente para este cliente
        $repositorios = array(
            'Fases' => $doctrine->getRepository('AppBundle:Fase'),
            'Usuarios' => $doctrine->getRepository('AppBundle:Usuario')
        );
        $fase = $repositorios['Fases']->findOneBy(
            array(
                'tipo' => 0
            )
        );


        //  Esta es la manera teniendo en cuenta la asignación por capacidad y numero de exp asignados
        // $comercial = $this->getDoctrine()->getRepository(VistaComercialesExpedientes::class)->createQueryBuilder('v')
        //     ->orderBy('v.numExpedientes', 'ASC')              // Ordena según prefieras
        //     ->addOrderBy('v.numDisponibles', 'DESC')
        //     ->setMaxResults(1)
        //     ->getQuery()
        //     ->getOneOrNullResult();

        // Esta es la versión rotativa de uno en uno
        // $repo = $this->getDoctrine()->getRepository(\AppBundle\Entity\VistaRotacionComerciales::class);

        // $comercial = $repo->createQueryBuilder('v')
        //     ->orderBy('v.ultimaAsignacion', 'ASC')  // Ya incluye comerciales sin expediente al principio
        //     ->setMaxResults(1)
        //     ->getQuery()
        //     ->getOneOrNullResult();

        // if ($comercial != null) {
        //     $comercial = $this->getDoctrine()->getRepository(Usuario::class)->findOneBy(
        //         array(
        //             'idUsuario' => $comercial->getIdUsuario()
        //         )
        //     );
        // }

        // Versión para asignación a Sheila todos los de Sheets como técnico
        $tecnico = $this->getDoctrine()->getRepository(Usuario::class)->findOneBy(
            array(
                'idUsuario' => 1169
            )
        );

        $expediente = (new Expediente())
            ->setEstado(1)
            ->setIdFaseActual($fase)
            ->setVivienda(' ')
            // ->setIdComercial($comercial)
            ->setIdTecnico($tecnico)
            ->setFechaCreacion(new DateTime());
        $managerEntidad = $doctrine->getManager();

        $fases = $doctrine->getRepository(Fase::class)->findBy(array(), array(
            'orden' => 'ASC'
        ));

        $opcionCampo = $doctrine->getRepository(OpcionesCampo::class)->findOneBy(array(
            'idOpcionesCampo' => '663'
        ));

        $ultimaFecha = '';

        foreach ($fases as $fase) {
            $hitos = $doctrine->getRepository(Hito::class)->findBy(array(
                'idFase' => $fase
            ), array(
                'orden' => 'ASC'
            ));
            foreach ($hitos as $hito) {
                $hitoExpediente = (new HitoExpediente())
                    ->setIdHito($hito)
                    ->setIdExpediente($expediente)
                    ->setFechaModificacion(new DateTime())
                    ->setEstado(0);

                $gruposCamposHito = $doctrine->getRepository(GrupoCampos::class)->findBy(array(
                    'idHito' => $hito
                ), array(
                    'orden' => 'ASC'
                ));

                foreach ($gruposCamposHito as $grupoCamposHito) {

                    $grupoHitoExpediente = (new GrupoHitoExpediente())
                        ->setIdHitoExpediente($hitoExpediente)
                        ->setIdGrupoCamposHito($grupoCamposHito);

                    $camposHito = $doctrine->getRepository(CampoHito::class)->findBy(array(
                        'idGrupoCamposHito' => $grupoCamposHito
                    ), array(
                        'orden' => 'ASC'
                    ));

                    foreach ($camposHito as $campoHito) {
                        $campoHitoExpediente = (new CampoHitoExpediente())
                            ->setIdCampoHito($campoHito)
                            ->setIdHitoExpediente($hitoExpediente)
                            ->setIdGrupoHitoExpediente($grupoHitoExpediente)
                            ->setIdExpediente($expediente)
                            ->setFechaModificacion(new DateTime());

                        if ($campoHito->getTipo() == 4) {
                            $campoHitoExpediente->setObligatorio(1)
                                ->setSolicitarAlColaborador(1);
                        }
                        // Asignar valor según idCampoHito
                        switch ($campoHito->getIdCampoHito()) {
                            // PRIMERO LOS CAMPOS DEL SHEET
                            case 688: // Fecha del Lead
                                $campoHitoExpediente->setValor($registro['Fecha del Lead']);
                                $fechaRegistro = new \DateTime($registro['Fecha del Lead']);
                                $ultimaFecha = $fechaRegistro;
                                break;
                            case 693: // Nombre
                                $campoHitoExpediente->setValor($registro['Nombre']);
                                break;
                            case 694: // Apellidos
                                $campoHitoExpediente->setValor($registro['Apellidos']);
                                break;
                            case 695: // Teléfono
                                $campoHitoExpediente->setValor($registro['Teléfono']);
                                break;
                            case 696: // Email
                                $campoHitoExpediente->setValor($registro['Email ']);
                                break;
                            case 689: // Provincia
                                $campoHitoExpediente->setValor($registro['Provincia']);
                                break;
                            case 690: // Trabajo o Estado Laboral
                                $campoHitoExpediente->setValor($registro['Trabajo o Estado Laboral']);
                                break;
                            case 702: // Interesado compraventa o cambio de hipoteca
                                $campoHitoExpediente->setValor($registro['Interesado compraventa o cambio de hipoteca']);
                                break;
                            case 692: // ¿Tienes ya el inmueble?
                                $campoHitoExpediente->setValor($registro['¿Tienes ya el inmueble?']);
                                break;
                            case 691: // Valor del Inmueble
                                $campoHitoExpediente->setValor($registro['Valor del Inmueble']);
                                break;
                            case 697: // En qué ciudad está el inmueble
                                $campoHitoExpediente->setValor($registro['En qué ciudad está el inmueble']);
                                break;
                            case 698: // Menor de 35 años?
                                $campoHitoExpediente->setValor($registro['Menor de 35 años?']);
                                break;
                            case 699: // Cuanto ahorro aportas
                                $campoHitoExpediente->setValor($registro['Cuanto ahorro aportas']);
                                break;
                            case 700: // Observaciones
                                $campoHitoExpediente->setValor($registro['Observaciones']);
                                break;
                            case 701: // Creatividad
                                $campoHitoExpediente->setValor($registro['Creatividad']);
                                break;
                            case 704: // Campaña
                                $campoHitoExpediente->setValor($clavePrimerNivel);
                                break;


                            // AHORA LOS DEL FORMULARIO
                            case 192: // Nombre y Apellidos
                                $campoHitoExpediente->setValor($registro['Nombre'] . ' ' . $registro['Apellidos']);
                                break;
                            case 407: // Email
                                $campoHitoExpediente->setValor($registro['Email ']);
                                break;
                            case 408: // Teléfono
                                $campoHitoExpediente->setValor($registro['Teléfono']);
                                break;
                            case 673: // Origen Capta
                                $campoHitoExpediente->setIdOpcionesCampo($opcionCampo);
                                break;
                            default:
                                $campoHitoExpediente->setValor(''); // vacío para resto
                        }
                        $managerEntidad->persist($campoHitoExpediente);
                    }

                    $managerEntidad->persist($grupoHitoExpediente);
                }

                $managerEntidad->persist($hitoExpediente);
            }
        }


        $managerEntidad->persist($expediente);

        $parametros = $managerEntidad->getRepository(SincronizacionSheets::class)->findOneBy(['nombreCampania' => $clavePrimerNivel]);
        $parametros->setFechaSincronizacionSheets(DateTimeImmutable::createFromMutable($ultimaFecha));
        $managerEntidad->persist($parametros);

        $managerEntidad->flush();

        // Buscar expedientes relacionados desde la entidad de la vista
        $relacion = $managerEntidad->getRepository(VistaExpedientesRelacionados::class)
            ->find($expediente->getIdExpediente());

        if ($relacion && $relacion->getIdsExpedientesRelacionados()) {
            // Tomar el primer id de expediente relacionado
            $idsRelacionados = explode(',', $relacion->getIdsExpedientesRelacionados());
            $idRelacionado = reset($idsRelacionados);

            // Obtener expediente relacionado
            $expRel = $managerEntidad->getRepository(Expediente::class)->find($idRelacionado);

            if ($expRel && $expRel->getIdComercial()) {
                // Actualizar el comercial del nuevo expediente
                $expediente->setIdComercial($expRel->getIdComercial());
                $managerEntidad->persist($expediente);
                $managerEntidad->flush();
            }
        }

        // Vista minima o return correcto
        return new Response('<h2>Expediente y campos simulados insertados correctamente</h2>');
    }
}
