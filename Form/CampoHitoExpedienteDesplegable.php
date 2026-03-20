<?php

namespace AppBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampoHitoExpedienteDesplegable extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idOpcionesCampo', EntityType::class, array(
			'choices' => $options['opciones_campo'],
			'choice_attr' => function ($clave) {
				$atributos = array();
				if (!is_null($clave->getIdHitoCondicional())) {
					$atributos['data-id-hito-condicional'] = $clave->getIdHitoCondicional();
				}
				if (!is_null($clave->getIdCampoCondicional())) {
					$atributos['data-id-campo-condicional'] = $clave->getIdCampoCondicional();
				}
				return $atributos;
			},
			'class' => 'AppBundle:OpcionesCampo',
			'label' => 'Escoja una opción',
			'required' => false
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setRequired(array(
			'opciones_campo'
		));
	}

	public function getParent()
	{
		return CampoHitoExpediente::class;
	}
}
