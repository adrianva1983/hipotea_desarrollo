<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimuladorRiesgoType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('tienePrestamosImpagados', ChoiceType::class, array(
			'choices' => array(
				'Sí' => true,
				'No' => false
			),
			'label' => '¿Tienes impagos en préstamos o créditos?',
			'required' => true,
			'expanded' => true,
			'multiple' => false
		))->add('situacionLaboral', ChoiceType::class, array(
			'choices' => array(
				'Funcionario' => 'funcionario',
				'Contrato indefinido' => 'contrato_indefinido',
				'Contrato temporal' => 'contrato_temporal',
				'Autónomo' => 'autonomo',
				'Empresario' => 'empresario',
				'Otro' => 'otros'
			),
			'label' => 'Situación laboral - Titular 1',
			'required' => true,
			'placeholder' => '- Seleccionar -'
		))->add('antiguedadLaboral', ChoiceType::class, array(
			'choices' => array(
				'Menos de 1 año' => 'menos_1_anio',
				'1 año' => 'un_anio',
				'Más de 2 años' => 'mas_2_anios'
			),
			'label' => 'Antigüedad en el trabajo actual - Titular 1',
			'required' => true,
			'placeholder' => '- Seleccionar -'
		))->add('situacionLaboralTitularDos', ChoiceType::class, array(
			'choices' => array(
				'Funcionario' => 'funcionario',
				'Contrato indefinido' => 'contrato_indefinido',
				'Contrato temporal' => 'contrato_temporal',
				'Autónomo' => 'autonomo',
				'Empresario' => 'empresario',
				'Otro' => 'otros'
			),
			'label' => 'Situación laboral - Titular 2',
			'required' => false,
			'placeholder' => '- Seleccionar -'
		))->add('antiguedadLaboralTitularDos', ChoiceType::class, array(
			'choices' => array(
				'Menos de 1 año' => 'menos_1_anio',
				'1 año' => 'un_anio',
				'Más de 2 años' => 'mas_2_anios'
			),
			'label' => 'Antigüedad en el trabajo actual - Titular 2',
			'required' => false,
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
