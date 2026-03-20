<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityManagerInterface;

use DateTime;

/**
 * Dispositivo
 */
class Parametros
{
	/**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $edadAmortizacion;

    /**
     * @var float
     */
    private $gastosInmobiliaria;

    /**
     * @var float
     */
    private $porComisionApertura;

    /**
     * @var float
     */
    private $honorariosFinanciacion;

    /**
     * @var int
     */
    private $tasacion;

    /**
     * @var int
     */
    private $vinculaciones;

    /**
     * @var int
     */
    private $escrituraCompraNotario;

    /**
     * @var int
     */
    private $escrituraCompraRegistro;

    /**
     * @var int
     */
    private $escrituraCompraGestoria;

    /**
     * @var int
     */
    private $gastosImporteMaximo;

    /**
     * @var float
     */
    private $interesImporteMaximo;

    /**
     * @var float
     */
    private $tipoCienVariable;

    /**
     * @var float
     */
    private $tipoCienFijo1519;

    /**
     * @var float
     */
    private $tipoCienFijo2024;

    /**
     * @var float
     */
    private $tipoCienFijo2530;

    /**
     * @var float
     */
    private $tipoPremiumVariable;

    /**
     * @var float
     */
    private $tipoPremiumFijo1519;

    /**
     * @var float
     */
    private $tipoPremiumFijo2024;

    /**
     * @var float
     */
    private $tipoPremiumFijo2530;

    /**
     * @var float
     */
    private $tipoSinCompromisoVariable;

    /**
     * @var float
     */
    private $tipoSinCompromisoFijoMenos20;

    /**
     * @var float
     */
    private $tipoSinCompromisoFijo2030;

    /**
     * @var float
     */
    private $tipoCambioCasaVariable;

    /**
     * @var float
     */
    private $tipoCambioCasaFijo;

    /**
     * @var int
     */
    private $levantamientoRegistral;


	/**
     * @var float
     */
    private $andaluciaItpGeneral;

    /**
     * @var float
     */
    private $andaluciaItpHabitual;

    /**
     * @var float
     */
    private $andaluciaAjdGeneral;

    /**
     * @var float
     */
    private $andaluciaAjdHabitual;

    /**
     * @var float
     */
    private $aragonItpGeneral;

    /**
     * @var float
     */
    private $aragonItpHabitual;

    /**
     * @var float
     */
    private $aragonAjdGeneral;

    /**
     * @var float
     */
    private $aragonAjdHabitual;

    /**
     * @var float
     */
    private $asturiasItpGeneral;

    /**
     * @var float
     */
    private $asturiasItpHabitual;

    /**
     * @var float
     */
    private $asturiasAjdGeneral;

    /**
     * @var float
     */
    private $asturiasAjdHabitual;

    /**
     * @var float
     */
    private $balearesItpGeneral;

    /**
     * @var float
     */
    private $balearesItpHabitual;

    /**
     * @var float
     */
    private $balearesAjdGeneral;

    /**
     * @var float
     */
    private $balearesAjdHabitual;

    /**
     * @var float
     */
    private $canariasItpGeneral;

    /**
     * @var float
     */
    private $canariasItpHabitual;

    /**
     * @var float
     */
    private $canariasAjdGeneral;

    /**
     * @var float
     */
    private $canariasAjdHabitual;

    /**
     * @var float
     */
    private $cantabriaItpGeneral;

    /**
     * @var float
     */
    private $cantabriaItpHabitual;

    /**
     * @var float
     */
    private $cantabriaAjdGeneral;

    /**
     * @var float
     */
    private $cantabriaAjdHabitual;

    /**
     * @var float
     */
    private $castillaLaManchaItpGeneral;

    /**
     * @var float
     */
    private $castillaLaManchaItpHabitual;

    /**
     * @var float
     */
    private $castillaLaManchaAjdGeneral;

    /**
     * @var float
     */
    private $castillaLaManchaAjdHabitual;

    /**
     * @var float
     */
    private $castillaYLeonItpGeneral;

    /**
     * @var float
     */
    private $castillaYLeonItpHabitual;

    /**
     * @var float
     */
    private $castillaYLeonAjdGeneral;

    /**
     * @var float
     */
    private $castillaYLeonAjdHabitual;

    /**
     * @var float
     */
    private $catalunyaItpGeneral;

    /**
     * @var float
     */
    private $catalunyaItpHabitual;

    /**
     * @var float
     */
    private $catalunyaAjdGeneral;

    /**
     * @var float
     */
    private $catalunyaAjdHabitual;

    /**
     * @var float
     */
    private $comunidadValencianaItpGeneral;

