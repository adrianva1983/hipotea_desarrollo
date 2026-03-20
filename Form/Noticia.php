<?php

namespace AppBundle\Form;

use AppBundle\Entity\Noticia as NoticiaEntidad;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Noticia extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('titulo', TextType::class, array(
			'label' => 'Título'
		))->add('descripcion', TextareaType::class, array(
			'label' => 'Descripción'
		))->add('fecha', DateType::class, array(
			'format' => 'dd/MM/yyyy',
			'html5' => false,
			'label' => 'Fecha',
			'required' => false,
			'widget' => 'single_text'
		))->add('estado', ChoiceType::class, array(
			'choices' => array(
				'Inactiva' => false,
				'Activa' => true
			),
			'label' => 'Estado',
			'preferred_choices' => array(
				true
			)
		))->add('url', UrlType::class, array(
			'label' => 'Enlace',
			'required' => false
		))->add('fichero', FileType::class, array(
			'label' => 'Imagen',
			'required' => false,
			'attr'     => [
				'accept' => 'image/*'
			]
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => NoticiaEntidad::class,
		));
	}
}
