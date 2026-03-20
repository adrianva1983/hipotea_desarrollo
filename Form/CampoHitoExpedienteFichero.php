<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CampoHitoExpedienteFichero extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('valor', TextType::class, array(
			'label' => 'Nombre',
			'required' => false
		))->add('obligatorio', CheckboxType::class, array(
			'label' => 'Solicitar al cliente',
			'required' => false
		))->add('solicitarAlColaborador', CheckboxType::class, array(
			'label' => 'Solicitar al colaborador',
			'required' => false
		))->add('avisarColaborador', CheckboxType::class, array(
			'label' => 'Envío automático',
			'required' => false
		))->add('paraFirmar', CheckboxType::class, array(
			'label' => 'Para firmar',
			'required' => false
		))->add('enviarAlCliente', CheckboxType::class, array(
			'label' => 'Envíar notificación al cliente',
			'required' => false
		))->add('enviarAlColaborador', CheckboxType::class, array(
			'label' => 'Envíar notificación al colaborador',
			'required' => false
		))->add('avisarColaborador', CheckboxType::class, array(
			'label' => 'Envío automático',
			'required' => false
		));
	}

	public function getParent()
	{
		return CampoHitoExpediente::class;
	}
}
