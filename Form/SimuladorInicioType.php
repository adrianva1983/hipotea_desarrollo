<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimuladorInicioType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('aceptaAvisoLegal', CheckboxType::class, array(
			'label' => 'Acepto el aviso legal',
			'required' => true
		))->add('tipoOperacion', ChoiceType::class, array(
			'choices' => array(
				'Compra de vivienda' => 'compra_vivienda',
				'Cambio de hipoteca' => 'cambio_hipoteca',
				'Reunificación de deuda' => 'reunificacion',
				'Garantía hipotecaria' => 'garantia_hipotecaria',
				'Liquidez' => 'liquidez',
				'Autopromoción' => 'autopromocion',
				'Compra de suelo' => 'compra_suelo',
				'Compra local/nave' => 'compra_local_nave'
			),
			'label' => 'Tipo de operación',
			'required' => true,
			'placeholder' => '- Seleccionar -'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => null
		));
	}
}
