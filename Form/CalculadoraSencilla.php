<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalculadoraSencilla extends AbstractType
{

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('precioTotal', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Precio Total'
		))->add('aportacionInicial', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Aportación Inicial'
		))->add('tasaInteres', NumberType::class, array(
			'attr' => array(
				'maxlength' => 14,
				'placeholder' => 'En %'
			),
			'label' => 'Tasa de Interés'
		))->add('plazoAmortizacion', IntegerType::class, array(
			'attr' => array(
				'max' => 255,
				'min' => 1,
				'placeholder' => 'En años'
			),
			'label' => 'Plazo de Amortización'
		))->add('calcular', SubmitType::class, array(
			'label' => 'Calcular'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => 'AppBundle\Entity\CalculadoraSencilla'
		));
	}
}
