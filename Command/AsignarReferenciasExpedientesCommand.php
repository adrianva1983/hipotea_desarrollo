<?php

namespace AppBundle\Command;

use AppBundle\Entity\Expediente;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class AsignarReferenciasExpedientesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:asignar-referencias-expedientes')
            ->setDescription('Asigna referencias únicas a todos los expedientes que no las tengan')
            ->setHelp('Este comando asigna referencias únicas (NNNN/YY) a expedientes existentes sin referencia, agrupados por año de creación.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $referenciaService = $container->get('app.referencia_expediente');

        $output->writeln('<info>Iniciando asignación de referencias únicas a expedientes...</info>');

        // Obtener expedientes sin referencia, ordenados por año y fecha de creación
        $expedientesSinReferencia = $em->getRepository(Expediente::class)
            ->createQueryBuilder('e')
            ->where('e.referencia IS NULL')
            ->orderBy('YEAR(e.fechaCreacion)', 'ASC')
            ->addOrderBy('e.fechaCreacion', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($expedientesSinReferencia)) {
            $output->writeln('<info>No hay expedientes sin referencia.</info>');
            return 0;
        }

        $output->writeln('<info>Se encontraron ' . count($expedientesSinReferencia) . ' expedientes sin referencia.</info>');
        $output->writeln('');

        // Barra de progreso
        $progressBar = new ProgressBar($output, count($expedientesSinReferencia));
        $progressBar->setFormat('  %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% elapsed | %estimated:-6s% remaining');
        $progressBar->start();

        $loteSize = 100;
        $contador = 0;

        foreach ($expedientesSinReferencia as $expediente) {
            try {
                $referenciaService->asignarReferenciaAExpediente($expediente);
                $contador++;

                // Hacer flush cada $loteSize registros
                if (($contador % $loteSize) === 0) {
                    $em->flush();
                    // Limpiar identidad map para liberar memoria
                    $em->clear();
                    
                    // Obtener el servicio de nuevo (ya que clear() limpia el contenedor)
                    $referenciaService = $container->get('app.referencia_expediente');
                }

                $progressBar->advance();
            } catch (\Exception $e) {
                $output->writeln('<error>Error asignando referencia al expediente ' . $expediente->getIdExpediente() . ': ' . $e->getMessage() . '</error>');
            }
        }

        // Hacer flush final para los registros restantes
        $em->flush();

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>? Se asignaron ' . $contador . ' referencias únicas correctamente.</info>');

        return 0;
    }
}