    /**
     * @var float
     */
    private $comunidadValencianaItpHabitual;

    /**
     * @var float
     */
    private $comunidadValencianaAjdGeneral;

    /**
     * @var float
     */
    private $comunidadValencianaAjdHabitual;

    /**
     * @var float
     */
    private $extremaduraItpGeneral;

    /**
     * @var float
     */
    private $extremaduraItpHabitual;

    /**
     * @var float
     */
    private $extremaduraAjdGeneral;

    /**
     * @var float
     */
    private $extremaduraAjdHabitual;

    /**
     * @var float
     */
    private $galiciaItpGeneral;

    /**
     * @var float
     */
    private $galiciaItpHabitual;

    /**
     * @var float
     */
    private $galiciaAjdGeneral;

    /**
     * @var float
     */
    private $galiciaAjdHabitual;

    /**
     * @var float
     */
    private $madridItpGeneral;

    /**
     * @var float
     */
    private $madridItpHabitual;

    /**
     * @var float
     */
    private $madridAjdGeneral;

    /**
     * @var float
     */
    private $madridAjdHabitual;

    /**
     * @var float
     */
    private $murciaItpGeneral;

    /**
     * @var float
     */
    private $murciaItpHabitual;

    /**
     * @var float
     */
    private $murciaAjdGeneral;

    /**
     * @var float
     */
    private $murciaAjdHabitual;

    /**
     * @var float
     */
    private $navarraItpGeneral;

    /**
     * @var float
     */
    private $navarraItpHabitual;

    /**
     * @var float
     */
    private $navarraAjdGeneral;

    /**
     * @var float
     */
    private $navarraAjdHabitual;

    /**
     * @var float
     */
    private $paisVascoItpGeneral;

    /**
     * @var float
     */
    private $paisVascoItpHabitual;

    /**
     * @var float
     */
    private $paisVascoAjdGeneral;

    /**
     * @var float
     */
    private $paisVascoAjdHabitual;

    /**
     * @var float
     */
    private $riojaItpGeneral;

    /**
     * @var float
     */
    private $riojaItpHabitual;

    /**
     * @var float
     */
    private $riojaAjdGeneral;

    /**
     * @var float
     */
    private $riojaAjdHabitual;




    // Getters y Setters

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getEdadAmortizacion()
    {
        return $this->edadAmortizacion;
    }

    public function setEdadAmortizacion($edadAmortizacion)
    {
        $this->edadAmortizacion = $edadAmortizacion;
    }

    public function getGastosInmobiliaria()
    {
        return $this->gastosInmobiliaria;
    }

    public function setGastosInmobiliaria($gastosInmobiliaria)
    {
        $this->gastosInmobiliaria = $gastosInmobiliaria;
    }

    public function getPorComisionApertura()
    {
        return $this->porComisionApertura;
    }

    public function setPorComisionApertura($porComisionApertura)
    {
        $this->porComisionApertura = $porComisionApertura;
    }

    public function getHonorariosFinanciacion()
    {
        return $this->honorariosFinanciacion;
    }

    public function setHonorariosFinanciacion($honorariosFinanciacion)
    {
        $this->honorariosFinanciacion = $honorariosFinanciacion;
    }

    public function getTasacion()
    {
        return $this->tasacion;
    }

    public function setTasacion($tasacion)
    {
        $this->tasacion = $tasacion;
    }

    public function getVinculaciones()
    {
        return $this->vinculaciones;
    }

    public function setVinculaciones($vinculaciones)
    {
        $this->vinculaciones = $vinculaciones;
    }

    public function getEscrituraCompraNotario()
    {
        return $this->escrituraCompraNotario;
    }

    public function setEscrituraCompraNotario($escrituraCompraNotario)
    {
        $this->escrituraCompraNotario = $escrituraCompraNotario;
    }

    public function getEscrituraCompraRegistro()
    {
        return $this->escrituraCompraRegistro;
    }

    public function setEscrituraCompraRegistro($escrituraCompraRegistro)
    {
        $this->escrituraCompraRegistro = $escrituraCompraRegistro;
    }

    public function getEscrituraCompraGestoria()
    {
        return $this->escrituraCompraGestoria;
    }

    public function setEscrituraCompraGestoria($escrituraCompraGestoria)
    {
        $this->escrituraCompraGestoria = $escrituraCompraGestoria;
    }

    public function getGastosImporteMaximo()
    {
        return $this->gastosImporteMaximo;
    }

    public function setGastosImporteMaximo($gastosImporteMaximo)
    {
        $this->gastosImporteMaximo = $gastosImporteMaximo;
    }

