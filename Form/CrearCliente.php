<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CrearCliente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nif', TextType::class, array(
			'label' => 'DNI/Pasaporte/Tarjeta de residencia/NIF'
		))->add('email', RepeatedType::class, array(
			'first_options' => array(
				'label' => 'E-Mail'
			),
			'invalid_message' => 'Ambos E-Mail deben coincidir',
			'options' => array(
				'attr' => array(
					'autocomplete' => 'email',
				)
			),
			'second_options' => array(
				'label' => 'Repetir E-Mail'
			),
			'type' => EmailType::class
		))->add('username', TextType::class, array(
			'label' => 'Nombre'
		))->add('apellidos', TextType::class, array(
			'label' => 'Apellidos'
		))->add('telefonoMovil', TextType::class, array(
			'label' => 'Teléfono móvil',
			'required' => false
		))->add('submit', SubmitType::class, array(
			'label' => 'Registrar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => UsuarioEntidad::class,
			'error_mapping' => array(
				'nombre' => 'username'
			)
		));
	}
}
