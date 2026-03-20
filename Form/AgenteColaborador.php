<?php

namespace AppBundle\Form;

use AppBundle\Entity\AgenteColaborador as AgenteColaboradorEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AgenteColaborador extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idEntidadColaboradora', EntityType::class, array(
			'choices' => $options['entidades_colaboradoras'],
			'class' => 'AppBundle:EntidadColaboradora',
			'label' => 'Entidad colaboradora'
		))->add('nombre', TextType::class, array(
			'label' => 'Nombre'
		))->add('apellidos', TextType::class, array(
			'label' => 'Apellidos'
		))->add('email', EmailType::class, array(
			'label' => 'E-Mail',
			'required' => false
		))->add('telefono', TextType::class, array(
			'label' => 'Teléfono',
			'required' => false
		))->add('direccion', TextType::class, array(
			'label' => 'Dirección',
			'required' => false
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => AgenteColaboradorEntidad::class
		))->setRequired(array(
			'entidades_colaboradoras'
		));
	}
}
