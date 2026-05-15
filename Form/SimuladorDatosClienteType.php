<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex; 

class SimuladorDatosClienteType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre completo',
			'required' => true,
			'attr' => array(
				'placeholder' => 'Ej: Juan García López'
			),
			'constraints' => array(
				new NotBlank(array(
					'message' => 'El nombre es obligatorio.'
				))
			)
		))->add('dni', TextType::class, array(
			'label' => 'DNI / NIE / Pasaporte',
			'required' => true,
			'attr' => array(
				'placeholder' => 'Ej: 12345678A o X1234567L o AA1234567'
			),
			'constraints' => array(
				new NotBlank(array(
					'message' => 'El documento es obligatorio.'
				)),
				new Regex(array(
					// Acepta DNI (8 dígitos + letra), NIE (X/Y/Z + 7 dígitos + letra) o números de pasaporte alfanuméricos (5-20 chars)
					'pattern' => '/(^[0-9]{8}[A-Z]$|^[XYZ][0-9]{7}[A-Z]$|^[A-Z0-9]{5,20}$)/',
					'message' => 'El formato del DNI/NIE/Pasaporte no es válido.'
				))
			)
		))->add('telefono', TelType::class, array(
			'label' => 'Teléfono',
			'required' => true,
			'attr' => array(
				'placeholder' => 'Ej: 666123456'
			),
			'constraints' => array(
				new NotBlank(array(
					'message' => 'El teléfono es obligatorio.'
				))
			)
		))->add('email', EmailType::class, array(
			'label' => 'Email',
			'required' => true,
			'attr' => array(
				'placeholder' => 'Ej: juan@example.com'
			),
			'constraints' => array(
				new NotBlank(array(
					'message' => 'El email es obligatorio.'
				)),
				new Email(array(
					'message' => 'El email no es válido.'
				))
			)
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => null
		));
	}
}
