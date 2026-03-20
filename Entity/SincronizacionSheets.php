<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Sincronizacion Sheets
 */

class SincronizacionSheets
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $nombreCampania;

    /**
     * @var datetime
     */
    private $fechaSincronizacionSheets;

    // Getters y Setters

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getNombreCampania()
    {
        return $this->nombreCampania;
    }

    public function setNombreCampania($nombreCampania): self
    {
        $this->nombreCampania = $nombreCampania;
        return $this;
    }

    public function getFechaSincronizacionSheets()
    {
        return $this->fechaSincronizacionSheets;
    }

    public function setFechaSincronizacionSheets($fechaSincronizacionSheets): self
    {
        $this->fechaSincronizacionSheets = $fechaSincronizacionSheets;
        return $this;
    }
}
