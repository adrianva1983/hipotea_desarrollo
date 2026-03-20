<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SimuladorViabilidadRepository")
 * @ORM\Table(name="simulador_viabilidad")
 */
class SimuladorViabilidad
{
    // Estados
    const ESTADO_BORRADOR = 'borrador';
    const ESTADO_EN_PROGRESO = 'en_progreso';
    const ESTADO_COMPLETADO = 'completado';
    const ESTADO_ENVIADO_A_HIPOTEA = 'enviado_a_hipotea';
    const ESTADO_CANCELADO = 'cancelado';

    // Pasos
    const PASO_INICIO = 0;
    const PASO_1 = 1;
    const PASO_2 = 2;
    const PASO_3 = 3;
    const PASO_4 = 4;
    const PASO_RESULTADO = 5;

    // Semáforo
    const SEMAFORO_VERDE = 'verde';
    const SEMAFORO_AMARILLO = 'amarillo';
    const SEMAFORO_ROJO = 'rojo';

    // Situaciones laborales
    const SITUACION_FUNCIONARIO = 'funcionario';
    const SITUACION_CONTRATO_INDEFINIDO = 'contrato_indefinido';
    const SITUACION_CONTRATO_TEMPORAL = 'contrato_temporal';
    const SITUACION_AUTONOMO = 'autonomo';
    const SITUACION_EMPRESARIO = 'empresario';
    const SITUACION_OTROS = 'otros';