    public function getInteresImporteMaximo()
    {
        return $this->interesImporteMaximo;
    }

    public function setInteresImporteMaximo($interesImporteMaximo)
    {
        $this->interesImporteMaximo = $interesImporteMaximo;
    }

    public function getTipoCienVariable()
    {
        return $this->tipoCienVariable;
    }

    public function setTipoCienVariable($tipoCienVariable)
    {
        $this->tipoCienVariable = $tipoCienVariable;
    }

    public function getTipoCienFijo1519()
    {
        return $this->tipoCienFijo1519;
    }

    public function setTipoCienFijo1519($tipoCienFijo1519)
    {
        $this->tipoCienFijo1519 = $tipoCienFijo1519;
    }

    public function getTipoCienFijo2024()
    {
        return $this->tipoCienFijo2024;
    }

    public function setTipoCienFijo2024($tipoCienFijo2024)
    {
        $this->tipoCienFijo2024 = $tipoCienFijo2024;
    }

    public function getTipoCienFijo2530()
    {
        return $this->tipoCienFijo2530;
    }

    public function setTipoCienFijo2530($tipoCienFijo2530)
    {
        $this->tipoCienFijo2530 = $tipoCienFijo2530;
    }

    public function getTipoPremiumVariable()
    {
        return $this->tipoPremiumVariable;
    }

    public function setTipoPremiumVariable($tipoPremiumVariable)
    {
        $this->tipoPremiumVariable = $tipoPremiumVariable;
    }

    public function getTipoPremiumFijo1519()
    {
        return $this->tipoPremiumFijo1519;
    }

    public function setTipoPremiumFijo1519($tipoPremiumFijo1519)
    {
        $this->tipoPremiumFijo1519 = $tipoPremiumFijo1519;
    }

    public function getTipoPremiumFijo2024()
    {
        return $this->tipoPremiumFijo2024;
    }

    public function setTipoPremiumFijo2024($tipoPremiumFijo2024)
    {
        $this->tipoPremiumFijo2024 = $tipoPremiumFijo2024;
    }

    public function getTipoPremiumFijo2530()
    {
        return $this->tipoPremiumFijo2530;
    }

    public function setTipoPremiumFijo2530($tipoPremiumFijo2530)
    {
        $this->tipoPremiumFijo2530 = $tipoPremiumFijo2530;
    }

    public function getTipoSinCompromisoVariable()
    {
        return $this->tipoSinCompromisoVariable;
    }

    public function setTipoSinCompromisoVariable($tipoSinCompromisoVariable)
    {
        $this->tipoSinCompromisoVariable = $tipoSinCompromisoVariable;
    }

    public function getTipoSinCompromisoFijoMenos20()
    {
        return $this->tipoSinCompromisoFijoMenos20;
    }

    public function setTipoSinCompromisoFijoMenos20($tipoSinCompromisoFijoMenos20)
    {
        $this->tipoSinCompromisoFijoMenos20 = $tipoSinCompromisoFijoMenos20;
    }

    public function getTipoSinCompromisoFijo2030()
    {
        return $this->tipoSinCompromisoFijo2030;
    }

    public function setTipoSinCompromisoFijo2030($tipoSinCompromisoFijo2030)
    {
        $this->tipoSinCompromisoFijo2030 = $tipoSinCompromisoFijo2030;
    }

    public function getTipoCambioCasaVariable()
    {
        return $this->tipoCambioCasaVariable;
    }

    public function setTipoCambioCasaVariable($tipoCambioCasaVariable)
    {
        $this->tipoCambioCasaVariable = $tipoCambioCasaVariable;
    }

    public function getTipoCambioCasaFijo()
    {
        return $this->tipoCambioCasaFijo;
    }

    public function setTipoCambioCasaFijo($tipoCambioCasaFijo)
    {
        $this->tipoCambioCasaFijo = $tipoCambioCasaFijo;
    }

    public function getLevantamientoRegistral()
    {
        return $this->levantamientoRegistral;
    }

    public function setLevantamientoRegistral($levantamientoRegistral)
    {
        $this->levantamientoRegistral = $levantamientoRegistral;
    }

	// Getter y Setter para andaluciaItpGeneral
    public function getAndaluciaItpGeneral()
    {
        return $this->andaluciaItpGeneral;
    }

    public function setAndaluciaItpGeneral($andaluciaItpGeneral)
    {
        $this->andaluciaItpGeneral = $andaluciaItpGeneral;
    }

    // Getter y Setter para andaluciaItpHabitual
    public function getAndaluciaItpHabitual()
    {
        return $this->andaluciaItpHabitual;
    }

