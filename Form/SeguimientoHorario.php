<?php

namespace AppBundle\Form;

use AppBundle\Entity\SeguimientoHorario as SeguimientoHorarioEntidad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeguimientoHorario extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idCliente', EntityType::class, array(
			'choices' => $options['clientes'],
			'choice_label' => function (UsuarioEntidad $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Cliente',
			'required' => false
		))->add('datosCliente', TextType::class, array(
			'attr' => array(
				'placeholder' => 'Información del cliente si no está en el sistema, Nombre, tlf'
			),
			'label' => 'Datos del cliente',
			'required' => false
		))->add('idInmobiliaria', EntityType::class, array(
			'class' => 'AppBundle:Inmobiliaria',
			'label' => 'Inmobiliaria',
			'required' => false
		))->add('tipo', ChoiceType::class, array(
			'choices' => array(
				'Apertura de cuenta' => 'Apertura de cuenta',
				'Entrega publicidad' => 'Entrega publicidad',
				'Entrevista banco' => 'Entrevista banco',
				'Pago comisión' => 'Pago comisión',
				'Recogida documentación' => 'Recogida documentación',
				'Reunión cliente en oficina' => 'Reunión cliente en oficina',
				'Reunión cliente fuera oficina' => 'Reunión cliente fuera oficina',
				'Tasación' => 'Tasación',
				'Visita comercial' => 'Visita comercial',
				'Notaría' => 'Notaría'
			),
			'choice_attr' => function ($key) {
				switch ($key) {
					case 'Apertura de cuenta':
						$color = 'red';
						break;
					case 'Entrega publicidad':
						$color = 'maroon';
						break;
					case 'Entrevista banco':
						$color = 'lime';
						break;
					case 'Pago comisión':
						$color = 'green';
						break;
					case 'Recogida documentación':
						$color = 'blue';
						break;
					case 'Reunión cliente en oficina':
						$color = 'navy';
						break;
					case 'Reunión cliente fuera oficina':
						$color = 'fuchsia';
						break;
					case 'Tasación':
						$color = 'purple';
						break;
					case 'Visita comercial':
						$color = 'teal';
						break;
					case 'Notaría':
						$color = 'bisque';
						break;
					default:
						$color = 'primary';
				}
				return array(
					'data-class' => 'bg-' . $color
				);
			},
			'label' => 'Tipo',
		))->add('observaciones', TextType::class, array(
			'label' => 'Observaciones',
			'required' => false
		))->add('estado', ChoiceType::class, array(
			'choices' => array(
				'Cancelado' => 0,
				'Planificado' => 1,
				'Realizado' => 2
			),
			'label' => 'Estado',
			'preferred_choices' => array(
				1
			)
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => SeguimientoHorarioEntidad::class
		))->setRequired(
			'clientes'
		);
	}
}
