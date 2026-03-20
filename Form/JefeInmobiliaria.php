<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class JefeInmobiliaria extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		// Remover el campo role del padre
		$builder->remove('role');
		
		$builder->add('plainPassword', RepeatedType::class, array(
			'first_options' => array(
				'label' => 'Contraseña'
			),
			'invalid_message' => 'Ambas contraseñas deben coincidir',
			'options' => array(
				'attr' => array(
					'autocomplete' => 'new-password',
					'maxlength' => 72
				)
			),
			'required' => false,
			'second_options' => array(
				'label' => 'Repetir contraseña'
			),
			'type' => PasswordType::class
		))->add('role', ChoiceType::class, array(
			'choices' => array(
				'Cliente' => 'ROLE_CLIENTE',
				'Colaborador de inmobiliaria' => 'ROLE_COLABORADOR',
				/*'Comercial' => 'ROLE_COMERCIAL',
				'Tecnico' => 'ROLE_TECNICO',
				'Administrador' => 'ROLE_ADMIN',*/
				'Jefe de oficina' => 'ROLE_JEFE_OFICINA',
				'Jefe de inmobiliaria' => 'ROLE_JEFE_INMOBILIARIA',
				'Responsable zona' => 'ROLE_RESPONSABLE_ZONA',
			),
			'label' => 'Rol',
			'required' => false,
			'data'     => 'ROLE_JEFE_INMOBILIARIA'
		));
		
		// Event listener para establecer el rol después de la validación
		$builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
			$usuario = $event->getData();
			if ($usuario instanceof UsuarioEntidad) {
				// No forzar el rol si el usuario ya seleccionó uno diferente.
				// Solo asignar el rol por defecto cuando el campo venga vacío (nuevo usuario).
				if (null === $usuario->getRole() || $usuario->getRole() === '') {
					$usuario->setRole('ROLE_JEFE_INMOBILIARIA');
				}
			}
		});
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => UsuarioEntidad::class,
			'validation_groups' => array(
				'Default'
			)
		));
	}

	public function getParent()
	{
		return Usuario::class;
	}
}
