<?php

namespace AppBundle\Controller;

use AppBundle\Entity\IaConfig;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;

class IaConfigController extends Controller
{
    public function indexAction()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $em = $this->getDoctrine()->getManager();
        $items = $em->getRepository('AppBundle:IaConfig')->findAll();

        return $this->render('@App/Backoffice/Lista/IaConfig.html.twig', [
            'items' => $items,
            'titulo' => 'Lista de Proveedores IA',
        ]);
    }

    public function newAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $ia = new IaConfig();

        $form = $this->createFormBuilder($ia)
            ->add('provider', TextType::class)
            ->add('apiKey', TextType::class)
            ->add('apiUrl', TextType::class)
            ->add('model', TextType::class)
            ->add('systemPrompt', TextareaType::class, ['required' => false])
            ->add('temperatura', NumberType::class, ['scale' => 2])
            ->add('maxTokens', IntegerType::class)
            ->add('topP', NumberType::class, ['scale' => 2])
            ->add('topK', IntegerType::class)
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('esProveedorPorDefecto', CheckboxType::class, ['required' => false])
            ->add('reset', ResetType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($ia);
            $em->flush();

            $this->addFlash('success', 'Proveedor IA creado.');
            return $this->redirectToRoute('ia_config_index');
        }

        return $this->render('@App/Backoffice/AgregarModificar/IaConfig.html.twig', [
            'entity' => $ia,
            'agregarModificarIaConfig' => $form->createView(),
        ]);
    }

    public function editAction(Request $request, $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $em = $this->getDoctrine()->getManager();
        $ia = $em->getRepository('AppBundle:IaConfig')->find($id);
        if (!$ia) {
            throw $this->createNotFoundException('Proveedor IA no encontrado');
        }

        $form = $this->createFormBuilder($ia)
            ->add('provider', TextType::class)
            ->add('apiKey', TextType::class)
            ->add('apiUrl', TextType::class)
            ->add('model', TextType::class)
            ->add('systemPrompt', TextareaType::class, ['required' => false])
            ->add('temperatura', NumberType::class, ['scale' => 2])
            ->add('maxTokens', IntegerType::class)
            ->add('topP', NumberType::class, ['scale' => 2])
            ->add('topK', IntegerType::class)
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('esProveedorPorDefecto', CheckboxType::class, ['required' => false])
            ->add('reset', ResetType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Proveedor IA actualizado.');
            return $this->redirectToRoute('ia_config_index');
        }

        return $this->render('@App/Backoffice/AgregarModificar/IaConfig.html.twig', [
            'entity' => $ia,
            'agregarModificarIaConfig' => $form->createView(),
        ]);
    }

    public function deleteAction(Request $request, $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $em = $this->getDoctrine()->getManager();
        $ia = $em->getRepository('AppBundle:IaConfig')->find($id);
        if ($ia) {
            $em->remove($ia);
            $em->flush();
            $this->addFlash('success', 'Proveedor IA eliminado.');
        }

        return $this->redirectToRoute('ia_config_index');
    }
}
