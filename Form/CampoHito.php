<?php

namespace AppBundle\Form;

use AppBundle\Entity\CampoHito as CampoHitoEntidad;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampoHito extends AbstractType
{
	private $em;

	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		try {
			$builder->add('idGrupoCamposHito', EntityType::class, array(
				'attr' => array(
					'hidden' => true
				),
				'class' => 'AppBundle:GrupoCamposHito',
				'data' => $this->em->getReference('AppBundle:GrupoCamposHito', $options['idGrupoCamposHito'])
			))->add('tipo', ChoiceType::class, array(
				'choices' => array(
					'Texto' => 1,
					'Verdadero/Falso' => 2,
					'Desplegable' => 3,
					'Fichero' => 4,
					'Email' => 5,
					'Fecha' => 6,
					'Banco' => 7,
					'Tasadora' => 8,
					'Notaria' => 9,
					'Espacio blanco' => 10
				),
				'label' => 'Tipo de entidad'
			))->add('nombre', TextType::class, array(
				'label' => 'Nombre'
			))->add('orden', ChoiceType::class, array(
				'choices' => $options['campo_hitos_array'],
				'data' => $options['numero_campo_hitos'],
				'label' => 'Posicion'
			))->add('campoCondicional', ChoiceType::class, array(
				'choices' => array(
					'No' => false,
					'Si' => true
				),
				'label' => 'Ocultar por defecto',
			))->add('mostrarCliente', ChoiceType::class, array(
				'choices' => array(
					'Si' => true,
					'No' => false
				),
				'label' => 'Mostrar a Clientes',
			))->add('mostrarColaborador', ChoiceType::class, array(
				'choices' => array(					
					'Si' => true,
					'No' => false
				),
				'label' => 'Mostrar a Colaboradores',
			))->add('reset', ResetType::class, array(
				'label' => 'Reiniciar'
			))->add('submit', SubmitType::class, array(
				'label' => 'Aceptar'
			));
		} catch (ORMException $e) {
		}
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => CampoHitoEntidad::class
		))->setRequired(array(
			'idGrupoCamposHito',
			'campo_hitos_array',
			'numero_campo_hitos'
		));
	}
}
