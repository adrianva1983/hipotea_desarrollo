<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class UsuarioClienteModificar extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idColaborador', EntityType::class, array(
			'class' => 'AppBundle:Usuario',
			'label' => 'Colaborador Inmobiliaria',
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
			'required' => false,
			'type' => PasswordType::class
		))->add('estado', CheckboxType::class, array(
			'label' => 'Activo',
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
		return UsuarioCliente::class;
	}
}
