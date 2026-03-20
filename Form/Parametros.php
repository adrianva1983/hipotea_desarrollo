<?php

namespace AppBundle\Form;

use AppBundle\Entity\Parametros as ParametrosEntidad;
use DateTime;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Parametros extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
		->add('edadAmortizacion', IntegerType::class, array(
			'label' => 'Edad Amortizacion',
			'required' => false
		))
		->add('gastosInmobiliaria', NumberType::class, array(
			'label' => 'Gastos Inmobiliaria',
			'required' => false
		))
		->add('porComisionApertura', NumberType::class, array(
			'label' => '% Comisión Apertura',
			'required' => false
		))
		->add('honorariosFinanciacion', NumberType::class, array(
			'label' => 'Honorarios Financiacion',
			'required' => false
		))
		->add('tasacion', IntegerType::class, array(
			'label' => 'Gastos Tasación',
			'required' => false
		))
		->add('vinculaciones', IntegerType::class, array(
			'label' => 'Vinculaciones',
			'required' => false
		))
		->add('escrituraCompraNotario', IntegerType::class, array(
			'label' => 'Escritura Compra Notario',
			'required' => false
		))
		->add('escrituraCompraRegistro', IntegerType::class, array(
			'label' => 'Escritura Compra Registro',
			'required' => false
		))
		->add('escrituraCompraGestoria', IntegerType::class, array(
			'label' => 'Escritura Compra Gestoria',
			'required' => false
		))
		->add('gastosImporteMaximo', IntegerType::class, array(
			'label' => 'Gastos Importe Maximo',
			'required' => false
		))
		->add('interesImporteMaximo', NumberType::class, array(
			'label' => 'Interes Importe Maximo',
			'required' => false
		))
		->add('tipoCienVariable', NumberType::class, array(
			'label' => 'Cien Tipo Variable',
			'required' => false
		))
		// ->add('tipoCienFijo1519', NumberType::class, array(
		// 	'label' => 'Cien Tipo Fijo',
		// 	'required' => false
		// ))
		->add('tipoCienFijo2024', NumberType::class, array(
			'label' => 'Cien Tipo Mixto',
			'required' => false
		))
		->add('tipoCienFijo2530', NumberType::class, array(
			'label' => 'Cien Tipo Fijo',
			'required' => false
		))
		->add('tipoPremiumVariable', NumberType::class, array(
			'label' => 'Premium Tipo Variable',
			'required' => false
		))
		// ->add('tipoPremiumFijo1519', NumberType::class, array(
		// 	'label' => 'Premium Tipo Fijo 15-19 años amortización',
		// 	'required' => false
		// ))
		->add('tipoPremiumFijo2024', NumberType::class, array(
			'label' => 'Premium Tipo Mixto',
			'required' => false
		))
		->add('tipoPremiumFijo2530', NumberType::class, array(
			'label' => 'Premium Tipo Fijo',
			'required' => false
		))
		->add('tipoSinCompromisoVariable', NumberType::class, array(
			'label' => 'Sin Compromiso Tipo Variable',
			'required' => false
		))
		->add('tipoSinCompromisoFijoMenos20', NumberType::class, array(
			'label' => 'Sin Compromiso Mixto',
			'required' => false
		))
		->add('tipoSinCompromisoFijo2030', NumberType::class, array(
			'label' => 'Sin Compromiso Fijo',
			'required' => false
		))
		->add('tipoCambioCasaVariable', NumberType::class, array(
			'label' => 'Cambio de Casa Tipo Variable',
			'required' => false
		))
		->add('tipoCambioCasaFijo', NumberType::class, array(
			'label' => 'Cambio de Casa Tipo Fijo',
			'required' => false
		))
		->add('tipoCienFijo1519', NumberType::class, array(
			'label' => 'Cambio de Casa Tipo Mixto',
			'required' => false
		))
		->add('levantamientoRegistral', IntegerType::class, array(
			'label' => 'Levantamiento Registral',
			'required' => false
		))

		->add('andaluciaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para andaluciaItpHabitual
		->add('andaluciaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para andaluciaAjdGeneral
		->add('andaluciaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para andaluciaAjdHabitual
		->add('andaluciaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		->add('aragonItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para aragonItpHabitual
		->add('aragonItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para aragonAjdGeneral
		->add('aragonAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para aragonAjdHabitual
		->add('aragonAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para asturiasItpGeneral
		->add('asturiasItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para asturiasItpHabitual
		->add('asturiasItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para asturiasAjdGeneral
		->add('asturiasAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para asturiasAjdHabitual
		->add('asturiasAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para balearesItpGeneral
		->add('balearesItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para balearesItpHabitual
		->add('balearesItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para balearesAjdGeneral
		->add('balearesAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para balearesAjdHabitual
		->add('balearesAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para canariasItpGeneral
		->add('canariasItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para canariasItpHabitual
		->add('canariasItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para canariasAjdGeneral
		->add('canariasAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para canariasAjdHabitual
		->add('canariasAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para cantabriaItpGeneral
		->add('cantabriaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para cantabriaItpHabitual
		->add('cantabriaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para cantabriaAjdGeneral
		->add('cantabriaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para cantabriaAjdHabitual
		->add('cantabriaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para castillaLaManchaItpGeneral
		->add('castillaLaManchaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para castillaLaManchaItpHabitual
		->add('castillaLaManchaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para castillaLaManchaAjdGeneral
		->add('castillaLaManchaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para castillaLaManchaAjdHabitual
		->add('castillaLaManchaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para castillaYLeonItpGeneral
		->add('castillaYLeonItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para castillaYLeonItpHabitual
		->add('castillaYLeonItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para castillaYLeonAjdGeneral
		->add('castillaYLeonAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para castillaYLeonAjdHabitual
		->add('castillaYLeonAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])


		// Para catalunyaItpGeneral
		->add('catalunyaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para catalunyaItpHabitual
		->add('catalunyaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para catalunyaAjdGeneral
		->add('catalunyaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para catalunyaAjdHabitual
		->add('catalunyaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])


		// Para comunidadValencianaItpGeneral
		->add('comunidadValencianaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para comunidadValencianaItpHabitual
		->add('comunidadValencianaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para comunidadValencianaAjdGeneral
		->add('comunidadValencianaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para comunidadValencianaAjdHabitual
		->add('comunidadValencianaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para extremaduraItpGeneral
		->add('extremaduraItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para extremaduraItpHabitual
		->add('extremaduraItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para extremaduraAjdGeneral
		->add('extremaduraAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para extremaduraAjdHabitual
		->add('extremaduraAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para galiciaItpGeneral
		->add('galiciaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para galiciaItpHabitual
		->add('galiciaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para galiciaAjdGeneral
		->add('galiciaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para galiciaAjdHabitual
		->add('galiciaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para madridItpGeneral
		->add('madridItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para madridItpHabitual
		->add('madridItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para madridAjdGeneral
		->add('madridAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para madridAjdHabitual
		->add('madridAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para murciaItpGeneral
		->add('murciaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para murciaItpHabitual
		->add('murciaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para murciaAjdGeneral
		->add('murciaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para murciaAjdHabitual
		->add('murciaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para navarraItpGeneral
		->add('navarraItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para navarraItpHabitual
		->add('navarraItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para navarraAjdGeneral
		->add('navarraAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para navarraAjdHabitual
		->add('navarraAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para paisVascoItpGeneral
		->add('paisVascoItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para paisVascoItpHabitual
		->add('paisVascoItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para paisVascoAjdGeneral
		->add('paisVascoAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para paisVascoAjdHabitual
		->add('paisVascoAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])

		// Para riojaItpGeneral
		->add('riojaItpGeneral', NumberType::class, [
			'label' => 'ITP General',
			'required' => false
		])

		// Para riojaItpHabitual
		->add('riojaItpHabitual', NumberType::class, [
			'label' => 'ITP Habitual',
			'required' => false
		])

		// Para riojaAjdGeneral
		->add('riojaAjdGeneral', NumberType::class, [
			'label' => 'AJD General',
			'required' => false
		])

		// Para riojaAjdHabitual
		->add('riojaAjdHabitual', NumberType::class, [
			'label' => 'AJD Habitual',
			'required' => false
		])


		->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))
		->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => ParametrosEntidad::class
		));
	}
}