    public function setAndaluciaItpHabitual($andaluciaItpHabitual)
    {
        $this->andaluciaItpHabitual = $andaluciaItpHabitual;
    }

    // Getter y Setter para andaluciaAjdGeneral
    public function getAndaluciaAjdGeneral()
    {
        return $this->andaluciaAjdGeneral;
    }

    public function setAndaluciaAjdGeneral($andaluciaAjdGeneral)
    {
        $this->andaluciaAjdGeneral = $andaluciaAjdGeneral;
    }

    // Getter y Setter para andaluciaAjdHabitual
    public function getAndaluciaAjdHabitual()
    {
        return $this->andaluciaAjdHabitual;
    }

    public function setAndaluciaAjdHabitual($andaluciaAjdHabitual)
    {
        $this->andaluciaAjdHabitual = $andaluciaAjdHabitual;
    }

    // Getter y Setter para aragonItpGeneral
    public function getAragonItpGeneral()
    {
        return $this->aragonItpGeneral;
    }

    public function setAragonItpGeneral($aragonItpGeneral)
    {
        $this->aragonItpGeneral = $aragonItpGeneral;
    }

	// Getter y Setter para aragonItpHabitual
    public function getAragonItpHabitual()
    {
        return $this->aragonItpHabitual;
    }

    public function setAragonItpHabitual($aragonItpHabitual)
    {
        $this->aragonItpHabitual = $aragonItpHabitual;
    }

    // Getter y Setter para aragonAjdGeneral
    public function getAragonAjdGeneral()
    {
        return $this->aragonAjdGeneral;
    }

    public function setAragonAjdGeneral($aragonAjdGeneral)
    {
        $this->aragonAjdGeneral = $aragonAjdGeneral;
    }

    // Getter y Setter para aragonAjdHabitual
    public function getAragonAjdHabitual()
    {
        return $this->aragonAjdHabitual;
    }

    public function setAragonAjdHabitual($aragonAjdHabitual)
    {
        $this->aragonAjdHabitual = $aragonAjdHabitual;
    }

    // Getter y Setter para asturiasItpGeneral
    public function getAsturiasItpGeneral()
    {
        return $this->asturiasItpGeneral;
    }

    public function setAsturiasItpGeneral($asturiasItpGeneral)
    {
        $this->asturiasItpGeneral = $asturiasItpGeneral;
    }

    // Getter y Setter para asturiasItpHabitual
    public function getAsturiasItpHabitual()
    {
        return $this->asturiasItpHabitual;
    }

    public function setAsturiasItpHabitual($asturiasItpHabitual)
    {
        $this->asturiasItpHabitual = $asturiasItpHabitual;
    }

    // Getter y Setter para asturiasAjdGeneral
    public function getAsturiasAjdGeneral()
    {
        return $this->asturiasAjdGeneral;
    }

    public function setAsturiasAjdGeneral($asturiasAjdGeneral)
    {
        $this->asturiasAjdGeneral = $asturiasAjdGeneral;
    }

    // Getter y Setter para asturiasAjdHabitual
    public function getAsturiasAjdHabitual()
    {
        return $this->asturiasAjdHabitual;
    }

    public function setAsturiasAjdHabitual($asturiasAjdHabitual)
    {
        $this->asturiasAjdHabitual = $asturiasAjdHabitual;
    }

	// Getter y Setter para balearesItpGeneral
    public function getBalearesItpGeneral()
    {
        return $this->balearesItpGeneral;
    }

    public function setBalearesItpGeneral($balearesItpGeneral)
    {
        $this->balearesItpGeneral = $balearesItpGeneral;
    }

    // Getter y Setter para balearesItpHabitual
    public function getBalearesItpHabitual()
    {
        return $this->balearesItpHabitual;
    }

    public function setBalearesItpHabitual($balearesItpHabitual)
    {
        $this->balearesItpHabitual = $balearesItpHabitual;
    }

    // Getter y Setter para balearesAjdGeneral
    public function getBalearesAjdGeneral()
    {
        return $this->balearesAjdGeneral;
    }

    public function setBalearesAjdGeneral($balearesAjdGeneral)
    {
        $this->balearesAjdGeneral = $balearesAjdGeneral;
    }

    // Getter y Setter para balearesAjdHabitual
    public function getBalearesAjdHabitual()
    {
        return $this->balearesAjdHabitual;
    }

    public function setBalearesAjdHabitual($balearesAjdHabitual)
    {
        $this->balearesAjdHabitual = $balearesAjdHabitual;
    }

