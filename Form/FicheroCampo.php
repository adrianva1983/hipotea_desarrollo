<?php

namespace AppBundle\Form;

use AppBundle\Entity\FicheroCampo as FicheroCampoEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FicheroCampo extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('fichero', FileType::class, array(
			'label' => 'Fichero',
			'multiple' => true,
			'required' => false,
			'attr'     => [
				'multiple' => 'multiple'
			]
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => FicheroCampoEntidad::class
		));
	}
}
