<?php

namespace AppBundle\Controller;

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
use AppBundle\Utils\UsuariosNombreCompleto;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;
use Doctrine\Common\Collections\Criteria;
use Swift_Attachment;
use Swift_SmtpTransport;


class APIController extends Controller
{
	public function iniciarSesionAction()
	{
	}

	public function documentosPorSubirAction($idExpediente)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo'),
			'Fases' => $doctrine->getRepository('AppBundle:Fase'),
			'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
			'HitoExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
			'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
			'CamposHito' => $doctrine->getRepository('AppBundle:CampoHito'),
			'CamposHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo'),
		);

		if($idExpediente != null && $idExpediente != 'null'){
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idExpediente' => $idExpediente,
				'estado' => '1'
			), array(
					'idExpediente' => 'DESC'
				)
			);
		}else{
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idCliente' => $this->getUser()->getIdUsuario(),
				'estado' => '1'
			), array(
					'idExpediente' => 'DESC'
				)
			);
		}
		
		$fases = $repositorios['Fases']->findBy(array(
			'tipo' => 1),
			array(
			'orden' => 'ASC'
		));
		$hitosSinFiltro = $repositorios['Hitos']->findBy(array(
			'idFase' => $fases
		));

		$hitosTodos = array();
		foreach ($hitosSinFiltro as $hito){
			if($hito->getHitoCondicional() == 0){
				$hitosTodos[] = $hito;
			}else{
				$opcionCond = $repositorios['OpcionesCampo']->findBy(array(
					'idHitoCondicional' => $hito
				));
				$campoCond =  $repositorios['CamposHitoExpediente']->findBy(array(
					'idOpcionesCampo' => $opcionCond,
					'idExpediente' => $expediente->getIdExpediente(),
				));
				if($campoCond != null and count($campoCond)>0){
					$hitosTodos[] = $hito;
				}
			}
		}
		
		$fases_grupo = array();
		if ($expediente) {
			foreach ($fases as $fase) {
				$hitos = $repositorios['HitoExpediente']->findBy(array(
					'idExpediente' => $expediente,
					'idHito' => $hitosTodos
				), array(
					'idHito' =>'ASC'
				));

				$hitos_grupo = array();
				$ficheros_grupo = array();
				foreach ($hitos as $hito) {
					$gruposCampos = $repositorios['GruposCampos']->findBy(array(
						'idHito' => $hito->getIdHito()
					), array(
						'orden' => 'ASC'
					));
					$grupos_campos_grupo = array();
					foreach ($gruposCampos as $grupoCampos) {
						$camposHito = $repositorios['CamposHito']->findBy(array(
							'idGrupoCamposHito' => $grupoCampos,
							'tipo' => '4'
						), array(
							'orden' => 'ASC'
						));
						$ficheros_grupo = array();
						foreach ($camposHito as $campoHito) {
							$camposHitoExpediente = $repositorios['CamposHitoExpediente']->findBy(array(
								'idCampoHito' => $campoHito->getIdCampoHito(),
								'idExpediente' => $expediente->getIdExpediente(),
								'idHitoExpediente' => $hito,
								'obligatorio' => true,
								'paraFirmar' => false
							));
							foreach ($camposHitoExpediente as $campoHitoExpediente) {
								$ficheroCampo = $repositorios['FicheroCampo']->findOneBy(array(
									'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
									'idCampoHitoExpediente' => $campoHitoExpediente,
									'idExpediente' => $campoHitoExpediente->getIdExpediente()
								));
								$fichero = array(
									'idCampoHito' => $campoHito->getIdCampoHito(),
									'idExpediente' => $expediente->getIdExpediente(),
									'idCampoHitoExpediente' => $campoHitoExpediente->getIdCampoHitoExpediente(),
									'nombre' => $campoHito->getNombre()
								);
								if ($ficheroCampo) {
									$fichero['nombreFichero'] = $ficheroCampo->getNombreFichero();
								} else {
									$fichero['nombreFichero'] = '';
								}
								$ficheros_grupo[] = $fichero;
							}
						}
						if (count($ficheros_grupo)) {
							$grupo_campos_hito = array(
								'grupoCampos' => $grupoCampos->getNombre(),
								'ficheros' => $ficheros_grupo
							);
							$grupos_campos_grupo[] = $grupo_campos_hito;
						}
					}
					if (count($ficheros_grupo)) {
						$hito_grupo = array(
							'hito' => $hito->getIdHito()->getNombre(),
							'idHito' => $hito->getIdHito()->getIdHito(),
							'idHitoExpediente' => $hito->getIdHitoExpediente(),
							'repetible' => $hito->getIdHito()->getRepetible(),
							'ficheros' => $ficheros_grupo
						);
						$hitos_grupo[] = $hito_grupo;
					}
				}
				if (count($hitos_grupo)) {
					$fase_grupo = array(
						'fase' => $fase->getNombre(),
						'hitos' => $hitos_grupo
					);
					$fases_grupo[] = $fase_grupo;
				}
			}
		}
		return new JsonResponse($fases_grupo, JSON_UNESCAPED_UNICODE);
	}

	public function recuperaDocumentoAction($idCampoHitoExpediente = null)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'CampoHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
		);
		$expediente = $repositorios['Expedientes']->findOneBy(array(
			'idCliente' => $this->getUser()->getIdUsuario(),
			'estado' => '1'
		), array(
				'idExpediente' => 'DESC'
			)
		);

		$camposHitoExpediente = $repositorios['CampoHitoExpediente']->findOneBy(array(
			'idCampoHitoExpediente' => $idCampoHitoExpediente,
			'idExpediente' => $expediente
		));
		$fichero = array();
		if ($camposHitoExpediente) {
			$fichero = array(
				'idCampoHito' => $camposHitoExpediente->getIdCampoHito()->getIdCampoHito(),
				'idExpediente' => $camposHitoExpediente->getIdExpediente()->getIdExpediente(),
				'idCampoHitoExpediente' => $idCampoHitoExpediente
			);
		}
		return new JsonResponse($fichero, JSON_UNESCAPED_UNICODE);
	}

	public function recibirImagenesAction(Request $request, $idCampoHitoExpediente, LoggerInterface $logger, Swift_Mailer $mailer)
	{
		$respuesta = null;
		$doctrine = $this->getDoctrine();
		$managerEntidad = $doctrine->getManager();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
			'GrupoHitoExpediente' => $doctrine->getRepository('AppBundle:GrupoHitoExpediente'),
			'CampoHito' => $doctrine->getRepository('AppBundle:CampoHito'),
			'CamposHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo'),
			'ImagenesFichero' => $doctrine->getRepository('AppBundle:ImagenFichero')
		);

		if(strpos($idCampoHitoExpediente,'[')){
			$idCampoHitoExpediente = substr($idCampoHitoExpediente,0,strpos($idCampoHitoExpediente,'['));
			$campoHitoExpedienteRepetido = $repositorios['CamposHitoExpediente']->findOneBy(array(
				'idCampoHitoExpediente' => $idCampoHitoExpediente
			));

			$hitoExpediente = (new HitoExpediente())
				->setIdHito($campoHitoExpedienteRepetido->getIdHitoExpediente()->getIdHito())
				->setIdExpediente($campoHitoExpedienteRepetido->getIdHitoExpediente()->getIdExpediente())
				->setEstado(0)
				->setFechaModificacion(new DateTime());
			
			$managerEntidad->persist($hitoExpediente);

			$gruposHito = $repositorios['GruposCampos']->findBy(array(
				'idHito' => $campoHitoExpedienteRepetido->getIdHitoExpediente()->getIdHito()
			));
			foreach($gruposHito as $grupoHito){
				$nuevoGrupoHitoExpediente = (new GrupoHitoExpediente())
					->setIdHitoExpediente($hitoExpediente)
					->setIdGrupoCamposHito($grupoHito);
				$managerEntidad->persist($nuevoGrupoHitoExpediente);
				$camposHitoExpediente = $repositorios['CamposHitoExpediente']->findBy(array(
					'idHitoExpediente' => $campoHitoExpedienteRepetido->getIdHitoExpediente()
				));
				foreach($camposHitoExpediente as $campoHitoExpediente){
					$campoHitoExpedienteNuevo = (new CampoHitoExpediente())
						->setIdCampoHito($campoHitoExpediente->getIdCampoHito())
						->setIdExpediente($campoHitoExpedienteRepetido->getIdExpediente())
						->setIdHitoExpediente($hitoExpediente)
						->setValor($campoHitoExpediente->getValor())
						->setObligatorio($campoHitoExpediente->getObligatorio())
						->setSolicitarAlColaborador($campoHitoExpediente->getSolicitarAlColaborador())
						->setAvisarColaborador($campoHitoExpediente->getAvisarColaborador())
						->setParaFirmar($campoHitoExpediente->getParaFirmar())
						->setFirmado($campoHitoExpediente->getFirmado())
						->setEnviarAlCliente($campoHitoExpediente->getEnviarAlCliente())
						->setEnviarAlColaborador($campoHitoExpediente->getEnviarAlColaborador())
						->setIdGrupoHitoExpediente($nuevoGrupoHitoExpediente)
						->setFechaModificacion(new DateTime());
					$managerEntidad->persist($campoHitoExpedienteNuevo);
				}
			}
			$managerEntidad->flush();

			$grupoHitoExpediente = $repositorios['GrupoHitoExpediente']->findOneBy(array(
				'idHitoExpediente' => $hitoExpediente,
				'idGrupoCamposHito' => $campoHitoExpedienteRepetido->getIdGrupoHitoExpediente()->getIdGrupoCamposHito()
			));

			$campoHitoExpediente = $repositorios['CamposHitoExpediente']->findOneBy(array(
				'idHitoExpediente' => $hitoExpediente,
				'idGrupoHitoExpediente' => $grupoHitoExpediente,
				'idCampoHito' => $campoHitoExpedienteRepetido->getIdCampoHito()
			));
		}else{
			$campoHitoExpediente = $repositorios['CamposHitoExpediente']->findOneBy(array(
				'idCampoHitoExpediente' => $idCampoHitoExpediente
			));
		}
		
		$expediente = $repositorios['Expedientes']->findOneBy(array(
			'idExpediente' => $campoHitoExpediente->getIdExpediente(),
			'idCliente' => $this->getUser()->getIdUsuario(),
			'estado' => '1'
		));
		if(!$expediente){
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idExpediente' => $campoHitoExpediente->getIdExpediente(),
				'idColaborador' => $this->getUser()->getIdUsuario(),
				'estado' => '1'
			));
		}
		if ($expediente) {
			if ($campoHitoExpediente) {
				$imagenes = json_decode($request->getContent())->imagenes;
				$sonImagenes = true;
				if (isset($imagenes)) {
					if ($sonImagenes) {
						$pdf = $this->get('white_october.tcpdf')->create();
						foreach ($imagenes as $imagen) {
							$img = base64_decode(str_replace('data:image/jpeg;base64,', "", $imagen));
							$pdf->AddPage();
							$pdf->Image('@' . $img);
						}
						$ficheroCampo = $repositorios['FicheroCampo']->findOneBy(array(
							'idCampoHitoExpediente' => $campoHitoExpediente
						));
						$existeRegistroActividadConEntidadFaseDatos = false;
						if ($ficheroCampo) {
							$pdf->Output($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampo->getNombreFichero(), 'F');
							foreach ($imagenes as $imagen) {
								$img = base64_decode(str_replace('data:image/jpeg;base64,', "", $imagen));
								$nombreFichero = md5(uniqid()) . '.' . 'jpg';
								try {
									$nombreImg = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $nombreFichero;
									file_put_contents($nombreImg, $img);
								} catch (FileException $e) {
									error_log("Error al guardar imagen");
								}
								$imagenFichero = (new ImagenFichero())
									->setNombreImagen($nombreFichero)
									->setIdFicheroCampo($ficheroCampo);
								$managerEntidad->persist($imagenFichero);
								if ($campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
									if (!$existeRegistroActividadConEntidadFaseDatos) {
										$existeRegistroActividadConEntidadFaseDatos = true;
										$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
									}
								} else {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Subido fichero al campo hito ' . $ficheroCampo->getIdCampoHito()->getNombre(), $expediente));
								}
								$managerEntidad->flush();
							}
						} else {
							$nombreFichero = md5(uniqid()) . '.pdf';
							$pdf->Output($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $nombreFichero, 'F');
							$ficheroCampo = (new FicheroCampo())
								->setIdExpediente($expediente)
								->setIdCampoHito($campoHitoExpediente->getIdCampoHito())
								->setIdCampoHitoExpediente($campoHitoExpediente)
								->setNombreFichero($nombreFichero);
							$managerEntidad->persist($ficheroCampo);
							if ($campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Subido fichero al campo ' . $ficheroCampo->getIdCampoHito()->getNombre(), $expediente));
							}
							$managerEntidad->flush();
							$imagenesFichero = $repositorios['ImagenesFichero']->findBy(array(
								'idFicheroCampo' => $ficheroCampo
							));
							foreach ($imagenesFichero as $imagenFicheroDel) {
								$managerEntidad->remove($imagenFicheroDel);
								$managerEntidad->flush();
							}
							foreach ($imagenes as $imagen) {
								$img = base64_decode(str_replace('data:image/jpeg;base64,', "", $imagen));
								$nombreFichero = md5(uniqid()) . '.' . 'jpg';
								try {
									$nombreImg = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $nombreFichero;
									file_put_contents($nombreImg, $img);
								} catch (FileException $e) {
									error_log("Error al guardar imagen");
								}
								$imagenFichero = (new ImagenFichero())
									->setNombreImagen($nombreFichero)
									->setIdFicheroCampo($ficheroCampo);
								$managerEntidad->persist($imagenFichero);
								if ($campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
									if (!$existeRegistroActividadConEntidadFaseDatos) {
										$existeRegistroActividadConEntidadFaseDatos = true;
										$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
									}
								} else {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Subido fichero al campo ' . $ficheroCampo->getIdCampoHito()->getNombre(), $expediente));
								}
								$managerEntidad->flush();
							}
						}
						$titulo = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado un documento";
						$mensaje = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " con el escáner.";
						// $this->enviarNotificacion($expediente,$campoHitoExpediente,null,$titulo,$mensaje);
						// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
						if($expediente->getIdComercial()){
							$notificacion = (new Notificacion)
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
						
						if($expediente->getIdTecnico()){
							$notificacion = (new Notificacion)
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

						if($expediente->getIdComercial() == null && $expediente->getIdTecnico() == null){
							//Aviso de que se ha creado una cuenta
							$roles = array("ROLE_ADMIN", "ROLE_TECNICO", "ROLE_COMERCIAL");
							$usuarios_gn = $doctrine->getRepository(Usuario::class)->findBy(array(
									'role' => $roles
								)
							);
							foreach($usuarios_gn as $usuario_gn){
								$notificacion = (new Notificacion)
									->setIdExpediente($expediente)
									->setEstado(1)
									->setFecha(new DateTime())
									->setIdUsuario($usuario_gn)
									->setTitulo('Nuevo documento aportado al expediente')
									->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
								$managerEntidad->persist($notificacion);
								$managerEntidad->flush();
							}
						}else{
							$admins = $doctrine->getRepository(Usuario::class)->findBy(array(
									'role' => 'ROLE_ADMIN'
								)
							);
							foreach($admins as $admin){
								$notificacion = (new Notificacion)
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
						if($campoHitoExpediente->getAvisarColaborador() and $camposHitoExpedienteColaboradores){
							if($expediente->getIdComercial() != null){
								$mailerOk = $this->obtenerMailer($mailer,$expediente->getIdComercial());
								if($expediente->getIdComercial()->getMailerTransport()!= null && $expediente->getIdComercial()->getMailerTransport()!= ""){
									$from = array($expediente->getIdComercial()->getMailerUser() => $expediente->getIdComercial()->getUsername()." ".$expediente->getIdComercial()->getApellidos(). ' - Hipotea');
									if($expediente->getIdComercial()->getFirmaCorreo() != null && $expediente->getIdComercial()->getFirmaCorreo() != ""){
										$imagenCorreo = $expediente->getIdComercial()->getFirmaCorreo();
									}else{
										$imagenCorreo = "firma_base.png";
									}
									$subBody = '<img src="https://areaprivada.hipotea.com/uploads/'.$imagenCorreo.'"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
									Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
								}else{
									$from = array($this->getParameter('mailer_user') => 'Hipotea');
									$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
									Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
								}
							}elseif($expediente->getIdTecnico() != null){
								$mailerOk = $this->obtenerMailer($mailer,$expediente->getIdTecnico());
								if($expediente->getIdTecnico()->getMailerTransport()!= null && $expediente->getIdTecnico()->getMailerTransport()!= ""){
									$from = array($expediente->getIdTecnico()->getMailerUser() => $expediente->getIdTecnico()." ".$expediente->getIdTecnico()->getApellidos(). ' - Hipotea');
									if($expediente->getIdTecnico()->getFirmaCorreo() != null && $expediente->getIdTecnico()->getFirmaCorreo() != ""){
										$imagenCorreo = $expediente->getIdTecnico()->getFirmaCorreo();
									}else{
										$imagenCorreo = "firma_base.png";
									}
									$subBody = '<img src="https://areaprivada.hipotea.com/uploads/'.$imagenCorreo.'"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
									Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
								}else{
									$from = array($this->getParameter('mailer_user') => 'Hipotea');
									$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
									Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
								}
							}else{
								$mailerOk = $this->obtenerMailer($mailer,null);
								$from = array($this->getParameter('mailer_user') => 'Hipotea');
								$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
								Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
							}
							$body = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha aportado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.";
							$body.=$subBody;
							$mensaje = (new Swift_Message('Hipotea: Nuevo documento de '.$this->getUser()->getUsername() . " " . $this->getUser()->getApellidos()))
								->setFrom($from)
								->setTo($camposHitoExpedienteColaboradores->getIdAgenteColaborador()->getEmail())
								->setBody($body, 'text/html');
								
							$documento = $doctrine->getRepository(FicheroCampoEntidad::class)->findOneBy(array(
								'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
								'idExpediente' => $expediente
							));
							$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $documento->getNombreFichero())->setFilename($documento->getIdCampoHito()->getNombre().'.pdf'));

							if ($mailerOk->send($mensaje)) {
								$respuesta = $expediente->getIdExpediente();
							} else {
								$respuesta = $expediente->getIdExpediente();
							}
						}else{
							$respuesta = $expediente->getIdExpediente();
						}
					} else {
						$respuesta = null;
					}
				} else {
					$respuesta = null;
				}
			} else {
				$respuesta = null;
			}
		} else {
			$respuesta = null;
		}
		return new Response($respuesta);
	}

	public function documentosParaFirmarAction()
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'CamposHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo')
		);
		$expedientes = $repositorios['Expedientes']->findBy(array(
			'idCliente' => $this->getUser()->getIdUsuario(),
			'estado' => '1'
		));
		$respuesta = array();
		foreach ($expedientes as $expediente) {
			$camposHitoExpediente = $repositorios['CamposHitoExpediente']->findBy(array(
				'idExpediente' => $expediente->getIdExpediente(),
				'paraFirmar' => true
			));
			foreach ($camposHitoExpediente as $campoHitoExpediente) {
				$ficheroCampos = $repositorios['FicheroCampo']->findBy(array(
					'idCampoHitoExpediente' => $campoHitoExpediente
				));
				foreach ($ficheroCampos as $ficheroCampo) {
					// $path = $this->getParameter('kernel.project_dir') . '/web/uploads/' . $ficheroCampo->getNombreFichero();
					// if ($ficheroCampo->getNombreFichero()) {
					// 	$b64Doc = chunk_split(base64_encode(file_get_contents($path)));
					// } else {
					$b64Doc = null;
					// }
					$campo = array(
						'idExpediente' => $expediente->getIdExpediente(),
						'idCampoHitoExpediente' => $campoHitoExpediente->getIdCampoHitoExpediente(),
						'nombre' => $campoHitoExpediente->getValor(),
						'firmado' => $campoHitoExpediente->getFirmado(),
						'nombreFichero' => $ficheroCampo->getNombreFichero(),
						'fichero64' => $b64Doc
					);
					$respuesta [] = $campo;
				}
			}
		}
		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	public function recibirDocumentoFirmadoAction(Request $request, $idCampoHitoExpediente, Swift_Mailer $mailer)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'CamposHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo')
		);
		$campoHitoExpediente = $repositorios['CamposHitoExpediente']->findOneBy(array(
			'idCampoHitoExpediente' => $idCampoHitoExpediente,
			'paraFirmar' => true
		));
		$expediente = $repositorios['Expedientes']->findOneBy(array(
			'idExpediente' => $campoHitoExpediente->getIdExpediente(),
			'idCliente' => $this->getUser()->getIdUsuario(),
			'estado' => '1'
		));
		if ($expediente) {
			if ($campoHitoExpediente) {
				$fichero = json_decode($request->getContent())->pdf;
				if (isset($fichero) && count($fichero) > 0) {
					$ficheroCampo = $repositorios['FicheroCampo']->findOneBy(array(
						'idCampoHitoExpediente' => $campoHitoExpediente
					));
					if ($ficheroCampo) {
						try {
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
							foreach ($fichero as $imagen) {
								$img = base64_decode(str_replace('data:image/jpeg;base64,', "", $imagen));
								$pdf->AddPage();
								// $pdf->Image('@' . $img, '', '', 0, 0, '', '', true);
								$bMargin = $pdf->getBreakMargin();
								// get current auto-page-break mode
								$auto_page_break = $pdf->getAutoPageBreak();
								// disable auto-page-break
								$pdf->SetAutoPageBreak(false, 0);
								$pdf->Image('@' . $img, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
								// restore auto-page-break status
								$pdf->SetAutoPageBreak($auto_page_break, $bMargin);
								// set the starting point for the page content
								$pdf->setPageMark();
							}
							$pdf->Output($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $ficheroCampo->getNombreFichero(), 'F');
							$campoHitoExpediente->setFirmado(true)
								->setFechaModificacion(new DateTime());
							$managerEntidad = $doctrine->getManager();
							$managerEntidad->persist($campoHitoExpediente);
							if ($campoHitoExpediente->getIdCampoHito()->getIdGrupoCamposHito()->getIdHito()->getIdFase()->getTipo() === 0) {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación del campo hito "' . $campoHitoExpediente->getIdCampoHito()->getNombre() . '"', $expediente));
							}
							$managerEntidad->flush();

							$titulo = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado un documento";
							$mensaje = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre();
							// $this->enviarNotificacion($expediente,$campoHitoExpediente,null,$titulo,$mensaje);
							// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
							if($expediente->getIdComercial()){
								$notificacion = (new Notificacion)
									->setIdExpediente($expediente)
									->setEstado(1)
									->setFecha(new DateTime())
									->setIdUsuario($expediente->getIdComercial())
									->setTitulo('Nuevo documento firmado')
									->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
								$managerEntidad->persist($notificacion);
								// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
								$managerEntidad->flush();
							}
							
							if($expediente->getIdTecnico()){
								$notificacion = (new Notificacion)
									->setIdExpediente($expediente)
									->setEstado(1)
									->setFecha(new DateTime())
									->setIdUsuario($expediente->getIdTecnico())
									->setTitulo('Nuevo documento firmado')
									->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
								$managerEntidad->persist($notificacion);
								// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
								$managerEntidad->flush();
							}

							if($expediente->getIdComercial() == null && $expediente->getIdTecnico() == null){
								//Aviso de que se ha creado una cuenta
								$roles = array("ROLE_ADMIN", "ROLE_TECNICO", "ROLE_COMERCIAL");
								$usuarios_gn = $doctrine->getRepository(Usuario::class)->findBy(array(
										'role' => $roles
									)
								);
								foreach($usuarios_gn as $usuario_gn){
									$notificacion = (new Notificacion)
										->setIdExpediente($expediente)
										->setEstado(1)
										->setFecha(new DateTime())
										->setIdUsuario($usuario_gn)
										->setTitulo('Nuevo documento firmado')
										->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
									$managerEntidad->persist($notificacion);
									$managerEntidad->flush();
								}
							}else{
								$admins = $doctrine->getRepository(Usuario::class)->findBy(array(
										'role' => 'ROLE_ADMIN'
									)
								);
								foreach($admins as $admin){
									$notificacion = (new Notificacion)
										->setIdExpediente($expediente)
										->setEstado(1)
										->setFecha(new DateTime())
										->setIdUsuario($admin)
										->setTitulo('Nuevo documento firmado')
										->setTexto($this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.");
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
							if($campoHitoExpediente->getAvisarColaborador() and $camposHitoExpedienteColaboradores){
								if($expediente->getIdComercial() != null){
									$mailerOk = $this->obtenerMailer($mailer,$expediente->getIdComercial());
									if($expediente->getIdComercial()->getMailerTransport()!= null && $expediente->getIdComercial()->getMailerTransport()!= ""){
										$from = array($expediente->getIdComercial()->getMailerUser() => $expediente->getIdComercial()->getUsername()." ".$expediente->getIdComercial()->getApellidos(). ' - Hipotea');
										if($expediente->getIdComercial()->getFirmaCorreo() != null && $expediente->getIdComercial()->getFirmaCorreo() != ""){
											$imagenCorreo = $expediente->getIdComercial()->getFirmaCorreo();
										}else{
											$imagenCorreo = "firma_base.png";
										}
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/'.$imagenCorreo.'"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
										Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									}else{
										$from = array($this->getParameter('mailer_user') => 'Hipotea');
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
										Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									}
								}elseif($expediente->getIdTecnico() != null){
									$mailerOk = $this->obtenerMailer($mailer,$expediente->getIdTecnico());
									if($expediente->getIdTecnico()->getMailerTransport()!= null && $expediente->getIdTecnico()->getMailerTransport()!= ""){
										$from = array($expediente->getIdTecnico()->getMailerUser() => $expediente->getIdTecnico()." ".$expediente->getIdTecnico()->getApellidos(). ' - Hipotea');
										if($expediente->getIdTecnico()->getFirmaCorreo() != null && $expediente->getIdTecnico()->getFirmaCorreo() != ""){
											$imagenCorreo = $expediente->getIdTecnico()->getFirmaCorreo();
										}else{
											$imagenCorreo = "firma_base.png";
										}
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/'.$imagenCorreo.'"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
										Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									}else{
										$from = array($this->getParameter('mailer_user') => 'Hipotea');
										$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
										Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
									}
								}else{
									$mailerOk = $this->obtenerMailer($mailer,null);
									$from = array($this->getParameter('mailer_user') => 'Hipotea');
									$subBody = '<img src="https://areaprivada.hipotea.com/uploads/firma_base.png"><p style="font-size:9px;color:#CCC;">En cumplimiento de la normativa de Protección de Datos, le informamos que sus datos son tratados por esta empresa con la finalidad de prestar el servicio solicitado y establecer contacto. La base legal del tratamiento es la ejecución de un contrato, interés legítimo del responsable o consentimiento del interesado. Asimismo, le indicamos que podrá ejercer sus derechos de derechos de acceso, rectificación, limitación del tratamiento, portabilidad, oposición al tratamiento y supresión de sus datos a través de nuestro correo electrónico o domicilio fiscal. De igual modo, podría interponer una reclamación ante la Autoridad de Control en www.aepd.es, mediante escrito .” Para más información, puede consultar nuestra política de privacidad en HIPOTEA.COM
									Esta comunicación es privada y los documentos adjuntos a la misma son confidenciales y dirigidos exclusivamente a los destinatarios de los mismos, por lo que su divulgación está expresamente prohibida. Por favor, si Ud. no es uno de dichos destinatarios, sírvase notificarnos este hecho y no copie o revele su contenido a terceros por ningún medio.</p>';
								}

								$body = $this->getUser()->getUsername() . " " . $this->getUser()->getApellidos() . " ha firmado el documento " .  $campoHitoExpediente->getIdCampoHito()->getNombre() . " que estaba esperando.";
								$body.=$subBody;
								// $from = array($this->getParameter('mailer_user') => 'Hipotea');
								$mensaje = (new Swift_Message('Hipotea: Nuevo documento firmado de '.$this->getUser()->getUsername() . " " . $this->getUser()->getApellidos()))
									->setFrom($from)
									->setTo($camposHitoExpedienteColaboradores->getIdAgenteColaborador()->getEmail())
									->setBody($body, 'text/html');
									
								$documento = $doctrine->getRepository(FicheroCampoEntidad::class)->findOneBy(array(
									'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
									'idExpediente' => $expediente
								));
								$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . $documento->getNombreFichero())->setFilename($documento->getIdCampoHito()->getNombre().'.pdf'));

								if ($mailerOk->send($mensaje)) {
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Se ha enviado un email con el documento firmado '.$campoHitoExpediente->getIdCampoHito()->getNombre().' a ' . (new UsuariosNombreCompleto())->obtener($camposHitoExpedienteColaboradores->getIdAgenteColaborador()), $expediente));
									$respuesta = 0;
								} else {
									$respuesta = 1;
								}
							}
						} catch (FileException $e) {
							$respuesta = 4;
						}
					} else {
						$respuesta = 6;
					}
				} else {
					$respuesta = 1;
				}
				// $this->get('event_dispatcher')->dispatch('documento.subido', new DocumentoNotificacion($this->getUser(), $ficheroCampo->getNombreFichero(), $respuesta));
			} else {
				$respuesta = 5;
			}
		} else {
			$respuesta = 7;
		}
		return new Response($respuesta);
	}

	public function listadoDeExpedientesAction()
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente')
		);
		if ($this->getUser()->getRole() === 'ROLE_CLIENTE') {
			$expedientes = $repositorios['Expedientes']->findBy(array(
				'idCliente' => $this->getUser()->getIdUsuario(),
				'estado' => 1
			), array(
				'idExpediente' => 'DESC'
			));
		} elseif ($this->getUser()->getRole() === 'ROLE_COLABORADOR') {
			$expedientes = $repositorios['Expedientes']->findBy(array(
				'idColaborador' => $this->getUser()->getIdUsuario(),
				'estado' => 1
			), array(
				'idExpediente' => 'DESC'
			));
		}
		$camposExtra = array();
		$hitoVivienda = $doctrine->getRepository(Hito::class)->findOneBy(array(
			'nombre' => 'Inmueble'
		));
		$gruposHitoVivienda = $doctrine->getRepository(GrupoCampos::class)->findOneBy(array(
			'idHito' =>  $hitoVivienda
		));

		$campoVivienda = $doctrine->getRepository(CampoHito::class)->findOneBy(array(
			'nombre' => 'Dirección de la propiedad',
			'idGrupoCamposHito' => $gruposHitoVivienda
		));

		$hitoNombre = $doctrine->getRepository(Hito::class)->findOneBy(array(
			'nombre' => 'Titular'
		));
		$gruposHitoNombre = $doctrine->getRepository(GrupoCampos::class)->findOneBy(array(
			'idHito' =>  $hitoNombre
		));
		$campoNombre = $doctrine->getRepository(CampoHito::class)->findOneBy(array(
			'nombre' => 'Nombre y Apellidos',
			'idGrupoCamposHito' => $gruposHitoNombre
		));

		
		
		
		foreach($expedientes as $expediente){
			$campoAux = array();
			$valorVivienda = $expediente->getVivienda();
			if ($campoVivienda){
				$valorCampoVivienda = $doctrine->getRepository(CampoHitoExpediente::class)->findOneBy(array(
					'idExpediente' => $expediente,
					'idCampoHito' => $campoVivienda
				)
				);
				if($valorCampoVivienda && $valorCampoVivienda->getValor() != ""){
					$valorVivienda = $valorCampoVivienda->getValor();
				}
			}

			if($expediente->getIdCliente()){
				$valorNombre = $expediente->getIdCliente()->getUsername() . ' ' . $expediente->getIdCliente()->getApellidos();
			}else{
				$valorNombre = "";
			}
			if ($campoNombre){
				$valorCampoNombre = $doctrine->getRepository(CampoHitoExpediente::class)->findOneBy(array(
					'idExpediente' => $expediente,
					'idCampoHito' => $campoNombre
				)
				);
				if($valorCampoNombre && $valorCampoNombre->getValor() != ""){
					$valorNombre = $valorCampoNombre->getValor();
				}
			}

			$campoAux['vivienda'] = $valorVivienda;
			$campoAux['nombre'] = $valorNombre;
			$camposExtra[$expediente->getIdExpediente()] = $campoAux;
		}
		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));
		$respuesta['expedientes'] = $serializador->normalize($expedientes, null, array(
			'attributes' => array(
				'idExpediente',
				'fechaCreacion' => array(
					'timestamp'
				),
				'idFaseActual' => array(
					'nombre',
					'orden',
					'final'
				),
				'idCliente' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'idComercial' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'idTecnico' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'idColaborador' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'vivienda',
				'estado'
			)
			));
		$respuesta['camposExtra'] = $camposExtra;
		return new JsonResponse($respuesta);
	}

	public function detallesDeExpedienteAction($idExpediente = null)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'HitosExpedientes' => $doctrine->getRepository('AppBundle:HitoExpediente'),
			'SeguimientosExpediente' => $doctrine->getRepository('AppBundle:SeguimientoExpediente'),
		);
		if ($this->getUser()->getRole() === 'ROLE_CLIENTE') {
			$expedientes = $repositorios['Expedientes']->findOneBy(array(
				'idCliente' => $this->getUser()->getIdUsuario(),
				'idExpediente' => $idExpediente
			));
		} elseif ($this->getUser()->getRole() === 'ROLE_COLABORADOR') {
			$expedientes = $repositorios['Expedientes']->findOneBy(array(
				'idColaborador' => $this->getUser()->getIdUsuario(),
				'idExpediente' => $idExpediente
			));
		}
		$seguimientosExpediente = $repositorios['SeguimientosExpediente']->findBy(array(
			'idExpediente' => $expedientes
		));

		$arrayFases = array();

		$fasesSeguimientos = $doctrine->getRepository(ConceptoSeguimientoExpediente::class)->findBy(array(),
			array(
				'orden' => 'ASC'
			));
		$fase_actual = "";
		foreach($fasesSeguimientos as $fase){
			if($fase->getFase() != $fase_actual){
				$arrayFases[]= $fase->getFase();
			}
			$fase_actual = $fase->getFase();
		}

		
		// $hitosExpedientes = $repositorios['HitosExpedientes']->findBy(array(
		// 	'idExpediente' => $expedientes
		// ));
		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));
		$expediente = $serializador->normalize($expedientes, null, array(
			'attributes' => array(
				'idExpediente',
				'idFaseActual' => array(
					'nombre',
					'orden',
					'final'
				),
				'idCliente' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'idComercial' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'idTecnico' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'idColaborador' => array(
					'idUsuario',
					'nif',
					'email',
					'username',
					'apellidos',
					'empresa',
					'telefonoMovil',
					'telefonoFijo',
					'direccion',
					'cp',
					'provincia',
					'municipio',
					'pais'
				),
				'vivienda',
				'estado'
			)
		));
		$seguimientoExpediente = $serializador->normalize($seguimientosExpediente, null, array(
			'attributes' => array(
				'idSeguimientoExpediente',
				'comentario',
				'fecha' => array(
					'timestamp'
				),
				'cliente',
				'colaborador',
				'idConceptoSeguimientoExpediente' => array(
					'idConceptoSeguimientoExpediente',
					'fase',
					'concepto',
					'orden'
				),
			)
		));
		$respuesta = array(
			'expediente' => $expediente,
			'seguimientosExpediente' => $seguimientoExpediente,
			'fases' => $arrayFases
		);
		return new JsonResponse($respuesta);
	}

	public function listadoDeDocumentosAction()
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Documentos' => $doctrine->getRepository('AppBundle:Documento')
		);
		if ($this->getUser()->getRole() === 'ROLE_CLIENTE') {
			$tipos_documentos = [0, 2];
		} elseif ($this->getUser()->getRole() === 'ROLE_COLABORADOR') {
			$tipos_documentos = [1, 2];
		} else {
			$tipos_documentos = [0, 1, 2, 3];
		}
		$documentos = $repositorios['Documentos']->findBy(array(
			'estado' => 1,
			'visiblePara' => $tipos_documentos
		));
		$serializador = new Serializer(array(
			new ObjectNormalizer()
		), array(
			new JsonEncoder()
		));
		return new JsonResponse($serializador->normalize($documentos, null, array(
			'attributes' => array(
				'idDocumento',
				'nombre',
				'nombreFichero',
				'descripcion'
			)
		)));
	}

	public function listadoDeNoticiasAction()
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Noticias' => $doctrine->getRepository('AppBundle:Noticia')
		);
		$noticias = $repositorios['Noticias']->findBy(array(
			'estado' => 1
		), array(
			'fecha' => 'DESC'
		));
		$serializador = new Serializer(array(
			new ObjectNormalizer()
		), array(
			new JsonEncoder()
		));
		return new JsonResponse($serializador->normalize($noticias, null, array(
			'attributes' => array(
				'idNoticia',
				'titulo',
				'imagen',
				'descripcion',
				'fecha',
				'estado'
			)
		)));
	}

	public function detallesDeNoticiaAction($idNoticia = null)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Noticias' => $doctrine->getRepository('AppBundle:Noticia')
		);
		$noticias = $repositorios['Noticias']->findOneBy(array(
			'idNoticia' => $idNoticia,
			'estado' => 1
		));
		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));
		return new JsonResponse($serializador->normalize($noticias, null, array(
			'attributes' => array(
				'idNoticia',
				'titulo',
				'imagen',
				'descripcion',
				'fecha',
				'estado'
			)
		)));
	}

	public function listadoDeNotificacionesAction()
	{
		$doctrine = $this->getDoctrine();
		$idUsuario = $this->getUser()->getIdUsuario();
		$repositorios = array(
			'Notificaciones' => $doctrine->getRepository('AppBundle:Notificacion')
		);
		$notificaciones = $repositorios['Notificaciones']->findBy(array(
			'idUsuario' => $idUsuario
		), array(
			'fecha' => 'DESC'
		));
		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));
		return new JsonResponse($serializador->normalize($notificaciones, null, array(
			'attributes' => array(
				'idNotificacion',
				'titulo',
				'texto',
				'idExpediente' => array(
					'idExpediente'),
				'fecha',
				'estado'
			)
		)));
	}

	public function detallesDeNotificacionAction($idNotificacion = null)
	{
		$doctrine = $this->getDoctrine();
		$idUsuario = $this->getUser()->getIdUsuario();
		$repositorios = array(
			'Notificaciones' => $doctrine->getRepository('AppBundle:Notificacion')
		);
		$notificaciones = $repositorios['Notificaciones']->findOneBy(array(
			'idNotificacion' => $idNotificacion,
			'idUsuario' => $idUsuario
		));
		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));
		return new JsonResponse($serializador->normalize($notificaciones, null, array(
			'attributes' => array(
				'idNotificacion',
				'titulo',
				'texto',
				'idExpediente' => array(
					'idExpediente'),
				'fecha',
				'estado'
			)
		)));
	}

	public function leerNotificacionAction($idNotificacion)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Notificaciones' => $doctrine->getRepository('AppBundle:Notificacion')
		);
		$notificacion = $repositorios['Notificaciones']->findOneBy(array(
			'idNotificacion' => $idNotificacion
		));
		if ($notificacion && $notificacion->getEstado() !== 0) {
			$notificacion->setEstado(0);
			$managerEntidad = $doctrine->getManager();
			$managerEntidad->persist($notificacion);
			if (!is_null($notificacion->getIdExpediente())) {
				$expediente = $doctrine->getRepository('AppBundle:Expediente')->findOneBy(array(
					'idExpediente' => $notificacion->getIdExpediente()
				));
				$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'El usuario ha leído una notificación del expediente.', $expediente));
			}
			$managerEntidad->flush();
		}
		return new Response('Notificación leída');
	}

	public function borrarNotificacionAction($idNotificacion)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Notificaciones' => $doctrine->getRepository('AppBundle:Notificacion')
		);
		$notificacion = $repositorios['Notificaciones']->findOneBy(array(
			'idNotificacion' => $idNotificacion
		));
		if ($notificacion) {
			$managerEntidad = $doctrine->getManager();
			$managerEntidad->remove($notificacion);
			$managerEntidad->flush();
		}
		return new Response('Notificación eliminada');
	}

	public function notificacionesSinLeerAction()
	{
		$doctrine = $this->getDoctrine();
		$idUsuario = $this->getUser()->getIdUsuario();
		$repositorios = array(
			'Notificaciones' => $doctrine->getRepository('AppBundle:Notificacion')
		);
		$notificaciones = $repositorios['Notificaciones']->findBy(array(
			'idUsuario' => $idUsuario,
			'estado' => 1
		));
		if ($notificaciones) {
			$num_notificaciones = count($notificaciones);
		} else {
			$num_notificaciones = 0;
		}
		return new JsonResponse(json_encode($num_notificaciones, JSON_UNESCAPED_UNICODE));
	}

	public function estudioViabilidadAction($idExpediente)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Fases' => $doctrine->getRepository('AppBundle:Fase'),
			'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
			'HitosExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
			'GruposHitosExpediente' => $doctrine->getRepository('AppBundle:GrupoHitoExpediente'),
			'GrupoCamposHito' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
			'CamposHito' => $doctrine->getRepository('AppBundle:CampoHito'),
			'CampoHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo'),
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente')
		);
		$fase = $repositorios['Fases']->findOneBy(array(
			'tipo' => 0
		));
		$hitos = $repositorios['Hitos']->findBy(array(
			'idFase' => $fase
		), array(
			'orden' => 'ASC'
		));
		if($idExpediente != null && $idExpediente != 'null'){
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idExpediente' => $idExpediente,
				'estado' => 1,
				// 'idFaseActual' => 1
			), array(
				'idExpediente' => 'DESC'
			));
		}else{
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idCliente' => $this->getUser(),
				'estado' => 1,
				// 'idFaseActual' => 1
			), array(
				'idExpediente' => 'DESC'
			));
		}
		$grupoCamposhitos = $repositorios['GrupoCamposHito']->findBy(array(
			'idHito' => $hitos
		), array(
			'idHito' => 'ASC',
			'orden' => 'ASC'
		));
		$hitosExpediente = $repositorios['HitosExpediente']->findBy(array(
			'idExpediente' => $expediente,
			'idHito' => $hitos
		),
			array(
				'idHito' => 'ASC',
				'idExpediente' => 'ASC',
			)
		);

		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));

		$hitos_expediente_json = $serializador->normalize($hitosExpediente, null, array(
			'attributes' => array(
				'idHitoExpediente',
				'idHito'  => array(
					'idHito',
					'nombre',
					'hitoCondicional',
					'orden',
					'repetible'
				),
				'idExpediente' => array(
					'idExpediente'
				)
			)
		));

		

		$hitos_json = $serializador->normalize($hitos, null, array(
			'attributes' => array(
				'idHito',
				'nombre',
				'orden',
				'repetible',
				'hitoCondicional',
				'idFase' => array(
					'idFase',
					'nombre'
				)
			)
		));

		$grupo_campos_hitos_json = $serializador->normalize($grupoCamposhitos, null, array(
			'attributes' => array(
				'idGrupoCamposHito',
				'nombre',
				'repetible',
				'orden',
				'idHito' => array(
					'idHito',
					'nombre',
					'orden',
					'repetible',
					'idFase' => array(
						'idFase',
						'nombre'
					)
				)
			)
		));

		$campos = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhitos
			),
			array(
				'orden' => 'ASC'
			)
		);

		$campos_json = $serializador->normalize($campos, null, array(
			'attributes' => array(
				'idCampoHito',
				'nombre',
				'tipo',
				'orden',
				'campoCondicional',
				'mostrarCliente',
				'mostrarColaborador',
				'idGrupoCamposHito' => array(
					'idGrupoCamposHito',
					'nombre',
					'orden',
					'idHito' => array(
						'idHito',
						'nombre',
						'orden',
						'repetible',
						'idFase' => array(
							'idFase',
							'nombre'
						)
					),
					'repetible'
				)
			)
		));

		$arrayGruposHitosExpediente = array();
		$arrayCamposHitoExpediente = array();
		foreach($hitosExpediente as $hitoExpediente){
			$gruposHitosExpediente = $repositorios['GruposHitosExpediente']->findBy(array(
				'idHitoExpediente' => $hitoExpediente
			),
				array(
					'idGrupoCamposHito' => 'ASC',
					'idGrupoHitoExpediente' => 'ASC'
				)
			);
			$grupos_hitos_expediente_json = $serializador->normalize($gruposHitosExpediente, null, array(
				'attributes' => array(
					'idGrupoHitoExpediente',
					'idGrupoCamposHito'  => array(
						'idGrupoCamposHito',
						'nombre',
						'repetible',
						'idHito' => array(
							'idHito'
						)
					),
					'idHitoExpediente' => array(
						'idHitoExpediente'
					)
				)
			));
			$arrayGruposHitosExpediente[$hitoExpediente->getIdHitoExpediente()] = $grupos_hitos_expediente_json;
			
			foreach($gruposHitosExpediente as $grupoHitoExpediente){
				$camposHitoExpediente = $repositorios['CampoHitoExpediente']->findBy(array(
					'idExpediente' => $expediente,
					'idGrupoHitoExpediente' => $grupoHitoExpediente
				));
				$camposExpediente_json = $serializador->normalize($camposHitoExpediente, null, array(
					'attributes' => array(
						'idCampoHitoExpediente',
						'idCampoHito'=> array(
							'idCampoHito',
							'nombre',
							'tipo',
							'campoCondicional',
							'orden',
							'mostrarCliente',
							'mostrarColaborador',
							'idGrupoCamposHito' => array(
								'idGrupoCamposHito'
							)
						),
						'idGrupoHitoExpediente' => array(
							'idGrupoHitoExpediente'
						),
						'idExpediente' => array(
							'idExpediente'
						),
						'idHitoExpediente' => array(
							'idHitoExpediente'
						),
						'valor',
						'idOpcionesCampo' => array(
							'idOpcionesCampo',
							'valor'
						)
					)
				));
				foreach($camposExpediente_json as $index => $campoExpediente_json){
					if($campoExpediente_json['idCampoHito']['tipo'] == 6 && $camposExpediente_json[$index]['valor'] != null){
						// $camposExpediente_json[$index]['valor'] = date('Y-m-d',strtotime(($camposExpediente_json[$index]['valor'])));
						// $fecha = new DateTime();
						// $fecha->setTimestamp(strtotime($camposExpediente_json[$index]['valor']));
						$fecha = DateTime::createFromFormat('d/m/Y', $camposExpediente_json[$index]['valor']);
						$fecha = $fecha->format(DateTime::ATOM);
						$camposExpediente_json[$index]['valor'] = $fecha;
					}
				}
				$arrayCamposHitoExpediente[$grupoHitoExpediente->getIdGrupoHitoExpediente()] = $camposExpediente_json;
			}
		}


		$campos = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhitos
			),
			array(
				'orden' => 'ASC'
			)
		);

		$arrayOpciones = array();

		// $opciones = $repositorios['OpcionesCampo']->findBy(array(
		// 		'idCampoHito' => $campos
		// 	),
		// 	array(
		// 		'idCampoHito' => 'ASC',
		// 		'orden' => 'ASC'
		// 	)
		// );

		
		$campos = array();
		$opciones = array();
		$arrayCamposHito = array();
		foreach ($grupoCamposhitos as $grupoCamposhito) {
			$camposHito = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhito
			), array(
				'orden' => 'ASC'
			));
			$campos[$grupoCamposhito->getIdGrupoCamposHito()] = $camposHito;
			foreach ($camposHito as $campoHito) {
				if ($campoHito->getTipo() !== 4 || $campoHito->getTipo() !== 10) { // Los ficheros y los espacios en blanco no se envían
					$arrayCamposHito[] = $campoHito->getIdCampoHito();
				}
				if ($campoHito->getTipo() === 2 || $campoHito->getTipo() === 3) {
					$opcionesCampo = $repositorios['OpcionesCampo']->findBy(array(
						'idCampoHito' => $campoHito
					), array(
						'orden' => 'ASC'
					));
					$opciones_json = $serializador->normalize($opcionesCampo, null, array(
						'attributes' => array(
							'idOpcionesCampo',
							'valor',
							'orden',
							'idHitoCondicional',
							'idCampoCondicional',
							'idCampoHito' => array(
								'idCampoHito',
								'idGrupoCamposHito' => array(
									'idGrupoCamposHito'
								)
							)
						)
					));
					$opciones[$campoHito->getIdCampoHito()] = $opciones_json;
				}
			}
		}


		$miIdExpediente = 0;
		$mostrarCampo = array();
		foreach ($grupoCamposhitos as $grupoCamposhito) {
			$camposHito = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhito
			), array(
				'orden' => 'ASC'
			));
			foreach($camposHito as $campo){
				if($campo->getCampoCondicional()){
					$mostrarCampo[$campo->getIdCampoHito()] = true;
				}else{
					$mostrarCampo[$campo->getIdCampoHito()] = false;
				}
			}
		}
		$mostrarHito = array();
		foreach($hitos as $hito){
			if($hito->getHitoCondicional()){
				$mostrarHito[$hito->getIdHito()] = true;
			}else{
				$mostrarHito[$hito->getIdHito()] = false;
			}
		}
		
		$mostrarCampoExpediente = array();
		$mostrarHitoExpediente = array();
		
		if($expediente){
			$miIdExpediente = $expediente->getIdExpediente();
			foreach($hitosExpediente as $hitoExpediente){
				$gruposHitosExpediente = $repositorios['GruposHitosExpediente']->findBy(array(
					'idHitoExpediente' => $hitoExpediente
				),
					array(
						'idGrupoCamposHito' => 'ASC',
						'idGrupoHitoExpediente' => 'ASC'
					)
				);
				foreach($gruposHitosExpediente as $grupoHitoExpediente){
					$camposHitoExpediente = $repositorios['CampoHitoExpediente']->findBy(array(
						'idExpediente' => $expediente,
						'idGrupoHitoExpediente' => $grupoHitoExpediente
					));
					// Primero rellenamos el array con los valores por defecto
					foreach($camposHitoExpediente as $campoHitoExpediente){
						if($campoHitoExpediente->getIdCampoHito()->getCampoCondicional()){
							$mostrarCampoExpediente[$campoHitoExpediente->getIdCampoHitoExpediente()] = true;
						}else{
							$mostrarCampoExpediente[$campoHitoExpediente->getIdCampoHitoExpediente()] = false;
						}
					}
				}
			}
			// $dependenciasCamposCampos = array();
			// Ahora mostramos los que se tienen que mostrar
			$opcionesConCamposCondicionales = $doctrine->getRepository(OpcionesCampo::class)->matching(Criteria::create()
				->Where(Criteria::expr()->neq('idCampoCondicional', null)));
			foreach($opcionesConCamposCondicionales as $opcionCondicional){
				// $camposPorOpcion = array();
				$idsCamposCondicionales = explode(';',$opcionCondicional->getIdCampoCondicional());
				foreach($idsCamposCondicionales as $idCampoCondicional){
					$idCampo = $repositorios['CamposHito']->findOneBy(array(
						'idCampoHito' => $idCampoCondicional
					));
					$camposHitoExpedienteConOpcion = $repositorios['CampoHitoExpediente']->findBy(array(
						'idOpcionesCampo' => $opcionCondicional,
						'idExpediente' => $expediente
					));
					foreach($camposHitoExpedienteConOpcion as $campoHitoExpedienteConOpcion){
						$camposHitoExpedienteMostrar = $repositorios['CampoHitoExpediente']->findBy(array(
							'idCampoHito' => $idCampoCondicional,
							'idGrupoHitoExpediente' => $campoHitoExpedienteConOpcion->getIdGrupoHitoExpediente(),
							'idExpediente' => $expediente
						));
						// $camposPorOpcion[$campoHitoExpedienteConOpcion->getIdCampoHitoExpediente()] = 
						foreach($camposHitoExpedienteMostrar as $campoHitoExpedienteMostrar){
							$mostrarCampoExpediente[$campoHitoExpedienteMostrar->getIdCampoHitoExpediente()] = false;
						}
					}
				}
			}
			// Primero rellenamos el array con los valores por defecto
			foreach($hitosExpediente as $hitoExpediente){
				if($hitoExpediente->getIdHito()->getHitoCondicional()){
					$mostrarHitoExpediente[$hitoExpediente->getIdHitoExpediente()] = true;
				}else{
					$mostrarHitoExpediente[$hitoExpediente->getIdHitoExpediente()] = false;
				}
			}

			// Ahora mostramos los que se tienen que mostrar
			$opcionesConHitosCondicionales = $doctrine->getRepository(OpcionesCampo::class)->matching(Criteria::create()
				->Where(Criteria::expr()->neq('idHitoCondicional', null)));
			foreach($opcionesConHitosCondicionales as $opcionCondicional){
				if($opcionCondicional->getIdHitoCondicional() != ""){
					$idsHitosCondicionales = explode(';',$opcionCondicional->getIdHitoCondicional());
					foreach($idsHitosCondicionales as $idHitoCondicional){
						$idHito = $repositorios['Hitos']->findOneBy(array(
							'idHito' => $idHitoCondicional
						));
						$camposHitoExpedienteConOpcion = $repositorios['CampoHitoExpediente']->findBy(array(
							'idOpcionesCampo' => $opcionCondicional,
							'idExpediente' => $expediente
						));
						foreach($camposHitoExpedienteConOpcion as $campoHitoExpedienteConOpcion){
							$camposHitoExpedienteMostrar = $repositorios['CampoHitoExpediente']->findBy(array(
								// 'idCampoHito' => $idCampoCondicional,
								'idHitoExpediente' => $campoHitoExpedienteConOpcion->getIdHitoExpediente(),
								'idExpediente' => $expediente,
								'idOpcionesCampo' => $opcionCondicional
							));
							foreach($camposHitoExpedienteMostrar as $campoHitoExpedienteMostrar){
								$hitosExpedienteConOpcion = $repositorios['HitosExpediente']->findBy(array(
									'idHito' => $idHito,
									'idExpediente' => $expediente
								));
								foreach($hitosExpedienteConOpcion as $hitoExpedienteConOpcion){
									$mostrarHitoExpediente[$hitoExpedienteConOpcion->getIdHitoExpediente()] = false;
								}
							}
						}
					}
				}
			}

		}
		$respuesta = array(
			'hitos' => $hitos_json,
			'grupos' => $grupo_campos_hitos_json,
			'campos' => $campos_json,
			'opciones' => $opciones,
			'valoresCampos' => $arrayCamposHitoExpediente,
			// 'idsCamposHito' => $arrayCamposHito,
			'hitosExpediente' => $hitos_expediente_json,
			'gruposHitosExpediente' => $arrayGruposHitosExpediente,
			'idExpediente' => $miIdExpediente,
			'mostrarCampo' => $mostrarCampo,
			'mostrarHito' => $mostrarHito,
			'mostrarCampoExpediente' => $mostrarCampoExpediente,
			'mostrarHitoExpediente' => $mostrarHitoExpediente

		);
		return new JsonResponse($respuesta);



		$camposHito = $repositorios['CamposHito']->findBy(array(
			'idGrupoCamposHito' => $grupoCamposhitos
		), array(
			'orden' => 'ASC'
		));
		$camposHitoExpediente = $repositorios['CampoHitoExpediente']->findBy(array(
			'idExpediente' => $expediente,
			'idCampoHito' => $camposHito
		));
		// $camposExpediente_array = [];
		// // dump($camposHitoExpediente);
		// // die();
		// foreach ($camposHitoExpediente as $campoHitoExpediente) {
		// 	if ($campoHitoExpediente->getIdCampoHito()->getTipo() === 1 || $campoHitoExpediente->getIdCampoHito()->getTipo() === 5 || $campoHitoExpediente->getIdCampoHito()->getTipo() === 6 || $campoHitoExpediente->getIdCampoHito()->getTipo() === 2) {
		// 		$camposExpediente_array[$campoHitoExpediente->getIdCampoHito()->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = $campoHitoExpediente->getValor();
		// 	} elseif ($campoHitoExpediente->getIdCampoHito()->getTipo() === 3 && $campoHitoExpediente->getIdOpcionesCampo()) {
		// 		$camposExpediente_array[$campoHitoExpediente->getIdCampoHito()->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = $campoHitoExpediente->getIdOpcionesCampo()->getIdOpcionesCampo();
		// 	}
		// }

		$campos = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhitos
			),
			array(
				'orden' => 'ASC'
			)
		);

		$opciones = $repositorios['OpcionesCampo']->findBy(array(
				'idCampoHito' => $campos
			),
			array(
				'idCampoHito' => 'ASC',
				'orden' => 'ASC'
			)
		);
		
		// $serializador = new Serializer(array(
		// 	new ObjectNormalizer()
		// ), array(
		// 	new JsonEncoder()
		// ));

		


		

		// $hitos_json = $serializador->normalize($hitos, null, array(
		// 	'idHito',
		// 	'tipoEntidad',
		// 	'nombre',
		// 	'orden',
		// 	'idFase'
		// ));
		// $grupo_campos_hitos_json = $serializador->normalize($grupoCamposhitos, null, array(
		// 	'idGrupoCamposHito',
		// 	'idHito',
		// 	'nombre',
		// 	'orden'
		// ));

		$grupo_campos_hitos_json = $serializador->normalize($grupoCamposhitos, null, array(
			'attributes' => array(
				'idGrupoCamposHito',
				'nombre',
				'repetible',
				'orden',
				'idHito' => array(
					'idHito',
					'nombre',
					'orden',
					'repetible',
					'idFase' => array(
						'idFase',
						'nombre'
					)
				)
			)
		));

		$campos_json = $serializador->normalize($campos, null, array(
			'attributes' => array(
				'idCampoHito',
				'nombre',
				'tipo',
				'orden',
				'campoCondicional',
				'mostrarCliente',
				'mostrarColaborador',
				'idGrupoCamposHito' => array(
					'idGrupoCamposHito',
					'nombre',
					'orden',
					'idHito' => array(
						'idHito',
						'nombre',
						'orden',
						'repetible',
						'idFase' => array(
							'idFase',
							'nombre'
						)
					)
				)
			)
		));

		$opciones_json = $serializador->normalize($opciones, null, array(
			'attributes' => array(
				'idOpcionesCampo',
				'valor',
				'orden',
				'idHitoCondicional',
				'idCampoCondicional',
				'idCampoHito' => array(
					'idCampoHito',
					'idGrupoCamposHito' => array(
						'idGrupoCamposHito'
					)
				)
			)
		));
		// $campos_json = $serializador->normalize($campos, null, array(
		// 	'idCampoHito',
		// 	'tipo',
		// 	'nombre',
		// 	'orden',
		// 	'idHito'
		// ));
		// $opciones_json = $serializador->normalize($opciones, null, array(
		// 	'idOpcionesCampo',
		// 	'orden',
		// 	'valor',
		// 	'orden',
		// 	'idCampoHito'
		// ));



		$hitos_expediente_json = $serializador->normalize($hitosExpediente, null, array(
			'attributes' => array(
				'idHitoExpediente',
				'idHito'  => array(
					'idHito',
					'nombre',
					'hitoCondicional',
					'orden',
					'repetible'
				),
				'idExpediente' => array(
					'idExpediente'
				)
			)
		));

		$grupos_hitos_expediente_json = $serializador->normalize($gruposHitosExpediente, null, array(
			'attributes' => array(
				'idGrupoHitoExpediente',
				'idGrupoCamposHito'  => array(
					'idGrupoCamposHito',
					'nombre',
					'idHito' => array(
						'idHito'
					)
				),
				'idHitoExpediente' => array(
					'idHitoExpediente'
				)
			)
		));
		// $camposExpediente_json = $serializador->normalize($camposHitoExpediente, null, array(
		// 	'idCampoHitoExpediente',
		// 	'idCampoHito' => array(
		// 		'idCampoHito',
		// 		'idGrupoCamposHito' => array(
		// 			'idGrupoCamposHito',
		// 			'nombre',
		// 			'orden',
		// 			'idHito' => array(
		// 				'idHito',
		// 				'nombre',
		// 				'orden',
		// 				'repetible',
		// 				'idFase' => array(
		// 					'idFase',
		// 					'nombre'
		// 				)
		// 			)
		// 		),
		// 		'nombre',
		// 		'orden',
		// 		'idHito' => array(
		// 			'idHito',
		// 			'nombre',
		// 			'orden',
		// 			'repetible',
		// 			'idFase' => array(
		// 				'idFase',
		// 				'nombre'
		// 			)
		// 		)
		// 	),
		// 	'idExpediente' => array(
		// 		'idExpediente'),
		// 	'idHitoExpediente' => array(
		// 		'idHitoExpediente',
		// 		'idHito'=> array(
		// 			'idHito',
		// 			'nombre',
		// 			'orden',
		// 			'repetible',
		// 			'idFase' => array(
		// 				'idFase',
		// 				'nombre'
		// 			)
		// 		),
		// 		'idExpediente'),
		// 	'valor',
		// 	'idOpcionesCampo' => array(
		// 		'idOpcionesCampo',
		// 		'valor',
		// 		'orden',
		// 		'idCampoHito' => array(
		// 			'idCampoHito',
		// 			'idGrupoCamposHito' => array(
		// 				'idGrupoCamposHito',
		// 				'nombre',
		// 				'orden',
		// 				'idHito' => array(
		// 					'idHito',
		// 					'nombre',
		// 					'orden',
		// 					'repetible',
		// 					'idFase' => array(
		// 						'idFase',
		// 						'nombre'
		// 					)
		// 				)
		// 			),
		// 			'nombre',
		// 			'orden',
		// 			'idHito' => array(
		// 				'idHito',
		// 				'nombre',
		// 				'orden',
		// 				'repetible',
		// 				'idFase' => array(
		// 					'idFase',
		// 					'nombre'
		// 				)
		// 			)
		// 		)
		// 	)
		// ));

		


		$camposExpediente_json = $serializador->normalize($camposHitoExpediente, null, array(
			'attributes' => array(
				'idCampoHitoExpediente',
				'idCampoHito'=> array(
					'idCampoHito',
					'nombre',
					'tipo',
					'campoCondicional',
					'orden',
					'mostrarCliente',
					'mostrarColaborador',
					'idGrupoCamposHito' => array(
						'idGrupoCamposHito'
					)
				),
				'idGrupoHitoExpediente' => array(
					'idGrupoHitoExpediente'
				),
				'idExpediente' => array(
					'idExpediente'
				),
				'idHitoExpediente' => array(
					'idHitoExpediente'
				),
				'valor',
				'idOpcionesCampo' => array(
					'idOpcionesCampo',
					'valor'
				)
			)
		));

		if(!$expediente){
			$miIdExpediente = 0;
			$mostrarCampo = array();
			foreach($camposHito as $campo){
				if($campo->getCampoCondicional()){
					$mostrarCampo[$campo->getIdCampoHito()] = true;
				}else{
					$mostrarCampo[$campo->getIdCampoHito()] = false;
				}
			}
			$mostrarHito = array();
			foreach($hitos as $hito){
				if($hito->getHitoCondicional()){
					$mostrarHito[$hito->getIdHito()] = true;
				}else{
					$mostrarHito[$hito->getIdHito()] = false;
				}
			}
		}else{
			$miIdExpediente = $expediente->getIdExpediente();
			$mostrarCampo = array();
			// Primero rellenamos el array con los valores por defecto
			foreach($camposHitoExpediente as $campoHitoExpediente){
				if($campoHitoExpediente->getIdCampoHito()->getCampoCondicional()){
					$mostrarCampo[$campoHitoExpediente->getIdCampoHitoExpediente()] = true;
				}else{
					$mostrarCampo[$campoHitoExpediente->getIdCampoHitoExpediente()] = false;
				}
			}
			// $dependenciasCamposCampos = array();
			// Ahora mostramos los que se tienen que mostrar
			$opcionesConCamposCondicionales = $doctrine->getRepository(OpcionesCampo::class)->matching(Criteria::create()
				->Where(Criteria::expr()->neq('idCampoCondicional', null)));
			foreach($opcionesConCamposCondicionales as $opcionCondicional){
				// $camposPorOpcion = array();
				$idsCamposCondicionales = explode(';',$opcionCondicional->getIdCampoCondicional());
				foreach($idsCamposCondicionales as $idCampoCondicional){
					$idCampo = $repositorios['CamposHito']->findOneBy(array(
						'idCampoHito' => $idCampoCondicional
					));
					$camposHitoExpedienteConOpcion = $repositorios['CampoHitoExpediente']->findBy(array(
						'idOpcionesCampo' => $opcionCondicional,
						'idExpediente' => $expediente
					));
					foreach($camposHitoExpedienteConOpcion as $campoHitoExpedienteConOpcion){
						$camposHitoExpedienteMostrar = $repositorios['CampoHitoExpediente']->findBy(array(
							'idCampoHito' => $idCampoCondicional,
							'idGrupoHitoExpediente' => $campoHitoExpedienteConOpcion->getIdGrupoHitoExpediente(),
							'idExpediente' => $expediente
						));
						// $camposPorOpcion[$campoHitoExpedienteConOpcion->getIdCampoHitoExpediente()] = 
						foreach($camposHitoExpedienteMostrar as $campoHitoExpedienteMostrar){
							$mostrarCampo[$campoHitoExpedienteMostrar->getIdCampoHitoExpediente()] = false;
						}
					}
				}
			}
			$mostrarHito = array();
			// Primero rellenamos el array con los valores por defecto
			foreach($hitosExpediente as $hitoExpediente){
				if($hitoExpediente->getIdHito()->getHitoCondicional()){
					$mostrarHito[$hitoExpediente->getIdHitoExpediente()] = true;
				}else{
					$mostrarHito[$hitoExpediente->getIdHitoExpediente()] = false;
				}
			}

			// Ahora mostramos los que se tienen que mostrar
			$opcionesConHitosCondicionales = $doctrine->getRepository(OpcionesCampo::class)->matching(Criteria::create()
				->Where(Criteria::expr()->neq('idHitoCondicional', null)));
			foreach($opcionesConHitosCondicionales as $opcionCondicional){
				$idsHitosCondicionales = explode(';',$opcionCondicional->getIdHitoCondicional());
				foreach($idsHitosCondicionales as $idHitoCondicional){
					$idHito = $repositorios['Hitos']->findOneBy(array(
						'idHito' => $idHitoCondicional
					));
					$camposHitoExpedienteConOpcion = $repositorios['CampoHitoExpediente']->findBy(array(
						'idOpcionesCampo' => $opcionCondicional,
						'idExpediente' => $expediente
					));
					foreach($camposHitoExpedienteConOpcion as $campoHitoExpedienteConOpcion){
						$camposHitoExpedienteMostrar = $repositorios['CampoHitoExpediente']->findBy(array(
							// 'idCampoHito' => $idCampoCondicional,
							'idHitoExpediente' => $campoHitoExpedienteConOpcion->getIdHitoExpediente(),
							'idExpediente' => $expediente
						));
						foreach($camposHitoExpedienteMostrar as $campoHitoExpedienteMostrar){
							$mostrarCampo[$campoHitoExpedienteMostrar->getIdHitoExpediente()->getIdHitoExpediente()] = false;
						}
					}
				}
			}

		}
		$respuesta = array(
			'hitos' => $hitos_json,
			'grupos' => $grupo_campos_hitos_json,
			'campos' => $campos_json,
			'opciones' => $opciones_json,
			'valoresCampos' => $camposExpediente_json,
			'idsCamposHito' => $arrayCamposHito,
			'hitosExpediente' => $hitos_expediente_json,
			'gruposHitosExpediente' => $grupos_hitos_expediente_json,
			'idExpediente' => $miIdExpediente,
			'mostrarCampo' => $mostrarCampo,
			'mostrarHito' => $mostrarHito

		);
		return new JsonResponse($respuesta);


		// $respuesta = array(
		// 	'hitos' => $hitos_json,
		// 	'grupos' => $grupo_campos_hitos_json,
		// 	'campos' => $campos_json,
		// 	'opciones' => $opciones_json,
		// 	'valoresCampos' => $camposExpediente_json,
		// 	'idsCamposHito' => $arrayCamposHito,
		// 	'hitosExpediente' => $hitos_expediente_json,
		// 	'idExpediente' => $expediente->getIdExpediente()
		// );
		// return new JsonResponse($respuesta);
	}

	public function estudioViabilidad2Action($idExpediente)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Fases' => $doctrine->getRepository('AppBundle:Fase'),
			'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
			'HitosExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
			'GruposHitosExpediente' => $doctrine->getRepository('AppBundle:GrupoHitoExpediente'),
			'GrupoCamposHito' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
			'CamposHito' => $doctrine->getRepository('AppBundle:CampoHito'),
			'CampoHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo'),
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente')
		);
		$fase = $repositorios['Fases']->findOneBy(array(
			'tipo' => 0
		));
		$hitos = $repositorios['Hitos']->findBy(array(
			'idFase' => $fase
		), array(
			'orden' => 'ASC'
		));
		if($idExpediente != null && $idExpediente != 'null'){
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idExpediente' => $idExpediente,
				'estado' => 1,
				'idFaseActual' => 1
			), array(
				'idExpediente' => 'DESC'
			));
		}else{
			$expediente = $repositorios['Expedientes']->findOneBy(array(
				'idCliente' => $this->getUser(),
				'estado' => 1,
				'idFaseActual' => 1
			), array(
				'idExpediente' => 'DESC'
			));
		}
		$grupoCamposhitos = $repositorios['GrupoCamposHito']->findBy(array(
			'idHito' => $hitos
		), array(
			'idHito' => 'ASC',
			'orden' => 'ASC'
		));
		$hitosExpediente = $repositorios['HitosExpediente']->findBy(array(
			'idExpediente' => $expediente,
			'idHito' => $hitos
		),
			array(
				'idHito' => 'ASC'
			)
		);

		$gruposHitosExpediente = $repositorios['GruposHitosExpediente']->findBy(array(
			'idHitoExpediente' => $hitosExpediente
		),
			array(
				'idGrupoCamposHito' => 'ASC',
				'idGrupoHitoExpediente' => 'ASC'
			)
		);
		$campos = array();
		$opciones = array();
		$arrayCamposHito = array();
		foreach ($grupoCamposhitos as $grupoCamposhito) {
			$camposHito = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhito
			), array(
				'orden' => 'ASC'
			));
			$campos[$grupoCamposhito->getIdGrupoCamposHito()] = $camposHito;
			foreach ($camposHito as $campoHito) {
				if ($campoHito->getTipo() !== 4 || $campoHito->getTipo() !== 10) { // Los ficheros y los espacios en blanco no se envían
					$arrayCamposHito[] = $campoHito->getIdCampoHito();
				}
				if ($campoHito->getTipo() === 2 || $campoHito->getTipo() === 3) {
					$opcionesCampo = $repositorios['OpcionesCampo']->findBy(array(
						'idCampoHito' => $campoHito
					), array(
						'orden' => 'ASC'
					));
					$opciones[$campoHito->getIdCampoHito()] = $opcionesCampo;
				}
			}
		}
		$camposHito = $repositorios['CamposHito']->findBy(array(
			'idGrupoCamposHito' => $grupoCamposhitos
		), array(
			'orden' => 'ASC'
		));
		$camposHitoExpediente = $repositorios['CampoHitoExpediente']->findBy(array(
			'idExpediente' => $expediente,
			'idCampoHito' => $camposHito
		));
		// $camposExpediente_array = [];
		// // dump($camposHitoExpediente);
		// // die();
		// foreach ($camposHitoExpediente as $campoHitoExpediente) {
		// 	if ($campoHitoExpediente->getIdCampoHito()->getTipo() === 1 || $campoHitoExpediente->getIdCampoHito()->getTipo() === 5 || $campoHitoExpediente->getIdCampoHito()->getTipo() === 6 || $campoHitoExpediente->getIdCampoHito()->getTipo() === 2) {
		// 		$camposExpediente_array[$campoHitoExpediente->getIdCampoHito()->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = $campoHitoExpediente->getValor();
		// 	} elseif ($campoHitoExpediente->getIdCampoHito()->getTipo() === 3 && $campoHitoExpediente->getIdOpcionesCampo()) {
		// 		$camposExpediente_array[$campoHitoExpediente->getIdCampoHito()->getIdCampoHito()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = $campoHitoExpediente->getIdOpcionesCampo()->getIdOpcionesCampo();
		// 	}
		// }

		$campos = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $grupoCamposhitos
			),
			array(
				'orden' => 'ASC'
			)
		);

		$opciones = $repositorios['OpcionesCampo']->findBy(array(
				'idCampoHito' => $campos
			),
			array(
				'idCampoHito' => 'ASC',
				'orden' => 'ASC'
			)
		);
		
		// $serializador = new Serializer(array(
		// 	new ObjectNormalizer()
		// ), array(
		// 	new JsonEncoder()
		// ));

		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));


		$hitos_json = $serializador->normalize($hitos, null, array(
			'attributes' => array(
				'idHito',
				'nombre',
				'orden',
				'repetible',
				'hitoCondicional',
				'idFase' => array(
					'idFase',
					'nombre'
				)
			)
		));

		// $hitos_json = $serializador->normalize($hitos, null, array(
		// 	'idHito',
		// 	'tipoEntidad',
		// 	'nombre',
		// 	'orden',
		// 	'idFase'
		// ));
		// $grupo_campos_hitos_json = $serializador->normalize($grupoCamposhitos, null, array(
		// 	'idGrupoCamposHito',
		// 	'idHito',
		// 	'nombre',
		// 	'orden'
		// ));

		$grupo_campos_hitos_json = $serializador->normalize($grupoCamposhitos, null, array(
			'attributes' => array(
				'idGrupoCamposHito',
				'nombre',
				'repetible',
				'orden',
				'idHito' => array(
					'idHito',
					'nombre',
					'orden',
					'repetible',
					'idFase' => array(
						'idFase',
						'nombre'
					)
				)
			)
		));

		$campos_json = $serializador->normalize($campos, null, array(
			'attributes' => array(
				'idCampoHito',
				'nombre',
				'tipo',
				'orden',
				'campoCondicional',
				'mostrarCliente',
				'mostrarColaborador',
				'idGrupoCamposHito' => array(
					'idGrupoCamposHito',
					'nombre',
					'orden',
					'idHito' => array(
						'idHito',
						'nombre',
						'orden',
						'repetible',
						'idFase' => array(
							'idFase',
							'nombre'
						)
					)
				)
			)
		));

		$opciones_json = $serializador->normalize($opciones, null, array(
			'attributes' => array(
				'idOpcionesCampo',
				'valor',
				'orden',
				'idHitoCondicional',
				'idCampoCondicional',
				'idCampoHito' => array(
					'idCampoHito',
					'idGrupoCamposHito' => array(
						'idGrupoCamposHito'
					)
				)
			)
		));
		// $campos_json = $serializador->normalize($campos, null, array(
		// 	'idCampoHito',
		// 	'tipo',
		// 	'nombre',
		// 	'orden',
		// 	'idHito'
		// ));
		// $opciones_json = $serializador->normalize($opciones, null, array(
		// 	'idOpcionesCampo',
		// 	'orden',
		// 	'valor',
		// 	'orden',
		// 	'idCampoHito'
		// ));



		$hitos_expediente_json = $serializador->normalize($hitosExpediente, null, array(
			'attributes' => array(
				'idHitoExpediente',
				'idHito'  => array(
					'idHito',
					'nombre',
					'hitoCondicional',
					'orden',
					'repetible'
				),
				'idExpediente' => array(
					'idExpediente'
				)
			)
		));

		$grupos_hitos_expediente_json = $serializador->normalize($gruposHitosExpediente, null, array(
			'attributes' => array(
				'idGrupoHitoExpediente',
				'idGrupoCamposHito'  => array(
					'idGrupoCamposHito',
					'nombre',
					'idHito' => array(
						'idHito'
					)
				),
				'idHitoExpediente' => array(
					'idHitoExpediente'
				)
			)
		));
		// $camposExpediente_json = $serializador->normalize($camposHitoExpediente, null, array(
		// 	'idCampoHitoExpediente',
		// 	'idCampoHito' => array(
		// 		'idCampoHito',
		// 		'idGrupoCamposHito' => array(
		// 			'idGrupoCamposHito',
		// 			'nombre',
		// 			'orden',
		// 			'idHito' => array(
		// 				'idHito',
		// 				'nombre',
		// 				'orden',
		// 				'repetible',
		// 				'idFase' => array(
		// 					'idFase',
		// 					'nombre'
		// 				)
		// 			)
		// 		),
		// 		'nombre',
		// 		'orden',
		// 		'idHito' => array(
		// 			'idHito',
		// 			'nombre',
		// 			'orden',
		// 			'repetible',
		// 			'idFase' => array(
		// 				'idFase',
		// 				'nombre'
		// 			)
		// 		)
		// 	),
		// 	'idExpediente' => array(
		// 		'idExpediente'),
		// 	'idHitoExpediente' => array(
		// 		'idHitoExpediente',
		// 		'idHito'=> array(
		// 			'idHito',
		// 			'nombre',
		// 			'orden',
		// 			'repetible',
		// 			'idFase' => array(
		// 				'idFase',
		// 				'nombre'
		// 			)
		// 		),
		// 		'idExpediente'),
		// 	'valor',
		// 	'idOpcionesCampo' => array(
		// 		'idOpcionesCampo',
		// 		'valor',
		// 		'orden',
		// 		'idCampoHito' => array(
		// 			'idCampoHito',
		// 			'idGrupoCamposHito' => array(
		// 				'idGrupoCamposHito',
		// 				'nombre',
		// 				'orden',
		// 				'idHito' => array(
		// 					'idHito',
		// 					'nombre',
		// 					'orden',
		// 					'repetible',
		// 					'idFase' => array(
		// 						'idFase',
		// 						'nombre'
		// 					)
		// 				)
		// 			),
		// 			'nombre',
		// 			'orden',
		// 			'idHito' => array(
		// 				'idHito',
		// 				'nombre',
		// 				'orden',
		// 				'repetible',
		// 				'idFase' => array(
		// 					'idFase',
		// 					'nombre'
		// 				)
		// 			)
		// 		)
		// 	)
		// ));

		


		$camposExpediente_json = $serializador->normalize($camposHitoExpediente, null, array(
			'attributes' => array(
				'idCampoHitoExpediente',
				'idCampoHito'=> array(
					'idCampoHito',
					'nombre',
					'tipo',
					'campoCondicional',
					'orden',
					'mostrarCliente',
					'mostrarColaborador',
					'idGrupoCamposHito' => array(
						'idGrupoCamposHito'
					)
				),
				'idGrupoHitoExpediente' => array(
					'idGrupoHitoExpediente'
				),
				'idExpediente' => array(
					'idExpediente'
				),
				'idHitoExpediente' => array(
					'idHitoExpediente'
				),
				'valor',
				'idOpcionesCampo' => array(
					'idOpcionesCampo',
					'valor'
				)
			)
		));

		if(!$expediente){
			$miIdExpediente = 0;
			$mostrarCampo = array();
			foreach($camposHito as $campo){
				if($campo->getCampoCondicional()){
					$mostrarCampo[$campo->getIdCampoHito()] = true;
				}else{
					$mostrarCampo[$campo->getIdCampoHito()] = false;
				}
			}
			$mostrarHito = array();
			foreach($hitos as $hito){
				if($hito->getHitoCondicional()){
					$mostrarHito[$hito->getIdHito()] = true;
				}else{
					$mostrarHito[$hito->getIdHito()] = false;
				}
			}
		}else{
			$miIdExpediente = $expediente->getIdExpediente();
			$mostrarCampo = array();
			// Primero rellenamos el array con los valores por defecto
			foreach($camposHitoExpediente as $campoHitoExpediente){
				if($campoHitoExpediente->getIdCampoHito()->getCampoCondicional()){
					$mostrarCampo[$campoHitoExpediente->getIdCampoHitoExpediente()] = true;
				}else{
					$mostrarCampo[$campoHitoExpediente->getIdCampoHitoExpediente()] = false;
				}
			}
			// $dependenciasCamposCampos = array();
			// Ahora mostramos los que se tienen que mostrar
			$opcionesConCamposCondicionales = $doctrine->getRepository(OpcionesCampo::class)->matching(Criteria::create()
				->Where(Criteria::expr()->neq('idCampoCondicional', null)));
			foreach($opcionesConCamposCondicionales as $opcionCondicional){
				// $camposPorOpcion = array();
				$idsCamposCondicionales = explode(';',$opcionCondicional->getIdCampoCondicional());
				foreach($idsCamposCondicionales as $idCampoCondicional){
					$idCampo = $repositorios['CamposHito']->findOneBy(array(
						'idCampoHito' => $idCampoCondicional
					));
					$camposHitoExpedienteConOpcion = $repositorios['CampoHitoExpediente']->findBy(array(
						'idOpcionesCampo' => $opcionCondicional,
						'idExpediente' => $expediente
					));
					foreach($camposHitoExpedienteConOpcion as $campoHitoExpedienteConOpcion){
						$camposHitoExpedienteMostrar = $repositorios['CampoHitoExpediente']->findBy(array(
							'idCampoHito' => $idCampoCondicional,
							'idGrupoHitoExpediente' => $campoHitoExpedienteConOpcion->getIdGrupoHitoExpediente(),
							'idExpediente' => $expediente
						));
						// $camposPorOpcion[$campoHitoExpedienteConOpcion->getIdCampoHitoExpediente()] = 
						foreach($camposHitoExpedienteMostrar as $campoHitoExpedienteMostrar){
							$mostrarCampo[$campoHitoExpedienteMostrar->getIdCampoHitoExpediente()] = false;
						}
					}
				}
			}
			$mostrarHito = array();
			// Primero rellenamos el array con los valores por defecto
			foreach($hitosExpediente as $hitoExpediente){
				if($hitoExpediente->getIdHito()->getHitoCondicional()){
					$mostrarHito[$hitoExpediente->getIdHitoExpediente()] = true;
				}else{
					$mostrarHito[$hitoExpediente->getIdHitoExpediente()] = false;
				}
			}

			// Ahora mostramos los que se tienen que mostrar
			$opcionesConHitosCondicionales = $doctrine->getRepository(OpcionesCampo::class)->matching(Criteria::create()
				->Where(Criteria::expr()->neq('idHitoCondicional', null)));
			foreach($opcionesConHitosCondicionales as $opcionCondicional){
				$idsHitosCondicionales = explode(';',$opcionCondicional->getIdHitoCondicional());
				foreach($idsHitosCondicionales as $idHitoCondicional){
					$idHito = $repositorios['Hitos']->findOneBy(array(
						'idHito' => $idHitoCondicional
					));
					$camposHitoExpedienteConOpcion = $repositorios['CampoHitoExpediente']->findBy(array(
						'idOpcionesCampo' => $opcionCondicional,
						'idExpediente' => $expediente
					));
					foreach($camposHitoExpedienteConOpcion as $campoHitoExpedienteConOpcion){
						$camposHitoExpedienteMostrar = $repositorios['CampoHitoExpediente']->findBy(array(
							// 'idCampoHito' => $idCampoCondicional,
							'idHitoExpediente' => $campoHitoExpedienteConOpcion->getIdHitoExpediente(),
							'idExpediente' => $expediente
						));
						foreach($camposHitoExpedienteMostrar as $campoHitoExpedienteMostrar){
							$mostrarCampo[$campoHitoExpedienteMostrar->getIdHitoExpediente()->getIdHitoExpediente()] = false;
						}
					}
				}
			}

		}
		$respuesta = array(
			'hitos' => $hitos_json,
			'grupos' => $grupo_campos_hitos_json,
			'campos' => $campos_json,
			'opciones' => $opciones_json,
			'valoresCampos' => $camposExpediente_json,
			'idsCamposHito' => $arrayCamposHito,
			'hitosExpediente' => $hitos_expediente_json,
			'gruposHitosExpediente' => $grupos_hitos_expediente_json,
			'idExpediente' => $miIdExpediente,
			'mostrarCampo' => $mostrarCampo,
			'mostrarHito' => $mostrarHito

		);
		return new JsonResponse($respuesta);


		// $respuesta = array(
		// 	'hitos' => $hitos_json,
		// 	'grupos' => $grupo_campos_hitos_json,
		// 	'campos' => $campos_json,
		// 	'opciones' => $opciones_json,
		// 	'valoresCampos' => $camposExpediente_json,
		// 	'idsCamposHito' => $arrayCamposHito,
		// 	'hitosExpediente' => $hitos_expediente_json,
		// 	'idExpediente' => $expediente->getIdExpediente()
		// );
		// return new JsonResponse($respuesta);
	}

	public function calcularAvanzadaAction(Request $request)
	{
		$respuesta = array(
			'cuota_fija' => 0,
			'cuota_variable' => 0,
			'importe_maximo' => 0,
			'mensaje' => ''
		);
		$datos = json_decode($request->getContent())->datos;
		$edad = $datos->edad;
		$amortizacion = 75 - $edad;
		$tipo_calculo = $datos->tipo_calculo;
		$valor_inmueble = (!isset($datos->valor_inmueble) || is_null($datos->valor_inmueble)) ? 0 : $datos->valor_inmueble;
		$aportacion = (!isset($datos->aportacion) || is_null($datos->aportacion)) ? 0 : $datos->aportacion;
		$tipo_hipoteca = (!isset($datos->tipo_hipoteca) || is_null($datos->tipo_hipoteca)) ? 0 : $datos->tipo_hipoteca;
		$hipoteca_actual = (!isset($datos->hipoteca_actual) || is_null($datos->hipoteca_actual)) ? 0 : $datos->hipoteca_actual;
		// $venta_casa_actual = (!isset($datos->venta_casa_actual) || is_null($datos->venta_casa_actual)) ? 0 : $datos->venta_casa_actual;
		$valor_vivienda_actual = (!isset($datos->valor_vivienda_actual) || is_null($datos->valor_vivienda_actual)) ? 0 : $datos->valor_vivienda_actual;
		$aportacion_tras_venta = (!isset($datos->aportacion_tras_venta) || is_null($datos->aportacion_tras_venta)) ? 0 : $datos->aportacion_tras_venta;
		$ingresos_mensuales = (!isset($datos->ingresos_mensuales) || is_null($datos->ingresos_mensuales)) ? 0 : $datos->ingresos_mensuales;
		$numero_pagas = (!isset($datos->numero_pagas) || is_null($datos->numero_pagas)) ? 0 : $datos->numero_pagas;
		$importe_paga_extra = (!isset($datos->importe_pagas) || is_null($datos->importe_pagas)) ? 0 : $datos->importe_pagas;
		$prestamos_mensuales = (!isset($datos->prestamos_mensuales) || is_null($datos->prestamos_mensuales)) ? 0 : $datos->prestamos_mensuales;
		$levantamiento_registral = 0;
		if ($amortizacion > 30) {
			$amortizacion = 30;
		} elseif ($amortizacion < 15) {
			$respuesta['mensaje'] = 'No es posible realizar la operación debido a la edad del cliente.';
			return new JsonResponse($respuesta);
		}
		$gasto_inmobiliaria = 3000 * 1.21;
		$comision_apertura = $valor_inmueble * 0.01;
		$honorarios_financiacion = 3500;
		$tasacion = 400;
		$vinculaciones = 2000;
		$escritura_compra_notario = 600;
		$escritura_compra_registro = 400;
		$escritura_compra_gestoria = 500;
		// if ($edad >= 35) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.08;
		// } elseif ($edad < 35 && $valor_inmueble <= 130000) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.035;
		// } else {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.08;
		// }
		if ($edad >= 35 && $valor_inmueble <= 150000) {
			$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.07;
		} elseif ($edad >= 35 && $valor_inmueble > 150000) {
			$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.07;
		} elseif ($edad < 35 && $valor_inmueble <= 150000) {
			$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.035;
		} elseif ($edad < 35 && $valor_inmueble > 150000) {
			$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.07;
		}
		$escritura_prestamo_hipotecario_notario = 0; //1100; Nueva Ley
		$escritura_prestamo_hipotecario_registro = 0; // 300; Nueva Ley
		$escritura_prestamo_hipotecario_gestoria = 0; //300; Nueva Ley
		if ($edad < 35 && $valor_inmueble <= 150000) {
			$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 16000 - $aportacion) * 1.3) * 0.003;
		} elseif ($valor_inmueble > 150000 && $valor_inmueble <= 200000) {
			$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 25000 - $aportacion) * 1.3) * 0.015;
		} elseif ($valor_inmueble > 200000) {
			$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 40000 - $aportacion) * 1.3) * 0.015;
		}
		$escritura_prestamo_hipotecario_impuesto_ajd = 0; // Nueva Ley
		switch ($tipo_hipoteca) {
			case 'cien':
				$tipo_variable = 0.015;
				if ($amortizacion >= 25 && $amortizacion <= 30) {
					$tipo_fijo = 0.025;
				} elseif ($amortizacion >= 20 && $amortizacion <= 24) {
					$tipo_fijo = 0.02;
				} elseif ($amortizacion >= 15 && $amortizacion <= 19) {
					$tipo_fijo = 0.0175;
				}
				break;
			case 'premium':
				$comision_apertura = 0;
				$vinculaciones = 0;
				$tipo_variable = 0.0055;
				if ($amortizacion >= 25 && $amortizacion <= 30) {
					$tipo_fijo = 0.011;
				} elseif ($amortizacion >= 20 && $amortizacion <= 24) {
					$tipo_fijo = 0.019;
				} elseif ($amortizacion >= 15 && $amortizacion <= 19) {
					$tipo_fijo = 0.0125;
				}
				break;
			case 'sin_compromiso':
				$vinculaciones = 0;
				$tipo_variable = 0.015;
				if ($amortizacion >= 20 && $amortizacion <= 30) {
					$tipo_fijo = 0.028;
				} elseif ($amortizacion < 20) {
					$tipo_fijo = 0.025;
				}
				break;
			case 'cambio_casa':
				$tipo_variable = 0.015;
				if ($amortizacion >= 25 && $amortizacion <= 30) {
					$tipo_fijo = 0.025;
				} elseif ($amortizacion >= 20 && $amortizacion <= 24) {
					$tipo_fijo = 0.02;
				} elseif ($amortizacion >= 15 && $amortizacion <= 19) {
					$tipo_fijo = 0.0175;
				}
				$levantamiento_registral = 1200;
				$tasacion = $tasacion * 2;
				$escritura_prestamo_hipotecario_notario = $escritura_prestamo_hipotecario_notario * 2;
				$escritura_prestamo_hipotecario_registro = $escritura_prestamo_hipotecario_registro * 2;
				$escritura_prestamo_hipotecario_gestoria = $escritura_prestamo_hipotecario_gestoria * 2;
				if ($edad < 35 && $valor_inmueble <= 150000) {
					$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 20000 - $aportacion) * 1.3) * 0.003;
				} elseif ($valor_inmueble > 150000 && $valor_inmueble <= 200000) {
					$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 30000 - $aportacion) * 1.3) * 0.015;
				} elseif ($valor_inmueble > 200000) {
					$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 45000 - $aportacion) * 1.3) * 0.015;
				}
				$escritura_prestamo_hipotecario_impuesto_ajd = 0; // Nueva Ley
				if ($valor_inmueble < 200000) {
					if ($edad < 35) {
						// $escritura_compra_impuesto_transmisiones = 15000;
					} else {
						// $escritura_compra_impuesto_transmisiones = 25000;
					}
				} else {
					// $escritura_compra_impuesto_transmisiones = 40000;
				}
				break;
		}
		if ($tipo_calculo === 'cuota') {
			if ($tipo_hipoteca != 'cambio_casa') {
				$valor_inmueble_inicial = $valor_inmueble;
				$valor_inmueble += $gasto_inmobiliaria + $honorarios_financiacion + $tasacion + $vinculaciones + $escritura_compra_notario + $escritura_compra_registro + $escritura_compra_gestoria + $escritura_compra_impuesto_transmisiones + $comision_apertura;
				$datos_calculo = array(
					'precio' => $valor_inmueble,
					'entrada' => $aportacion,
					'intereses' => $tipo_fijo,
					'plazo' => $amortizacion
				);
				$resultado = $this->calculoSencillo($datos_calculo);
				if ($edad < 35 && $valor_inmueble_inicial <=150000) {
					$respuesta['mensaje'] = 'Tiene bonificación por ser menor de 35 años.';
				}
				// $respuesta['mensaje'] .= 'Esta calculadora sólo esta destinada para vivienda habitual (descartando locales, naves, segunda residencia, etc.), y no contempla excepciones (minusvalía, etc.).';
				$respuesta['cuota_fija'] = $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_inmueble,
					'entrada' => $aportacion,
					'intereses' => $tipo_variable,
					'plazo' => $amortizacion
				);
				$resultado = $this->calculoSencillo($datos_calculo);
				$respuesta['cuota_variable'] = $resultado['cuota'];
				$respuesta['tipo_calculo'] = $tipo_calculo;
				$respuesta['intro'] = "Para un inmueble de ".$valor_inmueble_inicial."€ más gastos, con una aportación de ".$aportacion."€ en un plazo de ".$amortizacion." años, tu pago mensual a tipo fijo será de:";
				return new JsonResponse($respuesta);
			} else {
				// CAMBIO DE CASA:
				// Una persona de 30 años tiene una casa que vale 100000€ y le quedan 40000€ de hipoteca. Se quiere comprar una casa que vale 150000€
				// El precio serían los 150000 + 40000 + 15000(Gastos) - 10000 (aportación) = 195000€
				// Tenemos que calcular la cuota hasta la venta y la cuota después de la venta.
				// Si el importe total necesario (195000) supera el 80% del valor de las dos casas, mostrar mensaje de que se tiene que estudiar y se pongan en contacto.
				// Para el cálculo de la cuota antes de la venta tomamos como cantidad a financiar el 80% del valor de la casa a vender. Ahora calculamos con el interés fijo y variable con la carencia y con el importe restante.
				// La normal sería  con un interés fijo del 2,75% y un variable de 1,59%, el cálculo de la calculadora normal para 115000€ (195000 -80000) de importe, con entrada 0, el interés correspondiente y el plazo será de 30 años. Por lo que nos sale un importe de 401€ para variable y de 469€ para fijo. Ahora hay que sumarle la cuota de los 80000 de carencia con los interes correspondientes y la calculadora normal.
				// 80000  x 1.59% = 1272 /12 = 106€
				// 80000  x 2.75% = 2200 /12 = 183€
				// Sumando la carencia con la normal, la cuota a pagar hasta la venta sería de :
				// 507€ variable y 652€ fijo
				// // NOOOOOO Sin carencia serían 280€ para variable y 327€ de fijo por lo que al finalizar la carencia quedarían unas cuotas de 681€ (variable) y 796€ (fijo)
				// Tras la venta, si se vende por 100000 y se entregan los 100000, serían= 195000 - 100000 = 95000 Saldría 331€ variable y 387€ fijo
				$importe_total_inmuebles = $valor_inmueble + $valor_vivienda_actual;
				$ochenta_por_ciento_total = 0.8 * $importe_total_inmuebles;
				// echo "<br>Valor Inmueble: ".$valor_inmueble;
				// echo "<br>Hipoteca pendiente actual: ".$hipoteca_actual;
				// echo "<br>Gasto Inmobiliaria: ".$gasto_inmobiliaria;
				// echo "<br>Honorarios financiacion: ".$honorarios_financiacion;
				// echo "<br>Tasacion: ".$tasacion;
				// echo "<br>Escritura compra notario: ".$escritura_compra_notario;
				// echo "<br>Escritura compra registro: ".$escritura_compra_registro;
				// echo "<br>Escritura compra gestoria: ".$escritura_compra_gestoria;
				// echo "<br>Escritura compra impuesto transmisiones: ".$escritura_compra_impuesto_transmisiones;
				// echo "<br>Comision apertura: ".$comision_apertura;
				$valor_inmueble_inicial = $valor_inmueble;
				$valor_inmueble += $hipoteca_actual + $gasto_inmobiliaria + $honorarios_financiacion + $tasacion + $vinculaciones + $escritura_compra_notario + $escritura_compra_registro + $escritura_compra_gestoria + $escritura_compra_impuesto_transmisiones + $comision_apertura +$levantamiento_registral -$aportacion;
				if ($valor_inmueble > $ochenta_por_ciento_total) {
					// echo "<br><br>Suma Valor Inmueble Total: ".$valor_inmueble;
					// echo "<br>Precio vivienda actual ".$valor_vivienda_actual;
					// echo "<br>80% total dos viviendas ".$ochenta_por_ciento_total;
					// die();
					$respuesta['mensaje'] = 'Con los datos facilitados no es posible ofrecerte un resultado, debe ser estudiado con más detalle por nuestros asesores. Por favor ponte en contacto con nosotros.';
					$respuesta['importe_fijo'] = 0;
					$respuesta['importe_variable'] = 0;
					$respuesta['amortizacion'] = 0;
					$respuesta['entrada'] = 0;
					$respuesta['con_interes_fijo'] = 0;
					$respuesta['con_interes_variable'] = 0;
					$respuesta['con_entrada_fijo'] = 0;
					$respuesta['con_entrada_variable'] = 0;
					$respuesta['tipo_calculo'] = 0;
					$respuesta['cuota_fija'] = 0;
					$respuesta['cuota_variable'] = 0;
					$respuesta['cuota_variable_final'] = 0;
					$respuesta['cuota_fija_final'] = 0;
					return new JsonResponse($respuesta);
				}
				// Primero calculamos el importe antes de la venta
				// Y primero la cuota para el 80% del valor de la vivienda a vender
				$nuevo_interes_fijo = 0.0225;
				$nuevo_interes_variable = 0.0159;

				$valor_80_actual = 0.8 * $valor_vivienda_actual;
				$datos_calculo = array(
					'precio' => $valor_80_actual,
					'entrada' => 0,
					'intereses' => $nuevo_interes_fijo,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_fija_80_casa_vender= $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_80_actual,
					'entrada' => 0,
					'intereses' => $nuevo_interes_variable,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_variable_80_casa_vender= $resultado['cuota'];
				$valor_resto = $valor_inmueble - $valor_80_actual;
				$datos_calculo = array(
					'precio' => $valor_resto,
					'entrada' => 0,
					'intereses' => $nuevo_interes_fijo,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_fija_resto = $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_resto,
					'entrada' => 0,
					'intereses' => $nuevo_interes_variable,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_variable_resto = $resultado['cuota'];
				$intereses_fijo_80_vender = $valor_80_actual * $nuevo_interes_fijo / 12;
				$intereses_variable_80_vender = $valor_80_actual * $nuevo_interes_variable / 12;
				$cuota_fija_antes_venta = $cuota_fija_resto + $intereses_fijo_80_vender;
				$cuota_variable_antes_venta = $cuota_variable_resto + $intereses_variable_80_vender;
				$valor_final = $valor_inmueble - $aportacion_tras_venta;
				$datos_calculo = array(
					'precio' => $valor_final,
					'entrada' => 0,
					'intereses' => $nuevo_interes_fijo,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_fija_final = $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_final,
					'entrada' => 0,
					'intereses' => $nuevo_interes_variable,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_variable_final = $resultado['cuota'];
				if ($edad < 35 && $valor_inmueble <= 150000) {
					$respuesta['mensaje'] = 'Tiene bonificación por ser menor de 35 años.';
				}
				// $respuesta['mensaje'] .= 'Esta calculadora sólo esta destinada para vivienda habitual (descartando locales, naves, segunda residencia, etc.), y no contempla excepciones (minusvalía, etc.).';
				$respuesta['importe_fijo'] = round($cuota_fija_antes_venta,2);
				$respuesta['importe_variable'] =round( $cuota_variable_antes_venta,2);
				$respuesta['cuota_fija'] = round($cuota_fija_antes_venta,2);
				$respuesta['cuota_variable'] = round($cuota_variable_antes_venta,2);
				$respuesta['cuota_fija_final'] = round($cuota_fija_final,2);
				$respuesta['cuota_variable_final'] = round($cuota_variable_final,2);
				$respuesta['con_entrada_fijo'] = 0;
				$respuesta['con_interes_fijo'] = 0;
				$respuesta['entrada'] = $aportacion;
				$respuesta['con_entrada_variable'] = 0;
				$respuesta['con_interes_variable'] = 0;
				$respuesta['tipo_calculo'] = $tipo_calculo;
				$respuesta['amortizacion'] = $amortizacion;
				$respuesta['valor_inmueble'] = $valor_inmueble_inicial;
				$respuesta['valor_vivienda_actual'] = $valor_vivienda_actual;
				$respuesta['aportacion_tras_venta'] = $aportacion_tras_venta;
				$respuesta['hipoteca_actual'] = $hipoteca_actual;
				
				
				return new JsonResponse($respuesta);
			}
		} else {
			$cuota = (($ingresos_mensuales + ($importe_paga_extra * $numero_pagas / 12)) * 0.40) - $prestamos_mensuales;
			$gastos = 15000;
			// $interes_fijo = 0.015;
			$interes_fijo = 0.02; // Interes para este calculo sera 2% siempre, ni fijo ni variable
			$interes_variable = 0.025;
			$interes_fijo_l = "1,5%";
			$interes_variable_l = "2,5%";
			$aportacion_inicial = $aportacion;
			
			$datos_calculo = array(
				'cuota' => $cuota,
				'entrada' => $aportacion_inicial,
				'intereses' => $interes_fijo,
				'plazo' => $amortizacion,
				'gastos' => $gastos,
				'edad' => $edad
			);
			$resultado_f = $this->calculoImporteMaximo($datos_calculo);
			// $datos_calculo = array(
			// 	'cuota' => $cuota,
			// 	'entrada' => $aportacion_inicial,
			// 	'intereses' => $interes_variable,
			// 	'plazo' => $amortizacion,
			// 	'gastos' => $gastos
			// );
			// $resultado_v = $this->calculoImporteMaximo($datos_calculo);
			$respuesta['importe_fijo'] = $resultado_f['importe'];
			if ($edad < 35 && $resultado_f['importe'] <= 150000) {
				// $respuesta['mensaje'] = 'Tiene bonificación por ser menor de 35 años.';
			}
			// $respuesta['importe_variable'] = $resultado_v['importe'];
			// $respuesta['con_entrada_fijo'] = $resultado_f['conEntrada'];
			// $respuesta['con_entrada_variable'] = $resultado_v['conEntrada'];
			$respuesta['gastos'] = $gastos;
			$respuesta['cuota'] = $cuota;
			// $respuesta['con_interes_fijo'] = $resultado_f['interes'];
			// $respuesta['con_interes_variable'] = $resultado_v['interes'];
			$respuesta['entrada'] = $aportacion_inicial;
			$respuesta['tipo_calculo'] = $tipo_calculo;
			$respuesta['amortizacion'] = $amortizacion;
			return new JsonResponse($respuesta);
		}
	}

	private function buscarCadenaEnFichero($fichero, $cadena)
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

	private function calculoSencillo($datos)
	{
		$precio = $datos['precio'];
		$entrada = $datos['entrada'];
		$interes = $datos['intereses'];
		$plazos = $datos['plazo'];
		$ta = $precio;
		$dp = $entrada;
		$ir = $interes;
		$am = $plazos;
		$pp = 12;
		$cp = 12;
		$loan = $ta - $dp;
		$np = $am * $pp;
		if (!$ir) {
			$payment = $loan / $np;
		} else {
			$rNom = $ir;
			$rPeriod = pow(1 + $rNom / $cp, $cp / $pp) - 1;
			$rFactor = pow($rPeriod + 1, $np);
			$payment = $loan * (($rPeriod * $rFactor) / ($rFactor - 1));
		}
		// echo 'loan: '.$loan;
		// echo 'np: '.$np;
		// echo 'rNom: '.$rNom;
		// echo 'rPeriod: '.$rPeriod;
		// echo 'rFactor: '.$rFactor;
		// echo 'payment: '.$payment;
		$result = round($payment, 2);
		$display_total = $ta - $dp;
		// echo 'result: '.$result;
		$conIntereses = round($payment * $np, 2);
		$conEntrada = $conIntereses + $entrada;
		// $resultado = new Object('cuota', $result, 'interes', $conIntereses, 'conEntrada', $conEntrada);
		$resultado = array(
			'cuota' => $result,
			'interes' => $conIntereses,
			'conEntrada' => $conEntrada
		);
		return $resultado;
	}

	public function registrarClienteAction(Request $request, UserPasswordEncoderInterface $passwordEncoder, Swift_Mailer $mailer)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$jsonRecibido = json_decode($request->getContent(), true);
			$respuesta = array();
			// if (json_last_error() === 0) {
			$array_keys = array(
				'username',
				'password',
				'password2',
				'nombre',
				'apellidos',
				'dni',
				'telefono',
				'privacidad',
				'fipre'
			);
			$jsonRecibidoValido = true;
			// dump($jsonRecibido);
			// die();
			foreach ($array_keys as $array_key) {
				if (!array_key_exists($array_key, $jsonRecibido)) {
					$jsonRecibidoValido = false;
					break;
				}
			}
			if ($jsonRecibidoValido) {
				if ($jsonRecibido['password'] === $jsonRecibido['password2']) {
					$doctrine = $this->getDoctrine();
					$usuario = (new Usuario())
						->setPoliticaPrivacidad($jsonRecibido['privacidad'])
						->setContratoFipre($jsonRecibido['fipre']);
					if ($usuario->getPoliticaPrivacidad() && $usuario->getContratoFipre()) {
						try {
							$usuario->setTokenActivacion(bin2hex(random_bytes(60)));
						} catch (Exception $e) {
							$respuesta['errorlevel'] = 7;
						}
						$usuario->setEmail($jsonRecibido['username'])
							->setPlainPassword($jsonRecibido['password'])
							->setUsername($jsonRecibido['nombre'])
							->setApellidos($jsonRecibido['apellidos'])
							->setNif($jsonRecibido['dni'])
							->setTelefonoFijo($jsonRecibido['telefono'])
							->setTelefonoMovil($jsonRecibido['telefono'])
							->setPoliticaPrivacidad($jsonRecibido['privacidad'])
							->setContratoFipre($jsonRecibido['fipre'])
							->setRole('ROLE_CLIENTE')
							->setEstado(false)
							->setTokenFecha(new DateTime());
						$usuario->setPassword($passwordEncoder->encodePassword($usuario, $usuario->getPlainPassword()));
						$validador = $this->get('validator');
						$violaciones = $validador->validate($usuario);
						if (count($violaciones) === 0) {
							$managerEntidad = $doctrine->getManager();
							$managerEntidad->persist($usuario);
							$managerEntidad->flush();
							$from = array($this->getParameter('mailer_user') => 'Hipotea');
							$mensaje = (new Swift_Message('Activar cuenta en Hipotea.'))
								->setFrom($from)
								->setTo($jsonRecibido['username'])
								->setBody($this->renderView('@App/Backoffice/Correo/ActivarCuenta.html.twig', array(
									'urlgenerada' => $this->generateUrl('activar_cuenta', array(
										'token' => $usuario->getTokenActivacion()
									), UrlGeneratorInterface::ABSOLUTE_URL)
								)), 'text/html');
							if ($mailer->send($mensaje)) {
								$roles = array("ROLE_ADMIN", "ROLE_TECNICO", "ROLE_COMERCIAL");

								//Aviso de que se ha creado una cuenta
								$usuarios_gn = $doctrine->getRepository(Usuario::class)->findBy(array(
										'role' => $roles
									)
								);
								foreach($usuarios_gn as $usuario_gn){
									$notificacion = (new Notificacion)
										->setEstado(1)
										->setFecha(new DateTime())
										->setIdUsuario($usuario_gn)
										->setTitulo('Nueva cuenta creada')
										->setTexto('El cliente ' . $usuario->getUsername() . ' ' . $usuario->getApellidos() . ' ha creado un nueva cuenta y debe activarla');
									$managerEntidad->persist($notificacion);
									$managerEntidad->flush();
								}
								$respuesta['errorlevel'] = 0;
							} else {
								$respuesta['errorlevel'] = 6;
							}
						} else {
							$respuesta['errorlevel'] = 1;
							foreach ($violaciones as $violacion) {
								$respuesta['errores'][] = array(
									'propiedad' => $violacion->getPropertyPath(),
									'mensaje' => $violacion->getMessage()
								);
							}
						}
					} else {
						$respuesta['errorlevel'] = 2;
					}
				} else {
					$respuesta['errorlevel'] = 3;
				}
			} else {
				$respuesta['errorlevel'] = 4;
			}
			// } else {
			// 	$respuesta['errorlevel'] = 5;
			// }
			return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
		}
		throw new HttpException(400);
	}

	public function datosPerfilAction()
	{
		$usuario = $this->getUser();
		$miUsuario = array(
			'username' => $usuario->getUsername(),
			'apellidos' => $usuario->getApellidos(),
			'nif' => $usuario->getNif(),
			'telefonoMovil' => $usuario->getTelefonoMovil(),
			'email' => $usuario->getEmail(),
			'role' => $usuario->getRole()
		);
		return new JsonResponse($miUsuario, JSON_UNESCAPED_UNICODE);
	}

	public function actualizarPerfilAction(Request $request, UserPasswordEncoderInterface $passwordEncoder)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$jsonRecibido = json_decode($request->getContent(), true);
			$respuesta = array();
			if (json_last_error() === 0) {
				$array_keys = array(
					'username',
					'nombre',
					'apellidos',
					'dni',
					'telefono'
				);
				$jsonRecibidoValido = true;
				foreach ($array_keys as $array_key) {
					if (!array_key_exists($array_key, $jsonRecibido)) {
						$jsonRecibidoValido = false;
						break;
					}
				}
				if ($jsonRecibidoValido) {
					$doctrine = $this->getDoctrine();
					$repositorios = array(
						'Usuarios' => $doctrine->getRepository('AppBundle:Usuario')
					);
					$usuario = $doctrine->getRepository(Usuario::class)->findOneBy(array(
						'idUsuario' => $this->getUser()->getIdUsuario()
					));
					if ($jsonRecibido['password'] === $jsonRecibido['password2'] && $jsonRecibido['password'] !== '') {
						$usuario->setPlainPassword($jsonRecibido['password']);
						$usuario->setPassword($passwordEncoder->encodePassword($usuario, $usuario->getPlainPassword()));
					}
					$usuario->setEmail($jsonRecibido['username'])
						->setUsername($jsonRecibido['nombre'])
						->setApellidos($jsonRecibido['apellidos'])
						->setNif($jsonRecibido['dni'])
						->setTelefonoMovil($jsonRecibido['telefono']);
					$validador = $this->get('validator');
					$violaciones = $validador->validate($usuario);
					if (count($violaciones) === 0) {
						$managerEntidad = $doctrine->getManager();
						$managerEntidad->persist($usuario);
						$managerEntidad->flush();
						$respuesta = 'OK';
					} else {
						$respuesta['errorlevel'] = 1;
						foreach ($violaciones as $violacion) {
							$respuesta['errores'][] = array(
								'propiedad' => $violacion->getPropertyPath(),
								'mensaje' => $violacion->getMessage()
							);
						}
					}
				} else {
					$respuesta = 'Error en información recibida';
				}
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}
			throw new HttpException(400);
		}
	}

	public function clientesColaboradorAction()
	{
		$doctrine = $this->getDoctrine();
		$idUsuario = $this->getUser()->getIdUsuario();
		$usuario = $this->getUser();
		$repositorios = array(
			'Usuarios' => $doctrine->getRepository('AppBundle:Usuario'),
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente')
		);
		$expedientes = $repositorios['Expedientes']->findBy(array(
				'idColaborador' => $usuario,
				'estado' => 1
			)
		);
		$idsClientes = array();
		foreach ($expedientes as $expediente) {
			if ($expediente->getIdCliente()){
				$idsClientes[] = $expediente->getIdCliente()->getIdUsuario();
			}
		}
		$clientes = $doctrine->getRepository(Usuario::class)->findBy(array(
			'idUsuario' => $idsClientes,
			// 'estado' => 1
		), array(
				'apellidos' => 'ASC',
				'nombre' => 'ASC'
			)
		);
		$serializador = new Serializer(array(
			new ObjectNormalizer(),
			new DateTimeNormalizer('H:i:s d-m-Y')
		), array(
			new JsonEncoder()
		));
		return new JsonResponse($serializador->normalize($clientes, null, array(
			'attributes' => array(
				'idUsuario',
				'username',
				'apellidos'
			)
		)));
	}

	public function crearExpedienteColaboradorAction(Request $request, UserPasswordEncoderInterface $passwordEncoder, Swift_Mailer $mailer)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$usuarioRecibido = json_decode($request->getContent(), true);
			if(array_key_exists('idUsuario', $usuarioRecibido)){
				$idUsuario = $usuarioRecibido['idUsuario'];
			}else{
				$idUsuario = null;
			}
			
			$vivienda = $usuarioRecibido['direccion'];
			$respuesta = array();
			$colaborador = $this->getUser();
			$doctrine = $this->getDoctrine();
			// Ahora creamos el expediente para este cliente
			$repositorios = array(
				'Fases' => $doctrine->getRepository('AppBundle:Fase'),
				'Usuarios' => $doctrine->getRepository('AppBundle:Usuario')
			);
			$fase = $repositorios['Fases']->findOneBy(array(
					'tipo' => 0
				)
			);
			$usuario = $doctrine->getRepository(Usuario::class)->findOneBy(array(
					'idUsuario' => $idUsuario
				)
			);
			$expediente = (new Expediente())
				->setEstado(1)
				->setIdCliente($usuario)
				->setIdColaborador($colaborador)
				->setIdComercial($colaborador->getIdInmobiliaria()->getIdComercial())
				->setIdFaseActual($fase)
				->setVivienda($vivienda)
				->setFechaCreacion(new DateTime());
			$managerEntidad = $doctrine->getManager();

			$fases = $doctrine->getRepository(Fase::class)->findBy(array(), array(
				'orden' => 'ASC'
			));

			foreach($fases as $fase){
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
							
							if($campoHito->getTipo() == 4){
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


			$managerEntidad->persist($expediente);
			$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Creación del expediente', $expediente));
			$managerEntidad->flush();
			// Enviar notificación NO PUSH al admin y al comercial
			$comercialesnoti = $doctrine->getRepository(Usuario::class)->findBy(array(
					'role' => 'ROLE_COMERCIAL'
				)
			);
			foreach($comercialesnoti as $comercialnoti){
				$notificacion = (new Notificacion)
					->setIdExpediente($expediente)
					->setEstado(1)
					->setFecha(new DateTime())
					->setIdUsuario($comercialnoti)
					->setTitulo('Nuevo expediente creado')
					->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
				$managerEntidad->persist($notificacion);
				// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
				$managerEntidad->flush();
				$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($comercialnoti, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
			}
			
			$tecnicosnoti = $doctrine->getRepository(Usuario::class)->findBy(array(
					'role' => 'ROLE_TECNICO'
				)
			);
			foreach($tecnicosnoti as $tecniconoti){
				$notificacion = (new Notificacion)
					->setIdExpediente($expediente)
					->setEstado(1)
					->setFecha(new DateTime())
					->setIdUsuario($tecniconoti)
					->setTitulo('Nuevo expediente creado')
					->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
				$managerEntidad->persist($notificacion);
				// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
				$managerEntidad->flush();
				$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($tecniconoti, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
			}
			$admin = $doctrine->getRepository(Usuario::class)->findOneBy(array(
					'role' => 'ROLE_ADMIN'
				)
			);
			$notificacion = (new Notificacion)
				->setIdExpediente($expediente)
				->setEstado(1)
				->setFecha(new DateTime())
				->setIdUsuario($admin)
				->setTitulo('Nuevo expediente creado')
				->setTexto('El colaborador ' . $colaborador->getUsername() . ' ' . $colaborador->getApellidos() . ' ha creado un nuevo expediente');
			$managerEntidad->persist($notificacion);
			$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
			$managerEntidad->flush();
			$respuesta['errorlevel'] = 0;
			return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
		}
		throw new HttpException(400);
	}

	public function crearClienteColaboradorAction(Request $request, UserPasswordEncoderInterface $passwordEncoder, Swift_Mailer $mailer)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$jsonRecibido = json_decode($request->getContent(), true);
			$respuesta = array();
			$colaborador = $this->getUser();
			$array_keys = array(
				'username',
				'nombre',
				'apellidos',
				'dni',
				'telefono',
				'direccion'
			);
			$jsonRecibidoValido = true;
			foreach ($array_keys as $array_key) {
				if (!array_key_exists($array_key, $jsonRecibido)) {
					$jsonRecibidoValido = false;
					break;
				}
			}
			if ($jsonRecibidoValido) {
				$doctrine = $this->getDoctrine();
				$usuario = new Usuario();
				try {
					$usuario->setTokenActivacion(bin2hex(random_bytes(60)));
				} catch (Exception $e) {
					$respuesta['errorlevel'] = 7;
				}
				$usuario->setEmail($jsonRecibido['username'])
					->setUsername($jsonRecibido['nombre'])
					->setApellidos($jsonRecibido['apellidos'])
					->setNif($jsonRecibido['dni'])
					->setTelefonoFijo($jsonRecibido['telefono'])
					->setTelefonoMovil($jsonRecibido['telefono'])
					->setRole('ROLE_CLIENTE')
					->setEstado(false)
					->setTokenFecha(new DateTime());
				$validador = $this->get('validator');
				$violaciones = $validador->validate($usuario);
				if (count($violaciones) === 0) {
					$managerEntidad = $doctrine->getManager();
					$managerEntidad->persist($usuario);
					$managerEntidad->flush();
					$from = array($this->getParameter('mailer_user') => 'Hipotea');
					$mensaje = (new Swift_Message('Activar cuenta en Hipotea.'))
						->setFrom($from)
						->setTo($jsonRecibido['username'])
						->setBody($this->renderView('@App/Backoffice/Correo/ActivarCuentaSinPassword.html.twig', array(
							'urlgenerada' => $this->generateUrl('activar_cliente_creado_colaborador', array(
								'token' => $usuario->getTokenActivacion()
							), UrlGeneratorInterface::ABSOLUTE_URL)
						)), 'text/html');
					if ($mailer->send($mensaje)) {
						// Ahora creamos el expediente para este cliente
						$repositorios = array(
							'Fases' => $doctrine->getRepository('AppBundle:Fase'),
							'Usuarios' => $doctrine->getRepository('AppBundle:Usuario')
						);
						$fase = $repositorios['Fases']->findOneBy(array(
								'orden' => 1
							)
						);
						$expediente = (new Expediente())
							->setEstado(1)
							->setIdCliente($usuario)
							->setIdColaborador($colaborador)
							->setIdComercial($colaborador->getIdInmobiliaria()->getIdComercial())
							->setIdFaseActual($fase)
							->setVivienda($jsonRecibido['direccion'])
							->setFechaCreacion(new DateTime());
						$managerEntidad = $doctrine->getManager();

						$fases = $doctrine->getRepository(Fase::class)->findBy(array(), array(
							'orden' => 'ASC'
						));
			
						foreach($fases as $fase){
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
										
										if($campoHito->getTipo() == 4){
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

						$managerEntidad->persist($expediente);
						$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Creación del expediente', $expediente));
						$managerEntidad->flush();
						
						$roles = array("ROLE_ADMIN", "ROLE_TECNICO", "ROLE_COMERCIAL");

						//Aviso de que se ha creado una cuenta
						$usuarios_gn = $doctrine->getRepository(Usuario::class)->findBy(array(
								'role' => $roles
							)
						);
						foreach($usuarios_gn as $usuario_gn){
							$notificacion = (new Notificacion)
								->setIdExpediente($expediente)
								->setEstado(1)
								->setFecha(new DateTime())
								->setIdUsuario($usuario_gn)
								->setTitulo('Nuevo expediente creado')
								->setTexto('El colaborador '.$this->getUser()->getUsername().' '.$this->getUser()->getApellidos().' ha creado el cliente ' . $usuario->getUsername() . ' ' . $usuario->getApellidos() . '.');
							$managerEntidad->persist($notificacion);
						}
						$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
						$managerEntidad->flush();
						$respuesta['errorlevel'] = 0;
					} else {
						$respuesta['errorlevel'] = 6;
					}
				} else {
					$respuesta['errorlevel'] = 1;
					foreach ($violaciones as $violacion) {
						$respuesta['errores'][] = array(
							'propiedad' => $violacion->getPropertyPath(),
							'mensaje' => $violacion->getMessage()
						);
					}
				}
			} else {
				$respuesta['errorlevel'] = 4;
			}
			return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
		}
		throw new HttpException(400);
	}

	public function documentoParaFirmarAction($idCampoHitoExpediente)
	{
		$doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'CamposHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo')
		);
		// $expedientes = $repositorios['Expedientes']->findBy(array(
		// 	'idCliente' => $this->getUser()->getIdUsuario(),
		// 	'estado' => '1'
		// ));
		$respuesta = array();
		// foreach ($expedientes as $expediente) {
		$campoHitoExpediente = $repositorios['CamposHitoExpediente']->findOneBy(array(
			'idCampoHitoExpediente' => $idCampoHitoExpediente
		));
		// foreach ($camposHitoExpediente as $campoHitoExpediente) {
		$ficheroCampo = $repositorios['FicheroCampo']->findOneBy(array(
			'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
			'idExpediente' => $campoHitoExpediente->getIdExpediente()
		));
		// foreach ($ficheroCampos as $ficheroCampo) {
		$path = $this->getParameter('kernel.project_dir') . '/web/uploads/' . $ficheroCampo->getNombreFichero();
		if ($ficheroCampo->getNombreFichero()) {
			$b64Doc = chunk_split(base64_encode(file_get_contents($path)));
		} else {
			$b64Doc = null;
		}
		$respuesta = array(
			'idExpediente' => $campoHitoExpediente->getIdExpediente()->getIdExpediente(),
			'idCampoHitoExpediente' => $campoHitoExpediente->getIdCampoHitoExpediente(),
			'nombre' => $campoHitoExpediente->getValor(),
			'firmado' => $campoHitoExpediente->getFirmado(),
			'nombreFichero' => $ficheroCampo->getNombreFichero(),
			'fichero64' => $b64Doc
		);
		// $respuesta [] = $campo;
		// }
		// }
		// }
		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	private function calculoImporteMaximo($datos)
	{
		$cuota = $datos['cuota'];
		$entrada = $datos['entrada'];
		$interes = $datos['intereses'];
		$plazos = $datos['plazo'];
		$gastos = $datos['gastos'];
		$edad = $datos['edad'];
		// $ta = $precio;
		$dp = $entrada;
		$ir = $interes;
		$am = $plazos;
		$pp = 12;
		$cp = 12;
		// $loan = $ta - $dp;
		$np = $am * $pp;
		if (!$ir) {
			$loan = $cuota * $np;
		} else {
			$rNom = $ir;
			$rPeriod = pow(1 + $rNom / $cp, $cp / $pp) - 1;
			$rFactor = pow($rPeriod + 1, $np);
			$loan = $cuota / (($rPeriod * $rFactor) / ($rFactor - 1));
		}
		$resultado_sin_gastos = round($loan - $entrada, 2);
		if ($resultado_sin_gastos <= 150000 && $edad < 35) {
			$gastos = 15000;
		} elseif ($resultado_sin_gastos <= 150000 && $edad >= 35) {
			$gastos = 20000;
		} elseif ($resultado_sin_gastos > 150000 && $resultado_sin_gastos <= 200000) {
			$gastos = 25000;
		} elseif ($resultado_sin_gastos > 200000) {
			$gastos = 25000 + (10000 * (floor($resultado_sin_gastos / 100000) - 1));
		}
		$resultado = round($loan + $entrada - $gastos, 2);
		$resultado = array(
			'importe' => $resultado
		);
		return $resultado;
	}

	public function enviarCuestionarioAction(Request $request, $idExpediente, LoggerInterface $logger)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$jsonRecibido = json_decode($request->getContent(), true);
			$respuesta = array();
			if (json_last_error() === 0) {
				$array_keys = array(
					'cuestionario'
				);
				$jsonRecibidoValido = true;
				foreach ($array_keys as $array_key) {
					if (!array_key_exists($array_key, $jsonRecibido)) {
						$jsonRecibidoValido = false;
						break;
					}
				}
				if ($jsonRecibidoValido) {
					$doctrine = $this->getDoctrine();
					// $this->getUser()->getIdUsuario()
					$repositorios = array(
						'CampoHito' => $doctrine->getRepository('AppBundle:CampoHito'),
						'CampoHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
						'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
						'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo'),
						'HitoExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente')
					);
					$existeRegistroActividadConEntidadFaseDatos = false;
					$hitoExpedienteAnterior = -1;
					$campoAnterior = -1;
					foreach ($jsonRecibido['cuestionario'] as $idCampoHito) {
						$campoHito = $repositorios['CampoHito']->findOneBy(array(
							'idCampoHito' => $idCampoHito['idCampoHito']
						));
						$expediente = $repositorios['Expedientes']->findOneBy(array(
							'idExpediente' => $idExpediente,
							'idCliente' => $this->getUser()
						));
						if ($idCampoHito['idCampoHito'] != $campoAnterior || ($idCampoHito['idCampoHito'] == $campoAnterior && $idCampoHito['idHitoExpediente'] != $hitoExpedienteAnterior)) {
							$hitoExpediente = $repositorios['HitoExpediente']->findOneBy(array(
								'idHitoExpediente' => $idCampoHito['idHitoExpediente']
							));
						} else {
							$hitoExpediente = null;
						}
						$campoHitoExpediente = $repositorios['CampoHitoExpediente']->findOneBy(array(
							'idCampoHito' => $campoHito,
							'idExpediente' => $expediente,
							'idHitoExpediente' => $hitoExpediente
						));
						if ($campoHitoExpediente) {
							if ($campoHito->getTipo() === 1 || $campoHito->getTipo() === 2 || $campoHito->getTipo() === 5 || $campoHito->getTipo() === 6) {
								$campoHitoExpediente->setValor($idCampoHito['valor']);
							} else {
								$idOpcion = substr($idCampoHito['valor'], strpos($idCampoHito['valor'], '_') + 1);
								for ($i = 0; $i < 3; $i += 1) {
									$idOpcion = substr($idOpcion, strpos($idOpcion, '_') + 1);
								}
								$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
									'idOpcionesCampo' => $idOpcion
								));
								$campoHitoExpediente->setIdOpcionesCampo($opcion);
							}
							$campoHitoExpediente->setFechaModificacion(new DateTime());
							$managerEntidad = $doctrine->getManager();
							$managerEntidad->persist($campoHitoExpediente);
							if ($campoHito->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación del campo hito "' . $campoHito->getNombre() . '"', $expediente));
							}
							$managerEntidad->flush();
							$respuesta = 'OK';
						} else {
							if ($hitoExpediente == null) {
								$hitoExpediente = (new HitoExpediente())
									->setIdHito($campoHito->getIdHito())
									->setIdExpediente($expediente)
									->setEstado(0);
								$managerEntidad = $doctrine->getManager();
								$managerEntidad->persist($hitoExpediente);
								$managerEntidad->flush();
							}

							$campoHitoExpediente = (new CampoHitoExpediente())
								->setIdCampoHito($campoHito)
								->setIdExpediente($expediente)
								->setIdHitoExpediente($hitoExpediente)
								->setFechaModificacion(new DateTime());
							if ($campoHito->getTipo() === 1 || $campoHito->getTipo() === 2 || $campoHito->getTipo() === 5 || $campoHito->getTipo() === 6) {
								$campoHitoExpediente->setValor($idCampoHito['valor']);
							} else {
								$idOpcion = substr($idCampoHito['valor'], strpos($idCampoHito['valor'], '_') + 1);
								for ($i = 0; $i < 3; $i += 1) {
									$idOpcion = substr($idOpcion, strpos($idOpcion, '_') + 1);
								}
								$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
									'idOpcionesCampo' => $idOpcion
								));
								$campoHitoExpediente->setIdOpcionesCampo($opcion);
							}
							// $managerEntidad = $doctrine->getManager();
							$managerEntidad->persist($campoHitoExpediente);
							if ($campoHito->getIdHito()->getIdFase()->getTipo() === 0) {
								if (!$existeRegistroActividadConEntidadFaseDatos) {
									$existeRegistroActividadConEntidadFaseDatos = true;
									$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Entrada del expediente', $expediente));
								}
							} else {
								$this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($this->getUser(), 'Modificación del campo hito "' . $campoHito->getNombre() . '"', $expediente));
							}
							$managerEntidad->flush();
							$respuesta = 'OK';
						}
						$hitoExpedienteAnterior = $idCampoHito['idHitoExpediente'];
						$campoAnterior = $idCampoHito['idCampoHito'];
					}
				} else {
					$respuesta = 'Error en información recibida';
				}
			}
			return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
		}
		throw new HttpException(400);
	}

	
	public function estructuraExpedienteAction()
	{
		$doctrine = $this->getDoctrine();
		$idUsuario = $this->getUser()->getIdUsuario();
		$usuario = $this->getUser();
		$repositorios = array(
			'Fases' => $doctrine->getRepository('AppBundle:Fase'),
			'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
			'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
			'CamposHito' => $doctrine->getRepository('AppBundle:CampoHito'),
			'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo')
		);
		$fase = $repositorios['Fases']->findOneBy(array(
				'tipo' => 0
			)
		);
		$hitos = $repositorios['Hitos']->findBy(array(
				'idFase' => $fase
			),
			array(
				'orden' => 'ASC'
			)
		);

		$gruposCampos = $repositorios['GruposCampos']->findBy(array(
				'idHito' => $hitos
			),
			array(
				'orden' => 'ASC'
			)
		);

		$campos = $repositorios['CamposHito']->findBy(array(
				'idGrupoCamposHito' => $gruposCampos
			),
			array(
				'orden' => 'ASC'
			)
		);

		$opciones = $repositorios['OpcionesCampo']->findBy(array(
				'idCampoHito' => $campos
			),
			array(
				'idCampoHito' => 'ASC',
				'orden' => 'ASC'
			)
		);

		$serializador = new Serializer(array(
			new ObjectNormalizer()
		), array(
			new JsonEncoder()
		));

		$fase = $serializador->normalize($fase, null, array(
			'attributes' => array(
				'idFase',
				'nombre'
			)
		));

		$hitos = $serializador->normalize($hitos, null, array(
			'attributes' => array(
				'idHito',
				'nombre',
				'orden',
				'repetible',
				'hitoCondicional'
				// 'idFase' => array(
				// 	'idFase',
				// 	'nombre'
				// )
			)
		));

		$grupos = $serializador->normalize($gruposCampos, null, array(
			'attributes' => array(
				'idGrupoCamposHito',
				'nombre',
				'orden',
				'repetible',
				'idHito' => array(
					'idHito',
					'nombre',
					'orden',
					'repetible',
					'hitoCondicional'
					// 'idFase' => array(
					// 	'idFase',
					// 	'nombre'
					// )
				)
			)
		));

		$campos = $serializador->normalize($campos, null, array(
			'attributes' => array(
				'idCampoHito',
				'nombre',
				'tipo',
				'orden',
				'campoCondicional',
				'mostrarCliente',
				'mostrarColaborador',
				'idGrupoCamposHito' => array(
					'idGrupoCamposHito',
					'nombre',
					'orden',
					'idHito' => array(
						'idHito',
						// 'nombre',
						// 'orden',
						// 'repetible',
						// 'idFase' => array(
						// 	'idFase',
						// 	'nombre'
						// )
					)
				)
			)
		));

		$opciones = $serializador->normalize($opciones, null, array(
			'attributes' => array(
				'idOpcionesCampo',
				'orden',
				'valor',
				'orden',
				'idHitoCondicional',
				'idCampoCondicional',
				'idCampoHito' => array(
					'idCampoHito',
					'idGrupoCamposHito' => array(
						'idGrupoCamposHito',
						'idHito' => array(
							'idHito'
						)
					)
				)
			)
		));

		$arrayRespuesta = array();

		$arrayRespuesta['fase'] = $fase;
		$arrayRespuesta['hitos'] = $hitos;
		$arrayRespuesta['grupos'] = $grupos;
		$arrayRespuesta['campos'] = $campos;
		$arrayRespuesta['opciones'] = $opciones;
		// $serializador = new Serializer(array(
		// 	new ObjectNormalizer(),
		// 	new DateTimeNormalizer('H:i:s d-m-Y')
		// ), array(
		// 	new JsonEncoder()
		// ));

		// $serializador->normalize($clientes, null, array(
		// 	'attributes' => array(
		// 		'idUsuario',
		// 		'username',
		// 		'apellidos'
		// 	)
		// ));

		return new JsonResponse($arrayRespuesta);
	}

	
	public function crearCuestionarioAction(Request $request, LoggerInterface $logger)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$valoresCampos = json_decode($request->getContent(), true);
			// return new JsonResponse(dump($valoresCampos), JSON_UNESCAPED_UNICODE);
			
			$respuesta = array();
			if (json_last_error() === 0) {

				$repeticionesHitos = array(array());
				$repeticionesGrupos = array();
				$repeticionesCampos = array();

				$creacionesGrupos = array(array());
				
				
				$doctrine = $this->getDoctrine();
				$usuario = $this->getUser();
				$managerEntidad = $doctrine->getManager();
				// $this->getUser()->getIdUsuario()
				$repositorios = array(
					'CampoHito' => $doctrine->getRepository('AppBundle:CampoHito'),
					'Fases' => $doctrine->getRepository('AppBundle:Fase'),
					'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
					'HitosExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
					'GruposHitoExpediente' => $doctrine->getRepository('AppBundle:GrupoHitoExpediente'),
					'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
					'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo')
				);

				$faseActual = $repositorios['Fases']->findOneBy(array(
					'tipo' => 0
				));

				$expediente = (new Expediente())
					->setIdColaborador($usuario)
					->setIdFaseActual($faseActual)
					->setEstado(1)
					->setFechaCreacion(new DateTime())
					->setVivienda('CUESTIONARIO APP')
				;
				
				// Ahora creamos el nuevo expediente para el cuestionario
				$managerEntidad->persist($expediente);
				$managerEntidad->flush();


				foreach($valoresCampos as $valorCampo){
					$idCampoHito = $valorCampo['idCampoHito']['idCampoHito'];
					$tipoCampo = $valorCampo['idCampoHito']['tipo'];
					$idGrupoCamposHito = $valorCampo['idCampoHito']['idGrupoCamposHito']['idGrupoCamposHito'];
					$idHito = $valorCampo['idCampoHito']['idGrupoCamposHito']['idHito']['idHito'];
					$valor = $valorCampo['valor'];

					if(strpos($idHito,'[')===false){ // No es un hito repetido
						$hito = $repositorios['Hitos']->findOneBy(array(
							'idHito' => $idHito
						));
						$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
							'idHito' => $hito,
							'idExpediente' => $expediente
						));
						if(!$hitoExpediente){// No existe el hito expediente
							$hitoExpediente = (new HitoExpediente())
								->setIdHito($hito)
								->setIdExpediente($expediente)
								->setEstado(1)
								->setFechaModificacion(new DateTime())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($hitoExpediente);
							$managerEntidad->flush();
						}
						$idCampo = $repositorios['CampoHito']->findOneBy(array(
							'idCampoHito' => $idCampoHito
						));

						if(array_key_exists($idHito, $creacionesGrupos) && array_key_exists($idGrupoCamposHito,$creacionesGrupos[$idHito])){// Ya existe el hito duplicado
							$grupoHitoExpediente = $repositorios['GruposHitoExpediente']->findOneBy(array(
								'idGrupoHitoExpediente' => $creacionesGrupos[$idHito][$idGrupoCamposHito]
							));
						}else{// No existe el grupo hito expediente
							$grupoHitoExpediente = (new GrupoHitoExpediente())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoCamposHito($idCampo->getIdGrupoCamposHito())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($grupoHitoExpediente);
							$managerEntidad->flush();
							$creacionesGrupos[$idHito][$idGrupoCamposHito] = $grupoHitoExpediente;
						}

						$campoHitoExpediente = (new CampoHitoExpediente())
							->setIdCampoHito($idCampo)
							->setIdExpediente($expediente)
							->setIdHitoExpediente($hitoExpediente)
							->setIdGrupoHitoExpediente($grupoHitoExpediente)
							->setFechaModificacion(new DateTime())
						;
						if($idCampo->getTipo() == 4){
							$campoHitoExpediente->setObligatorio(1)
								->setSolicitarAlColaborador(1);
						}

						if($idCampo->getTipo() != 3){// No es un campo de opciones
							if($idCampo->getTipo() == 6){// Es tipo fecha
								if($valor != ''){
									$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
								}
							}else{
								$campoHitoExpediente->setValor($valor);
							}
						}else{// Es un campo de opciones
							$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
							if(strpos($idOpcion,'[')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'['));
							}
							if(strpos($idOpcion,'_')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'_'));
							}

							if(strpos($valor,'[')>-1){
								$valor = substr($valor,0,strpos($valor,'['));
							}
							$pos1 = strpos($valor,'_');
							if($pos1 > -1){
								$pos2 = strpos($valor, '_', $pos1 + 1);
								if($pos2 > -1){
									$pos3 = strpos($valor, '_', $pos2 + 1);
									if($pos3 > -1){
										$pos4 = strpos($valor, '_', $pos3 + 1);
										if($pos4 > -1){
											$valor = substr($valor,0,$pos4);
										}
									}
								}
							}


							$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
								'idOpcionesCampo' => $idOpcion
							));
							$campoHitoExpediente->setValor('');
							$campoHitoExpediente->setIdOpcionesCampo($opcion);
						}
						
						// Ahora creamos el nuevo hito expediente para el cuestionario
						$managerEntidad->persist($campoHitoExpediente);
						$managerEntidad->flush();
						if($idCampo->getTipo() == 3 && $valor != ""){
							$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();
						}

					}else{// Es un hito repetido
						$idHito2 = substr($idHito,0,strpos($idHito,'['));
						$idRepeticion = substr($idHito,strpos($idHito,'[')+1,-1);
						$hito = $repositorios['Hitos']->findOneBy(array(
							'idHito' => $idHito2
						));

						if(array_key_exists($idHito2, $repeticionesHitos) && array_key_exists($idRepeticion,$repeticionesHitos[$idHito2])){// Ya existe el hito duplicado
							$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
								'idHitoExpediente' => $repeticionesHitos[$idHito2][$idRepeticion]
							));
						}else{// No existe el hito expediente
							$hitoExpediente = (new HitoExpediente())
								->setIdHito($hito)
								->setIdExpediente($expediente)
								->setEstado(1)
								->setFechaModificacion(new DateTime())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($hitoExpediente);
							$managerEntidad->flush();
							$repeticionesHitos[$idHito2][$idRepeticion] = $hitoExpediente;
						}

						$idCampo = $repositorios['CampoHito']->findOneBy(array(
							'idCampoHito' => $idCampoHito
						));

						if(array_key_exists($idHito, $creacionesGrupos) && array_key_exists($idGrupoCamposHito,$creacionesGrupos[$idHito])){// Ya existe el hito duplicado
							$grupoHitoExpediente = $repositorios['GruposHitoExpediente']->findOneBy(array(
								'idGrupoHitoExpediente' => $creacionesGrupos[$idHito][$idGrupoCamposHito]
							));
						}else{// No existe el grupo hito expediente
							$grupoHitoExpediente = (new GrupoHitoExpediente())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoCamposHito($idCampo->getIdGrupoCamposHito())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($grupoHitoExpediente);
							$managerEntidad->flush();
							$creacionesGrupos[$idHito][$idGrupoCamposHito] = $grupoHitoExpediente;
						}

						$campoHitoExpediente = (new CampoHitoExpediente())
							->setIdCampoHito($idCampo)
							->setIdExpediente($expediente)
							->setIdHitoExpediente($hitoExpediente)
							->setIdGrupoHitoExpediente($grupoHitoExpediente)
							->setFechaModificacion(new DateTime())
						;
						
						if($idCampo->getTipo() != 3){// No es un campo de opciones
							if($idCampo->getTipo() == 6){// Es tipo fecha
								if($valor != ''){
									$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
								}
							}else{
								$campoHitoExpediente->setValor($valor);
							}
						}else{// Es un campo de opciones
							$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
							if(strpos($idOpcion,'[')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'['));
							}
							if(strpos($idOpcion,'_')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'_'));
							}

							if(strpos($valor,'[')>-1){
								$valor = substr($valor,0,strpos($valor,'['));
							}
							
							$pos1 = strpos($valor,'_');
							if($pos1 > -1){
								$pos2 = strpos($valor, '_', $pos1 + 1);
								if($pos2 > -1){
									$pos3 = strpos($valor, '_', $pos2 + 1);
									if($pos3 > -1){
										$pos4 = strpos($valor, '_', $pos3 + 1);
										if($pos4 > -1){
											$valor = substr($valor,0,$pos4);
										}
									}
								}
							}


							$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
								'idOpcionesCampo' => $idOpcion
							));
							$campoHitoExpediente->setValor('');
							$campoHitoExpediente->setIdOpcionesCampo($opcion);
						}
						
						// Ahora creamos el nuevo hito expediente para el cuestionario
						$managerEntidad->persist($campoHitoExpediente);
						$managerEntidad->flush();
						if($idCampo->getTipo() == 3 && $valor != ""){
							$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();
						}


						// $respuesta = "El hito es: ".$idHito.' el idHito: '.$idHito2.' y la repeticion la: '.$idRepeticion;
						// return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
					}
				}

				// AHORA CREAMOS LOS CAMPOS DEL RESTO DE FASES
				$fases = $doctrine->getRepository(Fase::class)->findBy(array(), array(
					'orden' => 'ASC'
				));

				foreach($fases as $fase){
					if($fase != $faseActual){
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
									
									if($campoHito->getTipo() == 4){
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
				}
				
				// CAMBIAMOS LA DIRECCION POR LA DEL CAMPO
				if($expediente->getVivienda() == "CUESTIONARIO APP" || $expediente->getVivienda() == "NUEVA VIVIENDA"){
					
					// $campoVivienda = $doctrine->getRepository(CampoHito::class)->matching(Criteria::create()
					// 	->where(Criteria::expr()->in('nombre', $ficheroCampoArray))
					// 	->andWhere(Criteria::expr()->neq('valor', null))
					// );
					$campoVivienda = $doctrine->getRepository(CampoHito::class)->findOneBy(array(
						'nombre' => 'Dirección de la propiedad'
					)
					);

					if ($campoVivienda){
						$valorCampoVivienda = $doctrine->getRepository(CampoHitoExpediente::class)->findOneBy(array(
							'idExpediente' => $expediente,
							'idCampoHito' => $campoVivienda
						)
						);
						if($valorCampoVivienda && $valorCampoVivienda->getValor() != ""){
							$expediente->setVivienda($valorCampoVivienda->getValor());
							$managerEntidad->persist($expediente);
							$managerEntidad->flush();
						}
					}
				}

				// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
				$comercialesnoti = $doctrine->getRepository(Usuario::class)->findBy(array(
						'role' => 'ROLE_COMERCIAL'
					)
				);
				foreach($comercialesnoti as $comercialnoti){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($comercialnoti)
						->setTitulo('Nuevo expediente creado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}
				
				$tecnicosnoti = $doctrine->getRepository(Usuario::class)->findBy(array(
						'role' => 'ROLE_TECNICO'
					)
				);
				foreach($tecnicosnoti as $tecniconoti){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($tecniconoti)
						->setTitulo('Nuevo expediente creado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}

				$admin = $doctrine->getRepository(Usuario::class)->findOneBy(array(
						'role' => 'ROLE_ADMIN'
					)
				);
				$notificacion = (new Notificacion)
					->setIdExpediente($expediente)
					->setEstado(1)
					->setFecha(new DateTime())
					->setIdUsuario($admin)
					->setTitulo('Nuevo expediente creado')
					->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
				$managerEntidad->persist($notificacion);
				// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
				$managerEntidad->flush();
				
				$respuesta['errorlevel']= 0;

				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}else{
				$respuesta['errorlevel']= 1;
				$error['propiedad'] = 1;
				$error['mensaje'] = 'Se ha producido un error';
				$respuesta['errores'] = $error;
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}
		}
		throw new HttpException(400);
	}

	public function crearCuestionarioClienteAction(Request $request, LoggerInterface $logger)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$parametros = json_decode($request->getContent(), true);
			$valoresCampos = $parametros['cuestionario'];
			$esColaborador =  $parametros['idExpediente'];
			// return new JsonResponse($valoresCampos, JSON_UNESCAPED_UNICODE);

			if($esColaborador == -1){
				$esColaborador = true;
			}else{
				$esColaborador = false;
			}
			// return new JsonResponse(dump($valoresCampos), JSON_UNESCAPED_UNICODE);
			
			$respuesta = array();
			if (json_last_error() === 0) {

				$repeticionesHitos = array(array());
				$repeticionesGrupos = array();
				$repeticionesCampos = array();

				$creacionesGrupos = array(array());
				
				
				$doctrine = $this->getDoctrine();
				$usuario = $this->getUser();
				$managerEntidad = $doctrine->getManager();
				// $this->getUser()->getIdUsuario()
				$repositorios = array(
					'CampoHito' => $doctrine->getRepository('AppBundle:CampoHito'),
					'Fases' => $doctrine->getRepository('AppBundle:Fase'),
					'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
					'HitosExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
					'GruposHitoExpediente' => $doctrine->getRepository('AppBundle:GrupoHitoExpediente'),
					'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
					'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo')
				);

				$faseActual = $repositorios['Fases']->findOneBy(array(
					'tipo' => 0
				));

				$expediente = (new Expediente())
					->setIdFaseActual($faseActual)
					->setEstado(1)
					->setFechaCreacion(new DateTime())
					->setVivienda('CUESTIONARIO APP')
				;

				if($esColaborador){
					$expediente->setIdColaborador($usuario);
				}else{
					$expediente->setIdCliente($usuario);
				}
				
				// Ahora creamos el nuevo expediente para el cuestionario
				$managerEntidad->persist($expediente);
				$managerEntidad->flush();


				foreach($valoresCampos as $valorCampo){
					$idCampoHito = $valorCampo['idCampoHito']['idCampoHito'];
					$tipoCampo = $valorCampo['idCampoHito']['tipo'];
					$idGrupoCamposHito = $valorCampo['idCampoHito']['idGrupoCamposHito']['idGrupoCamposHito'];
					$idHito = $valorCampo['idCampoHito']['idGrupoCamposHito']['idHito']['idHito'];
					$valor = $valorCampo['valor'];

					if(strpos($idHito,'[')===false){ // No es un hito repetido
						$hito = $repositorios['Hitos']->findOneBy(array(
							'idHito' => $idHito
						));
						$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
							'idHito' => $hito,
							'idExpediente' => $expediente
						));
						if(!$hitoExpediente){// No existe el hito expediente
							$hitoExpediente = (new HitoExpediente())
								->setIdHito($hito)
								->setIdExpediente($expediente)
								->setEstado(1)
								->setFechaModificacion(new DateTime())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($hitoExpediente);
							$managerEntidad->flush();
						}
						$idCampo = $repositorios['CampoHito']->findOneBy(array(
							'idCampoHito' => $idCampoHito
						));

						if(array_key_exists($idHito, $creacionesGrupos) && array_key_exists($idGrupoCamposHito,$creacionesGrupos[$idHito])){// Ya existe el hito duplicado
							$grupoHitoExpediente = $repositorios['GruposHitoExpediente']->findOneBy(array(
								'idGrupoHitoExpediente' => $creacionesGrupos[$idHito][$idGrupoCamposHito]
							));
						}else{// No existe el grupo hito expediente
							$grupoHitoExpediente = (new GrupoHitoExpediente())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoCamposHito($idCampo->getIdGrupoCamposHito())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($grupoHitoExpediente);
							$managerEntidad->flush();
							$creacionesGrupos[$idHito][$idGrupoCamposHito] = $grupoHitoExpediente;
						}
						

						$campoHitoExpediente = (new CampoHitoExpediente())
							->setIdCampoHito($idCampo)
							->setIdExpediente($expediente)
							->setIdHitoExpediente($hitoExpediente)
							->setIdGrupoHitoExpediente($grupoHitoExpediente)
							->setFechaModificacion(new DateTime())
						;

						if($idCampo->getTipo() == 4){
							$campoHitoExpediente->setObligatorio(1)
								->setSolicitarAlColaborador(1);
						}
						
						if($idCampo->getTipo() != 3){// No es un campo de opciones
							if($idCampo->getTipo() == 6){// Es tipo fecha
								if($valor != ''){
									$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
								}
							}else{
								$campoHitoExpediente->setValor($valor);
							}
						}else{// Es un campo de opciones
							$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
							if(strpos($idOpcion,'[')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'['));
							}
							if(strpos($idOpcion,'_')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'_'));
							}

							if(strpos($valor,'[')>-1){
								$valor = substr($valor,0,strpos($valor,'['));
							}
							$pos1 = strpos($valor,'_');
							if($pos1 > -1){
								$pos2 = strpos($valor, '_', $pos1 + 1);
								if($pos2 > -1){
									$pos3 = strpos($valor, '_', $pos2 + 1);
									if($pos3 > -1){
										$pos4 = strpos($valor, '_', $pos3 + 1);
										if($pos4 > -1){
											$valor = substr($valor,0,$pos4);
										}
									}
								}
							}


							$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
								'idOpcionesCampo' => $idOpcion
							));
							$campoHitoExpediente->setValor($valor);
							$campoHitoExpediente->setIdOpcionesCampo($opcion);
						}
						
						// Ahora creamos el nuevo hito expediente para el cuestionario
						$managerEntidad->persist($campoHitoExpediente);
						$managerEntidad->flush();
						if($idCampo->getTipo() == 3 && $valor != ""){
							$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();
						}

					}else{// Es un hito repetido
						$idHito2 = substr($idHito,0,strpos($idHito,'['));
						$idRepeticion = substr($idHito,strpos($idHito,'[')+1,-1);
						$hito = $repositorios['Hitos']->findOneBy(array(
							'idHito' => $idHito2
						));

						if(array_key_exists($idHito2, $repeticionesHitos) && array_key_exists($idRepeticion,$repeticionesHitos[$idHito2])){// Ya existe el hito duplicado
							$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
								'idHitoExpediente' => $repeticionesHitos[$idHito2][$idRepeticion]
							));
						}else{// No existe el hito expediente
							$hitoExpediente = (new HitoExpediente())
								->setIdHito($hito)
								->setIdExpediente($expediente)
								->setEstado(0)
								->setFechaModificacion(new DateTime())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($hitoExpediente);
							$managerEntidad->flush();
							$repeticionesHitos[$idHito2][$idRepeticion] = $hitoExpediente;
						}

						$idCampo = $repositorios['CampoHito']->findOneBy(array(
							'idCampoHito' => $idCampoHito
						));

						if(array_key_exists($idHito, $creacionesGrupos) && array_key_exists($idGrupoCamposHito,$creacionesGrupos[$idHito])){// Ya existe el hito duplicado
							$grupoHitoExpediente = $repositorios['GruposHitoExpediente']->findOneBy(array(
								'idGrupoHitoExpediente' => $creacionesGrupos[$idHito][$idGrupoCamposHito]
							));
						}else{// No existe el grupo hito expediente
							$grupoHitoExpediente = (new GrupoHitoExpediente())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoCamposHito($idCampo->getIdGrupoCamposHito())
							;
							
							// Ahora creamos el nuevo hito expediente para el cuestionario
							$managerEntidad->persist($grupoHitoExpediente);
							$managerEntidad->flush();
							$creacionesGrupos[$idHito][$idGrupoCamposHito] = $grupoHitoExpediente;
						}

						$campoHitoExpediente = (new CampoHitoExpediente())
							->setIdCampoHito($idCampo)
							->setIdExpediente($expediente)
							->setIdHitoExpediente($hitoExpediente)
							->setIdGrupoHitoExpediente($grupoHitoExpediente)
							->setFechaModificacion(new DateTime())
						;
						
						if($idCampo->getTipo() != 3){// No es un campo de opciones
							if($idCampo->getTipo() == 6){// Es tipo fecha
								if($valor != ''){
									$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
								}
							}else{
								$campoHitoExpediente->setValor($valor);
							}
						}else{// Es un campo de opciones
							$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
							if(strpos($idOpcion,'[')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'['));
							}
							if(strpos($idOpcion,'_')>-1){
								$idOpcion = substr($idOpcion,0,strpos($idOpcion,'_'));
							}

							if(strpos($valor,'[')>-1){
								$valor = substr($valor,0,strpos($valor,'['));
							}
							
							$pos1 = strpos($valor,'_');
							if($pos1 > -1){
								$pos2 = strpos($valor, '_', $pos1 + 1);
								if($pos2 > -1){
									$pos3 = strpos($valor, '_', $pos2 + 1);
									if($pos3 > -1){
										$pos4 = strpos($valor, '_', $pos3 + 1);
										if($pos4 > -1){
											$valor = substr($valor,0,$pos4);
										}
									}
								}
							}
							

							$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
								'idOpcionesCampo' => $idOpcion
							));
							$campoHitoExpediente->setValor($valor);
							$campoHitoExpediente->setIdOpcionesCampo($opcion);
						}
						
						// Ahora creamos el nuevo hito expediente para el cuestionario
						$managerEntidad->persist($campoHitoExpediente);
						$managerEntidad->flush();
						if($idCampo->getTipo() == 3 && $valor != ""){
							$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();
						}


						// $respuesta = "El hito es: ".$idHito.' el idHito: '.$idHito2.' y la repeticion la: '.$idRepeticion;
						// return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
					}
				}

				// AHORA CREAMOS LOS CAMPOS DEL RESTO DE FASES
				$fases = $doctrine->getRepository(Fase::class)->findBy(array(), array(
					'orden' => 'ASC'
				));

				foreach($fases as $fase){
					if($fase != $faseActual){
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
									
									if($campoHito->getTipo() == 4){
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
				}

				// CAMBIAMOS LA DIRECCION POR LA DEL CAMPO
				if($expediente->getVivienda() == "CUESTIONARIO APP" || $expediente->getVivienda() == "NUEVA VIVIENDA"){
					
					// $campoVivienda = $doctrine->getRepository(CampoHito::class)->matching(Criteria::create()
					// 	->where(Criteria::expr()->in('nombre', $ficheroCampoArray))
					// 	->andWhere(Criteria::expr()->neq('valor', null))
					// );
					$campoVivienda = $doctrine->getRepository(CampoHito::class)->findOneBy(array(
						'nombre' => 'Dirección de la propiedad'
					)
					);

					if ($campoVivienda){
						$valorCampoVivienda = $doctrine->getRepository(CampoHitoExpediente::class)->findOneBy(array(
							'idExpediente' => $expediente,
							'idCampoHito' => $campoVivienda
						)
						);
						if($valorCampoVivienda && $valorCampoVivienda->getValor() != ""){
							$expediente->setVivienda($valorCampoVivienda->getValor());
							$managerEntidad->persist($expediente);
							$managerEntidad->flush();
						}
					}
				}

				// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
				$comercialesnoti = $doctrine->getRepository(Usuario::class)->findBy(array(
						'role' => 'ROLE_COMERCIAL'
					)
				);
				foreach($comercialesnoti as $comercialnoti){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($comercialnoti)
						->setTitulo('Nuevo expediente creado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}
				
				$tecnicosnoti = $doctrine->getRepository(Usuario::class)->findBy(array(
						'role' => 'ROLE_TECNICO'
					)
				);
				foreach($tecnicosnoti as $tecniconoti){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($tecniconoti)
						->setTitulo('Nuevo expediente creado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}

				$admins = $doctrine->getRepository(Usuario::class)->findBy(array(
						'role' => 'ROLE_ADMIN'
					)
				);
				foreach($admins as $admin){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($admin)
						->setTitulo('Nuevo expediente creado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
				}
				// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
				$managerEntidad->flush();

				$respuesta['errorlevel']= 0;

				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}else{
				$respuesta['errorlevel']= 1;
				$error['propiedad'] = 1;
				$error['mensaje'] = 'Se ha producido un error';
				$respuesta['errores'] = $error;
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}
		}
		throw new HttpException(400);
	}

	public function actualizarExpedienteClienteAction(Request $request, LoggerInterface $logger)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$valoresCampos = json_decode($request->getContent(), true);
			// return new JsonResponse(dump($valoresCampos), JSON_UNESCAPED_UNICODE);
			
			$respuesta = array();
			if (json_last_error() === 0) {

				$repeticionesHitos = array(array());
				$repeticionesGrupos = array(array(array()));
				$repeticionesCampos = array();
				
				
				$doctrine = $this->getDoctrine();
				$usuario = $this->getUser();
				$managerEntidad = $doctrine->getManager();
				// $this->getUser()->getIdUsuario()
				$repositorios = array(
					'CampoHito' => $doctrine->getRepository('AppBundle:CampoHito'),
					'CampoHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
					'Fases' => $doctrine->getRepository('AppBundle:Fase'),
					'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
					'HitosExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
					'GrupoHitoExpediente' => $doctrine->getRepository('AppBundle:GrupoHitoExpediente'),
					'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
					'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo'),
					'Usuarios' => $doctrine->getRepository('AppBundle:Usuario')
				);

				$gruposHitosExpedienteGrabados = array(array());
				$hitosExpedienteGrabados = array(array());
				$idCampoValor = 0;
				foreach($valoresCampos as $valorCampo1){
					foreach($valorCampo1 as $valorCampo){
						$idCampoValor += 1;
						$idCampoHitoExpediente = $valorCampo['idCampoHitoExpediente'];
						$idCampoHito = $valorCampo['idCampoHito']['idCampoHito'];
						$idHitoExpediente = $valorCampo['idHitoExpediente']['idHitoExpediente'];
						if(isset($valorCampo['idGrupoHitoExpediente']) && isset($valorCampo['idGrupoHitoExpediente']['idGrupoHitoExpediente'])){
							$idGrupoHitoExpediente = $valorCampo['idGrupoHitoExpediente']['idGrupoHitoExpediente'];
						}else{
							$idGrupoHitoExpediente = null;
						}
						
						$tipoCampo = $valorCampo['idCampoHito']['tipo'];				
						$valor = $valorCampo['valor'];

						if (!$idCampoHitoExpediente || strpos($idCampoHitoExpediente,'repe')){// Es un CampoHitoExpediente nuevo
							$idCampoHitoExpediente2 = substr($idCampoHitoExpediente,0,strpos($idCampoHitoExpediente,'['));
							if(strpos($idHitoExpediente,'[')>-1){
								$idHitoExpediente2 = substr($idHitoExpediente,0,strpos($idHitoExpediente,'['));
								$idRepeticion = substr($idHitoExpediente2,strpos($idHitoExpediente2,'[')+1,-1);
							}else{
								$idHitoExpediente2 = $idHitoExpediente;
								$idRepeticion = 0;
							}
							
							// if(array_key_exists($idHitoExpediente2, $repeticionesHitos) && array_key_exists($idRepeticion,$repeticionesHitos[$idHitoExpediente2])){// Ya existe el hito duplicado
							// 	$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
							// 		'idHitoExpediente' => $repeticionesHitos[$idHitoExpediente2][$idRepeticion]
							// 	));
							// }else{// No existe el hito expediente
							// 	$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
							// 		'idHitoExpediente' => $idHitoExpediente2
							// 	));

							// 	$hitoExpediente2 = (new HitoExpediente())
							// 		->setIdHito($hitoExpediente->getIdHito())
							// 		->setIdExpediente($hitoExpediente->getIdExpediente())
							// 		->setEstado(0)
							// 		->setFechaModificacion(new DateTime())
							// 	;
								
							// 	// Ahora creamos el nuevo hito expediente para el cuestionario
							// 	$managerEntidad->persist($hitoExpediente2);
							// 	$managerEntidad->flush();
							// 	$repeticionesHitos[$idHitoExpediente2][$idRepeticion] = $hitoExpediente2;
							// }

							// $campoHitoExpedienteOld = $repositorios['CampoHitoExpediente']->findOneBy(array(
							// 	'idCampoHitoExpediente' => $idCampoHitoExpediente2
							// ));

							

							$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
								'idHitoExpediente' => $idHitoExpediente2
							));

							$expediente = $hitoExpediente->getIdExpediente();

							
							$campoHito = $repositorios['CampoHito']->findOneBy(array(
								'idCampoHito' => $idCampoHito
							));
							
							if(!$idGrupoHitoExpediente || strpos($idGrupoHitoExpediente,'[')>-1){
								$grupoHitoExpediente = (new GrupoHitoExpediente())
									->setIdHitoExpediente($hitoExpediente)
									->setIdGrupoCamposHito($campoHito->getIdGrupoCamposHito())
								;

								$managerEntidad->persist($grupoHitoExpediente);
								// $managerEntidad->flush();
							}else{
								$grupoHitoExpediente = $repositorios['GrupoHitoExpediente']->findOneBy(array(
									'idGrupoHitoExpediente' => $idGrupoHitoExpediente
								));
							}


							$campoHitoExpediente = (new CampoHitoExpediente())
								->setIdCampoHito($campoHito)
								->setIdExpediente($hitoExpediente->getIdExpediente())
								->setIdHitoExpediente($hitoExpediente)
								->setIdGrupoHitoExpediente($grupoHitoExpediente)
								->setFechaModificacion(new DateTime())
							;
							
							if($tipoCampo != 3){// No es un campo de opciones
								if($tipoCampo == 6){// Es tipo fecha
									if($valor != ''){
										$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
									}
								}else{
									$campoHitoExpediente->setValor($valor);
								}
							}else{// Es un campo de opciones
								$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
								if(strpos($idOpcion, '_')>-1){
									$idOpcion = substr($idOpcion,0, strpos($idOpcion, '_'));
								}
								
								$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
									'idOpcionesCampo' => $idOpcion
								));
								
								$campoHitoExpediente->setValor('');
								if($valor != ""){
									$campoHitoExpediente->setIdOpcionesCampo($opcion);
								}
							}

							

							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();
							$gruposHitosExpedienteGrabados[$campoHitoExpediente->getIdGrupoHitoExpediente()->getIdGrupoHitoExpediente()][$hitoExpediente->getIdHitoExpediente()] = 1;
							$hitosExpedienteGrabados[$hitoExpediente->getIdHitoExpediente()] = 1;
							if($tipoCampo == 3 && $valor != ""){
								$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
								$managerEntidad->persist($campoHitoExpediente);
								$managerEntidad->flush();
							}

							



						}
						elseif(strpos($idCampoHitoExpediente,'[')===false && strpos($idCampoHitoExpediente,'repe')===false){ // No es un campoHitoExpediente repetido
							$campoHitoExpediente = $repositorios['CampoHitoExpediente']->findOneBy(array(
								'idCampoHitoExpediente' => $idCampoHitoExpediente
							));

							$campoHitoExpediente
								->setFechaModificacion(new DateTime())
							;
							
							if($tipoCampo != 3){// No es un campo de opciones
								if($tipoCampo == 6){// Es tipo fecha
									if($valor != ''){
										$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
									}
								}else{
									$campoHitoExpediente->setValor($valor);
								}
							}else{// Es un campo de opciones
								$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
								
								
								$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
									'idOpcionesCampo' => $idOpcion
								));
								$campoHitoExpediente->setValor('');
								if($valor != ""){
									$campoHitoExpediente->setIdOpcionesCampo($opcion);
									$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
								}
								
							}

							$expediente = $campoHitoExpediente->getIdHitoExpediente()->getIdExpediente();

							$gruposHitosExpedienteGrabados[$campoHitoExpediente->getIdGrupoHitoExpediente()->getIdGrupoHitoExpediente()][$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = 1;
							$hitosExpedienteGrabados[$campoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()] = 1;

							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();


							

						}else{// Es un CampoHitoExpediente repetido
							if(strpos($idCampoHitoExpediente,'[') && strpos($idCampoHitoExpediente,'repe')===false){
								$idCampoHitoExpediente2 = substr($idCampoHitoExpediente,0,strpos($idCampoHitoExpediente,'['));
								$idHitoExpediente2 = substr($idHitoExpediente,0,strpos($idHitoExpediente,'['));
								$idRepeticionGrupo = 0;
							}elseif(strpos($idCampoHitoExpediente,'[')===false && strpos($idCampoHitoExpediente,'repe')!==false){ // Es un nuevo campo de un nuevo grupo en un hitoexpediente existente
								$idCampoHitoExpediente2 = substr($idCampoHitoExpediente,0,strpos($idCampoHitoExpediente,'-'));
								$idCampoHitoExpediente2 = substr($idCampoHitoExpediente2,strpos($idCampoHitoExpediente2,'_')+1);
								$idCampoHitoExpediente2 = substr($idCampoHitoExpediente2,0,strpos($idCampoHitoExpediente2,'_'));
								$idCampoHitoRepe = $idCampoHitoExpediente2;
								$idHitoExpediente2 = $idHitoExpediente;
								$idRepeticionGrupo = substr($idCampoHitoExpediente,strpos($idCampoHitoExpediente,'-')+1);
								$idRepeticionGrupo = intval(substr($idRepeticionGrupo,0,-strlen($idRepeticionGrupo)+strpos($idRepeticionGrupo,'_')));
								$esGrupoHitoAnterior = true;
							}else{
								$idCampoHitoExpediente2 = substr($idCampoHitoExpediente,strpos($idCampoHitoExpediente,'_')+1,strlen($idCampoHitoExpediente));
								$idCampoHitoExpediente3 = substr($idCampoHitoExpediente2,0,strpos($idCampoHitoExpediente2,'_'));
								$idCampoHitoExpediente4 = substr($idCampoHitoExpediente2,strpos($idCampoHitoExpediente2,'_')+1,-strlen($idCampoHitoExpediente2)+strpos($idCampoHitoExpediente2,'-'));
								$idRepeticionGrupo = substr($idCampoHitoExpediente2,strpos($idCampoHitoExpediente2,'-')+1);
								$idRepeticionGrupo = intval(substr($idRepeticionGrupo,0,-strlen($idRepeticionGrupo)+strpos($idRepeticionGrupo,'_')));
								$idCampoHitoRepe = $idCampoHitoExpediente3;
								if(strpos($idCampoHitoExpediente4,'[')){
									$idCampoHitoExpediente4 = substr($idCampoHitoExpediente4,0,strpos($idCampoHitoExpediente4,'['));
								}
								$idHitoExpediente2 = $idCampoHitoExpediente4;
							}
							
							if(strpos($idHitoExpediente,'[')){
								$idRepeticion = substr($idHitoExpediente,strpos($idHitoExpediente,'[')+1,-1);
							}else{
								$idRepeticion = 0;
							}

							if(array_key_exists($idHitoExpediente2, $repeticionesHitos) && array_key_exists($idRepeticion,$repeticionesHitos[$idHitoExpediente2])){// Ya existe el hito duplicado
								$hitoExpediente2 = $repositorios['HitosExpediente']->findOneBy(array(
									'idHitoExpediente' => $repeticionesHitos[$idHitoExpediente2][$idRepeticion]
								));
							}elseif(isset($esGrupoHitoAnterior)){
								$hitoExpediente2 = $repositorios['HitosExpediente']->findOneBy(array(
									'idHitoExpediente' => $idHitoExpediente2
								));
								unset($esGrupoHitoAnterior);
							}else{// No existe el hito expediente
								$hitoExpediente = $repositorios['HitosExpediente']->findOneBy(array(
									'idHitoExpediente' => $idHitoExpediente2
								));

								$hitoExpediente2 = (new HitoExpediente())
									->setIdHito($hitoExpediente->getIdHito())
									->setIdExpediente($hitoExpediente->getIdExpediente())
									->setEstado(0)
									->setFechaModificacion(new DateTime())
								;
								
								// Ahora creamos el nuevo hito expediente para el cuestionario
								$managerEntidad->persist($hitoExpediente2);
								$managerEntidad->flush();
								$repeticionesHitos[$idHitoExpediente2][$idRepeticion] = $hitoExpediente2;
							}
							if(!isset($idCampoHitoRepe)){
								$campoHitoExpedienteOld = $repositorios['CampoHitoExpediente']->findOneBy(array(
									'idCampoHitoExpediente' => $idCampoHitoExpediente2
								));

								if(array_key_exists($idHitoExpediente, $repeticionesGrupos) && array_key_exists($campoHitoExpedienteOld->getIdCampoHito()->getIdGrupoCamposHito()->getIdGrupoCamposHito(),$repeticionesGrupos[$idHitoExpediente]) && array_key_exists($idRepeticionGrupo,$repeticionesGrupos[$idHitoExpediente][$campoHitoExpedienteOld->getIdCampoHito()->getIdGrupoCamposHito()->getIdGrupoCamposHito()])){// Ya existe el hito duplicado
									$grupoHitoExpediente = $repositorios['GrupoHitoExpediente']->findOneBy(array(
										'idGrupoHitoExpediente' => $repeticionesGrupos[$idHitoExpediente][$campoHitoExpedienteOld->getIdCampoHito()->getIdGrupoCamposHito()->getIdGrupoCamposHito()][$idRepeticionGrupo]
									));
								}elseif(!$idGrupoHitoExpediente || strpos($idGrupoHitoExpediente,'[')>-1){
									$grupoHitoExpediente = (new GrupoHitoExpediente())
										->setIdHitoExpediente($hitoExpediente2)
										->setIdGrupoCamposHito($campoHitoExpedienteOld->getIdCampoHito()->getIdGrupoCamposHito())
									;

									$managerEntidad->persist($grupoHitoExpediente);
									$managerEntidad->flush();
									$repeticionesGrupos[$idHitoExpediente][$campoHitoExpedienteOld->getIdCampoHito()->getIdGrupoCamposHito()->getIdGrupoCamposHito()][$idRepeticionGrupo] = $grupoHitoExpediente;
								}else{
									$grupoHitoExpediente = $repositorios['GrupoHitoExpediente']->findOneBy(array(
										'idGrupoHitoExpediente' => $idGrupoHitoExpediente
									));
								}
								


								$campoHitoExpediente = (new CampoHitoExpediente())
									->setIdCampoHito($campoHitoExpedienteOld->getIdCampoHito())
									->setIdExpediente($campoHitoExpedienteOld->getIdExpediente())
									->setIdHitoExpediente($hitoExpediente2)
									->setIdGrupoHitoExpediente($grupoHitoExpediente)
									->setFechaModificacion(new DateTime())
								;
							}else{
								$campoHitoOld = $repositorios['CampoHito']->findOneBy(array(
									'idCampoHito' => $idCampoHitoRepe
								));

								if(array_key_exists($idHitoExpediente, $repeticionesGrupos) && array_key_exists($campoHitoOld->getIdGrupoCamposHito()->getIdGrupoCamposHito(),$repeticionesGrupos[$idHitoExpediente]) && array_key_exists($idRepeticionGrupo,$repeticionesGrupos[$idHitoExpediente][$campoHitoOld->getIdGrupoCamposHito()->getIdGrupoCamposHito()])){// Ya existe el hito duplicado
									$grupoHitoExpediente = $repositorios['GrupoHitoExpediente']->findOneBy(array(
										'idGrupoHitoExpediente' => $repeticionesGrupos[$idHitoExpediente][$campoHitoOld->getIdGrupoCamposHito()->getIdGrupoCamposHito()][$idRepeticionGrupo]
									));
								}elseif(!$idGrupoHitoExpediente || strpos($idGrupoHitoExpediente,'[')>-1 || strpos($idGrupoHitoExpediente,'_')>-1){
									$grupoHitoExpediente = (new GrupoHitoExpediente())
										->setIdHitoExpediente($hitoExpediente2)
										->setIdGrupoCamposHito($campoHitoOld->getIdGrupoCamposHito())
									;

									$managerEntidad->persist($grupoHitoExpediente);
									$managerEntidad->flush();
									$repeticionesGrupos[$idHitoExpediente][$campoHitoOld->getIdGrupoCamposHito()->getIdGrupoCamposHito()][$idRepeticionGrupo] = $grupoHitoExpediente;
								}else{
									$grupoHitoExpediente = $repositorios['GrupoHitoExpediente']->findOneBy(array(
										'idGrupoHitoExpediente' => $idGrupoHitoExpediente
									));
								}
								


								$campoHitoExpediente = (new CampoHitoExpediente())
									->setIdCampoHito($campoHitoOld)
									->setIdExpediente($hitoExpediente2->getIdExpediente())
									->setIdHitoExpediente($hitoExpediente2)
									->setIdGrupoHitoExpediente($grupoHitoExpediente)
									->setFechaModificacion(new DateTime())
								;

								unset($idCampoHitoRepe);
							}

							$gruposHitosExpedienteGrabados[$grupoHitoExpediente->getIdGrupoHitoExpediente()][$hitoExpediente2->getIdHitoExpediente()] = 1;
							$hitosExpedienteGrabados[$hitoExpediente2->getIdHitoExpediente()] = 1;

							if($tipoCampo != 3){// No es un campo de opciones
								if($tipoCampo == 6){// Es tipo fecha
									if($valor != ''){
										$campoHitoExpediente->setValor(date('d/m/Y', strtotime($valor)));
									}
								}else{
									$campoHitoExpediente->setValor($valor);
								}
							}else{// Es un campo de opciones
								$idOpcion = substr($valor,strpos($valor,'_opcion_')+8);
								if(strpos($idOpcion,'_')){
									$idOpcion = substr($idOpcion,0,strpos($idOpcion,'_'));
								}
								$opcion = $repositorios['OpcionesCampo']->findOneBy(array(
									'idOpcionesCampo' => $idOpcion
								));
								
								$campoHitoExpediente->setValor('');
								if($valor != ""){
									$campoHitoExpediente->setIdOpcionesCampo($opcion);
								}
							}

							$expediente = $campoHitoExpediente->getIdHitoExpediente()->getIdExpediente();

							$managerEntidad->persist($campoHitoExpediente);
							$managerEntidad->flush();

							if($tipoCampo == 3 && $valor != ""){
								$campoHitoExpediente->setValor('campo_hito_'.$campoHitoExpediente->getIdCampoHitoExpediente().'_opcion_'.$idOpcion);
								$managerEntidad->persist($campoHitoExpediente);
								$managerEntidad->flush();
							}


						}
					}
				}

				$fase = $repositorios['Fases']->findOneBy(array(
					'tipo' => 0
				));

				$hitos = $repositorios['Hitos']->findBy(array(
					'idFase' => $fase
				));

				$hitosExpediente = $repositorios['HitosExpediente']->findBy(array(
					'idExpediente' => $expediente,
					'idHito' => $hitos
				));
				$gruposHitosExpediente = $repositorios['GrupoHitoExpediente']->findBy(array(
					'idHitoExpediente' => $hitosExpediente
				));


				foreach($gruposHitosExpediente as $grupoHitoExpediente){
					$existe = false;
					if(isset($gruposHitosExpedienteGrabados[$grupoHitoExpediente->getIdGrupoHitoExpediente()])){
						if(isset($gruposHitosExpedienteGrabados[$grupoHitoExpediente->getIdGrupoHitoExpediente()][$grupoHitoExpediente->getIdHitoExpediente()->getIdHitoExpediente()])){
							$existe = true;
						}
					}
					if(!$existe){
						$camposHitoExpediente =$repositorios['CampoHitoExpediente']->findBy(array(
							'idGrupoHitoExpediente' => $grupoHitoExpediente
						));
						
						foreach ($camposHitoExpediente as $campoHitoExpediente) {
							$managerEntidad->remove($campoHitoExpediente);
						}
						$managerEntidad->remove($grupoHitoExpediente);
					}
				}

				foreach($hitosExpediente as $hitoExpediente){
					$existe = false;
					if(isset($hitosExpedienteGrabados[$hitoExpediente->getIdHitoExpediente()])){
						$existe = true;
					}
					if(!$existe){
						$gruposHitosExpediente = $repositorios['GrupoHitoExpediente']->findBy(array(
							'idHitoExpediente' => $hitoExpediente
						));

						$camposHitoExpediente =$repositorios['CampoHitoExpediente']->findBy(array(
							'idGrupoHitoExpediente' => $gruposHitosExpediente
						));
						
						foreach ($camposHitoExpediente as $campoHitoExpediente) {
							$managerEntidad->remove($campoHitoExpediente);
						}
						foreach ($gruposHitosExpediente as $grupoHitoExpediente) {
							$managerEntidad->remove($grupoHitoExpediente);
						}

						$managerEntidad->remove($hitoExpediente);
					}
				}

				// CAMBIAMOS LA DIRECCION POR LA DEL CAMPO
				if($expediente->getVivienda() == "CUESTIONARIO APP" || $expediente->getVivienda() == "NUEVA VIVIENDA"){
					
					// $campoVivienda = $doctrine->getRepository(CampoHito::class)->matching(Criteria::create()
					// 	->where(Criteria::expr()->in('nombre', $ficheroCampoArray))
					// 	->andWhere(Criteria::expr()->neq('valor', null))
					// );
					$campoVivienda = $doctrine->getRepository(CampoHito::class)->findOneBy(array(
						'nombre' => 'Dirección de la propiedad'
					)
					);

					if ($campoVivienda){
						$valorCampoVivienda = $doctrine->getRepository(CampoHitoExpediente::class)->findOneBy(array(
							'idExpediente' => $expediente,
							'idCampoHito' => $campoVivienda
						)
						);
						if($valorCampoVivienda && $valorCampoVivienda->getValor() != ""){
							$expediente->setVivienda($valorCampoVivienda->getValor());
							$managerEntidad->persist($expediente);
						}
					}
				}

				$managerEntidad->flush();

				// Enviar notificación NO PUSH al admin y al comercial  y al tecnico
				if($expediente->getIdComercial()){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($expediente->getIdComercial())
						->setTitulo('Expediente Modificado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}
				
				if($expediente->getIdTecnico()){
					$notificacion = (new Notificacion)
						->setIdExpediente($expediente)
						->setEstado(1)
						->setFecha(new DateTime())
						->setIdUsuario($expediente->getIdTecnico())
						->setTitulo('Expediente Modificado')
						->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
					$managerEntidad->persist($notificacion);
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}

				if($expediente->getIdComercial() == null && $expediente->getIdTecnico() == null){
					//Aviso de que se ha creado una cuenta
					$roles = array("ROLE_ADMIN", "ROLE_TECNICO", "ROLE_COMERCIAL");
					$usuarios_gn = $doctrine->getRepository(Usuario::class)->findBy(array(
							'role' => $roles
						)
					);
					foreach($usuarios_gn as $usuario_gn){
						$notificacion = (new Notificacion)
							->setIdExpediente($expediente)
							->setEstado(1)
							->setFecha(new DateTime())
							->setIdUsuario($usuario_gn)
							->setTitulo('Expediente Modificado')
							->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
						$managerEntidad->persist($notificacion);
						$managerEntidad->flush();
					}
				}else{
					$admins = $doctrine->getRepository(Usuario::class)->findBy(array(
							'role' => 'ROLE_ADMIN'
						)
					);
					foreach($admins as $admin){
						$notificacion = (new Notificacion)
							->setIdExpediente($expediente)
							->setEstado(1)
							->setFecha(new DateTime())
							->setIdUsuario($admin)
							->setTitulo('Expediente Modificado')
							->setTexto('El expediente ' . $expediente->getVivienda() . ' se ha actualizado.');
						$managerEntidad->persist($notificacion);
					}
					// $this->get('event_dispatcher')->dispatch('log.registrarActividadConEntidad', new RegistrarActividad($colaborador, 'Se ha enviado una notificacion a ' . (new UsuariosNombreCompleto())->obtener($notificacion->getIdUsuario()), $expediente));
					$managerEntidad->flush();
				}

				$respuesta['errorlevel']= 0;

				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}else{
				$respuesta['errorlevel']= 1;
				$error['propiedad'] = 1;
				$error['mensaje'] = 'Se ha producido un error';
				$respuesta['errores'] = $error;
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}
		}
		throw new HttpException(400);
	}


	public function borrarDocumentoAction($idCampoHitoExpediente)
	{
		$doctrine = $this->getDoctrine();
		
		$repositorios = array(
			'CampoHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo'),
			'ImagenesFichero' => $doctrine->getRepository('AppBundle:ImagenFichero'),
		);
		

		$camposHitoExpediente = $repositorios['CampoHitoExpediente']->findOneBy(array(
			'idCampoHitoExpediente' => $idCampoHitoExpediente
		));

		$fichero = $repositorios['FicheroCampo']->findOneBy(array(
			'idCampoHitoExpediente' => $camposHitoExpediente
		));

		if ($fichero) {
			$managerEntidad = $doctrine->getManager();
			$imagenes_fichero = $repositorios['ImagenesFichero']->findBy(array(
				'idFicheroCampo' => $fichero
			));
			foreach ($imagenes_fichero as $imagen ) {
				$managerEntidad->remove($imagen);
				$managerEntidad->flush();
			}
	
			
			$managerEntidad->remove($fichero);
			$managerEntidad->flush();
		}


		// return new Response('Documento eliminado');
		// $doctrine = $this->getDoctrine();
		$repositorios = array(
			'Expedientes' => $doctrine->getRepository('AppBundle:Expediente'),
			'FicheroCampo' => $doctrine->getRepository('AppBundle:FicheroCampo'),
			'Fases' => $doctrine->getRepository('AppBundle:Fase'),
			'Hitos' => $doctrine->getRepository('AppBundle:Hito'),
			'HitoExpediente' => $doctrine->getRepository('AppBundle:HitoExpediente'),
			'GruposCampos' => $doctrine->getRepository('AppBundle:GrupoCamposHito'),
			'CamposHito' => $doctrine->getRepository('AppBundle:CampoHito'),
			'CamposHitoExpediente' => $doctrine->getRepository('AppBundle:CampoHitoExpediente'),
			'OpcionesCampo' => $doctrine->getRepository('AppBundle:OpcionesCampo'),
		);

		$expediente = $repositorios['Expedientes']->findOneBy(array(
			'idCliente' => $this->getUser()->getIdUsuario(),
			'estado' => '1'
		), array(
				'idExpediente' => 'DESC'
			)
		);
		
		$fases = $repositorios['Fases']->findBy(array(
			'tipo' => 1),
			array(
			'orden' => 'ASC'
		));
		$hitosSinFiltro = $repositorios['Hitos']->findBy(array(
			'idFase' => $fases
		));

		$hitosTodos = array();
		foreach ($hitosSinFiltro as $hito){
			if($hito->getHitoCondicional() == 0){
				$hitosTodos[] = $hito;
			}else{
				$opcionCond = $repositorios['OpcionesCampo']->findBy(array(
					'idHitoCondicional' => $hito
				));
				$campoCond =  $repositorios['CamposHitoExpediente']->findBy(array(
					'idOpcionesCampo' => $opcionCond,
					'idExpediente' => $expediente->getIdExpediente(),
				));
				if($campoCond != null and count($campoCond)>0){
					$hitosTodos[] = $hito;
				}
			}
		}
		
		$fases_grupo = array();
		if ($expediente) {
			foreach ($fases as $fase) {
				$hitos = $repositorios['HitoExpediente']->findBy(array(
					'idExpediente' => $expediente,
					'idHito' => $hitosTodos
				), array(
					'idHito' =>'ASC'
				));

				$hitos_grupo = array();
				$ficheros_grupo = array();
				foreach ($hitos as $hito) {
					$gruposCampos = $repositorios['GruposCampos']->findBy(array(
						'idHito' => $hito->getIdHito()
					), array(
						'orden' => 'ASC'
					));
					$grupos_campos_grupo = array();
					foreach ($gruposCampos as $grupoCampos) {
						$camposHito = $repositorios['CamposHito']->findBy(array(
							'idGrupoCamposHito' => $grupoCampos,
							'tipo' => '4'
						), array(
							'orden' => 'ASC'
						));
						$ficheros_grupo = array();
						foreach ($camposHito as $campoHito) {
							$camposHitoExpediente = $repositorios['CamposHitoExpediente']->findBy(array(
								'idCampoHito' => $campoHito->getIdCampoHito(),
								'idExpediente' => $expediente->getIdExpediente(),
								'idHitoExpediente' => $hito,
								'obligatorio' => true,
								'paraFirmar' => false
							));
							foreach ($camposHitoExpediente as $campoHitoExpediente) {
								$ficheroCampo = $repositorios['FicheroCampo']->findOneBy(array(
									'idCampoHito' => $campoHitoExpediente->getIdCampoHito(),
									'idCampoHitoExpediente' => $campoHitoExpediente,
									'idExpediente' => $campoHitoExpediente->getIdExpediente()
								));
								$fichero = array(
									'idCampoHito' => $campoHito->getIdCampoHito(),
									'idExpediente' => $expediente->getIdExpediente(),
									'idCampoHitoExpediente' => $campoHitoExpediente->getIdCampoHitoExpediente(),
									'nombre' => $campoHito->getNombre()
								);
								if ($ficheroCampo) {
									$fichero['nombreFichero'] = $ficheroCampo->getNombreFichero();
								} else {
									$fichero['nombreFichero'] = '';
								}
								$ficheros_grupo[] = $fichero;
							}
						}
						if (count($ficheros_grupo)) {
							$grupo_campos_hito = array(
								'grupoCampos' => $grupoCampos->getNombre(),
								'ficheros' => $ficheros_grupo
							);
							$grupos_campos_grupo[] = $grupo_campos_hito;
						}
					}
					if (count($ficheros_grupo)) {
						$hito_grupo = array(
							'hito' => $hito->getIdHito()->getNombre(),
							'idHito' => $hito->getIdHito()->getIdHito(),
							'idHitoExpediente' => $hito->getIdHitoExpediente(),
							'repetible' => $hito->getIdHito()->getRepetible(),
							'ficheros' => $ficheros_grupo
						);
						$hitos_grupo[] = $hito_grupo;
					}
				}
				if (count($hitos_grupo)) {
					$fase_grupo = array(
						'fase' => $fase->getNombre(),
						'hitos' => $hitos_grupo
					);
					$fases_grupo[] = $fase_grupo;
				}
			}
		}
		return new JsonResponse($fases_grupo, JSON_UNESCAPED_UNICODE);
	}


	public function registrarDispositivoAction(Request $request)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$jsonRecibido = json_decode($request->getContent(), true);
			$respuesta = array();
			if (json_last_error() === 0) {
				$array_keys = array(
					'uuid'
				);
				$jsonRecibidoValido = true;
				foreach ($array_keys as $array_key) {
					if (!array_key_exists($array_key, $jsonRecibido)) {
						$jsonRecibidoValido = false;
						break;
					}
				}
				if ($jsonRecibidoValido) {
					$doctrine = $this->getDoctrine();
					$repositorios = array(
						'Usuarios' => $doctrine->getRepository('AppBundle:Usuario'),
						'Dispositivos' => $doctrine->getRepository('AppBundle:Dispositivo')
					);
					$usuario = $doctrine->getRepository(Usuario::class)->findOneBy(array(
						'idUsuario' => $this->getUser()->getIdUsuario()
					));

					$dispositivo = $repositorios['Dispositivos']->findOneBy(array(
						'idUsuario' =>$usuario,
						'identificador' => $jsonRecibido['uuid']
					));

					if(!$dispositivo){
						$dispositivo = (new Dispositivo())
						->setIdUsuario($usuario)
						->setIdentificador($jsonRecibido['uuid'])
						->setTipo('app')
						->setFechaRegistro(new DateTime())
						->setFechaAcceso(new DateTime());
					}else{
						$dispositivo->setFechaAcceso(new DateTime());
					}
					
					$managerEntidad = $doctrine->getManager();
					$managerEntidad->persist($dispositivo);
					$managerEntidad->flush();
					$respuesta = 'OK';
					
				} else {
					$respuesta = 'Error en información recibida';
				}
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}
			throw new HttpException(400);
		}
	}


	public function desRegistrarDispositivoAction(Request $request)
	{
		if ($request->headers->get('Content-Type') === 'application/json') {
			$jsonRecibido = json_decode($request->getContent(), true);
			$respuesta = array();
			if (json_last_error() === 0) {
				$array_keys = array(
					'uuid'
				);
				$jsonRecibidoValido = true;
				foreach ($array_keys as $array_key) {
					if (!array_key_exists($array_key, $jsonRecibido)) {
						$jsonRecibidoValido = false;
						break;
					}
				}
				if ($jsonRecibidoValido) {
					$doctrine = $this->getDoctrine();
					$repositorios = array(
						'Usuarios' => $doctrine->getRepository('AppBundle:Usuario'),
						'Dispositivos' => $doctrine->getRepository('AppBundle:Dispositivo')
					);
					$usuario = $doctrine->getRepository(Usuario::class)->findOneBy(array(
						'idUsuario' => $this->getUser()->getIdUsuario()
					));

					$dispositivo = $repositorios['Dispositivos']->findOneBy(array(
						'idUsuario' => $usuario,
						'identificador' => $jsonRecibido['uuid']
					));

					if($dispositivo){
						$managerEntidad = $doctrine->getManager();
						$managerEntidad->remove($dispositivo);
						$managerEntidad->flush();
					}					
					
					$respuesta = 'OK';
					
				} else {
					$respuesta = 'Error en información recibida';
				}
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}
			throw new HttpException(400);
		}
	}

	function enviarNotificacion($expediente = null, $campoHitoExpediente, $usuario = null, $tituloNotificacion, $textoNotificacion){
		$doctrine = $this->getDoctrine();
		$managerEntidad = $doctrine->getManager();
		

		if($usuario == null){
			$usuarios = $doctrine->getRepository(Usuario::class)->findBy(array(
				'estado' => 1
			));
			
			foreach($usuarios as $usuario){
				if($usuario->getRole() == 'ROLE_ADMIN' || ($usuario->getRole() == 'ROLE_TECNICO' && $usuario == $expediente->getIdTecnico()) || ($usuario->getRole() == 'ROLE_COMERCIAL' && $usuario == $expediente->getIdComercial())){
					$notificacion = (new Notificacion())
					->setIdExpediente($expediente)
					->setFecha(new DateTime());

					$notificacion->setIdUsuario($usuario)
					->setTitulo($tituloNotificacion)
					->setTexto($textoNotificacion);

			
					$managerEntidad->persist($notificacion);
					$managerEntidad->flush();
				}
			}
		}else{
			$notificacion = (new Notificacion())
			->setIdExpediente($expediente)
			->setFecha(new DateTime());

			$notificacion->setIdUsuario($usuario)
			->setTitulo($tituloNotificacion)
			->setTexto($textoNotificacion);

	
			$managerEntidad->persist($notificacion);
			$managerEntidad->flush();
		}

		
	}

	function enviarNotificacionPush($expediente = null, $campoHitoExpediente, $usuario = null, $tituloNotificacion, $textoNotificacion){
		$doctrine = $this->getDoctrine();
		$managerEntidad = $doctrine->getManager();

		$notificacion = (new Notificacion())
			->setIdExpediente($expediente)
			->setFecha(new DateTime());

		if($usuario == null){
			$usuarios = $doctrine->getRepository(Usuario::class)->findBy(array(
				'role' => 'ROLE_ADMIN'
			), array(
				'orden' => 'ASC'
			));
		
		}else{
			
		}

		$notificacion->setIdUsuario($usuario)
			->setTitulo($tituloNotificacion)
			->setTexto($textoNotificacion);

		// Ahora enviamos la notificacion push
		$helpers = $this->get("app.helpers");
		$id_devices = array();
		$dispositivos = $doctrine->getRepository(Dispositivo::class)->findAll(array(
			'idUsuario' => $usuario
		));
		foreach ($dispositivos as $dispositivo) {
			$id_devices[] = $dispositivo->getIdentificador();
		}
		$destinatario = '';
		$id_expediente = $expediente->getIdExpediente();
		$grupo_destinatarios = $id_devices;
		if (count($id_devices) === 1) {
			$destinatario = $id_devices[0];
		}
		$managerEntidad->persist($notificacion);
		$managerEntidad->flush();
		if($destinatario != "" || count($grupo_destinatarios)>0){
			$helpers->sendFCM($textoNotificacion, $tituloNotificacion, $destinatario, $grupo_destinatarios, $notificacion->getIdNotificacion());
		}
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

	

	public function actualizarBelenderHitoAction(Request $request)
    {
        if ($request->headers->get('Content-Type') === 'application/json') {
            $jsonRecibido = json_decode($request->getContent(), true);
            $respuesta = array();
            
            if (json_last_error() === 0) {
                $array_keys = array(
                    'request_id',
                    'status_code'
                );
                $jsonRecibidoValido = true;
                
                foreach ($array_keys as $array_key) {
                    if (!array_key_exists($array_key, $jsonRecibido)) {
                        $jsonRecibidoValido = false;
                        break;
                    }
                }
                
                if ($jsonRecibidoValido) {
                    $doctrine = $this->getDoctrine();
                    $managerEntidad = $doctrine->getManager();
                    $repositorios = array(
                        'BelenderHitoExpediente' => $doctrine->getRepository('AppBundle:BelenderHitoExpediente')
                    );
                    
                    $requestId = $jsonRecibido['request_id'];
                    $statusCode = $jsonRecibido['status_code'];
                    $fechaStatus = isset($jsonRecibido['fecha_status']) ? $jsonRecibido['fecha_status'] : date('Y-m-d H:i:s');
                    
                    try {
                        // Buscar por requestId_belender (que es el campo request_id en la tabla)
                        $belenderHito = $repositorios['BelenderHitoExpediente']->findOneBy(array(
                            'requestId_belender' => $requestId
                        ));
                        
                        if ($belenderHito) {
                            $belenderHito->setStatus_code($statusCode)
                                ->setFecha_status(new DateTime($fechaStatus));
                            
                            $managerEntidad->persist($belenderHito);
                            $managerEntidad->flush();
                            
                            $respuesta['errorlevel'] = 0;
                            $respuesta['mensaje'] = 'Belender hito actualizado correctamente';
                            $respuesta['request_id'] = $requestId;
                            $respuesta['status_code'] = $statusCode;
                        } else {
                            $respuesta['errorlevel'] = 1;
                            $respuesta['mensaje'] = 'Registro de Belender no encontrado para requestId: ' . $requestId;
                            $respuesta['request_id'] = $requestId;
                        }
                    } catch (Exception $e) {
                        $respuesta['errorlevel'] = 2;
                        $respuesta['mensaje'] = 'Error actualizando Belender: ' . $e->getMessage();
                    }
                } else {
                    $respuesta['errorlevel'] = 3;
                    $respuesta['mensaje'] = 'Faltan campos requeridos (request_id, status_code)';
                }
                
                return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
            }
            throw new HttpException(400);
        }
    }

}
