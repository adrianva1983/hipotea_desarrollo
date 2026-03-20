<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CampoHitoExpedienteTexto extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('valor', TextType::class, array(
			'label' => 'Texto',
			'required' => false
		));
	}

	public function getParent()
	{
		return CampoHitoExpediente::class;
	}
}
