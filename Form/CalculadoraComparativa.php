<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalculadoraComparativa extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('destino', ChoiceType::class, array(
			'choices' => array(
				'Compra de vivienda' => 1,
				'Mejorar mi hipoteca actual' => 2
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => '¿Para qué sería?'
		))->add('tipoHipoteca', ChoiceType::class, array(
			'choices' => array(
				'Fija' => 1,
				'Mixta' => 2,
				'Variable' => 3
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'label' => 'Tipo de hipoteca'
		))->add('aniosPendientesHipoteca', IntegerType::class, array(
			'attr' => array(
				'placeholder' => '¿Cuántos años te quedan de hipoteca?'
			),
			'label' => '¿Cuántos años te quedan de hipoteca?',
			'required' => false
		))->add('plazoAmortizacion', IntegerType::class, array(
			'attr' => array(
				'placeholder' => 'Plazo de amortización'
			),
			'label' => 'Plazo de amortización',
			'required' => false
		))->add('aniosPlazoFijo', IntegerType::class, array(
			'attr' => array(
				'placeholder' => 'Años de plazo fijo'
			),
			'label' => '¿Cuántos años son el plazo fijo?',
			'required' => false
		))->add('plazoTotal', IntegerType::class, array(
			'attr' => array(
				'placeholder' => 'Plazo total'
			),
			'label' => '¿Plazo total?',
			'required' => false
		))->add('importeHipoteca', MoneyType::class, array(
			'attr' => array(
				'placeholder' => 'Importe hipoteca'
			),
			'label' => 'Importe hipoteca',
			'required' => false
		))->add('tipo', NumberType::class, array(
			'attr' => array(
				'placeholder' => '¿Qué tipo pagas actualmente?'
			),
			'label' => '¿Qué tipo pagas actualmente?',
			'required' => false
		))->add('revision', NumberType::class, array(
			'attr' => array(
				'placeholder' => 'Euribor + '
			),
			'label' => '¿Qué revisión sobre el euríbor te están aplicando?',
			'required' => false
		))->add('tipoFijo', NumberType::class, array(
			'attr' => array(
				'placeholder' => '¿Qué tipo fijo te han ofertado?'
			),
			'label' => '¿Qué tipo fijo te han ofertado?',
			'required' => false
		))->add('tipoVariable', NumberType::class, array(
			'attr' => array(
				'placeholder' => '¿Qué tipo te han ofrecido el primer año?'
			),
			'label' => '¿Qué tipo te han ofrecido el primer año?',
			'required' => false
		))->add('revisionVariable', NumberType::class, array(
			'attr' => array(
				'placeholder' => 'Euribor + '
			),
			'label' => '¿Qué revisión sobre el euríbor te aplicarán?',
			'required' => false
		))
		
		->add('tipoMixta', NumberType::class, array(
			'attr' => array(
				'placeholder' => '¿Qué tipo te han ofrecido los primeros años?'
			),
			'label' => '¿Qué tipo te han ofrecido los primeros años?',
			'required' => false
		))->add('aniosMixta', IntegerType::class, array(
			'attr' => array(
				'placeholder' => 'Años de plazo fijo restantes'
			),
			'label' => '¿Cuántos años de plazo fijo quedan?',
			'required' => false
		))->add('revisionMixta', NumberType::class, array(
			'attr' => array(
				'placeholder' => 'Euribor + '
			),
			'label' => '¿Qué revisión sobre el euríbor te aplicarán?',
			'required' => false
		))
		
		->add('oferta', ChoiceType::class, array(
			// 'data' => true,
			'choices' => array(
				//'Tipo mixto 3 años con vinculación 1%' => 1,
				// 'Tipo mixto 3 años sin vinculación 1.55%' => 2,
				//'Tipo mixto 5 años con vinculación 1%' => 3,
				'Tipo mixto 10 años con vinculación 1.55%' => 4,
				// 'Tipo mixto 5 años sin vinculación 1.55%' => 4,
				// 'Tipo mixto 10 años sin vinculación 1.30%' => 5,
				'Tipo fijo con vinculación 1.70%' => 6,
				'Tipo fijo sin vinculación 2.50%' => 7,
				'Personalizada' => 8,
			),
			'multiple' => false,
			'label' => '¿Con qué oferta deseas compararla?'
		))

		->add('persoTipoHipoteca', ChoiceType::class, array(
			'choices' => array(
				'Fija' => 1,
				'Mixta' => 2,
				'Variable' => 3
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'required' => false,         // No obligatorio
			'placeholder' => null,       // Sin opción "None" adicional
			'data' => null,  
			'label' => 'Personalizada: Tipo de hipoteca'
		))
		->add('persoVinculacion', ChoiceType::class, array(
			'choices' => array(
				'Sí' => 1,
				'No' => 2
			),
			'expanded' => true,
			'multiple' => false,
			'attr' => array(
				'class' => 'horizontal'
			),
			'required' => false,         // No obligatorio
			'placeholder' => null,       // Sin opción "None" adicional
			'data' => null,  
			'label' => 'Personalizada: ¿Tiene vinculación?'
		))
		->add('persoAnios', IntegerType::class, array(
			'attr' => array(
				'placeholder' => 'Años con el tipo fijo'
			),
			'label' => 'Personalizada: Años con el tipo fijo',
			'required' => false
		))
		->add('persoTipo', NumberType::class, array(
			'attr' => array(
				'placeholder' => '¿Qué tipo se ofrece?'
			),
			'label' => 'Personalizada: ¿Qué tipo se ofrece?',
			'required' => false
		))
		->add('persoRevision', NumberType::class, array(
			'attr' => array(
				'placeholder' => 'Euribor + '
			),
			'label' => 'Personalizada: ¿Qué revisión sobre el euríbor se aplicará?',
			'required' => false
		))
		->add('calcular', SubmitType::class, array(
			'label' => 'Calcular'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => 'AppBundle\Entity\CalculadoraComparativa'
		));
	}
}
