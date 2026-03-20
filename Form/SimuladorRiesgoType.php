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
		$builder->add('tienePrestamosImpagados', CheckboxType::class, array(
			'label' => 'Tengo préstamos o créditos impagados',
			'required' => false
		))->add('situacionLaboral', ChoiceType::class, array(
			'choices' => array(
				'Funcionario' => 'funcionario',
				'Contrato indefinido' => 'contrato_indefinido',
				'Contrato temporal' => 'contrato_temporal',
				'Autónomo' => 'autonomo',
				'Empresario' => 'empresario',
				'Otro' => 'otros'
			),
			'label' => 'Situación laboral',
			'required' => true,
			'placeholder' => '- Seleccionar -'
		))->add('antiguedadLaboral', ChoiceType::class, array(
			'choices' => array(
				'Menos de 1 año' => 'menos_1_anio',
				'1 año' => 'un_anio',
				'Más de 2 años' => 'mas_2_anios'
			),
			'label' => 'Antigüedad en el trabajo actual',
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
