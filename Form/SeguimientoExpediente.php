<?php

namespace AppBundle\Form;

use AppBundle\Entity\SeguimientoExpediente as SeguimientoExpedienteEntidad;
use DateTime;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeguimientoExpediente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$dateTime = new DateTime();
		$builder->add('idExpediente', EntityType::class, array(
			'class' => 'AppBundle:Expediente',
			'label' => 'Expediente'
		))->add('idConceptoSeguimientoExpediente', EntityType::class, array(
			'class' => 'AppBundle:ConceptoSeguimientoExpediente',
            'label' => 'Concepto',
            'attr' => array(
				'class' => 'select-concepto'
			)
		))->add('fecha', DateType::class, array(
			'format' => 'dd/MM/yyyy',
			'html5' => false,
			'label' => 'Fecha',
			'required' => false,
			'widget' => 'single_text'
		))->add('comentario', TextType::class, array(
			'label' => 'Comentario'
		))->add('cliente', ChoiceType::class, array(
            'choices' => array(
                'Pendiente' => 0,
                'Terminada' => 1,
                // 'Aprobada' => 2,
                // 'Denegada' => 3,
                // 'Aceptada' => 4
            ),
            'label' => 'Cliente',
        ))->add('colaborador', ChoiceType::class, array(
            'choices' => array(
                'Pendiente' => 0,
                'Terminada' => 1,
                // 'Aprobada' => 2,
                // 'Denegada' => 3,
                // 'Aceptada' => 4
            ),
            'label' => 'Colaborador',
        ))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => SeguimientoExpedienteEntidad::class
		));
	}
}
