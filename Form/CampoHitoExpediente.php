<?php

namespace AppBundle\Form;

use AppBundle\Entity\CampoHitoExpediente as CampoHitoExpedienteEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampoHitoExpediente extends AbstractType
{
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => CampoHitoExpedienteEntidad::class
		));
	}
}
