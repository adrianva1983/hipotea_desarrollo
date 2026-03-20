<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class CampoHitoModificar extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('orden', ChoiceType::class, array(
			'choices' => $options['campo_hitos_array'],
			'label' => 'Posicion'
		));
	}

	public function getParent()
	{
		return CampoHito::class;
	}
}
