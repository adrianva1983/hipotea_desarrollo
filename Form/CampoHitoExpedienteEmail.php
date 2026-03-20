<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;

class CampoHitoExpedienteEmail extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('valor', EmailType::class, array(
			'label' => 'Email',
			'required' => false
		));
	}

	public function getParent()
	{
		return CampoHitoExpediente::class;
	}
}
