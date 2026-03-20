<?php

namespace AppBundle\Form;

use AppBundle\Entity\AgenteColaborador as AgenteColaboradorEntidad;
use AppBundle\Utils\UsuariosNombreCompleto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampoHitoExpedienteColaboradores extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idEntidadColaboradora', EntityType::class, array(
			'choices' => $options['entidades_colaboradoras'],
			'class' => 'AppBundle:EntidadColaboradora',
			'data' => $options['entidades_colaboradoras_seleccionadas'],
			'label' => 'Entidad colaboradora',
			'mapped' => false,
			'multiple' => true,
			'required' => false
		))->add('idAgenteColaborador', EntityType::class, array(
			'choice_label' => function (AgenteColaboradorEntidad $usuario) {
				return (new UsuariosNombreCompleto())->obtener($usuario);
			},
			'class' => 'AppBundle:AgenteColaborador',
			'data' => $options['agentes_colaboradores_seleccionados'],
			'label' => 'Agente colaborador',
			'mapped' => false,
			'multiple' => true,
			'required' => false
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => 'AppBundle\Entity\CampoHitoExpedienteColaboradores',
			'agentes_colaboradores_seleccionados' => null,
			'entidades_colaboradoras_seleccionadas' => null,
		))->setRequired(array(
			'entidades_colaboradoras'
		));
	}
}
