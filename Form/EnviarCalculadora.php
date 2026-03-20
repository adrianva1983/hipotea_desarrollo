<?php

namespace AppBundle\Form;

use AppBundle\Entity\EnvioCalculadora;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnviarCalculadora extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre y apellidos'
		))->add('email', EmailType::class, array(
			'label' => 'Email'
		))->add('telefono', TextType::class, array(
			'label' => 'Teléfono'
		))->add('privacidad', CheckboxType::class, array(
			'label' => 'Acepto el aviso legal y la política de privacidad'
		))->add('submit', SubmitType::class, array(
			'label' => 'Calcular resultado'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => EnvioCalculadora::class,
		));
	}
}