    // Getter y Setter para canariasItpGeneral
    public function getCanariasItpGeneral()
    {
        return $this->canariasItpGeneral;
    }

    public function setCanariasItpGeneral($canariasItpGeneral)
    {
        $this->canariasItpGeneral = $canariasItpGeneral;
    }

    // Getter y Setter para canariasItpHabitual
    public function getCanariasItpHabitual()
    {
        return $this->canariasItpHabitual;
    }

    public function setCanariasItpHabitual($canariasItpHabitual)
    {
        $this->canariasItpHabitual = $canariasItpHabitual;
    }

    // Getter y Setter para canariasAjdGeneral
    public function getCanariasAjdGeneral()
    {
        return $this->canariasAjdGeneral;
    }

    public function setCanariasAjdGeneral($canariasAjdGeneral)
    {
        $this->canariasAjdGeneral = $canariasAjdGeneral;
    }

    // Getter y Setter para canariasAjdHabitual
    public function getCanariasAjdHabitual()
    {
        return $this->canariasAjdHabitual;
    }

    public function setCanariasAjdHabitual($canariasAjdHabitual)
    {
        $this->canariasAjdHabitual = $canariasAjdHabitual;
    }

	// Getter y Setter para cantabriaItpGeneral
    public function getCantabriaItpGeneral()
    {
        return $this->cantabriaItpGeneral;
    }

    public function setCantabriaItpGeneral($cantabriaItpGeneral)
    {
        $this->cantabriaItpGeneral = $cantabriaItpGeneral;
    }

    // Getter y Setter para cantabriaItpHabitual
    public function getCantabriaItpHabitual()
    {
        return $this->cantabriaItpHabitual;
    }

    public function setCantabriaItpHabitual($cantabriaItpHabitual)
    {
        $this->cantabriaItpHabitual = $cantabriaItpHabitual;
    }

    // Getter y Setter para cantabriaAjdGeneral
    public function getCantabriaAjdGeneral()
    {
        return $this->cantabriaAjdGeneral;
    }

    public function setCantabriaAjdGeneral($cantabriaAjdGeneral)
    {
        $this->cantabriaAjdGeneral = $cantabriaAjdGeneral;
    }

    // Getter y Setter para cantabriaAjdHabitual
    public function getCantabriaAjdHabitual()
    {
        return $this->cantabriaAjdHabitual;
    }

    public function setCantabriaAjdHabitual($cantabriaAjdHabitual)
    {
        $this->cantabriaAjdHabitual = $cantabriaAjdHabitual;
    }

    // Getter y Setter para castillaLaManchaItpGeneral
    public function getCastillaLaManchaItpGeneral()
    {
        return $this->castillaLaManchaItpGeneral;
    }

    public function setCastillaLaManchaItpGeneral($castillaLaManchaItpGeneral)
    {
        $this->castillaLaManchaItpGeneral = $castillaLaManchaItpGeneral;
    }

    // Getter y Setter para castillaLaManchaItpHabitual
    public function getCastillaLaManchaItpHabitual()
    {
        return $this->castillaLaManchaItpHabitual;
    }

    public function setCastillaLaManchaItpHabitual($castillaLaManchaItpHabitual)
    {
        $this->castillaLaManchaItpHabitual = $castillaLaManchaItpHabitual;
    }

    // Getter y Setter para castillaLaManchaAjdGeneral
    public function getCastillaLaManchaAjdGeneral()
    {
        return $this->castillaLaManchaAjdGeneral;
    }

    public function setCastillaLaManchaAjdGeneral($castillaLaManchaAjdGeneral)
    {
        $this->castillaLaManchaAjdGeneral = $castillaLaManchaAjdGeneral;
    }

    // Getter y Setter para castillaLaManchaAjdHabitual
    public function getCastillaLaManchaAjdHabitual()
    {
        return $this->castillaLaManchaAjdHabitual;
    }

    public function setCastillaLaManchaAjdHabitual($castillaLaManchaAjdHabitual)
    {
        $this->castillaLaManchaAjdHabitual = $castillaLaManchaAjdHabitual;
    }

	// Getter y Setter para castillaYLeonItpGeneral
    public function getCastillaYLeonItpGeneral()
    {
        return $this->castillaYLeonItpGeneral;
    }

    public function setCastillaYLeonItpGeneral($castillaYLeonItpGeneral)
    {
        $this->castillaYLeonItpGeneral = $castillaYLeonItpGeneral;
    }

    // Getter y Setter para castillaYLeonItpHabitual
    public function getCastillaYLeonItpHabitual()
    {
        return $this->castillaYLeonItpHabitual;
    }

