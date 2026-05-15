<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * ExpedienteChatMensaje
 */
class ExpedienteChatMensaje
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var Expediente
     */
    private $idExpediente;

    /**
     * @var mixed
     */
    private $idKommoWebhook;

    /**
     * @var string
     */
    private $proveedor = 'kommo';

    /**
     * @var string
     */
    private $externalMessageId;

    /**
     * @var string
     */
    private $kommoLeadId;

    /**
     * @var string
     */
    private $kommoContactId;

    /**
     * @var string
     */
    private $talkId;

    /**
     * @var string
     */
    private $chatId;

    /**
     * @var string
     */
    private $direccion = 'entrante';

    /**
     * @var string
     */
    private $autorNombre;

    /**
     * @var string
     */
    private $autorTipo;

    /**
     * @var string
     */
    private $telefono;

    /**
     * @var string
     */
    private $mensaje;

    /**
     * @var string
     */
    private $payloadJson;

    /**
     * @var string
     */
    private $estado = 'recibido';

    /**
     * @var boolean
     */
    private $leido = false;

    /**
     * @var DateTime
     */
    private $fechaMensaje;

    /**
     * @var DateTime
     */
    private $fechaCreacion;

    /**
     * @var DateTime
     */
    private $fechaActualizacion;

    public function __construct()
    {
        $ahora = new DateTime();
        $this->fechaCreacion = $ahora;
        $this->fechaActualizacion = $ahora;
        $this->fechaMensaje = $ahora;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getIdExpediente()
    {
        return $this->idExpediente;
    }

    public function setIdExpediente(Expediente $idExpediente = null)
    {
        $this->idExpediente = $idExpediente;

        return $this;
    }

    public function getIdKommoWebhook()
    {
        return $this->idKommoWebhook;
    }

    public function setIdKommoWebhook($idKommoWebhook = null)
    {
        $this->idKommoWebhook = $idKommoWebhook;

        return $this;
    }

    public function getProveedor()
    {
        return $this->proveedor;
    }

    public function setProveedor($proveedor)
    {
        $this->proveedor = $proveedor;

        return $this;
    }

    public function getExternalMessageId()
    {
        return $this->externalMessageId;
    }

    public function setExternalMessageId($externalMessageId)
    {
        $this->externalMessageId = $externalMessageId;

        return $this;
    }

    public function getKommoLeadId()
    {
        return $this->kommoLeadId;
    }

    public function setKommoLeadId($kommoLeadId)
    {
        $this->kommoLeadId = $kommoLeadId;

        return $this;
    }

    public function getKommoContactId()
    {
        return $this->kommoContactId;
    }

    public function setKommoContactId($kommoContactId)
    {
        $this->kommoContactId = $kommoContactId;

        return $this;
    }

    public function getTalkId()
    {
        return $this->talkId;
    }

    public function setTalkId($talkId)
    {
        $this->talkId = $talkId;

        return $this;
    }

    public function getChatId()
    {
        return $this->chatId;
    }

    public function setChatId($chatId)
    {
        $this->chatId = $chatId;

        return $this;
    }

    public function getDireccion()
    {
        return $this->direccion;
    }

    public function setDireccion($direccion)
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getAutorNombre()
    {
        return $this->autorNombre;
    }

    public function setAutorNombre($autorNombre)
    {
        $this->autorNombre = $autorNombre;

        return $this;
    }

    public function getAutorTipo()
    {
        return $this->autorTipo;
    }

    public function setAutorTipo($autorTipo)
    {
        $this->autorTipo = $autorTipo;

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

    public function getMensaje()
    {
        return $this->mensaje;
    }

    public function setMensaje($mensaje)
    {
        $this->mensaje = $mensaje;

        return $this;
    }

    public function getPayloadJson()
    {
        return $this->payloadJson;
    }

    public function setPayloadJson($payloadJson)
    {
        $this->payloadJson = $payloadJson;

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

    public function getLeido()
    {
        return $this->leido;
    }

    public function setLeido($leido)
    {
        $this->leido = (bool) $leido;

        return $this;
    }

    public function getFechaMensaje()
    {
        return $this->fechaMensaje;
    }

    public function setFechaMensaje(DateTime $fechaMensaje = null)
    {
        $this->fechaMensaje = $fechaMensaje;

        return $this;
    }

    public function getFechaCreacion()
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(DateTime $fechaCreacion = null)
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }

    public function getFechaActualizacion()
    {
        return $this->fechaActualizacion;
    }

    public function setFechaActualizacion(DateTime $fechaActualizacion = null)
    {
        $this->fechaActualizacion = $fechaActualizacion;

        return $this;
    }

    public function __toString()
    {
        return (string) $this->mensaje;
    }
}
