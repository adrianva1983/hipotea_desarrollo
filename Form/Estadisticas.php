<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as Usuario;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Estadisticas extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('x', ChoiceType::class, array(
			'choices' => array(
				'Fases' => 0,
				'Estados' => 1,
				'Clientes' => 2,
				'Colaboradores' => 3,
				'Comerciales' => 4,
				'Tecnicos' => 5
			),
			'label' => 'Eje horizontal'
		))->add('idFase', EntityType::class, array(
			'class' => 'AppBundle:Fase',
			'label' => 'Fases',
			'multiple' => true,
			'required' => false
		))->add('estado', ChoiceType::class, array(
			'choices' => array(
				'Cancelado' => 0,
				'Activo' => 1,
				'Finalizado' => 2
			),
			'label' => 'Estados',
			'multiple' => true,
			'required' => false
		))->add('idCliente', EntityType::class, array(
			'choices' => $options['clientes'],
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Clientes',
			'multiple' => true,
			'required' => false
		))->add('idColaborador', EntityType::class, array(
			'choices' => $options['colaboradores'],
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario, true);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Colaboradores',
			'multiple' => true,
			'required' => false
		))->add('idComercial', EntityType::class, array(
			'choices' => $options['comerciales'],
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Comerciales',
			'multiple' => true,
			'required' => false
		))->add('idTecnico', EntityType::class, array(
			'choices' => $options['tecnicos'],
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Tecnicos',
			'multiple' => true,
			'required' => false
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'csrf_protection' => false,
		))->setRequired(array(
			'clientes',
			'colaboradores',
			'comerciales',
			'tecnicos'
		));
	}
}
