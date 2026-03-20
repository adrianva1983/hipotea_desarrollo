<?php

namespace AppBundle\Form;

use AppBundle\Entity\NoticiaUsuario as NoticiaUsuarioEntidad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NoticiaUsuario extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idUsuario', EntityType::class, array(
			'choices' => $options['usuarios'],
			'choice_attr' => function (UsuarioEntidad $usuario) {
				switch ($usuario->getRole()) {
					case 'ROLE_ADMIN':
						$rol = 1;
						break;
					case 'ROLE_COLABORADOR':
						$rol = 2;
						break;
					case 'ROLE_COMERCIAL':
						$rol = 3;
						break;
					case 'ROLE_TECNICO':
						$rol = 4;
						break;
					case 'ROLE_CLIENTE':
						$rol = 5;
						break;
					default:
						$rol = 5;
				}
				return array(
					'data-rol' => $rol,
					'data-grupo' => $usuario->getTag()
				);
			},
			'choice_label' => function (UsuarioEntidad $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:Usuario',
			'data' => $options['usuariosSeleccionados'],
			'label' => 'Destinatarios',
			'mapped' => false,
			'multiple' => true,
			'required' => false
		))->add('roles', ChoiceType::class, array(
			'choices' => $options['roles'],
			'label' => 'Rol',
			'mapped' => false,
			'multiple' => true,
			'required' => false
		))->add('grupos', ChoiceType::class, array(
			'choices' => $options['tags'],
			'label' => 'Grupo',
			'mapped' => false,
			'multiple' => true,
			'required' => false
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'csrf_protection' => false,
			'data_class' => NoticiaUsuarioEntidad::class
		))->setRequired(array(
			'usuarios',
			'usuariosSeleccionados',
			'roles',
			'tags'
		));
	}
}
