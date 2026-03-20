<?php

namespace AppBundle\Form;

use AppBundle\Entity\EntidadColaboradora as EntidadColaboradoraEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExpedienteEmail extends AbstractType
{

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('idEntidadColaboradora', EntityType::class, array(
			'choices' => $options['entidades_colaboradoras'],
			'choice_attr' => function (EntidadColaboradoraEntidad $entidadColaboradora) {
				return array(
					'data-tipo-entidad' => $entidadColaboradora->getTipoEntidad()
				);
			},
			'class' => 'AppBundle:EntidadColaboradora',
			'label' => 'Entidad colaboradora'
		))->add('idAgenteColaborador', EntityType::class, array(
			'choices' => $options['agentes_colaboradores'],
			'class' => 'AppBundle:AgenteColaborador',
			'label' => 'Agente colaborador'
		))->add('asunto', TextType::class, array(
			'label' => 'Asunto'
		))->add('mensaje', TextareaType::class, array(
			'label' => 'Mensaje'
		))->add('documentos', EntityType::class, array(
			'choices' => $options['documentos'],
			'class' => 'AppBundle:CampoHitoExpediente',
			'expanded' => true,
			'label' => 'Documentos a adjuntar',
			'multiple' => true,
			'required' => false
		))->add('complementar', CheckboxType::class, array(
			'label' => 'Complementar',
			'required' => false
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Enviar Email'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => 'AppBundle\Entity\ExpedienteEmail'
		))->setRequired(array(
			'entidades_colaboradoras',
			'agentes_colaboradores',
			'documentos'
		));
	}

	public function finishView(FormView $view, FormInterface $form, array $options)
	{
		usort($view->children['idEntidadColaboradora']->vars['choices'], function (ChoiceView $a, ChoiceView $b) {
			return strcasecmp($a->label, $b->label);
		});
		usort($view->children['idAgenteColaborador']->vars['choices'], function (ChoiceView $a, ChoiceView $b) {
			return strcasecmp($a->data->getNombre() . ' ' . $a->data->getApellidos(), $b->data->getNombre() . ' ' . $b->data->getApellidos());
		});
	}
}
