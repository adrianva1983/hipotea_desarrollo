<?php

namespace AppBundle\Form;

use AppBundle\Entity\GrupoCamposHito as GrupoCamposHitoEntidad;
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

class GrupoCamposHito extends AbstractType
{
	private $em;

	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		try {
			$builder->add('idHito', EntityType::class, array(
				'attr' => array(
					'hidden' => true
				),
				'class' => 'AppBundle:Hito',
				'data' => $this->em->getReference('AppBundle:Hito', $options['idHito'])
			))->add('nombre', TextType::class, array(
				'label' => 'Nombre',
				'required' => false
			))->add('orden', ChoiceType::class, array(
				'choices' => $options['grupo_campos_hito_array'],
				'data' => $options['numero_grupos_campo_hito'],
				'label' => 'Posicion'
			))->add('repetible', ChoiceType::class, array(
				'choices' => array(
					'No' => false,
					'Si' => true
				),
				'label' => 'Repetible'
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
			'data_class' => GrupoCamposHitoEntidad::class
		))->setRequired(array(
			'idHito',
			'grupo_campos_hito_array',
			'numero_grupos_campo_hito'
		));
	}
}
