<?php

namespace AppBundle\Form;

use AppBundle\Entity\Factura as FacturaEntidad;
use DateTime;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Factura extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$dateTime = new DateTime();
		$builder->add('idEmisorFactura', EntityType::class, array(
			'class' => 'AppBundle:EmisorFactura',
			'label' => 'Emisor'
		))->add('idClienteFactura', EntityType::class, array(
			'class' => 'AppBundle:ClienteFactura',
			'label' => 'Cliente'
		))->add('fecha', DateType::class, array(
			'format' => 'dd/MM/yyyy',
			'html5' => false,
			'label' => 'Fecha',
			'required' => false,
			'widget' => 'single_text'
		))->add('serie', TextType::class, array(
			'attr' => array(
				'placeholder' => $dateTime->format('y')
			),
			'label' => 'Serie',
			'required' => false
		))->add('descripcion', TextType::class, array(
			'label' => 'Descripción'
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => FacturaEntidad::class
		));
	}
}
