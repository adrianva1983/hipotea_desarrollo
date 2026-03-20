<?php

namespace AppBundle\Controller;

use AppBundle\Entity\KommoMensaje;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/webhook/kommo")
 */
class KommoController extends Controller
{
    /**
     * Endpoint para recibir webhooks de Kommo
     * POST /webhook/kommo/eventos
     * 
     * @Route("/eventos", name="api_webhook_kommo_eventos", methods={"POST"})
     */
    public function webhookKommoEventosAction(Request $request, EntityManagerInterface $em)
    {
        error_log("?? Webhook Kommo recibido");

        // Extrae el payload JSON
        $payload = json_decode($request->getContent(), true);

        if (!$payload) {
            error_log("? Kommo: Payload JSON inválido");
            return new JsonResponse(['error' => 'Payload inválido'], 400);
        }

        try {
            // Valida estructura mínima
            if (!isset($payload['leads']) || !is_array($payload['leads']) || count($payload['leads']) === 0) {
                error_log("? Kommo: No hay leads en el payload");
                return new JsonResponse(['error' => 'No leads found'], 400);
            }

            $lead = $payload['leads'][0];
            $kommoLeadId = $lead['id'] ?? null;

            if (!$kommoLeadId) {
                error_log("? Kommo: Lead ID faltante");
                return new JsonResponse(['error' => 'Lead ID missing'], 400);
            }

            error_log("?? Procesando Lead Kommo ID: $kommoLeadId");

            // Busca si ya existe mensaje para este lead
            $repository = $em->getRepository(KommoMensaje::class);
            $existente = $repository->findOneBy(['kommoLeadId' => $kommoLeadId]);

            if ($existente) {
                error_log("?? Actualizando mensaje existente para Lead: $kommoLeadId");
                $mensaje = $existente;
                $mensaje->setUpdatedAt(new DateTime());
            } else {
                error_log("? Creando nuevo mensaje para Lead: $kommoLeadId");
                $mensaje = new KommoMensaje();
            }

            // Procesa el webhook con validación
            if (!$mensaje->procesarWebhook($payload)) {
                error_log("? Kommo: Error procesando webhook");
                return new JsonResponse(['error' => 'Error processing webhook'], 500);
            }

            // Valida que tenga datos mínimos
            if (!$mensaje->esValido()) {
                error_log("? Kommo: Datos de mensaje inválidos");
                return new JsonResponse(['error' => 'Invalid message data'], 400);
            }

            // Guarda en BD
            $em->persist($mensaje);
            $em->flush();

            error_log("? Kommo: Mensaje guardado exitosamente - ID BD: {$mensaje->getId()}");

            return new JsonResponse([
                'success' => true,
                'id' => $mensaje->getId(),
                'kommo_lead_id' => $kommoLeadId,
                'message' => 'Mensaje procesado correctamente'
            ], 200);

        } catch (\Exception $e) {
            error_log("? Kommo: Excepción - " . $e->getMessage());
            return new JsonResponse([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los mensajes de un Lead (para DEBUG/Admin)
     * GET /webhook/kommo/lead/{kommoLeadId}
     * 
     * @Route("/lead/{kommoLeadId}", name="api_kommo_get_lead_messages", methods={"GET"}, requirements={"kommoLeadId"="\d+"})
     */
    public function getLeadMessagesAction($kommoLeadId, EntityManagerInterface $em)
    {
        $repository = $em->getRepository(KommoMensaje::class);
        $mensajes = $repository->findByKommoLeadId((int)$kommoLeadId);

        if (empty($mensajes)) {
            return new JsonResponse(['error' => 'No messages found'], 404);
        }

        $data = [];
        foreach ($mensajes as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                'kommo_lead_id' => $msg->getKommoLeadId(),
                'message' => $msg->getMessageText(),
                'type' => $msg->getMessageType(),
                'status' => $msg->getStatus(),
                'created_at' => $msg->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $msg->getUpdatedAt() ? $msg->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            ];
        }

        return new JsonResponse($data, 200);
    }

    /**
     * Obtiene estadísticas de mensajes (para DEBUG/Admin)
     * GET /webhook/kommo/estadisticas
     * 
     * @Route("/estadisticas", name="api_kommo_estadisticas", methods={"GET"})
     */
    public function estadisticasAction(EntityManagerInterface $em)
    {
        $repository = $em->getRepository(KommoMensaje::class);

        $total = $repository->createQueryBuilder('km')
            ->select('COUNT(km.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $porEstado = [];
        $estados = ['received', 'processed', 'error'];
        foreach ($estados as $estado) {
            $count = $repository->createQueryBuilder('km')
                ->select('COUNT(km.id)')
                ->where('km.status = :status')
                ->setParameter('status', $estado)
                ->getQuery()
                ->getSingleScalarResult();
            $porEstado[$estado] = $count;
        }

        return new JsonResponse([
            'total_mensajes' => $total,
            'por_estado' => $porEstado,
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
        ], 200);
    }
}
