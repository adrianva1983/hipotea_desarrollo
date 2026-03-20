<?php

namespace AppBundle\Form;

use AppBundle\Entity\AgenteColaborador as AgenteColaboradorEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampoHitoExpedienteBanco extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idAgenteColaborador', EntityType::class, array(
			'choice_label' => function (AgenteColaboradorEntidad $agenteColaborador) {
				return $agenteColaborador->getIdEntidadColaboradora()->getNombre() . ' - ' . $agenteColaborador->getNombre() . ' ' . $agenteColaborador->getApellidos();
			},
			'choices' => $options['entidades'],
			'class' => 'AppBundle:AgenteColaborador',
			'label' => 'Escoja un agente colaborador',
			'required' => false
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setRequired(array(
			'entidades'
		));
	}

	public function getParent()
	{
		return CampoHitoExpediente::class;
	}

	public function finishView(FormView $view, FormInterface $form, array $options)
	{
		usort($view->children['idAgenteColaborador']->vars['choices'], function (ChoiceView $a, ChoiceView $b) {
			return strcasecmp($a->label, $b->label);
		});
	}
}
