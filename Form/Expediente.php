<?php

namespace AppBundle\Form;

use AppBundle\Entity\Expediente as ExpedienteEntidad;
use AppBundle\Entity\Usuario as Usuario;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Expediente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idCliente', EntityType::class, array(
			'choices' => $options['clientes'],
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario, true);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Cliente',
			'required' => $options['idClienteRequerido']
		))->add('vivienda', TextType::class, array(
			'label' => 'Inmueble'
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
		if ($options['esAdmin']) {
			$builder->add('idColaborador', EntityType::class, array(
				'choices' => $options['colaboradores'],
				'choice_label' => function (Usuario $usuario) {
					return (new UsuariosNombreCompleto())->obtener($usuario, true);
				},
				'class' => 'AppBundle:Usuario',
				'label' => 'Colaborador',
				'required' => false
			))->add('idComercial', EntityType::class, array(
				'choices' => $options['comerciales'],
				'choice_label' => function (Usuario $usuario) {
					return (new UsuariosNombreCompleto())->obtener($usuario);
				},
				'class' => 'AppBundle:Usuario',
				'label' => 'Comercial',
				'required' => false
			))->add('idTecnico', EntityType::class, array(
				'choices' => $options['tecnicos'],
				'choice_label' => function (Usuario $usuario) {
					return (new UsuariosNombreCompleto())->obtener($usuario);
				},
				'class' => 'AppBundle:Usuario',
				'label' => 'Tecnico',
				'required' => false
			))->add('idResponsableZona', EntityType::class, array(
				'choices' => isset($options['responsablesZona']) ? $options['responsablesZona'] : array(),
				'choice_label' => function ($usuario) {
					return (new UsuariosNombreCompleto())->obtener($usuario, true);
				},
				'class' => 'AppBundle:Usuario',
				'label' => 'Responsable de Zona',
				'required' => false,
				'placeholder' => '- Seleccionar -',
				'attr' => array('class' => 'select2')
			))->add('whatsappAutomatico', ChoiceType::class, array(
				'choices' => array(
					'Si' => true,
					'No' => false
				),
				'label' => 'Enviar WhatsApp Automático',
				'required' => false,
				'expanded' => false,
				'attr' => array('class' => 'form-control')
			));
		}
		if (!$options['nuevo']) {
			$builder->add('idFaseActual', EntityType::class, array(
				'choices' => $options['fases'],
				'choices' => $options['fasesTMP'],
				'class' => 'AppBundle:Fase',
				'label' => 'Fase'
			))->add('estado', ChoiceType::class, array(
				'choices' => array(
					'Cancelado' => 0,
					'Activo' => 1,
					'Firmado' => 2
				),
				'label' => 'Estado'
			));
		}
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => ExpedienteEntidad::class,
			'idClienteRequerido' => false,
			'esAdmin' => false,
			'colaboradores' => null,
			'comerciales' => null,
			'tecnicos' => null,
			'fases' => null,
			'responsablesZona' => null,
			'fasesTMP' => null  // ? AGREGAR ESTA LÍNEA
		))->setRequired(array(
			'clientes',
			'nuevo'
		));
	}
}
