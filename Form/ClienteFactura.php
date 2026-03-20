<?php

namespace AppBundle\Form;

use AppBundle\Entity\ClienteFactura as ClienteFacturaEntidad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClienteFactura extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('razonSocial', TextType::class, array(
			'label' => 'Razón social'
		))->add('cif', TextType::class, array(
			'attr' => array(
				'maxlength' => 9,
				'pattern' => '^(\d{8}[A-Za-z])|([A-Ha-hJjNnP-Sp-sU-Wu-w]\d{7}(\d|[A-Ja-j]))$'
			),
			'label' => 'CIF/NIF'
		))->add('direccion', TextType::class, array(
			'label' => 'Dirección'
		))->add('provincia', TextType::class, array(
			'label' => 'Provincia'
		))->add('municipio', TextType::class, array(
			'label' => 'Municipio'
		))->add('cp', TextType::class, array(
			'label' => 'Código postal'
		))->add('telefono', TextType::class, array(
			'label' => 'Teléfono'
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => ClienteFacturaEntidad::class
		));
	}
}
