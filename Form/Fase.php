<?php

namespace AppBundle\Form;

use AppBundle\Entity\Fase as FaseEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Fase extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre'
		))->add('tipo', ChoiceType::class, array(
			'choices' => array(
				'Datos' => 0,
				'Documentación' => 1
			),
			'label' => 'Tipo de fase',
			'required' => false
		))->add('color', ColorType::class, array(
			'label' => 'Color'
		))->add('orden', ChoiceType::class, array(
			'choices' => $options['fases_array'],
			'data' => $options['numero_fases'],
			'label' => 'Posicion'
		))->add('final', CheckboxType::class, array(
			'label' => 'Agregar al final',
			'required' => false
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		))->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $evento) {
			$datos = $evento->getData();
			if (isset($datos['final']) && $datos['final']) {
				$datos['orden'] = $evento->getForm()->getConfig()->getOption('numero_fases');
				$evento->setData($datos);
			} elseif ((int)$datos['orden'] === $evento->getForm()->getConfig()->getOption('numero_fases')) {
				$datos['final'] = true;
				$evento->setData($datos);
			}
		});
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => FaseEntidad::class
		))->setRequired(array(
			'fases_array',
			'numero_fases'
		));
	}
}
