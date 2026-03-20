<?php

namespace AppBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExpedienteEmailCheckboxes extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('campoHito', EntityType::class, array(
			'choices' => $options['campos_hito'],
			'class' => 'AppBundle:CampoHito',
			'expanded' => true,
			'multiple' => true,
			'required' => false
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'csrf_protection' => false,
		))->setRequired(array(
			'campos_hito'
		));
	}
}
