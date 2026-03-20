<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CampoHitoExpedienteFicheroCliente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('valor', TextType::class, array(
			'label' => 'Nombre',
			'required' => $options['obligatorio']
		));
	}

	public function getParent()
	{
		return CampoHitoExpedienteCliente::class;
	}
}