    public function setCastillaYLeonItpHabitual($castillaYLeonItpHabitual)
    {
        $this->castillaYLeonItpHabitual = $castillaYLeonItpHabitual;
    }

    // Getter y Setter para castillaYLeonAjdGeneral
    public function getCastillaYLeonAjdGeneral()
    {
        return $this->castillaYLeonAjdGeneral;
    }

    public function setCastillaYLeonAjdGeneral($castillaYLeonAjdGeneral)
    {
        $this->castillaYLeonAjdGeneral = $castillaYLeonAjdGeneral;
    }

    // Getter y Setter para castillaYLeonAjdHabitual
    public function getCastillaYLeonAjdHabitual()
    {
        return $this->castillaYLeonAjdHabitual;
    }

    public function setCastillaYLeonAjdHabitual($castillaYLeonAjdHabitual)
    {
        $this->castillaYLeonAjdHabitual = $castillaYLeonAjdHabitual;
    }

    // Getter y Setter para catalunyaItpGeneral
    public function getCatalunyaItpGeneral()
    {
        return $this->catalunyaItpGeneral;
    }

    public function setCatalunyaItpGeneral($catalunyaItpGeneral)
    {
        $this->catalunyaItpGeneral = $catalunyaItpGeneral;
    }

    // Getter y Setter para catalunyaItpHabitual
    public function getCatalunyaItpHabitual()
    {
        return $this->catalunyaItpHabitual;
    }

    public function setCatalunyaItpHabitual($catalunyaItpHabitual)
    {
        $this->catalunyaItpHabitual = $catalunyaItpHabitual;
    }

    // Getter y Setter para catalunyaAjdGeneral
    public function getCatalunyaAjdGeneral()
    {
        return $this->catalunyaAjdGeneral;
    }

    public function setCatalunyaAjdGeneral($catalunyaAjdGeneral)
    {
        $this->catalunyaAjdGeneral = $catalunyaAjdGeneral;
    }

    // Getter y Setter para catalunyaAjdHabitual
    public function getCatalunyaAjdHabitual()
    {
        return $this->catalunyaAjdHabitual;
    }

    public function setCatalunyaAjdHabitual($catalunyaAjdHabitual)
    {
        $this->catalunyaAjdHabitual = $catalunyaAjdHabitual;
    }

	// Getter y Setter para comunidadValencianaItpGeneral
    public function getComunidadValencianaItpGeneral()
    {
        return $this->comunidadValencianaItpGeneral;
    }

    public function setComunidadValencianaItpGeneral($comunidadValencianaItpGeneral)
    {
        $this->comunidadValencianaItpGeneral = $comunidadValencianaItpGeneral;
    }

    // Getter y Setter para comunidadValencianaItpHabitual
    public function getComunidadValencianaItpHabitual()
    {
        return $this->comunidadValencianaItpHabitual;
    }

    public function setComunidadValencianaItpHabitual($comunidadValencianaItpHabitual)
    {
        $this->comunidadValencianaItpHabitual = $comunidadValencianaItpHabitual;
    }

    // Getter y Setter para comunidadValencianaAjdGeneral
    public function getComunidadValencianaAjdGeneral()
    {
        return $this->comunidadValencianaAjdGeneral;
    }

    public function setComunidadValencianaAjdGeneral($comunidadValencianaAjdGeneral)
    {
        $this->comunidadValencianaAjdGeneral = $comunidadValencianaAjdGeneral;
    }

    // Getter y Setter para comunidadValencianaAjdHabitual
    public function getComunidadValencianaAjdHabitual()
    {
        return $this->comunidadValencianaAjdHabitual;
    }

    public function setComunidadValencianaAjdHabitual($comunidadValencianaAjdHabitual)
    {
        $this->comunidadValencianaAjdHabitual = $comunidadValencianaAjdHabitual;
    }

    // Getter y Setter para extremaduraItpGeneral
    public function getExtremaduraItpGeneral()
    {
        return $this->extremaduraItpGeneral;
    }

    public function setExtremaduraItpGeneral($extremaduraItpGeneral)
    {
        $this->extremaduraItpGeneral = $extremaduraItpGeneral;
    }

    // Getter y Setter para extremaduraItpHabitual
    public function getExtremaduraItpHabitual()
    {
        return $this->extremaduraItpHabitual;
    }

    public function setExtremaduraItpHabitual($extremaduraItpHabitual)
    {
        $this->extremaduraItpHabitual = $extremaduraItpHabitual;
    }

    // Getter y Setter para extremaduraAjdGeneral
    public function getExtremaduraAjdGeneral()
    {
        return $this->extremaduraAjdGeneral;
    }

