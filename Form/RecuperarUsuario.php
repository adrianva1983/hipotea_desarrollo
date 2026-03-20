<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as Usuario;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecuperarUsuario extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('plainPassword', RepeatedType::class, array(
			'first_options' => array(
				'label' => 'Nueva contraseña'
			),
			'invalid_message' => 'Ambas contraseñas deben coincidir',
			'options' => array(
				'attr' => array(
					'autocomplete' => 'new-password',
					'maxlength' => 72
				)
			),
			'second_options' => array(
				'label' => 'Repetir nueva contraseña'
			),
			'type' => PasswordType::class
		))->add('submit', SubmitType::class, array(
			'label' => 'Recuperar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => Usuario::class,
			'validation_groups' => array(
				'Default',
				'registration'
			)
		));
	}
}
