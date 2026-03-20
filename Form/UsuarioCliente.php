<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class UsuarioCliente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('email', RepeatedType::class, array(
			'type' => EmailType::class,
			'first_options' => array(
				'label' => 'E-Mail'
			),
			'options' => array(
				'attr' => array(
					'autocomplete' => 'email'
				)
			),
			'second_options' => array(
				'label' => 'Repetir E-Mail'
			),
			'invalid_message' => 'Ambos E-Mail deben coincidir',
			'required' => false
			))->add('telefonoMovil', TextType::class, array(
				'label' => 'Teléfono móvil',
				'required' => false
			))->add('idColaborador', EntityType::class, array(
				'class' => 'AppBundle:Usuario',
				'label' => 'Usuario Colaborador',
				'required' => false,
				'query_builder' => function (EntityRepository $er) {
					$query = $er->createQueryBuilder('u')
					->where('u.estado = 1')
					->andWhere('u.role = \'ROLE_COLABORADOR\'')
					->addOrderBy('u.apellidos', 'ASC')
					->addOrderBy('u.nombre', 'ASC');                
					return $query;
				}
			))->add('plainPassword', RepeatedType::class, array(
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
		))->add('role', TextType::class, array(
				'attr' => array(
					'hidden' => true
				),
				'data' => 'ROLE_CLIENTE',
				'required' => false
			));
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
