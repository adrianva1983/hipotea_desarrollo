<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalculadoraAvanzadaTest extends AbstractType
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
			'label' => 'Edad',
			'required' => false
		))->add('numTitulares', ChoiceType::class, array(
			'data' => true,
			'choices' => array(
				'Uno' => 1,
				'Dos' => 2
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Cuántos titulares sois?'
			))->add('edadTitularUno', IntegerType::class, array(
			'attr' => array(
				'max' => 65,
				'min' => 18,
				'placeholder' => 'Edad del titular uno'
			),
			'label' => 'Edad del titular uno',
			'required' => false
			))->add('edadTitularDos', IntegerType::class, array(
			'attr' => array(
				'max' => 65,
				'min' => 18,
				'placeholder' => 'Edad del titular dos'
			),
			'label' => 'Edad del titular dos',
			'required' => false
		))->add('tipologiaOperacion', ChoiceType::class, array(
			'data' => true,
			'choices' => array(
				'Compraventa' => 1,
				// 'Cambio de hipoteca' => 2,
				// 'Reunificación' => 3
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => 'Tipología de la operación'
		))->add('comunidadAutonoma', ChoiceType::class, array(
			'choices' => array(
				'Andalucía' => 1,
				'Aragón' => 2,
				'Asturias' => 3,
				'Baleares' => 4,
				'Canarias' => 5,
				'Cantabria' => 6,
				'Castilla-La Mancha' => 7,
				'Castilla y León' => 8,
				'Cataluña' => 9,
				// 'Ceuta' => 10,
				'Comunidad Valenciana' => 11,
				'Extremadura' => 12,
				'Galicia' => 13,
				'La Rioja' => 14,
				'Madrid' => 15,
				// 'Melilla' => 16,
				'Murcia' => 17,
				'Navarra' => 18,
				'País Vasco' => 19
			),
			'label' => 'Comunidad autónoma',
			'required' => false
		))->add('obraNueva', ChoiceType::class, array(
			'data' => true,
			'choices' => array(
				'Sí' => true,
				'No' => false
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Es una obra nueva?',
			'required' => true
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
			'label' => '¿Qué cantidad de dinero aportas?',
			'required' => false
		// ))->add('tieneMenosEdadMaxima', ChoiceType::class, array(
		// 	'data' => false,
		// 	'choices' => array(
		// 		'Sí' => true,
		// 		'No' => false
		// 	),
		// 	'expanded' => true,
		// 	'multiple' => false,
		// 	'attr' => array(
		// 		'class' => 'horizontal'
		// 	),
		// 	'label' => '¿Tienes menos de <span id="edadMaxima">35</span> años?',
		// 	'required' => true
		))->add('minusvaliaFamiliaNumerosa', ChoiceType::class, array(
			'data' => false,
			'choices' => array(
				'Sí' => true,
				'No' => false
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Presenta alguna discapacidad?',
			'required' => true
		))->add('familiaNumerosa', ChoiceType::class, array(
			'data' => false,
			'choices' => array(
				'Sí' => true,
				'No' => false
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Eres familia numerosa?',
			'required' => true
		))->add('monoparental', ChoiceType::class, array(
			'data' => false,
			'choices' => array(
				'Sí' => true,
				'No' => false
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Eres familia monoparental?',
			'required' => true
		))->add('vpo', ChoiceType::class, array(
			'data' => false,
			'choices' => array(
				'Sí' => true,
				'No' => false
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Es una Vivienda de Protección Oficial?',
			'required' => true
		))->add('honorariosInmobiliaria', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Honorarios de la inmobiliaria',
			'required' => false
		))->add('destinoCompra', ChoiceType::class, array(
			'choices' => array(
				'Vivienda habitual' => 1,
				'Segunda residencia' => 2,
				'Inversión' => 3,
				'Otros' => 4
			),
			'label' => 'Destino de la compra',
			'required' => false
		))->add('producto', ChoiceType::class, array(
			'choices' => array(
				'Hipoteca + 80%' => 1,
				'Premium' => 2,
				'Sin Compromiso' => 3,
				'Cambio de casa' => 4
			),
			'label' => 'Producto (elige la tipología de hipoteca que buscas)',
			'required' => false,
			'label_attr' => [
				'class' => 'label-with-tooltip',
			],
			// Añadir un contenedor o ícono extra en el form
			// 'attr' => [
			// 	'data-toggle' => 'tooltip',
			// 	'data-placement' => 'top',
			// 	'title' => 'Elige el tipo de hipoteca que buscas entre las opciones disponibles'
			// ]
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
			'label' => 'Ingresos Netos mensuales titular uno',
			'required' => false
		))->add('numPagasExtra', IntegerType::class, array(
			'attr' => array(
				'max' => 20,
				'min' => 0,
				//'placeholder' => '2'
			),
			'label' => 'Número de pagas extra titular uno',
			'required' => false
		))->add('importePagaExtra', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Importe de cada paga extra titular uno',
			'required' => false
		))->add('prestamosMensuales', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Prestamos Mensuales titular uno (considerar solo aquellos préstamos con capital pendiente superior a 5.000 €. Poner la suma de todos ellos)',
			'data' => '0',
			'required' => false
		))
		
		->add('ingresosMensualesDos', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Ingresos Netos mensuales titular dos',
			'data' => '0',
			'required' => false
		))->add('numPagasExtraDos', IntegerType::class, array(
			'attr' => array(
				'max' => 20,
				'min' => 0,
				//'placeholder' => '2'
			),
			'label' => 'Número de pagas extra titular dos',
			'required' => false
		))->add('importePagaExtraDos', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Importe de cada paga extra titular dos',
			'required' => false
		))->add('prestamosMensualesDos', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe en €'
			),
			'label' => 'Prestamos Mensuales titular dos (considerar solo aquellos préstamos con capital pendiente superior a 5.000 €. Poner la suma de todos ellos)',
			'required' => false
		))->add('plazoAmortizacion', RangeType::class, [
			'attr' => [
				'min' => 10,
				// 'max' => 40,
				// 'value' => 20,  // Valor predeterminado
                'oninput' => 'actualizarPlazoMaximo(this)'
			]
		])
		
		->add('calcular', SubmitType::class, array(
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
