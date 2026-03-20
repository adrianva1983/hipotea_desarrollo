<?php

namespace AppBundle\Form;

use AppBundle\Entity\Hito as HitoEntidad;
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

class Hito extends AbstractType
{
	private $em;

	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		try {
			$builder->add('idFase', EntityType::class, array(
				'attr' => array(
					'hidden' => true
				),
				'class' => 'AppBundle:Fase',
				'data' => $this->em->getReference('AppBundle:Fase', $options['idFase'])
			))->add('nombre', TextType::class, array(
				'label' => 'Nombre'
			))->add('orden', ChoiceType::class, array(
				'choices' => $options['hitos_array'],
				'data' => $options['numero_hitos'],
				'label' => 'Posicion'
			))->add('repetible', ChoiceType::class, array(
				'choices' => array(
					'No' => false,
					'Si' => true
				),
				'label' => 'Repetible'
			))->add('hitoCondicional', ChoiceType::class, array(
				'choices' => array(
					'No' => false,
					'Si' => true
				),
				'label' => 'Ocultar por defecto',
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
			'data_class' => HitoEntidad::class
		))->setRequired(array(
			'idFase',
			'hitos_array',
			'numero_hitos'
		));
	}
}
