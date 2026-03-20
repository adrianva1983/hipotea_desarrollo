<?php

namespace AppBundle\Form;

use AppBundle\Entity\Notificacion as NotificacionEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NotificacionExpediente extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('titulo', TextType::class, array(
			'label' => 'Título',
			'required' => false
		))->add('texto', TextareaType::class, array(
			'label' => 'Notificación'
		))->add('cliente', CheckboxType::class, array(
			'label' => 'Cliente',
			'data' => true,
			'required' => false
		))->add('colaborador', CheckboxType::class, array(
			'label' => 'Colaborador',
			'data' => true,
			'required' => false
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => NotificacionEntidad::class,
		));
	}
}
