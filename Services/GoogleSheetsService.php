<?php

namespace AppBundle\Services;

use AppBundle\Entity\Parametros;
use AppBundle\Entity\SincronizacionSheets;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Google_Client;
use Google_Service_Sheets;

class GoogleSheetsService
{
    private $client;
    private $service;
    private $spreadsheetId;

    public function __construct(string $spreadsheetId, string $credentialsPath)
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Integración de Hipotea con Google Sheets');
        $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $this->client->setAuthConfig($credentialsPath);
        $this->client->setAccessType('offline');

        $this->service = new Google_Service_Sheets($this->client);
        $this->spreadsheetId = $spreadsheetId;
    }

    public function readSheet(string $sheetName, string $range = 'A:O'): array
    {
        $response = $this->service->spreadsheets_values->get(
            $this->spreadsheetId,
            "{$sheetName}!{$range}"
        );

        return $response->getValues() ?? [];
    }

    public function getSheetNames(): array
    {
        /** @var Google_Service_Sheets_Spreadsheet $spreadsheet */
        $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);

        $sheetNames = [];
        $numHoja = 0;
        foreach ($spreadsheet->getSheets() as $sheet) {
            if($numHoja > 0){
                $sheetNames[] = $sheet->getProperties()->getTitle();
            }
            $numHoja++;
        }

        return $sheetNames;
    }

    public function getFilteredRecordsAndUpdateSyncDate(
        EntityManagerInterface $em,
        array $sheetNames,
        string $range = 'A1:O1000'
    ): array {
        /** @var SincronizacionSheets $parametros */

        $resultadosFiltrados = [];
 
        $ultimaFecha = "";

        foreach ($sheetNames as $sheetName) {
            try {
                $response = $this->service->spreadsheets_values->get($this->spreadsheetId, "{$sheetName}!{$range}");
                $values = $response->getValues();
            } catch (\Exception $e) {
                continue; // hoja inexistente o error
            }

            if (count($values) < 10) {
                continue;
            }

            $cabecera = $values[0];

            // Aseguramos que hay suficientes columnas
            $indicesDeseados = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14]; // B, D, E
            $filas = array_slice($values, 1);
            $filtradasHoja = [];

            $parametros = $em->getRepository(SincronizacionSheets::class)->findOneBy(['nombreCampania' => $sheetName]);

            if($parametros != null){
                $fechaCorte = $parametros->getFechaSincronizacionSheets();
                $ultimaFecha = $fechaCorte;
            }else {
                
                $parametros = (new SincronizacionSheets())
                    ->setNombreCampania($sheetName)
                    ->setFechaSincronizacionSheets(new \DateTime('1970-01-01 00:00:00'));
                $em->persist($parametros);
                $em->flush();
                $fechaCorte = '1970-01-01 00:00:00';
            }
            
            foreach ($filas as $fila) {
                if (empty($fila[0])) {
                    continue;
                }
                $fechaRegistro = new \DateTime($fila[0]);

                if (!$fechaRegistro) {
                    continue;
                }

                // Solo considerar registros anteriores a la fecha de corte
                if ($fechaRegistro > $fechaCorte) {
                    $registro = [];

                    foreach ($indicesDeseados as $i) {
                        $clave = $cabecera[$i] ?? "Col{$i}";
                        $valor = $fila[$i] ?? null;
                        $registro[$clave] = $valor;
                    }

                    // Filtramos si está totalmente vacío
                    $tieneContenido = array_filter($registro, fn($v) => !empty($v));
                    if (!empty($tieneContenido)) {
                        $filtradasHoja[] = $registro;

                        // Solo actualizamos la última fecha si fue un registro válido
                        if ($fechaRegistro > $ultimaFecha) {
                            $ultimaFecha = $fechaRegistro;
                        }
                    }
                }
            }


            if (!empty($filtradasHoja)) {
                $resultadosFiltrados[$sheetName] = $filtradasHoja;
            }
        }


        return $resultadosFiltrados;
    }
}
