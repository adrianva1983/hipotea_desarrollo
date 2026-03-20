<?php

namespace AppBundle\Form;

use AppBundle\Entity\Notificacion as NotificacionEntidad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Notificacion extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idUsuario', EntityType::class, array(
			'attr' => array(
				'placeholder' => 'Por defecto se envia a todos los clientes'
			),
			'choices' => $options['clientes'],
			'choice_label' => function (UsuarioEntidad $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario, true);
			},
			'class' => 'AppBundle:Usuario',
			'multiple' => true,
			'label' => 'Usuarios',
			'required' => false
		))/*->add('idExpediente', EntityType::class, array(
			'class' => 'AppBundle:Expediente',
			'label' => 'Expediente',
			'required' => false
		))*/ ->add('titulo', TextType::class, array(
			'attr' => array(
				'placeholder' => 'Por defecto: Información'
			),
			'label' => 'Título',
			'required' => false
		))->add('texto', TextareaType::class, array(
			'label' => 'Notificación'
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => NotificacionEntidad::class,
		))->setRequired(array(
			'clientes'
		));
	}
}
