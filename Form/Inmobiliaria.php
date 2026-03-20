<?php

namespace AppBundle\Form;

use AppBundle\Entity\Inmobiliaria as InmobiliariaEntidad;
use AppBundle\Entity\Usuario as Usuario;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class Inmobiliaria extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre del Colaborador'
		))->add('idComercial', EntityType::class, array(
			'choices' => $options['comerciales'],
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:Usuario',
			'label' => 'Comercial'
		))->add('idResponsableZona', EntityType::class, array(
			'class' => 'AppBundle:Usuario',
			'query_builder' => function (EntityRepository $er) {
				return $er->createQueryBuilder('u')
					->where('u.role = :role')
					->setParameter('role', 'ROLE_RESPONSABLE_ZONA')
					->orderBy('u.apellidos', 'ASC');
			},
			'choice_label' => function (Usuario $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'label' => 'Responsable de Zona',
			'required' => false,
			'placeholder' => '--- Sin asignar ---',
			'empty_data' => null
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => InmobiliariaEntidad::class
		))->setRequired(array(
			'comerciales'
		));
	}
}
