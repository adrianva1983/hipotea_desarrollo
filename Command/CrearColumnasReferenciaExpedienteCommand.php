<?php

namespace AppBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrearColumnasReferenciaExpedienteCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:crear-columnas-referencia')
            ->setDescription('Crea la columna referencia en la tabla expediente si no existe')
            ->setHelp('Este comando agrega la columna referencia (VARCHAR(8), UNIQUE, NULL) a la tabla expediente si aún no existe.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var Connection $conn */
        $conn = $em->getConnection();
        
        $output->writeln('<info>Verificando columna referencia en tabla expediente...</info>');
        
        try {
            // Verificar si la columna ya existe
            $tableColumns = $conn->getSchemaManager()->listTableColumns('expediente');
            
            if (isset($tableColumns['referencia'])) {
                $output->writeln('<info>? La columna referencia ya existe en la tabla expediente.</info>');
                return 0;
            }
            
            // La columna no existe, crearla
            $output->writeln('<comment>Creando columna referencia en tabla expediente...</comment>');
            
            $sql = "ALTER TABLE expediente ADD COLUMN referencia VARCHAR(8) UNIQUE NULL DEFAULT NULL AFTER vivienda";
            
            $conn->executeUpdate($sql);
            
            $output->writeln('<info>? Columna referencia creada exitosamente.</info>');
            $output->writeln('<info>Estructura de la columna: VARCHAR(8), UNIQUE, NULL</info>');
            
            return 0;
            
        } catch (\Exception $e) {
            $output->writeln('<error>Error al crear la columna: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
