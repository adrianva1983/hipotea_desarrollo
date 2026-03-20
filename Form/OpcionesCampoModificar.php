<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class OpcionesCampoModificar extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('orden', ChoiceType::class, array(
			'choices' => $options['opciones_campo_array'],
			'label' => 'Posicion'
		));
	}

	public function getParent()
	{
		return OpcionesCampo::class;
	}
}
