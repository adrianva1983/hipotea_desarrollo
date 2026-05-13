<?php

namespace AppBundle\Services;

use Doctrine\ORM\EntityManager;

/**
 * Servicio para generar referencias únicas para expedientes
 * Formato: NNNN/YY (ej: 0001/26, 0002/26, etc.)
 */
class ReferenciaExpedienteService
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Genera una referencia única para un expediente
     * Busca el máximo número para el año actual y lo incrementa
     * 
     * @return string Referencia en formato NNNN/YY
     * @throws \Exception Si hay error al generar la referencia
     */
    public function generarReferencia(): string
    {
        try {
            $anioActual = (int)date('y');  // 2 dígitos del año (26, 27, etc.)
            
            // Usar SQL nativo para buscar el máximo número del año actual
            $conn = $this->em->getConnection();
            
            // Query SQL para obtener el máximo número de referencia del año actual
            $sql = "
                SELECT MAX(CAST(SUBSTRING_INDEX(referencia, '/', 1) AS UNSIGNED)) as max_numero
                FROM expediente
                WHERE referencia LIKE :patron
            ";
            
            $stmt = $conn->prepare($sql);
            $patron = '%/' . str_pad($anioActual, 2, '0', STR_PAD_LEFT);
            $stmt->bindValue('patron', $patron);
            $result = $stmt->executeQuery()->fetchAssociative();
            
            $maxNumero = isset($result['max_numero']) && !is_null($result['max_numero']) 
                ? (int)$result['max_numero'] 
                : 0;
            
            $siguienteNumero = $maxNumero + 1;
            
            // Formatear: NNNN/YY (4 dígitos para número, 2 para año)
            $referencia = sprintf('%04d/%02d', $siguienteNumero, $anioActual);
            
            return $referencia;
        } catch (\Exception $e) {
            throw new \Exception('Error al generar referencia de expediente: ' . $e->getMessage());
        }
    }

    /**
     * Asigna una referencia a un expediente si no la tiene
     * 
     * @param \AppBundle\Entity\Expediente $expediente
     * @return string La referencia asignada
     */
    public function asignarReferenciaAExpediente(\AppBundle\Entity\Expediente $expediente): string
    {
        // Si ya tiene referencia, no generar otra
        if (!empty($expediente->getReferencia())) {
            return $expediente->getReferencia();
        }
        
        $referencia = $this->generarReferencia();
        $expediente->setReferencia($referencia);
        
        return $referencia;
    }
}
