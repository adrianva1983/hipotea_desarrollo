<?php

namespace AppBundle\Form;

use AppBundle\Entity\Documento as DocumentoEntidad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Documento extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre'
		))->add('descripcion', TextType::class, array(
			'label' => 'Descripción',
			'required' => false
		))->add('estado', CheckboxType::class, array(
			'label' => 'Publicado',
			'required' => false
		))->add('fichero', FileType::class, array(
			'label' => 'Documento',
			'required' => false
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
		if ($options['esAdmin']) {
			$builder->add('idUsuario', EntityType::class, array(
				'choices' => $options['usuarios'],
				'choice_label' => function (UsuarioEntidad $usuario) {
					return (new UsuariosNombreCompleto())->obtener($usuario);
				},
				'class' => 'AppBundle:Usuario',
				'label' => 'Usuario'
			))->add('visiblePara', ChoiceType::class, array(
				'choices' => array(
					'Clientes' => 0,
					'Colaboradores' => 1,
					'Clientes y colaboradores' => 2,
					// 'Administradores, comerciales y técnicos' => 3
				),
				'label' => 'Visible para',
			));
		}
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => DocumentoEntidad::class,
			'esAdmin' => false,
			'usuarios' => null
		));
	}
}
