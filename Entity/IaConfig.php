<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(name="ia_config")
 */
class IaConfig
{
	private $id;
	private $provider;
	private $apiKey;
	private $apiUrl;
	private $model;
	private $systemPrompt;
	private $temperatura;
	private $maxTokens;
	private $topP;
	private $topK;
	private $activo;
	private $esProveedorPorDefecto;
	private $createdAt;
	private $updatedAt;

	public function __construct()
	{
		$this->temperatura = 0.70;
		$this->maxTokens = 1024;
		$this->topP = 0.95;
		$this->topK = 40;
		$this->activo = false;
		$this->esProveedorPorDefecto = false;
		$this->createdAt = new DateTime();
		$this->updatedAt = new DateTime();
	}

	public function getId()
	{
		return $this->id;
	}

	public function getProvider()
	{
		return $this->provider;
	}

	public function setProvider($provider)
	{
		$this->provider = $provider;
		return $this;
	}

	public function getApiKey()
	{
		return $this->apiKey;
	}

	public function setApiKey($apiKey)
	{
		$this->apiKey = $apiKey;
		return $this;
	}

	public function getApiUrl()
	{
		return $this->apiUrl;
	}

	public function setApiUrl($apiUrl)
	{
		$this->apiUrl = $apiUrl;
		return $this;
	}

	public function getModel()
	{
		return $this->model;
	}

	public function setModel($model)
	{
		$this->model = $model;
		return $this;
	}

	public function getSystemPrompt()
	{
		return $this->systemPrompt;
	}

	public function setSystemPrompt($systemPrompt)
	{
		$this->systemPrompt = $systemPrompt;
		return $this;
	}

	public function getTemperatura()
	{
		return $this->temperatura;
	}

	public function setTemperatura($temperatura)
	{
		$this->temperatura = $temperatura;
		return $this;
	}

	public function getMaxTokens()
	{
		return $this->maxTokens;
	}

	public function setMaxTokens($maxTokens)
	{
		$this->maxTokens = $maxTokens;
		return $this;
	}

	public function getTopP()
	{
		return $this->topP;
	}

	public function setTopP($topP)
	{
		$this->topP = $topP;
		return $this;
	}

	public function getTopK()
	{
		return $this->topK;
	}

	public function setTopK($topK)
	{
		$this->topK = $topK;
		return $this;
	}

	public function isActivo()
	{
		return $this->activo;
	}

	public function setActivo($activo)
	{
		$this->activo = (bool) $activo;
		return $this;
	}

	public function isEsProveedorPorDefecto()
	{
		return $this->esProveedorPorDefecto;
	}

	public function setEsProveedorPorDefecto($esProveedorPorDefecto)
	{
		$this->esProveedorPorDefecto = (bool) $esProveedorPorDefecto;
		return $this;
	}

	public function getCreatedAt()
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt)
	{
		$this->createdAt = $createdAt;
		return $this;
	}

	public function getUpdatedAt()
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTime $updatedAt)
	{
		$this->updatedAt = $updatedAt;
		return $this;
	}
}