    public function setExtremaduraAjdGeneral($extremaduraAjdGeneral)
    {
        $this->extremaduraAjdGeneral = $extremaduraAjdGeneral;
    }

    // Getter y Setter para extremaduraAjdHabitual
    public function getExtremaduraAjdHabitual()
    {
        return $this->extremaduraAjdHabitual;
    }

    public function setExtremaduraAjdHabitual($extremaduraAjdHabitual)
    {
        $this->extremaduraAjdHabitual = $extremaduraAjdHabitual;
    }

	// Getter y Setter para galiciaItpGeneral
    public function getGaliciaItpGeneral()
    {
        return $this->galiciaItpGeneral;
    }

    public function setGaliciaItpGeneral($galiciaItpGeneral)
    {
        $this->galiciaItpGeneral = $galiciaItpGeneral;
    }

    // Getter y Setter para galiciaItpHabitual
    public function getGaliciaItpHabitual()
    {
        return $this->galiciaItpHabitual;
    }

    public function setGaliciaItpHabitual($galiciaItpHabitual)
    {
        $this->galiciaItpHabitual = $galiciaItpHabitual;
    }

    // Getter y Setter para galiciaAjdGeneral
    public function getGaliciaAjdGeneral()
    {
        return $this->galiciaAjdGeneral;
    }

    public function setGaliciaAjdGeneral($galiciaAjdGeneral)
    {
        $this->galiciaAjdGeneral = $galiciaAjdGeneral;
    }

    // Getter y Setter para galiciaAjdHabitual
    public function getGaliciaAjdHabitual()
    {
        return $this->galiciaAjdHabitual;
    }

    public function setGaliciaAjdHabitual($galiciaAjdHabitual)
    {
        $this->galiciaAjdHabitual = $galiciaAjdHabitual;
    }

    // Getter y Setter para madridItpGeneral
    public function getMadridItpGeneral()
    {
        return $this->madridItpGeneral;
    }

    public function setMadridItpGeneral($madridItpGeneral)
    {
        $this->madridItpGeneral = $madridItpGeneral;
    }

    // Getter y Setter para madridItpHabitual
    public function getMadridItpHabitual()
    {
        return $this->madridItpHabitual;
    }

    public function setMadridItpHabitual($madridItpHabitual)
    {
        $this->madridItpHabitual = $madridItpHabitual;
    }

    // Getter y Setter para madridAjdGeneral
    public function getMadridAjdGeneral()
    {
        return $this->madridAjdGeneral;
    }

    public function setMadridAjdGeneral($madridAjdGeneral)
    {
        $this->madridAjdGeneral = $madridAjdGeneral;
    }

    // Getter y Setter para madridAjdHabitual
    public function getMadridAjdHabitual()
    {
        return $this->madridAjdHabitual;
    }

    public function setMadridAjdHabitual($madridAjdHabitual)
    {
        $this->madridAjdHabitual = $madridAjdHabitual;
    }

	// Getter y Setter para murciaItpGeneral
    public function getMurciaItpGeneral()
    {
        return $this->murciaItpGeneral;
    }

    public function setMurciaItpGeneral($murciaItpGeneral)
    {
        $this->murciaItpGeneral = $murciaItpGeneral;
    }

    // Getter y Setter para murciaItpHabitual
    public function getMurciaItpHabitual()
    {
        return $this->murciaItpHabitual;
    }

    public function setMurciaItpHabitual($murciaItpHabitual)
    {
        $this->murciaItpHabitual = $murciaItpHabitual;
    }

    // Getter y Setter para murciaAjdGeneral
    public function getMurciaAjdGeneral()
    {
        return $this->murciaAjdGeneral;
    }

    public function setMurciaAjdGeneral($murciaAjdGeneral)
    {
        $this->murciaAjdGeneral = $murciaAjdGeneral;
    }

    // Getter y Setter para murciaAjdHabitual
    public function getMurciaAjdHabitual()
    {
        return $this->murciaAjdHabitual;
    }

    public function setMurciaAjdHabitual($murciaAjdHabitual)
    {
        $this->murciaAjdHabitual = $murciaAjdHabitual;
    }

    // Getter y Setter para navarraItpGeneral
    public function getNavarraItpGeneral()
    {
        return $this->navarraItpGeneral;
    }

    public function setNavarraItpGeneral($navarraItpGeneral)
    {
        $this->navarraItpGeneral = $navarraItpGeneral;
    }

    // Getter y Setter para navarraItpHabitual
    public function getNavarraItpHabitual()
    {
        return $this->navarraItpHabitual;
    }

