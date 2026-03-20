<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AgenteColaborador as AgenteColaboradorEntidad;
use AppBundle\Entity\CampoHito as CampoHitoEntidad;
use AppBundle\Entity\CampoHitoExpediente as CampoHitoExpedienteEntidad;
use AppBundle\Entity\CampoHitoExpedienteColaboradores as CampoHitoExpedienteColaboradoresEntidad;
use AppBundle\Entity\Dispositivo as DispositivoEntidad;
use AppBundle\Entity\EntidadColaboradora as EntidadColaboradoraEntidad;
use AppBundle\Entity\Expediente as ExpedienteEntidad;
use AppBundle\Entity\Fase as FaseEntidad;
use AppBundle\Entity\FicheroCampo as FicheroCampoEntidad;
use AppBundle\Entity\ImagenFichero as ImagenFicheroEntidad;
use AppBundle\Entity\GrupoCamposHito as GrupoCamposHitoEntidad;
use AppBundle\Entity\GrupoHitoExpediente as GrupoHitoExpedienteEntidad;
use AppBundle\Entity\Hito as HitoEntidad;
use AppBundle\Entity\HitoExpediente as HitoExpedienteEntidad;
use AppBundle\Entity\Notificacion as NotificacionEntidad;
use AppBundle\Entity\OpcionesCampo as OpcionesCampoEntidad;
use AppBundle\Entity\RegistrarActividad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Form\CampoHitoExpedienteBanco as CampoHitoExpedienteBancoFormulario;
use AppBundle\Form\CampoHitoExpedienteColaboradores as CampoHitoExpedienteColaboradoresFormulario;
use AppBundle\Form\CampoHitoExpedienteDesplegable as CampoHitoExpedienteDesplegableFormulario;
use AppBundle\Form\CampoHitoExpedienteEmail as CampoHitoExpedienteEmailFormulario;
use AppBundle\Form\CampoHitoExpedienteFecha as CampoHitoExpedienteFechaFormulario;
use AppBundle\Form\CampoHitoExpedienteFichero as CampoHitoExpedienteFicheroFormulario;
use AppBundle\Form\CampoHitoExpedienteNotaria as CampoHitoExpedienteNotariaFormulario;
use AppBundle\Form\CampoHitoExpedienteTasadora as CampoHitoExpedienteTasadoraFormulario;
use AppBundle\Form\CampoHitoExpedienteTexto as CampoHitoExpedienteTextoFormulario;
use AppBundle\Form\Expediente as ExpedienteFormulario;
use AppBundle\Form\FicheroCampo as FicheroCampoFormulario;
use AppBundle\Form\HitoExpediente as HitoExpedienteFormulario;
use AppBundle\Form\SeguimientoExpediente as FormularioSeguimientoExpediente;
use AppBundle\Entity\SeguimientoExpediente as SeguimientoExpedienteEntidad;
use AppBundle\Entity\ConceptoSeguimientoExpediente as ConceptoSeguimientoExpedienteEntidad;
use AppBundle\Entity\VistaComercialesExpedientes;
use AppBundle\Entity\VistaExpedientesRelacionados;
use AppBundle\Utils\UsuariosNombreCompleto;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use ReflectionClass;
use ReflectionException;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use PDFMerger;
use Swift_SmtpTransport;

