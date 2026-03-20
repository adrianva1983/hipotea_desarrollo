<?php

namespace AppBundle\Form;

use AppBundle\Entity\CampoHitoExpediente as CampoHitoExpedienteEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampoHitoExpedienteCliente extends AbstractType
{
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => CampoHitoExpedienteEntidad::class,
			'obligatorio' => false
		));
	}
}
