<?php

namespace AppBundle\Form;

use AppBundle\Entity\LineaFactura as LineaFacturaEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LineaFactura extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('concepto', TextType::class, array(
			'label' => 'Concepto'
		))->add('unidades', IntegerType::class, array(
			'attr' => array(
				'min' => 1,
				'placeholder' => '1'
			),
			'label' => 'Unidades',
			'required' => false
		))->add('importe', MoneyType::class, array(
			'attr' => array(
				'placeholder' => '0',
				'type' => 'number'
			),
			'label' => 'Importe',
			'required' => false
		))->add('tipoRetencion', NumberType::class, array(
			'attr' => array(
				'max' => 99,
				'min' => 0,
				'placeholder' => '0',
				'type' => 'number'
			),
			'label' => 'Tipo retención',
			'required' => false
		))->add('tipoIva', ChoiceType::class, array(
			'choices' => array(
				'21' => 21,
				'10' => 10,
				'4' => 4,
				'Exento' => 0
			),
			'label' => '% IVA'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => LineaFacturaEntidad::class
		));
	}
}