class WebController extends Controller
{
	public function nuevoRegistroAction(Request $request, RouterInterface $router, Swift_Mailer $mailer)
	{
		$irADocumentos = false;

		$doctrine = $this->getDoctrine();
		$managerEntidad = $doctrine->getManager();
		$primeraFase = $doctrine->getRepository(FaseEntidad::class)->findOneBy(array(
			'orden' => 1
		));
		if ($primeraFase) {
			$expediente = (new ExpedienteEntidad())
				->setIdFaseActual($primeraFase);
			$fasePrevia = $primeraFase;
		} else {
			$this->addFlash('warning', 'No hay fases.');
			return $this->redirectToRoute('lista_expedientes');
		}
		$formularioExpedienteOpciones = array(
			'esAdmin' => false,
			'colaboradores' => $doctrine->getRepository(UsuarioEntidad::class)->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('role', 'ROLE_COLABORADOR'))
					->andWhere(Criteria::expr()->orX(
						Criteria::expr()->gte('fechaCaducidad', new DateTime()),
						Criteria::expr()->isNull('fechaCaducidad')
					))
					->andWhere(Criteria::expr()->eq('estado', true))
			),
			'comerciales' => $doctrine->getRepository(UsuarioEntidad::class)->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('role', 'ROLE_COMERCIAL'))
					->andWhere(Criteria::expr()->orX(
						Criteria::expr()->gte('fechaCaducidad', new DateTime()),
						Criteria::expr()->isNull('fechaCaducidad')
					))
					->andWhere(Criteria::expr()->eq('estado', true))
			),
			'tecnicos' => $doctrine->getRepository(UsuarioEntidad::class)->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('role', 'ROLE_TECNICO'))
					->andWhere(Criteria::expr()->orX(
						Criteria::expr()->gte('fechaCaducidad', new DateTime()),
						Criteria::expr()->isNull('fechaCaducidad')
					))
					->andWhere(Criteria::expr()->eq('estado', true))
			)
		);

		$formularioExpedienteOpciones['clientes'] = $doctrine->getRepository(UsuarioEntidad::class)->findBy(array(
			'idUsuario' => 3, //Cliente WEB
			'role' => 'ROLE_CLIENTE'
		));
		$formularioExpedienteOpciones['nuevo'] = !isset($id);
		$formulariosValidos = true;
		$fases = $doctrine->getRepository(FaseEntidad::class)->findBy(array(
			'tipo' => 0
		), array(
			'orden' => 'ASC'
		));
		$formularioExpedienteOpciones['fases'] = $fases;
		$formularioExpediente = $this->createForm(ExpedienteFormulario::class, $expediente, $formularioExpedienteOpciones);
		$formularioExpediente->handleRequest($request);

		$expediente->setIdFaseActual($fasePrevia);

		$arraySeguimientosFase = array();
		$arrayFases = array();

		$fasesSeguimientos = $doctrine->getRepository(ConceptoSeguimientoExpedienteEntidad::class)->findBy(
			array(),
			array(
				'orden' => 'ASC'
			)
		);
		$fase_actual = "";
		foreach ($fasesSeguimientos as $fase) {
			if ($fase->getFase() != $fase_actual) {
				$arrayFases[] = $fase->getFase();
			}
			$fase_actual = $fase->getFase();
		}
		foreach ($arrayFases as $fase) {
			$arrayFormularioSeguimientoExpediente = array();
			$conceptosFase = $doctrine->getRepository(ConceptoSeguimientoExpedienteEntidad::class)->findBy(array(
				'fase' => $fase
			));
			$seguimientos = $doctrine->getRepository(SeguimientoExpedienteEntidad::class)->findBy(array(
				'idExpediente' => $expediente,
				'idConceptoSeguimientoExpediente' => $conceptosFase
			));


			if ($expediente->getIdExpediente() > 0 && count($seguimientos) == 0) {
				$conceptos = $doctrine->getRepository(ConceptoSeguimientoExpedienteEntidad::class)->findBy(
					array(
						'idConceptoSeguimientoExpediente' => $conceptosFase
					),
					array(
						'orden' => 'ASC'
					)
				);

				foreach ($conceptos as $concepto) {
					$seguimiento = (new SeguimientoExpedienteEntidad())
						->setIdExpediente($expediente)
						->setIdConceptoSeguimientoExpediente($concepto);
					$managerEntidad->persist($seguimiento);
					$managerEntidad->flush();
					$formularioSeguimientoExpediente = $this->createForm(FormularioSeguimientoExpediente::class, $seguimiento);
					$arrayFormularioSeguimientoExpediente[] = $formularioSeguimientoExpediente->createView();
				}
			} else {
				foreach ($seguimientos as $seguimiento) {
					$formularioSeguimientoExpediente = $this->createForm(FormularioSeguimientoExpediente::class, $seguimiento);
					$arrayFormularioSeguimientoExpediente[] = $formularioSeguimientoExpediente->createView();
				}
			}
			$arraySeguimientosFase[$fase] = $arrayFormularioSeguimientoExpediente;
		}

		if ($formularioExpediente->isSubmitted() && ($request->request->get('campo_hito_expediente_0_407_0')["valor"] == "" || $request->request->get('campo_hito_expediente_0_408_0')["valor"] == "" || $request->request->get('campo_hito_expediente_0_409_0')["idOpcionesCampo"] != "199" )){
		
			$formulariosValidos = false;
			$this->addFlash('danger', 'Es necesario rellenar tu email y teléfono para poder continuar.');
		}

		$faseActual = $expediente->getIdFaseActual();

		$variablesTwig = array(
			'titulo' => 'Nuevo registro Web',
			'migasPan' => array(
				array(
					'vista' => 'Lista de expedientes',
					'url' => $router->generate('lista_expedientes')
				)
			),
			'agregarModificarExpediente' => $formularioExpediente->createView(),
			'arraySeguimientosFase' => $arraySeguimientosFase,
			'idExpediente' => '',
			'idCliente' => '3',
			'irADocumentos' => $irADocumentos,
			'fases' => $fases,
			'faseActual' => $faseActual
		);
		$formulariosHitoExpediente = $formulariosCampoHitoExpediente = $formulariosCampoHitoExpedienteColaboradores = $formulariosFicheroCampo = $formulariosHitoExpedienteModelo = $formulariosCampoHitoExpedienteModelo = $formulariosCampoHitoExpedienteColaboradoresModelo = $formulariosFicheroCampoModelo = $nombresFichero = $gruposCamposHito = $gruposHitoExpediente = $camposHito = $hitosExpedienteABorrar = $gruposHitosExpediente = $gruposHitoExpedientes = $gruposHitoExpedienteABorrar = $nuevosHitosRepetidos = array();

		$idsHitosExpedienteABorrar = $request->get('ids-hitos-expedientes-borrar');
		$idsHitosExpedienteABorrar = explode(",", $idsHitosExpedienteABorrar);

		$misHitosExpedienteABorrar = $doctrine->getRepository(HitoExpedienteEntidad::class)->findBy(array(
			'idHitoExpediente' => $idsHitosExpedienteABorrar
		));
		foreach ($misHitosExpedienteABorrar as $miHitoExpedienteABorrar) {
			$hitosExpedienteABorrar[] = $miHitoExpedienteABorrar;
		}
		$misGruposHitosExpedienteABorrar = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
			'idHitoExpediente' => $misHitosExpedienteABorrar
		));



		if (count($idsHitosExpedienteABorrar) > 0 && 1 == 2) {
			foreach ($idsHitosExpedienteABorrar as $idHitoExpedienteABorrar) {
				$hitoExpedienteABorrar = $doctrine->getRepository(HitoExpedienteEntidad::class)->findOneBy(array(
					'idHitoExpediente' => $idHitoExpedienteABorrar
				));
				if ($hitoExpedienteABorrar) {
					$gruposHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
						'idHitoExpediente' => $hitoExpedienteABorrar
					));
					$camposHitoExpediente = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findBy(array(
						'idHitoExpediente' => $hitoExpedienteABorrar
					));
					$camposHitoExpedienteColaboradores = $doctrine->getRepository(CampoHitoExpedienteColaboradoresEntidad::class)->findBy(array(
						'idHitoExpediente' => $hitoExpedienteABorrar
					));
					$ficherosCampoABorrar = $doctrine->getRepository(FicheroCampoEntidad::class)->findBy(array(
						'idCampoHitoExpediente' => $camposHitoExpediente
					));
					if (count($ficherosCampoABorrar) > 0) {
						$ficherosCampoAReemplazar = $doctrine->getRepository(FicheroCampoEntidad::class)->findBy(array(
							'idCampoHito' => $ficherosCampoABorrar[0]->getIdCampoHito(),
							'idExpediente' => $expediente
						));
						foreach ($ficherosCampoAReemplazar as $indice => $ficheroCampoAReemplazar) {
							if ($indice > 1) {
								$aux = $ficherosCampoAReemplazar[$indice - 1]->getNombreFichero();
								$ficherosCampoAReemplazar[$indice - 1]->setNombreFichero($ficheroCampoAReemplazar->getNombreFichero());
								$ficheroCampoAReemplazar->setNombreFichero($aux);
								$managerEntidad->persist($ficherosCampoAReemplazar[$indice - 1]);
								$managerEntidad->persist($ficheroCampoAReemplazar);
							}
						}
					}
					foreach ($ficherosCampoABorrar as $ficheroCampoABorrar) {
						if (!is_null($ficheroCampoABorrar->getNombreFichero())) {
							$filesystem = new Filesystem();
							try {
								$filesystem->remove($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampoABorrar->getNombreFichero());
							} catch (IOExceptionInterface $e) {
								$campoHito = $ficheroCampoABorrar->getIdCampoHito();
								$this->addFlash('danger', 'Error al borrar el archivo del campo hito "' . $campoHito . '" del expediente con id="' . $expediente->getIdExpediente() . '".');
								return false;
							}
						}
						$managerEntidad->remove($ficheroCampoABorrar);
					}

					foreach ($camposHitoExpedienteColaboradores as $campoHitoExpedienteColaboradores) {
						$managerEntidad->remove($campoHitoExpedienteColaboradores);
					}
					foreach ($camposHitoExpediente as $campoHitoExpediente) {
						$managerEntidad->remove($campoHitoExpediente);
					}
					foreach ($gruposHitoExpediente as $grupoHitoExpediente) {
						$managerEntidad->remove($grupoHitoExpediente);
					}

					// IMPORTANTE: NO BORRAMOS EL HITO POR SI HA AÑADIDO UNO DESPUES DE BORRARLO
					// $managerEntidad->remove($hitoExpedienteABorrar);
					// if ($hitoExpedienteABorrar->getIdHito()->getIdFase()->getTipo() === 0) {
					// 	if (!$existeRegistroActividadConEntidadFaseDatos) {
					// 		$existeRegistroActividadConEntidadFaseDatos = true;
					// 		$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
					// 	}
					// } else {
					// 	$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Eliminación del hito duplicado "' . $hitoExpedienteABorrar->getIdHito() . '"', $expediente));
					// }
				}
			}
		}






		$numeroHitosExpedienteDuplicadosArray = $request->get('numero-hitos-expediente-duplicados');
		// echo "Hitos Duplicados: <br>";
		// dump($numeroHitosExpedienteDuplicadosArray);
		$numeroGruposHitoExpedienteDuplicadosArray1 = $request->get('numero-grupos-hito-expediente-duplicados');
		$numeroGruposHitoExpedienteDuplicadosArray = array();
		if ($numeroGruposHitoExpedienteDuplicadosArray1) {
			foreach ($numeroGruposHitoExpedienteDuplicadosArray1 as $i => $valor) {
				// $numeroGruposHitoExpedienteDuplicadosArray[$i] = intval($valor)-1;
				$grupoHitExp = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findOneBy(array(
					'idGrupoHitoExpediente' => $i
				));
				if ($grupoHitExp) {
					if ($numeroGruposHitoExpedienteDuplicadosArray && isset($numeroGruposHitoExpedienteDuplicadosArray[$grupoHitExp->getIdHitoExpediente()->getIdHitoExpediente()]) && isset($numeroGruposHitoExpedienteDuplicadosArray[$grupoHitExp->getIdHitoExpediente()->getIdHitoExpediente()][$grupoHitExp->getIdGrupoCamposHito()->getIdGrupoCamposHito()])) {
						$numeroGruposHitoExpedienteDuplicadosArray[$grupoHitExp->getIdHitoExpediente()->getIdHitoExpediente()][$grupoHitExp->getIdGrupoCamposHito()->getIdGrupoCamposHito()] += intval($numeroGruposHitoExpedienteDuplicadosArray1[$i]);
					} else { //if ($numeroGruposHitoExpedienteDuplicadosArray && isset($numeroGruposHitoExpedienteDuplicadosArray[$grupoHitExp->getIdHitoExpediente()->getIdHitoExpediente()])){
						$numeroGruposHitoExpedienteDuplicadosArray[$grupoHitExp->getIdHitoExpediente()->getIdHitoExpediente()][$grupoHitExp->getIdGrupoCamposHito()->getIdGrupoCamposHito()] = intval($numeroGruposHitoExpedienteDuplicadosArray1[$i]);
						// }else{

					}
				}
			}
		}
		foreach ($numeroGruposHitoExpedienteDuplicadosArray as $i => $valor) {
			foreach ($numeroGruposHitoExpedienteDuplicadosArray[$i] as $j => $valor) {
				$numeroGruposHitoExpedienteDuplicadosArray[$i][$j] = intval($valor) - 1;
			}
		}

		$nuevosHitosRepetidos =  $request->get('nuevos-hitos-repetidos');


		$entidadesColaboradoras = $doctrine->getRepository(EntidadColaboradoraEntidad::class)->findBy(array(
			'estado' => true
		), array(
			'nombre' => 'ASC'
		));
		foreach ($fases as $fase) {
			$hitos[$fase->getIdFase()] = $doctrine->getRepository(HitoEntidad::class)->findBy(array(
				'idFase' => $fase
			), array(
				'orden' => 'ASC'
			));
			$variablesTwig['hitos'] = $hitos;
			foreach ($hitos[$fase->getIdFase()] as $hito) {
				$hitosExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findBy(array(
					'idHito' => $hito,
					'idExpediente' => $expediente
				));
				// if($hito->getIdHito() == 30){
				// 	dump($hitosExpediente);
				// 	die();
				// }
				$numeroHitosExpediente = count($hitosExpediente);
				if ($numeroHitosExpediente === 0) {
					// echo "ENTRA EN NUEVO";
					$hitosExpediente[] = (new HitoExpedienteEntidad())
						->setIdHito($hito)
						->setIdExpediente($expediente)
						->setFechaModificacion(new DateTime())
						->setEstado(0);
					if (isset($numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()])) {
						for ($i = 0; $i < $numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()]; $i += 1) {
							$hitosExpediente[] = (new HitoExpedienteEntidad())
								->setIdHito($hito)
								->setIdExpediente($expediente)
								->setFechaModificacion(new DateTime())
								->setEstado(0);
						}
					}
					foreach ($hitosExpediente as $numHitoExp => $hitoExpediente) {
						// $gruposHitoExpediente = array();
						// $gruposHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
						// 	'idHitoExpediente' => $hitoExpediente
						// ));
						// $numeroGruposHitoExpediente = count($gruposHitoExpediente);
						// if ($numeroGruposHitoExpediente === 0) {
						$gruposHito = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
							'idHito' => $hito
						));
						foreach ($gruposHito as $grupoHito) {
							$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = (new GrupoHitoExpedienteEntidad())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoCamposHito($grupoHito);
							if (isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $numHitoExp . '_' . $grupoHito->getIdGrupoCamposHito()])) {
								for ($i = 0; $i < $numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $numHitoExp . '_' . $grupoHito->getIdGrupoCamposHito()]; $i += 1) {
									$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = (new GrupoHitoExpedienteEntidad())
										->setIdHitoExpediente($hitoExpediente)
										->setIdGrupoCamposHito($grupoHito);
								}
							}
						}
						// }
					}
					// $gruposHitosExpediente[] = $gruposHitoExpediente;
				} else {
					$numeroHitosExpedienteDuplicados = $numeroHitosExpediente - 1;
					// $numeroHitosExpedienteDuplicados = 0;

					$variablesTwig['numero_hitos_expediente_duplicados'][$hito->getIdHito()] = $numeroHitosExpedienteDuplicados;
					// if($hito->getIdHito() == 30){
					// 	if(isset($numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()])){
					// 		dump($numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()]);
					// 	}else{
					// 		echo "NO";
					// 	}
					// 	// die();
					// }
					if (isset($numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()])) {
						if ($numeroHitosExpedienteDuplicados < $numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()]) { // Hay nuevos hitos que crear
							$diferencia = $numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()] - $numeroHitosExpedienteDuplicados;
							// echo "Diferencia: ".$diferencia."<br>";

							for ($i = 0; $i < $diferencia; $i += 1) {
								$hitoExpediente = (new HitoExpedienteEntidad())
									->setIdHito($hito)
									->setIdExpediente($expediente)
									->setFechaModificacion(new DateTime())
									->setEstado(0);
								// $managerEntidad->persist($hitoExpediente);
								$hitosExpediente[] = $hitoExpediente;
								// dump($hitosExpediente);
								// die();


								$gruposHito = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
									'idHito' => $hito
								));
								foreach ($gruposHito as $grupoHito) {
									// $gruposHitoExpedienteObj = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
									// 	'idGrupoCamposHito' => $grupoHito,
									// ));

									// foreach ($gruposHitoExpedienteObj as $grupoHitoExpedienteObj){
									// 	$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpedienteObj;
									// }

									// $grupoHitoExpediente = (new GrupoHitoExpedienteEntidad())
									// 	->setIdHitoExpediente($hitoExpediente)
									// 	->setIdGrupoCamposHito($grupoHito);
									// // $managerEntidad->persist($grupoHitoExpediente);
									// $gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente;
									// if (isset($numeroGruposHitoExpedienteDuplicadosArray[$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()])) {
									// 	for ($i = 0; $i < $numeroGruposHitoExpedienteDuplicadosArray[$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()]; $i += 1) {
									// 		$grupoHitoExpediente = (new GrupoHitoExpedienteEntidad())
									// 			->setIdHitoExpediente($hitoExpediente)
									// 			->setIdGrupoCamposHito($grupoHito);
									// 		// $managerEntidad->persist($grupoHitoExpediente);
									// 		$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente;
									// 	}
									// }
									// $numNuevoHito = $numeroHitosExpediente + $i;
									// if (isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito().'_'.$numNuevoHito.'_'.$grupoHito->getIdGrupoCamposHito()])) {
									// 	if ($numeroGruposHitoExpedienteDuplicados < $numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito().'_'.$numNuevoHito.'_'.$grupoHito->getIdGrupoCamposHito()]) { // Hay nuevos grupos que crear
									// 		$diferencia =intval($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito().'_'.$numNuevoHito.'_'.$grupoHito->getIdGrupoCamposHito()]) - $numeroGruposHitoExpedienteDuplicados;
									// 		for ($i = 0; $i < $diferencia; $i += 1) {
									// 			$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = (new GrupoHitoExpedienteEntidad())
									// 				->setIdHitoExpediente($hitoExpediente)
									// 				->setIdGrupoCamposHito($grupoHito);
									// 		}
									// 	}
									// }

								}
								// dump($gruposHitoExpediente);
								// $gruposHitoExpedientes[] = $gruposHitoExpediente;
								// dump($gruposHitoExpediente);
								// 	die();
								// foreach ($gruposHito as $grupoHito){


								// 	$numeroGruposHitoExpedienteDuplicados = $numeroGruposHitoExpediente - 1;
								// 	$variablesTwig['numero_grupos_hitos_expediente_duplicados'][$grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpedienteDuplicados;
								// }
							}
							// dump($gruposHitoExpediente);
							// die();
							// for ($i = 0; $i < count($gruposHitoExpediente); $i += 1) {
							// 	$managerEntidad->persist($gruposHitoExpediente[$i]);
							// }
							// for ($i = 0; $i < count($hitosExpediente); $i += 1) {
							// 	$managerEntidad->persist($hitosExpediente[$i]);
							// }
						} elseif ($numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()] < $numeroHitosExpedienteDuplicados) { // Hay que borrar hitos
							$diferencia = $numeroHitosExpedienteDuplicados - $numeroHitosExpedienteDuplicadosArray[$hito->getIdHito()];
							for ($i = 0; $i < $diferencia; $i += 1) {
								$hitosExpedienteABorrar[] = $hitosExpediente[count($hitosExpediente) - 1];
								unset($hitosExpediente[count($hitosExpediente) - 1]);
							}
						} else {
							// No hay mas ni menos hitos, entonces para los que habia revisamos los grupos por si ha cambiado el numero
							$hitosExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findBy(array(
								'idHito' => $hito,
								'idExpediente' => $expediente
							));
							$gruposHito = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
								'idHito' => $hito
							));
							foreach ($hitosExpediente as $indiceHito => $hitoExpediente) {
								foreach ($gruposHito as $grupoHito) {
									$gruposHitoExp = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
										'idHitoExpediente' => $hitoExpediente,
										'idGrupoCamposHito' => $grupoHito
									));
									foreach ($gruposHitoExp as $indiceMiGrupo => $grupoHitoExpediente) {
										foreach ($misGruposHitosExpedienteABorrar as $miGruposHitosExpedienteABorrar) {
											if ($miGruposHitosExpedienteABorrar == $grupoHitoExpediente) {
												unset($gruposHitoExp[$indiceMiGrupo]);
											}
										}
									}

									$numeroGruposHitoExpediente = count($gruposHitoExp);
									$numeroGruposHitoExpedienteDuplicados = $numeroGruposHitoExpediente - 1;
									// $variablesTwig['numero_grupos_hito_expediente_duplicados'][$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpediente;
									$variablesTwig['numero_grupos_hito_expediente_duplicados'][$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpediente;
									foreach ($gruposHitoExp as $grupoHitoExpediente) {
										$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente;
									}
								}
							}
						}
					} elseif ($request->isMethod('POST') && $numeroHitosExpedienteDuplicados > 0) {
						for ($i = 0; $i < $numeroHitosExpedienteDuplicados; $i += 1) {
							$hitosExpedienteABorrar[] = $hitosExpediente[count($hitosExpediente) - 1];
							unset($hitosExpediente[count($hitosExpediente) - 1]);
						}
					}

					// $hitosExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findBy(array(
					// 	'idHito' => $hito,
					// 	'idExpediente' => $expediente
					// ));

					$gruposHito = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
						'idHito' => $hito
					));
					// unset($gruposHitoExpediente);
					foreach ($hitosExpediente as $indiceHito => $hitoExpediente) {
						foreach ($gruposHito as $indiceGrupo => $grupoHito) {
							$gruposHitoExp = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
								'idHitoExpediente' => $hitoExpediente,
								'idGrupoCamposHito' => $grupoHito
							));

							foreach ($gruposHitoExp as $indiceMiGrupo => $grupoHitoExpediente2) {
								foreach ($misGruposHitosExpedienteABorrar as $miGruposHitosExpedienteABorrar) {
									if ($miGruposHitosExpedienteABorrar == $grupoHitoExpediente2) {
										unset($gruposHitoExp[$indiceMiGrupo]);
									}
								}
							}

							$numeroGruposHitoExpediente = count($gruposHitoExp);
							$diferencia = 0;

							if ($numeroGruposHitoExpediente == 0) {
								$numeroGruposHitoExpedienteDuplicados = 0;
								$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = (new GrupoHitoExpedienteEntidad())
									->setIdHitoExpediente($hitoExpediente)
									->setIdGrupoCamposHito($grupoHito);
							} else {
								$numeroGruposHitoExpedienteDuplicados = $numeroGruposHitoExpediente - 1;
							}

							$variablesTwig['numero_grupos_hito_expediente_duplicados'][$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpediente - $diferencia;

							foreach ($gruposHitoExp as $grupoHitoExpediente) {
								if ((isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()]) && !in_array($grupoHitoExpediente, $gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()])) || !isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()])) {
									$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente;
								}
								$variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente->getIdGrupoHitoExpediente();
								// if(!isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][$indiceHito])){
								// 	$grupoHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
								// 		'idHitoExpediente' => $hitoExpediente,
								// 		'idGrupoCamposHito' => $grupoHito
								// 	));
								// 	$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][$indiceHito] = $grupoHitoExpediente;
								// }else{
								// 	$grupoHitoExpediente = $gruposHitoExpedientes[$grupoHito->getIdGrupoCamposHito()][$indiceHito];
								// }

							}

							// foreach ($gruposHitoExp as $grupoHitoExpediente){
							// 	if((isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()]) && !in_array($grupoHitoExpediente,$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()])) || !isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()])){
							// 		$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente;
							// 	}
							// }
							// $variablesTwig['numero_grupos_hito_expediente_duplicados'][$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpediente;

							// PArte nueva para tratar los grupos duplicados, borrados o iguales
							if (isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()]) && ($numeroGruposHitoExpedienteDuplicados < $numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()])) {
								// if ($numeroGruposHitoExpedienteDuplicados < $numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito().'_'.$indiceHito.'_'.$grupoHito->getIdGrupoCamposHito()]) { // Hay nuevos grupos que crear
								$diferencia = intval($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()]) - $numeroGruposHitoExpedienteDuplicados;
								for ($i = 0; $i < $diferencia; $i += 1) {
									$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = (new GrupoHitoExpedienteEntidad())
										->setIdHitoExpediente($hitoExpediente)
										->setIdGrupoCamposHito($grupoHito);
								}
								// } elseif ($numeroGruposHitoExpedienteDuplicadosArray[$grupoHitoExpediente->getIdGrupoHitoExpediente()] < $numeroGruposHitoExpedienteDuplicados) { // Hay que borrar grupos
								// 	$diferencia = $numeroGruposHitoExpedienteDuplicados - $numeroGruposHitoExpedienteDuplicadosArray[$grupoHitoExpediente->getIdGrupoHitoExpediente()];  
								// 	for ($i = 0; $i < $diferencia; $i += 1) {
								// 		$gruposHitoExpedienteABorrar[] = $gruposHitoExp[count($gruposHitoExp) - 1];
								// 		unset($gruposHitoExp[count($gruposHitoExp) - 1]);
								// 	}
								// }else{ // No hay mas ni menos grupos, entonces para los que habia revisamos los grupos por si ha cambiado el numero
								// $hitosExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findBy(array(
								// 	'idHito' => $hito,
								// 	'idExpediente' => $expediente
								// ));
								// $gruposHito = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
								// 	'idHito' => $hito
								// ));
								// foreach ($hitosExpediente as $hitoExpediente){
								// 	foreach ($gruposHito as $grupoHito){
								// 		$gruposHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
								// 			'idHitoExpediente' => $hitoExpediente,
								// 			'idGrupoCamposHito' => $grupoHito
								// 		));
								// 		$numeroGruposHitoExpediente = count($gruposHitoExpediente);
								// 		$numeroGruposHitoExpedienteDuplicados = $numeroGruposHitoExpediente - 1;
								// 		$variablesTwig['numero_grupos_hito_expediente_duplicados'][$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpedienteDuplicados;


								// 	}
								// }
								// }
							} elseif ($request->isMethod('POST') && $numeroGruposHitoExpedienteDuplicados > 0) {
								// for ($i = 0; $i < $numeroGruposHitoExpedienteDuplicados; $i += 1) {
								// $gruposHitoExpedienteABorrar[] = $gruposHitoExpediente[count($gruposHitoExpediente) - 1];
								// unset($gruposHitoExpediente[count($gruposHitoExpediente) - 1]);
								// $gruposHitoExpedienteABorrar[] = end($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()]);
								// array_pop($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()]);
								// }}
								if (isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()])) {
									if ($numeroGruposHitoExpedienteDuplicados > $numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()]) { // Hay nuevos grupos que crear
										$diferencia = $numeroGruposHitoExpedienteDuplicados - intval($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indiceHito . '_' . $grupoHito->getIdGrupoCamposHito()]);
										for ($i = 0; $i < $diferencia; $i += 1) {
											// $gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = (new GrupoHitoExpedienteEntidad())
											// 	->setIdHitoExpediente($hitoExpediente)
											// 	->setIdGrupoCamposHito($grupoHito);
											$gruposHitoExpedienteABorrar[] = end($gruposHitoExp);
											foreach ($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()] as $indiceGrupoDelHito => $grupoDelHito) {
												if ($grupoDelHito == end($gruposHitoExp)) {
													unset($grupoDelHito);
													unset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][$indiceGrupoDelHito]);
													array_pop($gruposHitoExp);
												}
											}
										}
									}
								}
							}
							// $variablesTwig['numero_grupos_hito_expediente_duplicados'][$hitoExpediente->getIdHito()->getIdHito().'_'.$indiceHito.'_'.$grupoHito->getIdGrupoCamposHito()] = $numeroGruposHitoExpediente - $diferencia;

							// foreach ($gruposHitoExp as $grupoHitoExpediente){
							// 	// if((isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()]) && !in_array($grupoHitoExpediente,$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()])) || !isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()])){
							// 	// 	$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente;
							// 	// }
							// 	$variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente->getIdGrupoHitoExpediente();
							// 	// if(!isset($gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][$indiceHito])){
							// 	// 	$grupoHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
							// 	// 		'idHitoExpediente' => $hitoExpediente,
							// 	// 		'idGrupoCamposHito' => $grupoHito
							// 	// 	));
							// 	// 	$gruposHitoExpediente[$grupoHito->getIdGrupoCamposHito()][$indiceHito] = $grupoHitoExpediente;
							// 	// }else{
							// 	// 	$grupoHitoExpediente = $gruposHitoExpedientes[$grupoHito->getIdGrupoCamposHito()][$indiceHito];
							// 	// }

							// }
						}
					}
				}
				$variablesTwig['hitos_expediente'][$hito->getIdHito()] = $hitosExpediente;
				$contador = 0;
				foreach ($hitosExpediente as $indice => $hitoExpediente) {
					$numRepeticionesGrupo = array();
					$formulariosHitoExpediente[$hito->getIdHito()][] = $this->get('form.factory')->createNamed('hito_expediente_' . $hito->getIdHito() . '_' . $indice, HitoExpedienteFormulario::class, $hitoExpediente, array(
						'csrf_protection' => false
					));
					if ($request->isMethod('POST')/* && $faseActual === $hitoExpediente->getIdHito()->getIdFase()*/) {
						if (!end($formulariosHitoExpediente[$hito->getIdHito()])->isSubmitted()) {
							end($formulariosHitoExpediente[$hito->getIdHito()])->submit($request->request->get(end($formulariosHitoExpediente[$hito->getIdHito()])->getName()));
							if (!end($formulariosHitoExpediente[$hito->getIdHito()])->isValid() && $formulariosValidos) {
								$formulariosValidos = false;
							}
						}
					}
					$variablesTwig['agregarModificarHitoExpediente'][$hito->getIdHito()][] = end($formulariosHitoExpediente[$hito->getIdHito()])->createView();
					if ($hito->getRepetible() && !isset($formulariosHitoExpedienteModelo[$hito->getIdHito()])) {
						$formulariosHitoExpedienteModelo[$hito->getIdHito()] = $this->get('form.factory')->createNamed('hito_expediente_modelo_' . $hito->getIdHito(), HitoExpedienteFormulario::class, null, array(
							'csrf_protection' => false
						));
						$variablesTwig['agregarModificarHitoExpedienteModelo'][$hito->getIdHito()] = $formulariosHitoExpedienteModelo[$hito->getIdHito()]->createView();
					}
					if (!isset($gruposCamposHito[$hito->getIdHito()])) {
						$gruposCamposHito[$hito->getIdHito()] = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
							'idHito' => $hito
						), array(
							'orden' => 'ASC'
						));
						$variablesTwig['grupos_campos_hito'][$hito->getIdHito()] = $gruposCamposHito[$hito->getIdHito()];
					}

					foreach ($gruposCamposHito[$hito->getIdHito()] as $indiceGrupo => $grupoCamposHito) {
						// $gruposHitoExp = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
						// 	'idHitoExpediente' => $hitoExpediente,
						// 	'idGrupoCamposHito' => $grupoHito
						// ));

						// if(count($gruposHitoExp)>1){
						// 	// foreach ($gruposHitoExp as $grupoHitoExpediente){
						// }
						if ((isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indice . '_' . $grupoCamposHito->getIdGrupoCamposHito()])) || (isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indice . '_' . $grupoCamposHito->getIdGrupoCamposHito()]) && $numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indice . '_' . $grupoCamposHito->getIdGrupoCamposHito()] == 0) || !isset($numeroGruposHitoExpedienteDuplicadosArray1[$hitoExpediente->getIdHito()->getIdHito() . '_' . $indice . '_' . $grupoCamposHito->getIdGrupoCamposHito()])) {
							foreach ($gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()] as $indiceGrupoExp => $grupoHitoExpediente) {

								if ($grupoHitoExpediente->getIdHitoExpediente() == $hitoExpediente) {
									if (!isset($gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp])) {
										$grupoHitoExpediente1 = (new GrupoHitoExpedienteEntidad())
											->setIdHitoExpediente($hitoExpediente)
											->setIdGrupoCamposHito($grupoCamposHito);
										$gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp] = $grupoHitoExpediente1;
										$variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente1->getIdGrupoHitoExpediente();
										// $gruposHitosExpedientes[$hitoExpediente->getIdHitoExpediente()] = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
										// 	'idHitoExpediente' => $hitoExpediente
										// ));
									} else {
										if (is_array($gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp])) {
											if (isset($gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp][0])) {
												$grupoHitoExpediente1 = $gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp][0];
												if (!isset($variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()]) || !in_array($grupoHitoExpediente1->getIdGrupoHitoExpediente(), $variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()])) {
													$variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente1->getIdGrupoHitoExpediente();
												}
											} else {
												$grupoHitoExpediente1 = (new GrupoHitoExpedienteEntidad())
													->setIdHitoExpediente($hitoExpediente)
													->setIdGrupoCamposHito($grupoCamposHito);
												$gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp] = $grupoHitoExpediente1;
												$variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente1->getIdGrupoHitoExpediente();
											}
										} else {
											$grupoHitoExpediente1 = $gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()][$indiceGrupoExp];
											if (!isset($variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()]) || !in_array($grupoHitoExpediente1->getIdGrupoHitoExpediente(), $variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()])) {
												$variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()][$grupoCamposHito->getIdGrupoCamposHito()][] = $grupoHitoExpediente1->getIdGrupoHitoExpediente();
											}
										}
									}
									// if (!isset($gruposHitoExpediente[$hitoExpediente->getIdHitoExpediente()])) {
									// 	$gruposHitoExpediente[$hitoExpediente->getIdHitoExpediente()] = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
									// 		'idHitoExpediente' => $hitoExpediente
									// 	));
									// $variablesTwig['grupos_hito_expediente'][$hitoExpediente->getIdHitoExpediente()] = $gruposHitoExpediente;//[$hitoExpediente->getIdHitoExpediente()];
									// }

									if (!isset($camposHito[$grupoCamposHito->getIdGrupoCamposHito()])) {
										$camposHito[$grupoCamposHito->getIdGrupoCamposHito()] = $doctrine->getRepository(CampoHitoEntidad::class)->findBy(array(
											'idGrupoCamposHito' => $grupoCamposHito
										), array(
											'orden' => 'ASC'
										));
										$variablesTwig['campos_hito'][$grupoCamposHito->getIdGrupoCamposHito()] = $camposHito[$grupoCamposHito->getIdGrupoCamposHito()];
									}
									// $grupoHitoExpediente = (new GrupoHitoExpedienteEntidad());
									// $grupoHitoExpediente->setIdHitoExpediente($hitoExpediente);
									// $grupoHitoExpediente->setIdGrupoCamposHito($grupoCamposHito);
									// $grupoHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findOneBy(array(
									// 		'idHitoExpediente' => $hitoExpediente,
									// 		'idGrupoCamposHito' => $grupoCamposHito
									// 	));

									// if(!isset($gruposHitoExpediente[$indiceGrupo])){
									// 	$grupoHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findOneBy(array(
									// 		'idHitoExpediente' => $hitoExpediente,
									// 		'idGrupoCamposHito' => $grupoCamposHito
									// 	));
									// }else{
									// 	$grupoHitoExpediente = $gruposHitoExpediente[$indiceGrupo];
									// }

									// if(!$grupoHitoExpediente){
									// 	$grupoHitoExpediente = (new GrupoHitoExpedienteEntidad());
									// 	$grupoHitoExpediente->setIdHitoExpediente($hitoExpediente);
									// 	$grupoHitoExpediente->setIdGrupoCamposHito($grupoCamposHito);
									// }

									// foreach($gruposHitoExpediente[$grupoCamposHito->getIdGrupoCamposHito()] as $grupoHitoExpediente){
									$grupoParaBorrar = false;
									foreach ($gruposHitoExpedienteABorrar as $grupoHitoExpedienteABorrar) {
										if ($grupoHitoExpedienteABorrar->getIdGrupoHitoExpediente() == $grupoHitoExpediente->getIdGrupoHitoExpediente()) {
											$grupoParaBorrar = true;
										}
									}
									if (!$grupoParaBorrar) {
										foreach ($camposHito[$grupoCamposHito->getIdGrupoCamposHito()] as $campoHito) {
											// $gruposHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
											// 	'idHitoExpediente' => $hitoExpediente,
											// 	'idGrupoCamposHito' => $grupoCamposHito
											// ));
											// foreach($gruposHitoExpediente as $grupoHitoExpediente){
											if ($hitoExpediente->getIdHitoExpediente() == $grupoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()) {

												$campoHitoExpediente = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findOneBy(array(
													'idCampoHito' => $campoHito,
													'idHitoExpediente' => $hitoExpediente,
													'idGrupoHitoExpediente' => $grupoHitoExpediente,
													'idExpediente' => $expediente
												));
												if (!$campoHitoExpediente) {
													$campoHitoExpediente = (new CampoHitoExpedienteEntidad())
														->setIdCampoHito($campoHito)
														->setIdHitoExpediente($hitoExpediente)
														->setIdGrupoHitoExpediente($grupoHitoExpediente)
														->setIdExpediente($expediente)
														->setFechaModificacion(new DateTime());
													if ($campoHito->getTipo() == 4) {
														$campoHitoExpediente->setObligatorio(1)
															->setSolicitarAlColaborador(1);
													}
												}
												$opcionesFormulario = array();
												if (!isset($numRepeticionesGrupo[$campoHito->getIdCampoHito()])) {
													$numRepeticionesGrupo[$campoHito->getIdCampoHito()] = 0;
												} else {
													$numRepeticionesGrupo[$campoHito->getIdCampoHito()] += 1;
												}
												switch ($campoHito->getTipo()) {
													case 1:
														$tipoFormulario = CampoHitoExpedienteTextoFormulario::class;
														break;
													case 2:
														$tipoFormulario = CampoHitoExpedienteDesplegableFormulario::class;
														$opcionesFormulario = array(
															'opciones_campo' => $doctrine->getRepository(OpcionesCampoEntidad::class)->findBy(array(
																'idCampoHito' => $campoHito
															), array(
																'orden' => 'ASC'
															))
														);
														break;
													case 3:
														$tipoFormulario = CampoHitoExpedienteDesplegableFormulario::class;
														$opcionesFormulario = array(
															'opciones_campo' => $doctrine->getRepository(OpcionesCampoEntidad::class)->findBy(array(
																'idCampoHito' => $campoHito
															), array(
																'orden' => 'ASC'
															))
														);
														break;
													case 4:
														if (!isset($variablesTwig['incluir_dropify'])) {
															$variablesTwig['incluir_dropify'] = true;
														}
														$campoHitoExpedienteColaboradores = $doctrine->getRepository(CampoHitoExpedienteColaboradoresEntidad::class)->findBy(array(
															'idCampoHito' => $campoHito,
															'idHitoExpediente' => $hitoExpediente,
															'idExpediente' => $expediente
														));
														$entidadesColaboradorasSeleccionadas = $agentesColaboradoresSeleccionados = array();
														if (count($campoHitoExpedienteColaboradores) === 0) {
															$campoHitoExpedienteColaboradores[0] = (new CampoHitoExpedienteColaboradoresEntidad())
																->setIdCampoHito($campoHito)
																->setIdHitoExpediente($hitoExpediente)
																->setIdExpediente($expediente);
														} else {
															foreach ($campoHitoExpedienteColaboradores as $campoHitoExpedienteColaborador) {
																$entidadesColaboradorasSeleccionadas[] = $campoHitoExpedienteColaborador->getIdEntidadColaboradora();
																$agentesColaboradoresSeleccionados[] = $campoHitoExpedienteColaborador->getIdAgenteColaborador();
															}
														}
														$formulariosCampoHitoExpedienteColaboradores[$campoHito->getIdCampoHito()][] = $this->get('form.factory')->createNamed('campo_hito_expediente_colaboradores_' . $campoHito->getIdCampoHito() . '_' . $indice, CampoHitoExpedienteColaboradoresFormulario::class, $campoHitoExpedienteColaboradores[0], array(
															'entidades_colaboradoras' => $entidadesColaboradoras,
															'entidades_colaboradoras_seleccionadas' => $entidadesColaboradorasSeleccionadas,
															'agentes_colaboradores_seleccionados' => $agentesColaboradoresSeleccionados,
															'csrf_protection' => false
														));
														if ($request->isMethod('POST')) {
															if (!end($formulariosCampoHitoExpedienteColaboradores[$campoHito->getIdCampoHito()])->isSubmitted()) {
																end($formulariosCampoHitoExpedienteColaboradores[$campoHito->getIdCampoHito()])->submit($request->request->get(end($formulariosCampoHitoExpedienteColaboradores[$campoHito->getIdCampoHito()])->getName()));
																if (!end($formulariosCampoHitoExpedienteColaboradores[$campoHito->getIdCampoHito()])->isValid() && $formulariosValidos) {
																	$formulariosValidos = false;
																}
															}
														}
														$variablesTwig['agregarModificarCampoHitoExpedienteColaboradores'][$campoHito->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = end($formulariosCampoHitoExpedienteColaboradores[$campoHito->getIdCampoHito()])->createView();
														if ($hito->getRepetible() && !isset($formulariosCampoHitoExpedienteColaboradoresModelo[$campoHito->getIdCampoHito()])) {
															$formulariosCampoHitoExpedienteColaboradoresModelo[$campoHito->getIdCampoHito()] = $this->get('form.factory')->createNamed('campo_hito_expediente_colaboradores_modelo_' . $campoHito->getIdCampoHito(), CampoHitoExpedienteColaboradoresFormulario::class, null, array(
																'entidades_colaboradoras' => $entidadesColaboradoras,
																'csrf_protection' => false
															));
															$variablesTwig['agregarModificarCampoHitoExpedienteColaboradoresModelo'][$campoHito->getIdCampoHito()] = $formulariosCampoHitoExpedienteColaboradoresModelo[$campoHito->getIdCampoHito()]->createView();
														}
														if (!isset($id) && $campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 1) {
															$campoHitoExpediente->setObligatorio(true)
																->setSolicitarAlColaborador(true);
														}
														$tipoFormulario = CampoHitoExpedienteFicheroFormulario::class;
														$ficheroCampoABorrar = $doctrine->getRepository(FicheroCampoEntidad::class)->findOneBy(array(
															'idCampoHito' => $campoHito,
															'idCampoHitoExpediente' => $campoHitoExpediente,
															'idExpediente' => $expediente
														));
														if ($ficheroCampoABorrar) {
															$rutaFichero = $this->getParameter('files_directory') . '/' . $ficheroCampoABorrar->getNombreFichero();
															$rutaFichero = str_replace($this->get('kernel')->getProjectDir() . '/web', '', $rutaFichero);
															$variablesTwig['fichero_subido'][$campoHito->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = $rutaFichero;
															$nombresFichero[] = $campoHitoExpediente->getValor();
															$variablesTwig['fichero_subido_ruta'][$campoHito->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = $router->generate('descargar_fichero_expediente', array(
																'id' => $ficheroCampoABorrar->getIdFicheroCampo()
															));
														} else {
															$ficheroCampoABorrar = (new FicheroCampoEntidad())
																->setIdCampoHito($campoHito)
																->setIdCampoHitoExpediente($campoHitoExpediente)
																->setIdExpediente($expediente);
															$nombresFichero[] = null;
														}
														$contador += 1;
														$formulariosFicheroCampo[$campoHito->getIdCampoHito()][] = $this->get('form.factory')->createNamed('fichero_campo_' . $indice . '_' . $campoHito->getIdCampoHito() . '_' . $numRepeticionesGrupo[$campoHito->getIdCampoHito()], FicheroCampoFormulario::class, $ficheroCampoABorrar, array(
															'csrf_protection' => false
														));
														if ($request->isMethod('POST')) {
															end($formulariosFicheroCampo[$campoHito->getIdCampoHito()])->handleRequest($request);

															// Modificación para procesar múltiples archivos en un mismo input
															// $arrayFile = $request->files->all()["fichero_campo_".$indice."_".$campoHito->getIdCampoHito()."_".$numRepeticionesGrupo[$campoHito->getIdCampoHito()]];
															// if($arrayFile && $arrayFile['fichero'] && count($arrayFile['fichero'])>0){
															// $miFile['fichero'] = $arrayFile['fichero'][0];
															// $request->files->set("fichero_campo_".$indice."_".$campoHito->getIdCampoHito()."_".$numRepeticionesGrupo[$campoHito->getIdCampoHito()],$miFile);
															// end($formulariosFicheroCampo[$campoHito->getIdCampoHito()])->handleRequest($request);
															// }else{
															// $miFile = new UploadedFile(null,null);
															// $miFile['fichero'] = null;
															// $request->files->set("fichero_campo_".$indice."_".$campoHito->getIdCampoHito()."_".$numRepeticionesGrupo[$campoHito->getIdCampoHito()],$miFile);
															// end($formulariosFicheroCampo[$campoHito->getIdCampoHito()])->handleRequest($request);
															// }
															// end($formulariosFicheroCampo[$campoHito->getIdCampoHito()])=$arrayFile;
															if (!end($formulariosFicheroCampo[$campoHito->getIdCampoHito()])->isValid() && $formulariosValidos) {
																$formulariosValidos = false;
															}
														}
														$variablesTwig['agregarModificarFicheroCampo'][$campoHito->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = end($formulariosFicheroCampo[$campoHito->getIdCampoHito()])->createView();
														if ($hito->getRepetible() && !isset($formulariosFicheroCampoModelo[$campoHito->getIdCampoHito()])) {
															$formulariosFicheroCampoModelo[$campoHito->getIdCampoHito()] = $this->get('form.factory')->createNamed('fichero_campo_modelo_' . $campoHito->getIdCampoHito(), FicheroCampoFormulario::class, null, array(
																'csrf_protection' => false
															));
															$variablesTwig['agregarModificarFicheroCampoModelo'][$campoHito->getIdCampoHito()] = $formulariosFicheroCampoModelo[$campoHito->getIdCampoHito()]->createView();
														}
														if (!isset($variablesTwig['maxFileSize'])) {
															$variablesTwig['maxFileSize'] = $this->redondearBytesAUnidad($this->convertirABytes(ini_get('upload_max_filesize')));
														}
														if (!isset($variablesTwig['extensiones_permitidas'])) {
															$formatosPermitidos = array_merge($this->getParameter('document_formats'), $this->getParameter('image_formats'));
															if (in_array('jpeg', $formatosPermitidos)) {
																array_push($formatosPermitidos, 'jpg');
															}
															$variablesTwig['extensiones_permitidas'] = implode(' ', $formatosPermitidos);
														}
														break;
													case 5:
														$tipoFormulario = CampoHitoExpedienteEmailFormulario::class;
														break;
													case 6:
														if (!isset($variablesTwig['incluir_datepicker'])) {
															$variablesTwig['incluir_datepicker'] = true;
														}
														$tipoFormulario = CampoHitoExpedienteFechaFormulario::class;
														break;
													case 7:
														$tipoFormulario = CampoHitoExpedienteBancoFormulario::class;
														$opcionesFormulario = array(
															'entidades' => $doctrine->getRepository(AgenteColaboradorEntidad::class)->findBy(array(
																'idEntidadColaboradora' => $doctrine->getRepository(EntidadColaboradoraEntidad::class)->findBy(array(
																	'tipoEntidad' => 1,
																	'estado' => 1
																))
															))
														);
														break;
													case 8:
														$tipoFormulario = CampoHitoExpedienteTasadoraFormulario::class;
														$opcionesFormulario = array(
															'entidades' => $doctrine->getRepository(AgenteColaboradorEntidad::class)->findBy(array(
																'idEntidadColaboradora' => $doctrine->getRepository(EntidadColaboradoraEntidad::class)->findBy(array(
																	'tipoEntidad' => 2,
																	'estado' => 1
																))
															))
														);
														break;
													case 9:
														$tipoFormulario = CampoHitoExpedienteNotariaFormulario::class;
														$opcionesFormulario = array(
															'entidades' => $doctrine->getRepository(AgenteColaboradorEntidad::class)->findBy(array(
																'idEntidadColaboradora' => $doctrine->getRepository(EntidadColaboradoraEntidad::class)->findBy(array(
																	'tipoEntidad' => 3,
																	'estado' => 1
																))
															))
														);
														break;
													case 10:
														unset($tipoFormulario);
														break;
													default:
														$this->addFlash('warning', 'El campo hito con id="' . $campoHito->getIdCampoHito() . '" es de un tipo no valido.');
														return $this->redirectToRoute('lista_expedientes');
												}
												if (isset($tipoFormulario)) {
													if (isset($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()])) {
														$numRepe = count($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()]);
													} else {
														$numRepe = 0;
													}

													if ($campoHitoExpediente->getObligatorio() == 1) {
														$campoHitoExpediente->setObligatorio(true);
													}
													if ($campoHitoExpediente->getSolicitarAlColaborador() == 1) {
														$campoHitoExpediente->setSolicitarAlColaborador(true);
													}
													if ($campoHitoExpediente->getAvisarColaborador() == 1) {
														$campoHitoExpediente->setAvisarColaborador(true);
													}
													if ($campoHitoExpediente->getParaFirmar() == 1) {
														$campoHitoExpediente->setParaFirmar(true);
													}
													if ($campoHitoExpediente->getFirmado() == 1) {
														$campoHitoExpediente->setFirmado(true);
													}
													if ($campoHitoExpediente->getEnviarAlCliente() == 1) {
														$campoHitoExpediente->setEnviarAlCliente(true);
													}
													if ($campoHitoExpediente->getEnviarAlColaborador() == 1) {
														$campoHitoExpediente->setEnviarAlColaborador(true);
													}
													$miCampoForm = $this->get('form.factory')->createNamed('campo_hito_expediente_' . $indice . '_' . $campoHito->getIdCampoHito() . '_' . $numRepeticionesGrupo[$campoHito->getIdCampoHito()], $tipoFormulario, $campoHitoExpediente, array_merge($opcionesFormulario, array(
														'csrf_protection' => false
													)));
													if ($campoHito->getTipo() == 3 && $miCampoForm->getData()->getIdOpcionesCampo() != null) {
														$miCampoForm->getData()->setValor('campo_hito_' . $miCampoForm->getData()->getIdCampoHitoExpediente() . '_opcion_' . $miCampoForm->getData()->getIdOpcionesCampo()->getIdOpcionesCampo());
													}
													$formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()][] = $miCampoForm;
													//  campo_hito_expediente_1_193_2_idOpcionesCampo
													$numRepeticion = count($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()]);

													if ($request->isMethod('POST')/* && $faseActual === $campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()*/) {
														if (!end($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()])->isSubmitted()) {
															end($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()]);
															($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()][$numRepeticion - 1])->submit($request->request->get(($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()][$numRepeticion - 1])->getName()));
															if (!end($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()])->isValid() && $formulariosValidos) {
																$formulariosValidos = false;
																// }elseif (end($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()])->isValid() && !$formulariosValidos) {
																// 	$formulariosValidos = true;
															}
														}
													}
													$variablesTwig['agregarModificarCampoHitoExpediente'][$campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdGrupoCamposHito()][$campoHito->getIdCampoHito()][$grupoHitoExpediente->getIdGrupoHitoExpediente()] = end($formulariosCampoHitoExpediente[$campoHito->getIdCampoHito()])->createView();
													if ($hito->getRepetible() && !isset($formulariosCampoHitoExpedienteModelo[$campoHito->getIdCampoHito()])) {
														$formulariosCampoHitoExpedienteModelo[$campoHito->getIdCampoHito()] = $this->get('form.factory')->createNamed('campo_hito_expediente_modelo_' . $campoHito->getIdCampoHito(), $tipoFormulario, null, array_merge($opcionesFormulario, array(
															'csrf_protection' => false
														)));
														$variablesTwig['agregarModificarCampoHitoExpedienteModelo'][$campoHito->getIdCampoHito()] = $formulariosCampoHitoExpedienteModelo[$campoHito->getIdCampoHito()]->createView();
													}
												}
											}
										}
									}
									// }
								}
							}
						}
					}
				}
			}
		}
		if (count($nombresFichero) > 0) {
			$variablesTwig['nombres_fichero'] = json_encode($nombresFichero, JSON_UNESCAPED_UNICODE);
		}
		if ($request->isMethod('POST') && $formulariosValidos) {
			$managerEntidad = $doctrine->getManager();
			if ($expediente->getEstado() === 2) {
				$expedienteFinalizado = true;
				$hitosExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findBy(array(
					'idExpediente' => $expediente
				));
				foreach ($hitosExpediente as $hitoExpediente) {
					if (!$hitoExpediente->getEstado()) {
						$expediente->setIdFaseActual($hitoExpediente->getIdHito()->getIdFase());
						$expedienteFinalizado = false;
						break;
					}
				}
				if ($expedienteFinalizado) {
					foreach ($formulariosHitoExpediente as $formularioHitoExpedienteArray) {
						foreach ($formularioHitoExpedienteArray as $formularioHitoExpediente) {
							$hitoExpediente = $formularioHitoExpediente->getData();
							if (!$hitoExpediente->getEstado()) {
								$expedienteFinalizado = false;
								break 2;
							}
						}
					}
				}
				if ($expedienteFinalizado) {
					$fases = $doctrine->getRepository(FaseEntidad::class)->findBy(array(), array(
						'orden' => 'ASC'
					));
					$expediente->setIdFaseActual(end($fases));
				}
			}/* else {
				$faseNueva = $expediente->getIdFaseActual();
				$hitosExpedienteCompletados = true;
				$fase = null;
				if ($faseNueva->getOrden() > $faseActual->getOrden()) {
					for ($i = $faseActual->getOrden(); $hitosExpedienteCompletados && $i < $faseNueva->getOrden(); $i += 1) {
						$fase = $doctrine->getRepository(FaseEntidad::class)->findOneBy(array(
							'orden' => $i
						));
						if ($fase) {
							$hitos = $doctrine->getRepository(HitoEntidad::class)->findBy(array(
								'idFase' => $fase->getIdFase()
							));
							foreach ($hitos as $hito) {
								$hitoExpediente = $doctrine->getRepository(HitoExpedienteEntidad::class)->findOneBy(array(
									'idHito' => $hito->getIdHito(),
									'idExpediente' => $expediente->getIdExpediente()
								));
								if ($hitoExpediente) {
									if (!$hitoExpediente->getEstado()) {
										$expediente = $formularioExpediente->getData();
										$expediente->setIdFaseActual($fase);
										$hitosExpedienteCompletados = false;
										break;
									}
								} else {
									if ($faseActual->getOrden() === $fase->getOrden()) {
										foreach ($formulariosHitoExpediente as $formularioHitoExpediente) {
											$hitoExpediente = $formularioHitoExpediente->getData();
											if (!$hitoExpediente->getEstado()) {
												$expediente = $formularioExpediente->getData();
												$expediente->setIdFaseActual($fase);
												$hitosExpedienteCompletados = false;
												break;
											}
										}
									} else {
										$expediente = $formularioExpediente->getData();
										$expediente->setIdFaseActual($fase);
										$hitosExpedienteCompletados = false;
									}
									break;
								}
							}
						}
					}
				}
			}*/
			// Esta es la manera teniendo en cuenta la asignación por capacidad y numero de exp asignados
			// $comercial = $this->getDoctrine()->getRepository(VistaComercialesExpedientes::class)->createQueryBuilder('v')
			// 	->orderBy('v.numExpedientes', 'ASC')              // Ordena según prefieras
			// 	->addOrderBy('v.numDisponibles', 'DESC')
			// 	->setMaxResults(1)
			// 	->getQuery()
			// 	->getOneOrNullResult();

			// Esta es la versión rotativa de uno en uno
			$repo = $this->getDoctrine()->getRepository(\AppBundle\Entity\VistaRotacionComerciales::class);

			$comercial = $repo->createQueryBuilder('v')
				->orderBy('v.ultimaAsignacion', 'ASC')  // Ya incluye comerciales sin expediente al principio
				->setMaxResults(1)
				->getQuery()
				->getOneOrNullResult();

			if ($comercial != null) {
				$comercial = $this->getDoctrine()->getRepository(UsuarioEntidad::class)->findOneBy(
					array(
						'idUsuario' => $comercial->getIdUsuario()
					)
				);
			}

			$expediente->setIdComercial($comercial);

			if (!$this->comprobarSiLasEntidadesSonIguales($managerEntidad, $expediente)) {
				$managerEntidad->persist($expediente);
				if (isset($id)) {
					$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación del expediente', $expediente));
				} else {
					$managerEntidad->flush();
					$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Creación del expediente', $expediente));
				}
			}
			$existeRegistroActividadConEntidadFaseDatos = false;
			if (count($hitosExpedienteABorrar) > 0) {
				foreach ($hitosExpedienteABorrar as $hitoExpedienteABorrar) {
					$gruposHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findBy(array(
						'idHitoExpediente' => $hitoExpedienteABorrar
					));
					$camposHitoExpediente = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findBy(array(
						'idHitoExpediente' => $hitoExpedienteABorrar
					));
					$camposHitoExpedienteColaboradores = $doctrine->getRepository(CampoHitoExpedienteColaboradoresEntidad::class)->findBy(array(
						'idHitoExpediente' => $hitoExpedienteABorrar
					));
					$ficherosCampoABorrar = $doctrine->getRepository(FicheroCampoEntidad::class)->findBy(array(
						'idCampoHitoExpediente' => $camposHitoExpediente
					));
					$imagenesFicherosCampoABorrar = $doctrine->getRepository(ImagenFicheroEntidad::class)->findBy(array(
						'idFicheroCampo' => $ficherosCampoABorrar
					));
					if (count($ficherosCampoABorrar) > 0) {
						$ficherosCampoAReemplazar = $doctrine->getRepository(FicheroCampoEntidad::class)->findBy(array(
							'idCampoHito' => $ficherosCampoABorrar[0]->getIdCampoHito(),
							'idExpediente' => $expediente
						));
						foreach ($ficherosCampoAReemplazar as $indice => $ficheroCampoAReemplazar) {
							if ($indice > 1) {
								$aux = $ficherosCampoAReemplazar[$indice - 1]->getNombreFichero();
								$ficherosCampoAReemplazar[$indice - 1]->setNombreFichero($ficheroCampoAReemplazar->getNombreFichero());
								$ficheroCampoAReemplazar->setNombreFichero($aux);
								$managerEntidad->persist($ficherosCampoAReemplazar[$indice - 1]);
								$managerEntidad->persist($ficheroCampoAReemplazar);
							}
						}
					}
					foreach ($imagenesFicherosCampoABorrar as $imagenFicherosCampoABorrar) {
						$managerEntidad->remove($imagenFicherosCampoABorrar);
					}
					foreach ($ficherosCampoABorrar as $ficheroCampoABorrar) {
						if (!is_null($ficheroCampoABorrar->getNombreFichero())) {
							$filesystem = new Filesystem();
							try {
								$filesystem->remove($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampoABorrar->getNombreFichero());
							} catch (IOExceptionInterface $e) {
								$campoHito = $ficheroCampoABorrar->getIdCampoHito();
								$this->addFlash('danger', 'Error al borrar el archivo del campo hito "' . $campoHito . '" del expediente con id="' . $expediente->getIdExpediente() . '".');
								return false;
							}
						}
						$managerEntidad->remove($ficheroCampoABorrar);
					}

					foreach ($camposHitoExpedienteColaboradores as $campoHitoExpedienteColaboradores) {
						$managerEntidad->remove($campoHitoExpedienteColaboradores);
					}
					foreach ($camposHitoExpediente as $campoHitoExpediente) {
						$managerEntidad->remove($campoHitoExpediente);
					}
					foreach ($gruposHitoExpediente as $grupoHitoExpediente) {
						$managerEntidad->remove($grupoHitoExpediente);
					}
					$managerEntidad->remove($hitoExpedienteABorrar);
					if ($hitoExpedienteABorrar->getIdHito()->getIdFase()->getTipo() === 0) {
						if (!$existeRegistroActividadConEntidadFaseDatos) {
							$existeRegistroActividadConEntidadFaseDatos = true;
							$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
						}
					} else {
						$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Eliminación del hito duplicado "' . $hitoExpedienteABorrar->getIdHito() . '"', $expediente));
					}
				}
			}

			//  Borramos los grupos marcados para borrar
			if (count($gruposHitoExpedienteABorrar) > 0) {
				foreach ($gruposHitoExpedienteABorrar as $grupoHitoExpedienteABorrar) {
					$gruposHitoExpediente = $doctrine->getRepository(GrupoHitoExpedienteEntidad::class)->findOneBy(array(
						'idGrupoHitoExpediente' => $grupoHitoExpedienteABorrar
					));
					$camposHitoExpediente = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findBy(array(
						'idGrupoHitoExpediente' => $gruposHitoExpediente
					));

					$ficherosCampoABorrar = $doctrine->getRepository(FicheroCampoEntidad::class)->findBy(array(
						'idCampoHitoExpediente' => $camposHitoExpediente
					));
					$imagenesFicherosCampoABorrar = $doctrine->getRepository(ImagenFicheroEntidad::class)->findBy(array(
						'idFicheroCampo' => $ficherosCampoABorrar
					));
					if (count($ficherosCampoABorrar) > 0) {
						$ficherosCampoAReemplazar = $doctrine->getRepository(FicheroCampoEntidad::class)->findBy(array(
							'idCampoHito' => $ficherosCampoABorrar[0]->getIdCampoHito(),
							'idExpediente' => $expediente
						));
						foreach ($ficherosCampoAReemplazar as $indice => $ficheroCampoAReemplazar) {
							if ($indice > 1) {
								$aux = $ficherosCampoAReemplazar[$indice - 1]->getNombreFichero();
								$ficherosCampoAReemplazar[$indice - 1]->setNombreFichero($ficheroCampoAReemplazar->getNombreFichero());
								$ficheroCampoAReemplazar->setNombreFichero($aux);
								$managerEntidad->persist($ficherosCampoAReemplazar[$indice - 1]);
								$managerEntidad->persist($ficheroCampoAReemplazar);
							}
						}
					}
					foreach ($imagenesFicherosCampoABorrar as $imagenFicherosCampoABorrar) {
						$managerEntidad->remove($imagenFicherosCampoABorrar);
					}
					foreach ($ficherosCampoABorrar as $ficheroCampoABorrar) {
						if (!is_null($ficheroCampoABorrar->getNombreFichero())) {
							$filesystem = new Filesystem();
							try {
								$filesystem->remove($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampoABorrar->getNombreFichero());
							} catch (IOExceptionInterface $e) {
								$campoHito = $ficheroCampoABorrar->getIdCampoHito();
								$this->addFlash('danger', 'Error al borrar el archivo del campo hito "' . $campoHito . '" del expediente con id="' . $expediente->getIdExpediente() . '".');
								return false;
							}
						}
						$managerEntidad->remove($ficheroCampoABorrar);
					}


					foreach ($camposHitoExpediente as $campoHitoExpediente) {
						$managerEntidad->remove($campoHitoExpediente);
						// $managerEntidad->persist($campoHitoExpediente);
					}
					// foreach ($gruposHitoExpediente as $grupoHitoExpediente) {
					$managerEntidad->remove($gruposHitoExpediente);
					// }

					// if ($hitoExpedienteABorrar->getIdHito()->getIdFase()->getTipo() === 0) {
					// 	if (!$existeRegistroActividadConEntidadFaseDatos) {
					// 		$existeRegistroActividadConEntidadFaseDatos = true;
					// 		$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
					// 	}
					// } else {
					// 	$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Eliminación del hito duplicado "' . $hitoExpedienteABorrar->getIdHito() . '"', $expediente));
					// }
				}
			}

			foreach ($formulariosHitoExpediente as $formularioHitoExpedienteArray) {
				//$hitosExpediente = array();
				// Aqui se procesan los hitos recibidos en la peticion
				foreach ($formularioHitoExpedienteArray as $formularioHitoExpediente) {
					$hitoExpediente = $formularioHitoExpediente->getData();
					//$hitosExpediente[] = $hitoExpediente;
					if (!$this->comprobarSiLasEntidadesSonIguales($managerEntidad, $hitoExpediente)) {
						$hitoExpediente->setFechaModificacion(new DateTime());
						$managerEntidad->persist($hitoExpediente);
						if (isset($id)) {
							if ($hitoExpediente->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								if (is_null($hitoExpediente->getIdHitoExpediente())) {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Creación del hito duplicado "' . $hitoExpediente->getIdHito() . '"', $expediente));
								} else {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación del hito "' . $hitoExpediente->getIdHito() . '"', $expediente));
								}
							}
						}
					}
				}
			}

			foreach ($formulariosCampoHitoExpediente as $formularioCampoHitoExpedienteArray) {
				// Aqui se procesan los campos de los hitos recibidos en la peticion
				foreach ($formularioCampoHitoExpedienteArray as $formularioCampoHitoExpediente) {
					$campoHitoExpediente = $formularioCampoHitoExpediente->getData();
					// if($campoHitoExpediente) AQUI KKK
					// dump($campoHitoExpediente);
					// die();
					if (!$this->comprobarSiLasEntidadesSonIguales($managerEntidad, $campoHitoExpediente)) {
						$campoHitoExpediente->setFechaModificacion(new DateTime());
						$managerEntidad->persist($campoHitoExpediente);
						if (isset($id) && !is_null($campoHitoExpediente->getIdCampoHitoExpediente())) {
							if ($campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación del campo hito "' . $campoHitoExpediente->getIdCampoHito() . '"', $expediente));
							}
						}
					}
				}
			}
			foreach ($formulariosCampoHitoExpedienteColaboradores as $formularioCampoHitoExpedienteColaboradoresArray) {
				foreach ($formularioCampoHitoExpedienteColaboradoresArray as $indice => $formularioCampoHitoExpedienteColaboradores) {
					$campoHitoExpedienteColaboradoresDatos = $formularioCampoHitoExpedienteColaboradores->getData();
					$campoHitoExpedienteColaboradoresEntrada = $request->get('campo_hito_expediente_colaboradores_' . $campoHitoExpedienteColaboradoresDatos->getIdCampoHito()->getIdCampoHito() . '_' . $indice);
					$campoHitoExpedienteColaboradores = $doctrine->getRepository(CampoHitoExpedienteColaboradoresEntidad::class)->findBy(array(
						'idCampoHito' => $campoHitoExpedienteColaboradoresDatos->getIdCampoHito(),
						'idHitoExpediente' => $campoHitoExpedienteColaboradoresDatos->getIdHitoExpediente(),
						'idExpediente' => $expediente
					));
					$numeroCampoHitoExpedienteColaboradores = count($campoHitoExpedienteColaboradores);
					$camposHitoExpedienteColaboradoresModificados = false;
					if (isset($campoHitoExpedienteColaboradoresEntrada['idAgenteColaborador'])) {
						$numeroCampoHitoExpedienteColaboradoresEntrada = count($campoHitoExpedienteColaboradoresEntrada['idAgenteColaborador']);
						if ($numeroCampoHitoExpedienteColaboradoresEntrada > 0) {
							if ($numeroCampoHitoExpedienteColaboradoresEntrada !== $numeroCampoHitoExpedienteColaboradores) {
								if ($numeroCampoHitoExpedienteColaboradoresEntrada > $numeroCampoHitoExpedienteColaboradores) {
									for ($i = $numeroCampoHitoExpedienteColaboradores; $i < $numeroCampoHitoExpedienteColaboradoresEntrada; $i += 1) {
										$campoHitoExpedienteColaboradores[$i] = new CampoHitoExpedienteColaboradoresEntidad();
									}
								} elseif ($numeroCampoHitoExpedienteColaboradoresEntrada < $numeroCampoHitoExpedienteColaboradores) {
									$diferencia = $numeroCampoHitoExpedienteColaboradores - $numeroCampoHitoExpedienteColaboradoresEntrada;
									for ($i = 0; $i < $diferencia; $i += 1) {
										$managerEntidad->remove(end($campoHitoExpedienteColaboradores));
										$numeroCampoHitoExpedienteColaboradores -= 1;
										unset($campoHitoExpedienteColaboradores[$numeroCampoHitoExpedienteColaboradores]);
										if (!$camposHitoExpedienteColaboradoresModificados) {
											$camposHitoExpedienteColaboradoresModificados = true;
										}
									}
								}
							}
							foreach ($campoHitoExpedienteColaboradoresEntrada['idAgenteColaborador'] as $indice2 => $idAgenteColaborador) {
								$agenteColaborador = $doctrine->getRepository(AgenteColaboradorEntidad::class)->findOneBy(array(
									'idAgenteColaborador' => $idAgenteColaborador
								));
								if (!is_null($agenteColaborador)) {
									$campoHitoExpedienteColaboradores[$indice2]->setIdCampoHito($campoHitoExpedienteColaboradoresDatos->getIdCampoHito())
										->setIdHitoExpediente($campoHitoExpedienteColaboradoresDatos->getIdHitoExpediente())
										->setIdExpediente($campoHitoExpedienteColaboradoresDatos->getIdExpediente())
										->setIdEntidadColaboradora($agenteColaborador->getIdEntidadColaboradora())
										->setIdAgenteColaborador($agenteColaborador);
								}
							}
							foreach ($campoHitoExpedienteColaboradores as $campoHitoExpedienteColaborador) {
								if (!$this->comprobarSiLasEntidadesSonIguales($managerEntidad, $campoHitoExpedienteColaborador)) {
									$managerEntidad->persist($campoHitoExpedienteColaborador);
									if (!$camposHitoExpedienteColaboradoresModificados) {
										$camposHitoExpedienteColaboradoresModificados = true;
									}
								}
							}
						}
					} elseif ($numeroCampoHitoExpedienteColaboradores > 0) {
						foreach ($campoHitoExpedienteColaboradores as $campoHitoExpedienteColaborador) {
							$managerEntidad->remove($campoHitoExpedienteColaborador);
						}
						$camposHitoExpedienteColaboradoresModificados = true;
					}
					if ($camposHitoExpedienteColaboradoresModificados) {
						$managerEntidad->flush();
						if (isset($id)) {
							if ($campoHitoExpedienteColaboradoresDatos->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación de los agentes colaboradores seleccionados del campo hito ' . $campoHitoExpedienteColaboradoresDatos->getIdCampoHito(), $expediente));
							}
						}
					}
				}
			}
			$camposHitoExpediente = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findBy(array(
				'idExpediente' => $expediente,
				'avisarColaborador' => true
			));
			foreach ($camposHitoExpediente as $campoHitoExpediente) {
				$campoHitoExpedienteColaboradores = $doctrine->getRepository(CampoHitoExpedienteColaboradoresEntidad::class)->findBy(array(
					'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
					'idHitoExpediente' => $campoHitoExpediente->getIdHitoExpediente(),
					'idExpediente' => $expediente
				));
				if (count($campoHitoExpedienteColaboradores) === 0) {
					$campoHitoExpediente->setAvisarColaborador(false);
					$managerEntidad->persist($campoHitoExpediente);
				}
			}
			foreach ($formulariosFicheroCampo as $formularioFicheroCampoArray) {
				foreach ($formularioFicheroCampoArray as $formularioFicheroCampo) {
					$ficheroCampoABorrar = $formularioFicheroCampo->getData();
					$campoHito = $ficheroCampoABorrar->getIdCampoHito();
					$campoHitoExpediente = $ficheroCampoABorrar->getIdCampoHitoExpediente();
					if (!is_null($ficheroCampoABorrar->getFichero()) && count($ficheroCampoABorrar->getFichero()) > 0) {
						if (count($ficheroCampoABorrar->getFichero()) == 1) { // Hay un sólo fichero y procedemos como antes
							if ($ficheroCampoABorrar->getFichero()[0]->guessExtension() === 'pdf') {
								if ($this->buscarCadenaEnFichero($ficheroCampoABorrar->getFichero()[0], 'adbe.pkcs7.detached')) {
									$campoHitoExpediente->setFirmado(true);
								} else {
									$campoHitoExpediente->setFirmado(false);
								}
							} else {
								if ($campoHitoExpediente->getParaFirmar()) {
									$this->addFlash('warning', 'Has marcado el ' . $campoHito . ' para firmar y has subido un archivo que no es un pdf.');
									return $this->render('@App/Backoffice/AgregarModificar/Expediente.html.twig', $variablesTwig);
								} elseif ($campoHitoExpediente->getFirmado()) {
									$campoHitoExpediente->setFirmado(false);
								}
							}
							if (!is_null($ficheroCampoABorrar->getNombreFichero())) {
								$filesystem = new Filesystem();
								try {
									$filesystem->remove($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampoABorrar->getNombreFichero());
								} catch (IOExceptionInterface $e) {
									$this->addFlash('danger', 'Error al borrar el archivo antiguo.');
									return $this->render('@App/Backoffice/AgregarModificar/Expediente.html.twig', $variablesTwig);
								}
							}
							$nombreFichero = md5(uniqid()) . '.' . $ficheroCampoABorrar->getFichero()[0]->guessExtension();
							try {
								$ficheroCampoABorrar->getFichero()[0]->move($this->getParameter('files_directory'), $nombreFichero);
							} catch (FileException $e) {
								$this->addFlash('danger', 'Error al guardar el archivo en el servidor.');
								return $this->render('@App/Backoffice/AgregarModificar/Expediente.html.twig', $variablesTwig);
							}
						} else { // Aquí tenemos ficheros múltiples y tenemos que unir las imágenes a un pdf y los pdfs entre sí
							$countPdfs = 0;
							$hayImagenesPDF = false;
							$hayPDFs = false;
							$hayOtrosFormatos = false;
							$nombreFichero = md5(uniqid()) . '.pdf';

							$pdf = $this->get('white_october.tcpdf')->create(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
							// set margins
							$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
							$pdf->SetHeaderMargin(0);
							$pdf->SetFooterMargin(0);
							$pdf->setPrintHeader(false);
							// remove default footer
							$pdf->setPrintFooter(false);
							// set auto page breaks
							$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
							// set image scale factor
							$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

							foreach ($ficheroCampoABorrar->getFichero() as $parteFichero) {
								if ($parteFichero->guessExtension() === 'jpg' || $parteFichero->guessExtension() === 'jpeg' || $parteFichero->guessExtension() === 'png') {
									// $ficheroAbierto = fopen($fichero, 'r');
									// fclose($ficheroAbierto);
									// $targetPath=$parteFichero->get;
									// $data = file_get_contents($targetPath);
									// $content= base64_decode($data);
									$ruta = $parteFichero->getPathname();
									$img = file_get_contents($ruta);

									$pdf->AddPage();
									// $pdf->Image('@' . $img, '', '', 0, 0, '', '', true);
									$bMargin = $pdf->getBreakMargin();
									// get current auto-page-break mode
									$auto_page_break = $pdf->getAutoPageBreak();
									// disable auto-page-break
									$pdf->SetAutoPageBreak(false, 0);
									$pdf->Image('@' . $img, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, true, false, false);
									// restore auto-page-break status
									$pdf->SetAutoPageBreak($auto_page_break, $bMargin);
									// set the starting point for the page content
									$pdf->setPageMark();
									$hayImagenesPDF = true;
								} elseif ($parteFichero->guessExtension() === 'pdf') {
									$countPdfs++;
								} else {
									$hayOtrosFormatos = true;
								}
							}

							// Hay documentos que no son pdf o imágenes, y también pdf o imágenes
							if ($hayOtrosFormatos && ($hayImagenesPDF || $countPdfs > 0)) {
								$this->addFlash('danger', 'Sólo puede unir documentos PDF y archivos de imagen .jpg .jpeg y .png. El resto de formatos no se pueden combinar.');
								return $this->render('@App/Backoffice/AgregarModificar/Expediente.html.twig', $variablesTwig);
							}

							// Guardamos el PDF de las imágenes
							if ($hayImagenesPDF) {
								$nombreFicheroPDF = md5(uniqid()) . '.pdf';
								$pdf->Output($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $nombreFicheroPDF, 'F');
							}
							// Ahora juntamos el pdf de las imágenes con otros pdfs que hayan podido adjuntar
							if ($countPdfs > 0) {
								// Create an instance of PDFMerger
								$pdf = new PDFMerger();
								if ($hayImagenesPDF) {
									$pdf->addPDF($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $nombreFicheroPDF, 'all');
								}
								foreach ($ficheroCampoABorrar->getFichero() as $parteFichero) {
									if ($parteFichero->guessExtension() === 'pdf') {
										// Add PDFs to the final PDF
										$pdf14 = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . md5(uniqid()) . '.pdf';
										shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="' . $pdf14 . '" "' . $parteFichero->getPathname() . '"');
										$pdf->addPDF($pdf14, 'all');
										$hayPDFs = true;
									}
								}
								if ($hayPDFs) {
									// Merge the files into a file in some directory
									$pathForTheMergedPdf = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $nombreFichero;
									$pdf->merge('file', $pathForTheMergedPdf);
								}
							} else {
								$nombreFichero = $nombreFicheroPDF;
							}



							if (!is_null($ficheroCampoABorrar->getNombreFichero())) {
								$filesystem = new Filesystem();
								try {
									$filesystem->remove($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampoABorrar->getNombreFichero());
								} catch (IOExceptionInterface $e) {
									$this->addFlash('danger', 'Error al borrar el archivo antiguo.');
									return $this->render('@App/Backoffice/AgregarModificar/Expediente.html.twig', $variablesTwig);
								}
							}
						}
						$ficheroCampoABorrar->setNombreFichero($nombreFichero);
						$managerEntidad->persist($ficheroCampoABorrar);
						if (isset($id)) {
							if ($campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Subido fichero al campo hito "' . $campoHito . '".', $expediente));
							}
						}
						if (!is_null($expediente->getIdCliente()) && !is_null($campoHitoExpediente->getEnviarAlCliente() || (!is_null($expediente->getIdColaborador()) || !is_null($campoHitoExpediente->getEnviarAlColaborador())))) {
							$enviarNotificacion = false;

							// Es cliente o colaborador
							$notificacion = (new NotificacionEntidad())
								->setIdExpediente($expediente)
								->setFecha(new DateTime());
							if ($campoHitoExpediente->getEnviarAlColaborador()) {
								$enviarNotificacion = true;
								$notificacion->setIdUsuario($expediente->getIdColaborador())
									->setTexto('El cliente ' . (new UsuariosNombreCompleto())->obtener($expediente->getIdCliente()) . ' acaba de subir un documento al campo "' . $campoHito . '" del hito "' . $campoHito->getIdGrupoCamposHito()->getIdHito() . '" del expediente nº ' . $expediente->getIdExpediente() . '.');
								$managerEntidad->persist($notificacion);
							}

							// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
							if ($expediente->getIdComercial()) {
								$notificacion = (new NotificacionEntidad)
									->setIdExpediente($expediente)
									->setEstado(1)
									->setFecha(new DateTime())
									->setIdUsuario($expediente->getIdComercial())
									->setTitulo('Nuevo documento aportado al expediente')
									->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
								$managerEntidad->persist($notificacion);
								// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
								$managerEntidad->flush();
							}

							if ($expediente->getIdTecnico()) {
								$notificacion = (new NotificacionEntidad)
									->setIdExpediente($expediente)
									->setEstado(1)
									->setFecha(new DateTime())
									->setIdUsuario($expediente->getIdTecnico())
									->setTitulo('Nuevo documento aportado al expediente')
									->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
								$managerEntidad->persist($notificacion);
								// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
								$managerEntidad->flush();
							}

							if ($expediente->getIdComercial() == null && $expediente->getIdTecnico() == null) {
								//Aviso de que se ha creado una cuenta
								$roles = array("ROLE_ADMIN", "ROLE_TECNICO", "ROLE_COMERCIAL");
								$usuarios_gn = $doctrine->getRepository(UsuarioEntidad::class)->findBy(
									array(
										'role' => $roles
									)
								);
								foreach ($usuarios_gn as $usuario_gn) {
									$notificacion = (new NotificacionEntidad)
										->setIdExpediente($expediente)
										->setEstado(1)
										->setFecha(new DateTime())
										->setIdUsuario($usuario_gn)
										->setTitulo('Nuevo documento aportado al expediente')
										->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
									$managerEntidad->persist($notificacion);
									$managerEntidad->flush();
								}
							} else {
								$admins = $doctrine->getRepository(UsuarioEntidad::class)->findBy(
									array(
										'role' => 'ROLE_ADMIN'
									)
								);
								foreach ($admins as $admin) {
									$notificacion = (new NotificacionEntidad)
										->setIdExpediente($expediente)
										->setEstado(1)
										->setFecha(new DateTime())
										->setIdUsuario($admin)
										->setTitulo('Nuevo documento aportado al expediente')
										->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
									$managerEntidad->persist($notificacion);
								}
								// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
								$managerEntidad->flush();
							}

							// Si hay que avisar a la entidad
							$camposHitoExpedienteColaboradores = $doctrine->getRepository(CampoHitoExpedienteColaboradoresEntidad::class)->findOneBy(array(
								'idHitoExpediente' => $campoHitoExpediente->getIdHitoExpediente(),
								'idExpediente' => $expediente,
								'idCampoHito' => $campoHitoExpediente->getIdCampoHito()
							));
							if ($campoHitoExpediente->getAvisarColaborador() and $camposHitoExpedienteColaboradores) {
								if ($expediente->getIdComercial() != null) {
									$mailerOk = $this->obtenerMailer($mailer, $expediente->getIdComercial());
									if ($expediente->getIdComercial()->getMailerTransport() != null && $expediente->getIdComercial()->getMailerTransport() != "") {
										$from = array($expediente->getIdComercial()->getMailerUser() => $expediente->getIdComercial()->getUsername() . " " . $expediente->getIdComercial()->getApellidos() . ' - Hipotea');
										if ($expediente->getIdComercial()->getFirmaCorreo() != null && $expediente->getIdComercial()->getFirmaCorreo() != "") {
											$imagenCorreo = $expediente->getIdComercial()->getFirmaCorreo();
										} else {
											$imagenCorreo = "firma_base.png";
										}
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/' . $imagenCorreo . '"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									} else {
										$from = array($this->getParameter('mailer_user') => 'Hipotea');
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									}
								} elseif ($expediente->getIdTecnico() != null) {
									$mailerOk = $this->obtenerMailer($mailer, $expediente->getIdTecnico());
									if ($expediente->getIdTecnico()->getMailerTransport() != null && $expediente->getIdTecnico()->getMailerTransport() != "") {
										$from = array($expediente->getIdTecnico()->getMailerUser() => $expediente->getIdTecnico() . " " . $expediente->getIdTecnico()->getApellidos() . ' - Hipotea');
										if ($expediente->getIdTecnico()->getFirmaCorreo() != null && $expediente->getIdTecnico()->getFirmaCorreo() != "") {
											$imagenCorreo = $expediente->getIdTecnico()->getFirmaCorreo();
										} else {
											$imagenCorreo = "firma_base.png";
										}
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/' . $imagenCorreo . '"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									} else {
										$from = array($this->getParameter('mailer_user') => 'Hipotea');
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									}
								} else {
									$mailerOk = $this->obtenerMailer($mailer, null);
									$from = array($this->getParameter('mailer_user') => 'Hipotea');
									$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
								}
								// $from = array($this->getParameter('mailer_user') => 'Hipotea');
								$body = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.";
								$body .= $subBody;
								$mensaje = (new Swift_Message('Hipotea: Nuevo documento aportado de ' . $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos()))
									->setFrom($from)
									->setTo($camposHitoExpedienteColaboradores->getIdAgenteColaborador()->getEmail())
									->setBody($body, 'text/html');

								$documento = $doctrine->getRepository(FicheroCampoEntidad::class)->findOneBy(array(
									'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
									'idExpediente' => $expediente
								));
								$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $documento->getNombreFichero())->setFilename($documento->getIdCampoHito()->getNombre() . '.pdf'));

								if ($mailerOk->send($mensaje)) {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Se ha enviado un email con el documento aportado ' . $campoHitoExpediente->getIdCampoHito()->getNombre() . ' a ' . (new UsuariosNombreCompleto())->obtener($camposHitoExpedienteColaboradores->getIdAgenteColaborador()), $expediente));
								}
							}

							if ($enviarNotificacion) {
								// $managerEntidad->persist($notificacion);
								if ($campoHito->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
									if (!$existeRegistroActividadConEntidadFaseDatos) {
										$existeRegistroActividadConEntidadFaseDatos = true;
										$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
									}
								} else {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($expediente->getIdCliente()), $expediente));
								}
							}
						}
					} elseif (isset($managerEntidad->getUnitOfWork()->getOriginalEntityData($campoHitoExpediente)['paraFirmar']) && !$managerEntidad->getUnitOfWork()->getOriginalEntityData($campoHitoExpediente)['paraFirmar'] && $campoHitoExpediente->getParaFirmar() && !is_null($ficheroCampoABorrar->getNombreFichero())) {
						$archivo = new File($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampoABorrar->getNombreFichero());
						if ($archivo->guessExtension() !== 'pdf') {
							$this->addFlash('warning', 'Has marcado el ' . $campoHito . ' para firmar y el archivo ya subido no es un pdf.');
							return $this->render('@App/Backoffice/AgregarModificar/Expediente.html.twig', $variablesTwig);
						}
					}
				}
			}

			// foreach ($gruposHitoExpediente as $grupoHitoExpediente){
			// 	$managerEntidad->persist($grupoHitoExpediente);
			// }
			// CAMBIAMOS LA DIRECCION POR LA DEL CAMPO
			if ($expediente->getVivienda() == "CUESTIONARIO APP" || $expediente->getVivienda() == "NUEVA VIVIENDA") {

				// $campoVivienda = $doctrine->getRepository(CampoHito::class)->matching(Criteria::create()
				// 	->where(Criteria::expr()->in('nombre', $ficheroCampoArray))
				// 	->andWhere(Criteria::expr()->neq('valor', null))
				// );
				$campoVivienda = $doctrine->getRepository(CampoHitoEntidad::class)->findOneBy(
					array(
						'nombre' => 'Dirección de la propiedad'
					)
				);

				if ($campoVivienda) {
					$valorCampoVivienda = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findOneBy(
						array(
							'idExpediente' => $expediente,
							'idCampoHito' => $campoVivienda
						)
					);
					if ($valorCampoVivienda && $valorCampoVivienda->getValor() != "") {
						$expediente->setVivienda($valorCampoVivienda->getValor());
						$managerEntidad->persist($expediente);
					}
				}
			}

			// Buscar expedientes relacionados desde la entidad de la vista
			$relacion = $managerEntidad->getRepository(VistaExpedientesRelacionados::class)
				->find($expediente->getIdExpediente());

			if ($relacion && $relacion->getIdsExpedientesRelacionados()) {
				// Tomar el primer id de expediente relacionado
				$idsRelacionados = explode(',', $relacion->getIdsExpedientesRelacionados());
				$idRelacionado = reset($idsRelacionados);

				// Obtener expediente relacionado
				$expRel = $managerEntidad->getRepository(ExpedienteEntidad::class)->find($idRelacionado);

				if ($expRel && $expRel->getIdComercial()) {
					// Actualizar el comercial del nuevo expediente
					$expediente->setIdComercial($expRel->getIdComercial());
					$managerEntidad->persist($expediente);
					$managerEntidad->flush();
				}
			}

			// Ponemos el valor de los campos opciones bien
			$camposHitoExpediente = $doctrine->getRepository(CampoHitoExpedienteEntidad::class)->findBy(array(
				'idExpediente' => $expediente
			));
			foreach ($camposHitoExpediente as $campoHitoExpediente) {
				if ($campoHitoExpediente->getIdOpcionesCampo()) {
					$campoHitoExpediente->setValor('campo_hito_' . $campoHitoExpediente->getIdCampoHitoExpediente() . '_opcion_' . $campoHitoExpediente->getIdOpcionesCampo()->getIdOpcionesCampo());
					$managerEntidad->persist($campoHitoExpediente);
				}
			}

			// AHORA CREAMOS LOS CAMPOS DE LA FASE DE RECOGIDA DE DOCUMENTOS SI ES EXPEDIENTE NUEVO PARA COLABORADOR Y CLIENTE
			if (!isset($id)) {
				$fases = $doctrine->getRepository(FaseEntidad::class)->findBy(array('tipo' => 1), array(
					'orden' => 'ASC'
				));

				if (!isset($id)) {
					$asunto = "Nuevo expediente creado";
				} else {
					$asunto = "Expediente modificado";
				}

				foreach ($fases as $fase) {
					$hitos = $doctrine->getRepository(HitoEntidad::class)->findBy(array(
						'idFase' => $fase
					), array(
						'orden' => 'ASC'
					));
					foreach ($hitos as $hito) {
						$hitoExpediente = (new HitoExpedienteEntidad())
							->setIdHito($hito)
							->setIdExpediente($expediente)
							->setFechaModificacion(new DateTime())
							->setEstado(0);

						$gruposCamposHito = $doctrine->getRepository(GrupoCamposHitoEntidad::class)->findBy(array(
							'idHito' => $hito
						), array(
							'orden' => 'ASC'
						));

						foreach ($gruposCamposHito as $grupoCamposHito) {

							$grupoHitoExpediente = (new GrupoHitoExpedienteEntidad())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoCamposHito($grupoCamposHito);

							$camposHito = $doctrine->getRepository(CampoHitoEntidad::class)->findBy(array(
								'idGrupoCamposHito' => $grupoCamposHito
							), array(
								'orden' => 'ASC'
							));
							foreach ($camposHito as $campoHito) {
								$campoHitoExpediente = (new CampoHitoExpedienteEntidad())
									->setIdCampoHito($campoHito)
									->setIdHitoExpediente($hitoExpediente)
									->setIdGrupoHitoExpediente($grupoHitoExpediente)
									->setIdExpediente($expediente)
									->setFechaModificacion(new DateTime());

								if ($campoHito->getTipo() == 4) {
									$campoHitoExpediente->setObligatorio(1)
										->setSolicitarAlColaborador(1);
								}
								$managerEntidad->persist($campoHitoExpediente);
							}

							$managerEntidad->persist($grupoHitoExpediente);
						}

						$managerEntidad->persist($hitoExpediente);
					}
				}

				// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
				$comercialesnoti = $doctrine->getRepository(UsuarioEntidad::class)->findBy(
					array(
						'role' => 'ROLE_COMERCIAL'
					)
				);
				foreach ($comercialesnoti as $comercialnoti) {
					$notificacion = (new NotificacionEntidad)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($comercialnoti)
						->setTitulo($asunto)
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
					$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($comercialnoti, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
				}

				$tecnicosnoti = $doctrine->getRepository(UsuarioEntidad::class)->findBy(
					array(
						'role' => 'ROLE_TECNICO'
					)
				);
				foreach ($tecnicosnoti as $tecniconoti) {
					$notificacion = (new NotificacionEntidad)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($tecniconoti)
						->setTitulo($asunto)
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
					$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($tecniconoti, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
				}

				$admins = $doctrine->getRepository(UsuarioEntidad::class)->findBy(
					array(
						'role' => 'ROLE_ADMIN'
					)
				);
				foreach ($admins as $admin) {
					$notificacion = (new NotificacionEntidad)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($admin)
						->setTitulo($asunto)
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}
			}

			$managerEntidad->flush();

			// AHORA SI HA CAMBIADO DE FASE ENVIAMOS NOTIFICACIÓN
			if (isset($fasePrevia) && $fasePrevia != $faseActual) {
				$tituloNotificacion = "Tu expediente ha cambiado de fase";
				$textoNotificacion = "Tu expediente se encuentra ahora en la fase de " . $faseActual->getNombre();
				if ($expediente->getIdCliente()) {
					$this->enviarNotificacionPush($expediente, null, $expediente->getIdCliente(), $tituloNotificacion, $textoNotificacion);
				}
			}
			// if (isset($expedienteFinalizado) && !$expedienteFinalizado) {
			// 	$this->addFlash('warning', 'El expediente no se pudo finalizar por que tiene hitos pendientes.');
			// 	return $this->redirectToRoute('agregar_modificar_expediente', array(
			// 		'id' => $id
			// 	));
			// }
			/*if (isset($hitosExpedienteCompletados) && isset($faseNueva) && isset($fase) && !$hitosExpedienteCompletados) {
				$this->addFlash('warning', 'No se puede pasar a la fase "' . $faseNueva . '" por que la fase "' . $fase . '"" tiene hitos pendientes.');
				return $this->redirectToRoute('agregar_modificar_expediente', array(
					'id' => $id
				));
			}*/
			$this->addFlash('success', 'El expediente ha sido agregado/modificado correctamente.');
			//return $this->redirectToRoute('lista_expedientes');
			// TODO: Cambiar redireccion

			// Ahora enviamos el email al clienteKKKK
			// Recuperamos el nombre y el email que ha introducido

			// Ahora recuperamos los campos email, telefono de los formularios web
			$camposPersonalizados = [];
			$repositorio['expedientes'] = $doctrine->getRepository(ExpedienteEntidad::class);
			$repositorio['usuarios'] = $doctrine->getRepository(UsuarioEntidad::class);
			$repositorio['camposHitoExpediente'] = $doctrine->getRepository(CampoHitoExpedienteEntidad::class);
			$repositorio['camposHito'] = $doctrine->getRepository(CampoHitoEntidad::class);

			$campoHitoEmail = $repositorio['camposHito']->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('idCampoHito', 407))
			);
			$campoHitoTelefono = $repositorio['camposHito']->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('idCampoHito', 408))
			);
			// $i = 0;
			// foreach ($expedientes as $expediente) {
			$campoExpedienteEmail = $repositorio['camposHitoExpediente']->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('idExpediente', $expediente))
					->andWhere(Criteria::expr()->eq('idCampoHito', $campoHitoEmail[0]))
			);
			$campoExpedienteTelefono = $repositorio['camposHitoExpediente']->matching(
				Criteria::create()
					->where(Criteria::expr()->eq('idExpediente', $expediente))
					->andWhere(Criteria::expr()->eq('idCampoHito', $campoHitoTelefono[0]))
			);
			$camposPersonalizados[$expediente->getIdExpediente()] = [
				"email" => ($campoExpedienteEmail[0] == null ? "" : ($campoExpedienteEmail[0])->getValor()),
				"telefono" => ($campoExpedienteTelefono[0] == null ? "" : ($campoExpedienteTelefono[0])->getValor())
			];
			// }


			$mailerOk = $this->obtenerMailer($mailer, null);
			$from = array($this->getParameter('mailer_user') => 'Hipotea');
			$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
			// $from = array($this->getParameter('mailer_user') => 'Hipotea');
			$body = "Estimado/a " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . ",<br><br>
					¡Gracias por confiar en nosotros para tu solicitud hipotecaria! Queremos confirmarte que hemos recibido tu solicitud correctamente y ya estamos trabajando para analizarla detalladamente. Nuestro objetivo es presentarte las mejores opciones adaptadas a tus necesidades.<br><br>
					Nuestro equipo se pondrá en contacto contigo en un plazo máximo de 5 días hábiles. Si no recibes noticias nuestras dentro de este período, lamentablemente, significa que, según las circunstancias actuales de tu solicitud y las opciones disponibles en el mercado, no podemos ofrecerte una propuesta adecuada. Esto puede deberse a varios factores específicos de tu situación financiera o a las condiciones del mercado hipotecario.<br><br>
					Agradecemos tu paciencia y comprensión. Si tienes alguna pregunta o necesitas información adicional mientras tanto, no dudes en contactarnos.<br><br>
					Un cordial saludo.<br><br>";
			$body .= $subBody;
			if ($campoHitoExpediente->getAvisarColaborador() and $camposHitoExpedienteColaboradores) {
				$mensaje = (new Swift_Message('Hipotea: Confirmación de recepción de tu solicitud hipotecaria'))
					->setFrom($from)
					->setTo($camposHitoExpedienteColaboradores->getIdAgenteColaborador()->getEmail())
					->setBody($body, 'text/html');

				$documento = $doctrine->getRepository(FicheroCampoEntidad::class)->findOneBy(array(
					'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
					'idExpediente' => $expediente
				));
				$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $documento->getNombreFichero())->setFilename($documento->getIdCampoHito()->getNombre() . '.pdf'));

				if ($mailerOk->send($mensaje)) {
					$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Se ha enviado un email con el documento aportado ' . $campoHitoExpediente->getIdCampoHito()->getNombre() . ' a ' . (new UsuariosNombreCompleto())->obtener($camposHitoExpedienteColaboradores->getIdAgenteColaborador()), $expediente));
				}
			}

			$mensajeUrl = [
				"mensaje" => "<center><h3>¡Gracias por tu tiempo!</h3><br><br>Tu información es valiosa para nosotros porque cada detalle cuenta. Pronto uno de nuestros expertos se pondrá en contacto contigo para ofrecerte la mejor hipoteca adaptada a ti.<br><br>Hipotea, si buscas hipoteca, la mejor idea.</center>"
			];

			return $this->render('@App/Frontoffice/FinRegistro.html.twig', $mensajeUrl);
			return $this->redirectToRoute('fin_registro', array(
				'mensaje' => "<h3>Hemos recibido su solicitud.</h3><br>Nos pondremos en contacto con la mayor brevedad posible."
			));
			//Recarga forzada para poder ver la imagen en el plugin Dropify
		}
		// dump($variablesTwig);
		// dump($variablesTwig['agregarModificarCampoHitoExpediente']);
		// dump($variablesTwig['grupos_hito_expediente']);
		// dump($formularioExpediente->getErrors(true));
		// die;
		return $this->render('@App/Frontoffice/NuevoExpediente.html.twig', $variablesTwig);
	}

	private
	function redondearBytesAUnidad($size)
	{
		if (is_numeric($size)) {
			$valor = $size;
			$unidades = array(
				'K',
				'M',
				'G',
				'T',
				'P',
				'E',
				'Z',
				'Y'
			);
			$numUnidades = count($unidades);
			for ($i = 0; $valor % 1024 === 0 && $i < $numUnidades; $i += 1) {
				$valor /= 1024;
			}
			if ($i === 0) {
				return $valor / 1024 . $unidades[$i];
			} else {
				return $valor . $unidades[$i - 1];
			}
		}
		return null;
	}

	private
	function convertirABytes($size)
	{
		if (is_numeric($size)) {
			$valor = $size;
		} else {
			$unidad = substr($size, -1);
			$valor = substr($size, 0, -1);
			switch (strtoupper($unidad)) {
				case 'Y':
					$valor *= 1208925819614629174706176;
					break;
				case 'Z':
					$valor *= 1180591620717411303424;
					break;
				case 'E':
					$valor *= 1152921504606846976;
					break;
				case 'P':
					$valor *= 1125899906842624;
					break;
				case 'T':
					$valor *= 1099511627776;
					break;
				case 'G':
					$valor *= 1073741824;
					break;
				case 'M':
					$valor *= 1048576;
					break;
				case 'K':
					$valor *= 1024;
					break;
				default:
					$valor = null;
			}
		}
		return $valor;
	}

	private
	function comprobarSiLasEntidadesSonIguales($managerEntidad, $entidadModificada)
	{
		$entidadOriginalArray = $managerEntidad->getUnitOfWork()->getOriginalEntityData($entidadModificada);
		$entidadModificadaArray = $this->convertirObjetoAArrayAsociativo($entidadModificada);
		if (count($entidadOriginalArray) > 0) {
			$iguales = true;
			foreach ($entidadModificadaArray as $clave => $valor) {
				if (isset($entidadModificadaArray[$clave]) && isset($entidadOriginalArray[$clave]) && $entidadModificadaArray[$clave] !== $entidadOriginalArray[$clave]) {
					$iguales = false;
					break;
				}
			}
		} else {
			$iguales = null;
		}
		return $iguales;
	}

	private
	function convertirObjetoAArrayAsociativo($objeto)
	{
		$array = array();
		try {
			$reflectionClass = new ReflectionClass(get_class($objeto));
			foreach ($reflectionClass->getProperties() as $propiedad) {
				$propiedad->setAccessible(true);
				$array[$propiedad->getName()] = $propiedad->getValue($objeto);
				$propiedad->setAccessible(false);
			}
		} catch (ReflectionException $e) {
		}
		return $array;
	}

	private function obtenerMailer(Swift_Mailer $mailer, $usuario){
		if($usuario != null && $usuario->getMailerTransport()!= null && $usuario->getMailerTransport()!= ""){
			$transport = Swift_SmtpTransport::newInstance($this->getUser()->getMailerHost());

			// change hostname and port
			$transport
				->setHost($usuario->getMailerHost())
				// ->setTransport($this->getUser()->getMailerTransport())
				->setUsername($usuario->getMailerUser())
				->setPassword($usuario->getMailerPassword())
				->setEncryption($usuario->getMailerEncryption())
				->setAuthMode($usuario->getMailerAuthMode())
				->setPort($usuario->getMailerPort());

			$mailer = Swift_Mailer::newInstance($transport);
		}
		return $mailer;
	}

	private
	function buscarCadenaEnFichero($fichero, $cadena)
	{
		$ficheroAbierto = fopen($fichero, 'r');
		$valido = false;
		while (($bufer = fgets($ficheroAbierto)) !== false) {
			if (strpos($bufer, $cadena) !== false) {
				$valido = true;
				break;
			}
		}
		fclose($ficheroAbierto);
		return $valido;
	}
}
