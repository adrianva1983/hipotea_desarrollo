<?php

namespace AppBundle\Form;

use AppBundle\Entity\EntidadColaboradora as EntidadColaboradoraEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntidadColaboradora extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre de la entidad'
		))->add('tipoEntidad', ChoiceType::class, array(
			'choices' => array(
				'Banco' => 1,
				'Tasadora' => 2,
				'Notaría' => 3
			),
			'label' => 'Tipo de entidad'
		))->add('estado', ChoiceType::class, array(
			'choices' => array(
				'Activo' => 1,
				'Inactivo' => 2
			),
			'label' => 'Estado'
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => EntidadColaboradoraEntidad::class
		));
	}
}
