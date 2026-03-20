<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalculadoraAvanzada extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('tipo', ChoiceType::class, array(
			'choices' => array(
				'Cuota mensual' => 1,
				'Precio máximo vivienda' => 2
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Qué quieres calcular?'
		))->add('edad', IntegerType::class, array(
			'attr' => array(
				'max' => 65,
				'min' => 18,
				'placeholder' => '¿Edad del mayor de los titulares?'
			),
			'label' => 'Edad'
		// ))->add('tipologiaOperacion', ChoiceType::class, array(
		// 	'choices' => array(
		// 		'Compraventa' => 1,
		// 		'Cambio de hipoteca' => 2,
		// 		'Reunificación' => 3
		// 	),
		// 	'expanded' => true,
		// 	'multiple' => false,
		// 	'attr' => array(
		// 		'class' => 'horizontal'
		// 	),
		// 	'label' => 'Tipología de la operación'
		))->add('valorInmueble', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Valor del inmueble',
			'required' => false
		))->add('aportacionInicial', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => '¿Cantidad que aporta el cliente?',
			'required' => false
		))->add('producto', ChoiceType::class, array(
			'choices' => array(
				'Hipoteca 100%' => 1,
				'Premium' => 2,
				'Sin Compromiso' => 3,
				'Cambio de casa' => 4
			),
			'label' => 'Producto',
			'required' => false
		))->add('valorViviendaActual', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Valor de la vivienda actual',
			'required' => false
		))->add('hipotecaActual', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Hipoteca actual',
			'required' => false
			// ))->add('ventaCasaActual', MoneyType::class, array(
			// 	'attr' => array(
			// 		'placeholder' => 'Importe en €'
			// 	),
			// 	'label' => 'Venta de casa actual',
			// 	'required' => false
		))->add('aportacionTrasVenta', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Aportación tras la venta',
			'required' => false
		))->add('ingresosMensuales', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Ingresos Mensuales',
			'required' => false
		))->add('numPagasExtra', IntegerType::class, array(
			'attr' => array(
				'max' => 20,
				'min' => 0,
				//'placeholder' => '2'
			),
			'label' => 'Número de pagas extra',
			'required' => false
		))->add('importePagaExtra', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Importe de cada paga extra',
			'required' => false
		))->add('prestamosMensuales', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Prestamos Mensuales',
			'required' => false
		))->add('calcular', SubmitType::class, array(
			'label' => 'Calcular'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => 'AppBundle\Entity\CalculadoraAvanzada'
		));
	}
}