    // Antigüedad laboral
    const ANTIGUEDAD_MENOS_1_ANIO = 'menos_1_anio';
    const ANTIGUEDAD_UN_ANIO = 'un_anio';
    const ANTIGUEDAD_MAS_2_ANIOS = 'mas_2_anios';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=36, unique=true)
     */
    private $uuid;

    /**
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="usuario_id", referencedColumnName="id", nullable=true)
     */
    private $usuario;

    /**
     * @ORM\ManyToOne(targetEntity="Inmobiliaria")
     * @ORM\JoinColumn(name="inmobiliaria_id", referencedColumnName="id", nullable=true)
     */
    private $inmobiliaria;

    /**
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="asesor_id", referencedColumnName="id_usuario", nullable=true)
     */
    private $asesor;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $estado;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pasoActual;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $avisoAceptado;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $tipoOperacion;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $operacionPermitida;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nombre;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $dni;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $telefono;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $datosPrecioMaximoJson;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=2, nullable=true)
     */
    private $precioMaximoRecomendado;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=2, nullable=true)
     */
    private $precioViviendaObjetivo;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $viviendaDentroPrecioMaximo;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $mensajeCoherencia;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $datosCuotaGastosJson;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $tipoVivienda;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $comunidadAutonoma;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=2, nullable=true)
     */
    private $gastosTotalesAproximados;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=2, nullable=true)
     */
    private $aportacionNecesaria;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=2, nullable=true)
     */
    private $importePrestamo;

    /**
     * @ORM\Column(type="decimal", precision=12, scale=2, nullable=true)
     */
    private $cuotaHipotecariaEstimada;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2, nullable=true)
     */
    private $porcentajeFinanciacion;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $tienePrestamosImpagados;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $situacionLaboral;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $antiguedadLaboral;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $resultadoSemaforo;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $resultadoMensaje;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $motivosResultadoJson;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $sugerenciasResultadoJson;

    /**
     * @ORM\Column(type="string", length=500, nullable=true)
     */
    private $informePdfPath;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $informeHash;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $leadId;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $expedienteId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fechaCreacion;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fechaActualizacion;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $fechaFinalizacion;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $fechaEnvioHipotea;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
        $this->fechaActualizacion = new \DateTime();
    }

    // Getters and Setters

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getUsuario()
    {
        return $this->usuario;
    }

    public function setUsuario($usuario)
    {
        $this->usuario = $usuario;
        return $this;
    }

    public function getInmobiliaria()
    {
        return $this->inmobiliaria;
    }

    public function setInmobiliaria($inmobiliaria)
    {
        $this->inmobiliaria = $inmobiliaria;
        return $this;
    }

    public function getAsesor()
    {
        return $this->asesor;
    }

    public function setAsesor($asesor)
    {
        $this->asesor = $asesor;
        return $this;
    }

    public function getEstado()
    {
        return $this->estado;
    }

    public function setEstado($estado)
    {
        $this->estado = $estado;
        return $this;
    }

    public function getPasoActual()
    {
        return $this->pasoActual;
    }

    public function setPasoActual($pasoActual)
    {
        $this->pasoActual = $pasoActual;
        return $this;
    }

    public function getAvisoAceptado()
    {
        return $this->avisoAceptado;
    }

    public function setAvisoAceptado($avisoAceptado)
    {
        $this->avisoAceptado = $avisoAceptado;
        return $this;
    }

    public function getTipoOperacion()
    {
        return $this->tipoOperacion;
    }

    public function setTipoOperacion($tipoOperacion)
    {
        $this->tipoOperacion = $tipoOperacion;
        return $this;
    }

    public function getOperacionPermitida()
    {
        return $this->operacionPermitida;
    }

    public function setOperacionPermitida($operacionPermitida)
    {
        $this->operacionPermitida = $operacionPermitida;
        return $this;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function setNombre($nombre)
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getDni()
    {
        return $this->dni;
    }

    public function setDni($dni)
    {
        $this->dni = $dni;
        return $this;
    }

    public function getTelefono()
    {
        return $this->telefono;
    }

    public function setTelefono($telefono)
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function getDatosPrecioMaximoJson()
    {
        return $this->datosPrecioMaximoJson;
    }

    public function setDatosPrecioMaximoJson($datosPrecioMaximoJson)
    {
        $this->datosPrecioMaximoJson = $datosPrecioMaximoJson;
        return $this;
    }

    public function getPrecioMaximoRecomendado()
    {
        return $this->precioMaximoRecomendado;
    }

    public function setPrecioMaximoRecomendado($precioMaximoRecomendado)
    {
        $this->precioMaximoRecomendado = $precioMaximoRecomendado;
        return $this;
    }

    public function getPrecioViviendaObjetivo()
    {
        return $this->precioViviendaObjetivo;
    }

    public function setPrecioViviendaObjetivo($precioViviendaObjetivo)
    {
        $this->precioViviendaObjetivo = $precioViviendaObjetivo;
        return $this;
    }

    public function getViviendaDentroPrecioMaximo()
    {
        return $this->viviendaDentroPrecioMaximo;
    }

    public function setViviendaDentroPrecioMaximo($viviendaDentroPrecioMaximo)
    {
        $this->viviendaDentroPrecioMaximo = $viviendaDentroPrecioMaximo;
        return $this;
    }

    public function getMensajeCoherencia()
    {
        return $this->mensajeCoherencia;
    }

    public function setMensajeCoherencia($mensajeCoherencia)
    {
        $this->mensajeCoherencia = $mensajeCoherencia;
        return $this;
    }

    public function getDatosCuotaGastosJson()
    {
        return $this->datosCuotaGastosJson;
    }

    public function setDatosCuotaGastosJson($datosCuotaGastosJson)
    {
        $this->datosCuotaGastosJson = $datosCuotaGastosJson;
        return $this;
    }

    public function getTipoVivienda()
    {
        return $this->tipoVivienda;
    }

    public function setTipoVivienda($tipoVivienda)
    {
        $this->tipoVivienda = $tipoVivienda;
        return $this;
    }

    public function getComunidadAutonoma()
    {
        return $this->comunidadAutonoma;
    }

    public function setComunidadAutonoma($comunidadAutonoma)
    {
        $this->comunidadAutonoma = $comunidadAutonoma;
        return $this;
    }

    public function getGastosTotalesAproximados()
    {
        return $this->gastosTotalesAproximados;
    }

    public function setGastosTotalesAproximados($gastosTotalesAproximados)
    {
        $this->gastosTotalesAproximados = $gastosTotalesAproximados;
        return $this;
    }

    public function getAportacionNecesaria()
    {
        return $this->aportacionNecesaria;
    }

    public function setAportacionNecesaria($aportacionNecesaria)
    {
        $this->aportacionNecesaria = $aportacionNecesaria;
        return $this;
    }

    public function getImportePrestamo()
    {
        return $this->importePrestamo;
    }

    public function setImportePrestamo($importePrestamo)
    {
        $this->importePrestamo = $importePrestamo;
        return $this;
    }

    public function getCuotaHipotecariaEstimada()
    {
        return $this->cuotaHipotecariaEstimada;
    }

    public function setCuotaHipotecariaEstimada($cuotaHipotecariaEstimada)
    {
        $this->cuotaHipotecariaEstimada = $cuotaHipotecariaEstimada;
        return $this;
    }

    public function getPorcentajeFinanciacion()
    {
        return $this->porcentajeFinanciacion;
    }

    public function setPorcentajeFinanciacion($porcentajeFinanciacion)
    {
        $this->porcentajeFinanciacion = $porcentajeFinanciacion;
        return $this;
    }

    public function getTienePrestamosImpagados()
    {
        return $this->tienePrestamosImpagados;
    }

    public function setTienePrestamosImpagados($tienePrestamosImpagados)
    {
        $this->tienePrestamosImpagados = $tienePrestamosImpagados;
        return $this;
    }

    public function getSituacionLaboral()
    {
        return $this->situacionLaboral;
    }

    public function setSituacionLaboral($situacionLaboral)
    {
        $this->situacionLaboral = $situacionLaboral;
        return $this;
    }

    public function getAntiguedadLaboral()
    {
        return $this->antiguedadLaboral;
    }

    public function setAntiguedadLaboral($antiguedadLaboral)
    {
        $this->antiguedadLaboral = $antiguedadLaboral;
        return $this;
    }

    public function getResultadoSemaforo()
    {
        return $this->resultadoSemaforo;
    }

    public function setResultadoSemaforo($resultadoSemaforo)
    {
        $this->resultadoSemaforo = $resultadoSemaforo;
        return $this;
    }

    public function getResultadoMensaje()
    {
        return $this->resultadoMensaje;
    }

    public function setResultadoMensaje($resultadoMensaje)
    {
        $this->resultadoMensaje = $resultadoMensaje;
        return $this;
    }

    public function getMotivosResultadoJson()
    {
        return $this->motivosResultadoJson;
    }

    public function setMotivosResultadoJson($motivosResultadoJson)
    {
        $this->motivosResultadoJson = $motivosResultadoJson;
        return $this;
    }

    public function getSugerenciasResultadoJson()
    {
        return $this->sugerenciasResultadoJson;
    }

    public function setSugerenciasResultadoJson($sugerenciasResultadoJson)
    {
        $this->sugerenciasResultadoJson = $sugerenciasResultadoJson;
        return $this;
    }

    public function getInformePdfPath()
    {
        return $this->informePdfPath;
    }

    public function setInformePdfPath($informePdfPath)
    {
        $this->informePdfPath = $informePdfPath;
        return $this;
    }

    public function getInformeHash()
    {
        return $this->informeHash;
    }

    public function setInformeHash($informeHash)
    {
        $this->informeHash = $informeHash;
        return $this;
    }

    public function getLeadId()
    {
        return $this->leadId;
    }

    public function setLeadId($leadId)
    {
        $this->leadId = $leadId;
        return $this;
    }

    public function getExpedienteId()
    {
        return $this->expedienteId;
    }

    public function setExpedienteId($expedienteId)
    {
        $this->expedienteId = $expedienteId;
        return $this;
    }

    public function getFechaCreacion()
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTime $fechaCreacion)
    {
        $this->fechaCreacion = $fechaCreacion;
        return $this;
    }

    public function getFechaActualizacion()
    {
        return $this->fechaActualizacion;
    }

    public function setFechaActualizacion(\DateTime $fechaActualizacion)
    {
        $this->fechaActualizacion = $fechaActualizacion;
        return $this;
    }

    public function getFechaFinalizacion()
    {
        return $this->fechaFinalizacion;
    }

    public function setFechaFinalizacion(\DateTime $fechaFinalizacion = null)
    {
        $this->fechaFinalizacion = $fechaFinalizacion;
        return $this;
    }

    public function getFechaEnvioHipotea()
    {
        return $this->fechaEnvioHipotea;
    }

    public function setFechaEnvioHipotea(\DateTime $fechaEnvioHipotea = null)
    {
        $this->fechaEnvioHipotea = $fechaEnvioHipotea;
        return $this;
    }

    // Helper methods - Estados disponibles

    /**
     * Obtiene todos los estados disponibles
     *
     * @return array
     */
    public static function getEstadosDisponibles()
    {
        return [
            self::ESTADO_BORRADOR,
            self::ESTADO_EN_PROGRESO,
            self::ESTADO_COMPLETADO,
            self::ESTADO_ENVIADO_A_HIPOTEA,
            self::ESTADO_CANCELADO,
        ];
    }

    /**
     * Obtiene todos los pasos disponibles
     *
     * @return array
     */
    public static function getPasosDisponibles()
    {
        return [
            self::PASO_INICIO,
            self::PASO_1,
            self::PASO_2,
            self::PASO_3,
            self::PASO_4,
            self::PASO_RESULTADO,
        ];
    }

    /**
     * Obtiene todos los semáforos disponibles
     *
     * @return array
     */
    public static function getSemaforosDisponibles()
    {
        return [
            self::SEMAFORO_VERDE,
            self::SEMAFORO_AMARILLO,
            self::SEMAFORO_ROJO,
        ];
    }

    /**
     * Obtiene todas las situaciones laborales disponibles
     *
     * @return array
     */
    public static function getSituacionesLaboralesDisponibles()
    {
        return [
            self::SITUACION_FUNCIONARIO,
            self::SITUACION_CONTRATO_INDEFINIDO,
            self::SITUACION_CONTRATO_TEMPORAL,
            self::SITUACION_AUTONOMO,
            self::SITUACION_EMPRESARIO,
            self::SITUACION_OTROS,
        ];
    }

    /**
     * Obtiene todas las antigüedades laborales disponibles
     *
     * @return array
     */
    public static function getAntiguedadesDisponibles()
    {
        return [
            self::ANTIGUEDAD_MENOS_1_ANIO,
            self::ANTIGUEDAD_UN_ANIO,
            self::ANTIGUEDAD_MAS_2_ANIOS,
        ];
    }
}