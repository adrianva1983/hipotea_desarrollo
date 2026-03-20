<?php

namespace AppBundle\Form;

use AppBundle\Entity\OpcionesCampo as OpcionesCampoEntidad;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpcionesCampo extends AbstractType
{
	private $em;

	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		try {
			$builder->add('idCampoHito', EntityType::class, array(
				'attr' => array(
					'hidden' => true
				),
				'class' => 'AppBundle:CampoHito',
				'data' => $this->em->getReference('AppBundle:CampoHito', $options['idCampoHito'])
			))->add('valor', TextType::class, array(
				'label' => 'Valor'
			))->add('orden', ChoiceType::class, array(
				'choices' => $options['opciones_campo_array'],
				'data' => $options['numero_opciones_campo'],
				'label' => 'Posicion'
			))->add($builder->create('idHitoCondicional', EntityType::class, array(
				'choices' => $options['hitos'],
				'class' => 'AppBundle:Hito',
				'label' => 'Al seleccionar, mostrar el/los hitos',
				'multiple' => true,
				'required' => false
			))->addModelTransformer(new CallbackTransformer(
				function ($string) {
					$array = explode(';', $string);
					$arrayCollection = new ArrayCollection();
					foreach ($array as $elemento) {
						$arrayCollection->add($this->em->getRepository('AppBundle:Hito')->find($elemento));
					}
					return $arrayCollection;
				},
				function ($arrayCollection) {
					$array = array();
					foreach ($arrayCollection as $elemento) {
						$array[] = $elemento->getIdHito();
					}
					return implode(';', $array);
				}
			)))->add($builder->create('idCampoCondicional', EntityType::class, array(
				'choices' => $options['campos_hito'],
				'class' => 'AppBundle:CampoHito',
				'label' => 'Al seleccionar, mostrar el/los campos',
				'multiple' => true,
				'required' => false
			))->addModelTransformer(new CallbackTransformer(
				function ($string) {
					$array = explode(';', $string);
					$arrayCollection = new ArrayCollection();
					foreach ($array as $elemento) {
						$arrayCollection->add($this->em->getRepository('AppBundle:CampoHito')->find($elemento));
					}
					return $arrayCollection;
				},
				function ($arrayCollection) {
					$array = array();
					foreach ($arrayCollection as $elemento) {
						$array[] = $elemento->getIdCampoHito();
					}
					return implode(';', $array);
				}
			)))->add('reset', ResetType::class, array(
				'label' => 'Reiniciar'
			))->add('submit', SubmitType::class, array(
				'label' => 'Aceptar'
			))->get('idCampoCondicional');
		} catch (ORMException $e) {
		}
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => OpcionesCampoEntidad::class
		))->setRequired(array(
			'idCampoHito',
			'opciones_campo_array',
			'numero_opciones_campo',
			'hitos',
			'campos_hito'
		));
	}
}
