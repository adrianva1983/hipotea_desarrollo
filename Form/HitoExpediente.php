<?php

namespace AppBundle\Form;

use AppBundle\Entity\HitoExpediente as HitoExpedienteEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HitoExpediente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('estado', ChoiceType::class, array(
			'choices' => array(
				'Pendiente' => false,
				'Completado' => true
			),
			'label' => 'Estado'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => HitoExpedienteEntidad::class
		));
	}
}
