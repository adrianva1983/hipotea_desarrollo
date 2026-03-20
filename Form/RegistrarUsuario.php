<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrarUsuario extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nif', TextType::class)
			->add('email', RepeatedType::class, array(
				'invalid_message' => 'Ambos E-Mail deben coincidir',
				'options' => array(
					'attr' => array(
						'autocomplete' => 'email',
					)
				),
				'type' => EmailType::class
			))->add('plainPassword', RepeatedType::class, array(
				'invalid_message' => 'Ambas contraseñas deben coincidir',
				'options' => array(
					'attr' => array(
						'autocomplete' => 'new-password',
						'maxlength' => 72
					)
				),
				'required' => false,
				'type' => PasswordType::class
			))->add('username', TextType::class, array(
				'required' => false
			))->add('apellidos', TextType::class, array(
				'required' => false
			))->add('telefonoMovil', TextType::class, array(
				'required' => false
			))->add('politicaPrivacidad', CheckboxType::class, array(
				'label' => 'Acepto la '
			))->add('contratoFipre', CheckboxType::class, array(
				'label' => 'Acepto el '
			))->add('submit', SubmitType::class, array(
				'label' => 'REGISTRAR'
			));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => UsuarioEntidad::class,
			'error_mapping' => array(
				'nombre' => 'username'
			),
			'validation_groups' => array(
				'Default',
				'registration'
			)
		));
	}
}