    public function setNavarraItpHabitual($navarraItpHabitual)
    {
        $this->navarraItpHabitual = $navarraItpHabitual;
    }

    // Getter y Setter para navarraAjdGeneral
    public function getNavarraAjdGeneral()
    {
        return $this->navarraAjdGeneral;
    }

    public function setNavarraAjdGeneral($navarraAjdGeneral)
    {
        $this->navarraAjdGeneral = $navarraAjdGeneral;
    }

    // Getter y Setter para navarraAjdHabitual
    public function getNavarraAjdHabitual()
    {
        return $this->navarraAjdHabitual;
    }

    public function setNavarraAjdHabitual($navarraAjdHabitual)
    {
        $this->navarraAjdHabitual = $navarraAjdHabitual;
    }

	// Getter y Setter para paisVascoItpGeneral
    public function getPaisVascoItpGeneral()
    {
        return $this->paisVascoItpGeneral;
    }

    public function setPaisVascoItpGeneral($paisVascoItpGeneral)
    {
        $this->paisVascoItpGeneral = $paisVascoItpGeneral;
    }

    // Getter y Setter para paisVascoItpHabitual
    public function getPaisVascoItpHabitual()
    {
        return $this->paisVascoItpHabitual;
    }

    public function setPaisVascoItpHabitual($paisVascoItpHabitual)
    {
        $this->paisVascoItpHabitual = $paisVascoItpHabitual;
    }

    // Getter y Setter para paisVascoAjdGeneral
    public function getPaisVascoAjdGeneral()
    {
        return $this->paisVascoAjdGeneral;
    }

    public function setPaisVascoAjdGeneral($paisVascoAjdGeneral)
    {
        $this->paisVascoAjdGeneral = $paisVascoAjdGeneral;
    }

    // Getter y Setter para paisVascoAjdHabitual
    public function getPaisVascoAjdHabitual()
    {
        return $this->paisVascoAjdHabitual;
    }

    public function setPaisVascoAjdHabitual($paisVascoAjdHabitual)
    {
        $this->paisVascoAjdHabitual = $paisVascoAjdHabitual;
    }

    // Getter y Setter para riojaItpGeneral
    public function getRiojaItpGeneral()
    {
        return $this->riojaItpGeneral;
    }

    public function setRiojaItpGeneral($riojaItpGeneral)
    {
        $this->riojaItpGeneral = $riojaItpGeneral;
    }

    // Getter y Setter para riojaItpHabitual
    public function getRiojaItpHabitual()
    {
        return $this->riojaItpHabitual;
    }

    public function setRiojaItpHabitual($riojaItpHabitual)
    {
        $this->riojaItpHabitual = $riojaItpHabitual;
    }

    // Getter y Setter para riojaAjdGeneral
    public function getRiojaAjdGeneral()
    {
        return $this->riojaAjdGeneral;
    }

    public function setRiojaAjdGeneral($riojaAjdGeneral)
    {
        $this->riojaAjdGeneral = $riojaAjdGeneral;
    }

    // Getter y Setter para riojaAjdHabitual
    public function getRiojaAjdHabitual()
    {
        return $this->riojaAjdHabitual;
    }

    public function setRiojaAjdHabitual($riojaAjdHabitual)
    {
        $this->riojaAjdHabitual = $riojaAjdHabitual;
    }

    public function obtenerNombreCCAA($idCCAA)
    {
        switch ($idCCAA) {
            case '1':
                return "Andalucia";
                break;
            case '2':
                return "Aragon";
                break;
            case '3':
                return "Asturias";
                break;
            case '4':
                return "Baleares";
                break;
            case '5':
                return "Canarias";
                break;
            case '6':
                return "Cantabria";
                break;
            case '7':
                return "CastillaLaMancha";
                break;
            case '8':
                return "CastillaYLeon";
                break;
            case '9':
                return "Catalunya";
                break;
            case '10':
                return "Ceuta";
                break;
            case '11':
                return "ComunidadValenciana";
                break;
            case '12':
                return "Extremadura";
                break;
            case '13':
                return "Galicia";
                break;
            case '14':
                return "Rioja";
                break;
            case '15':
                return "Madrid";
                break;
            case '16':
                return "Melilla";
                break;
            case '17':
                return "Murcia";
                break;
            case '18':
                return "Navarra";
                break;
            case '19':
                return "PaisVasco";
                break;
            
            default:
                return "andalucia";
                break;
        }
    }

    public static function obtenerParametros(EntityManagerInterface $entityManager): ?self
    {
        return $entityManager->getRepository(self::class)->findOneBy([], ['id' => 'ASC']);
    }

}
