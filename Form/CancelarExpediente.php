<?php

namespace AppBundle\Form;

use AppBundle\Entity\Expediente as ExpedienteEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CancelarExpediente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('motivoCancelacion', ChoiceType::class, array(
			'choices' => array(
				'Honorarios' => 1,
				'No le mejoramos la oferta' => 2,
				'Operación no viable por perfil del cliente' => 3,
				'Operación no viable por tasación' => 4,
				'No tienen vivienda' => 5,
				'Compran después de 3 meses' => 6,
				'Importe hipoteca inferior a 100.000 €' => 7,
				'Lo hacen con su banco' => 8,
				'Lo hacen con otra empresa intermediaria' => 9,
				'Duplicado' => 10,
				'Otros' => 11
			),
			'label' => 'Motivo de la cancelación',
			'required' => true
		))->add('texto', TextareaType::class, array(
			'label' => 'Especificar motivo',
			'required' => false
		))->add('enviarEmail', CheckboxType::class, array(
			'label' => 'Enviar email al cliente',
			'data' => false,
			'required' => false
		))->add('submit', SubmitType::class, array(
			'label' => 'Cancelar expediente'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => ExpedienteEntidad::class,
		));
	}
}
