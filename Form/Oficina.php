<?php

namespace AppBundle\Form;

use AppBundle\Entity\Oficina as OficinaEntidad;
use AppBundle\Entity\Inmobiliaria as InmobiliariaEntidad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Oficina extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		// Determinar el valor inicial del usuarioId si estamos editando
		$usuarioIdInicial = null;
		if (isset($options['data']) && $options['data'] && $options['data']->getIdUsuario()) {
			$usuarioIdInicial = $options['data']->getIdUsuario()->getIdUsuario();
		}

		$builder->add('nombre', TextType::class, array(
			'label' => 'Nombre de la oficina',
			'required' => true
		))->add('idInmobiliaria', EntityType::class, array(
			'choices' => $options['inmobiliarias'],
			'choice_label' => 'nombre',
			'class' => 'AppBundle:Inmobiliaria',
			'label' => 'Inmobiliaria',
			'required' => true
		))->add('usuarioId', HiddenType::class, array(
			'mapped' => false,
			'data' => $usuarioIdInicial,
			'attr' => array(
				'class' => 'usuario-id-field'
			)
		))->add('direccion', TextType::class, array(
			'label' => 'Dirección',
			'required' => false
		))->add('telefono', TextType::class, array(
			'label' => 'Teléfono',
			'required' => false
		))->add('email', EmailType::class, array(
			'label' => 'Email',
			'required' => false
		))->add('codigoPostal', TextType::class, array(
			'label' => 'Código Postal',
			'required' => false
		))->add('ciudad', TextType::class, array(
			'label' => 'Ciudad',
			'required' => false
		))->add('provincia', TextType::class, array(
			'label' => 'Provincia',
			'required' => false
		))->add('activa', CheckboxType::class, array(
			'label' => 'Oficina activa',
			'required' => false,
			'data' => true
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => OficinaEntidad::class
		))->setRequired(array(
			'inmobiliarias',
			'usuarios'
		));
	}
}