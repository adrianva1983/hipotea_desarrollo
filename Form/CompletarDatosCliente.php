<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompletarDatosCliente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('plainPassword', RepeatedType::class, array(
			'invalid_message' => 'Ambas contraseñas deben coincidir',
			'options' => array(
				'attr' => array(
					'autocomplete' => 'new-password',
					'maxlength' => 72
				)
			),
			'type' => PasswordType::class
		))->add('politicaPrivacidad', CheckboxType::class, array(
			'label' => 'Acepto la '
		))->add('contratoFipre', CheckboxType::class, array(
			'label' => 'Acepto el '
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => UsuarioEntidad::class
		));
	}
}
