<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="kommo_webhook")
 */
class KommoWebhook
{
	/**
	 * @ORM\Id
	 * @ORM\GeneratedValue
	 * @ORM\Column(type="integer")
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", length=50)
	 */
	private $webhookType;

	/**
	 * @ORM\Column(type="string", length=100)
	 */
	private $kommoId;

	/**
	 * @ORM\Column(type="json")
	 */
	private $jsonRecibido;

	/**
	 * @ORM\Column(type="string", length=30, nullable=true)
	 */
	private $estado;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $errorMensaje;

	/**
	 * @ORM\Column(type="datetime")
	 */
	private $fecha;

	public function __construct()
	{
		$this->fecha = new \DateTime();
	}

	public function getId()
	{
		return $this->id;
	}

	public function getWebhookType()
	{
		return $this->webhookType;
	}

	public function setWebhookType($webhookType)
	{
		$this->webhookType = $webhookType;
		return $this;
	}

	public function getKommoId()
	{
		return $this->kommoId;
	}

	public function setKommoId($kommoId)
	{
		$this->kommoId = $kommoId;
		return $this;
	}

	public function getJsonRecibido()
	{
		return $this->jsonRecibido;
	}

	public function setJsonRecibido($jsonRecibido)
	{
		$this->jsonRecibido = $jsonRecibido;
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

	public function getErrorMensaje()
	{
		return $this->errorMensaje;
	}

	public function setErrorMensaje($errorMensaje)
	{
		$this->errorMensaje = $errorMensaje;
		return $this;
	}

	public function getFecha()
	{
		return $this->fecha;
	}

	public function setFecha($fecha)
	{
		$this->fecha = $fecha;
		return $this;
	}
}
