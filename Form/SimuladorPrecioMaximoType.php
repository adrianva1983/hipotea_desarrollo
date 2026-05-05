<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SimuladorPrecioMaximoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('numTitulares', ChoiceType::class, [
                'label' => '¿Cuántos titulares sois?',
                'required' => true,
                'choices' => [
                    'Uno' => 1,
                    'Dos' => 2,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 1,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Seleccione el número de titulares']),
                ],
            ])
            ->add('edadTitularUno', IntegerType::class, [
                'label' => 'Edad del titular uno',
                'required' => true,
                'attr' => ['min' => 20, 'max' => 65, 'placeholder' => 'Edad del titular uno', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La edad del titular uno es obligatoria']),
                    new Assert\Range([ 'min' => 20, 'max' => 65, 'minMessage' => 'La edad mínima es 20 años', 'maxMessage' => 'La edad máxima es 65 años' ]),
                ],
            ])
            ->add('edadTitularDos', IntegerType::class, [
                'label' => 'Edad del titular dos',
                'required' => false,
                'attr' => ['min' => 20, 'max' => 65, 'placeholder' => 'Edad del titular dos', 'class' => 'form-control'],
                'constraints' => [
                    new Assert\Range([ 'min' => 20, 'max' => 65, 'minMessage' => 'La edad mínima es 20 años', 'maxMessage' => 'La edad máxima es 65 años' ]),
                ],
            ])
            ->add('plazoAmortizacion', RangeType::class, [
                'label' => 'Plazo amortización (años)',
                'required' => true,
                'data' => 30,
                'attr' => [
                    'min' => 10,
                    'max' => 40,
                    'value' => 30,
                    'oninput' => 'actualizarPlazoMaximo(this)',
                    'class' => 'plazo-range'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El plazo de amortización es obligatorio']),
                    new Assert\Range([ 'min' => 10, 'max' => 40, 'minMessage' => 'El plazo mínimo es 10 años', 'maxMessage' => 'El plazo máximo es 40 años' ]),
                ],
            ]);

        // Campos adicionales para Paso 2 / cuota (aportación, destino, obra nueva)
        $builder
            ->add('aportacionInicial', MoneyType::class, [
                'label' => '¿Qué cantidad de dinero aportas?',
                'required' => false,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ])
            ->add('destinoCompra', ChoiceType::class, [
                'label' => 'Destino de la compra',
                'required' => false,
                'choices' => [
                    'Vivienda habitual' => 1,
                    'Segunda residencia' => 2,
                    'Inversión' => 3,
                    'Otros' => 4,
                ],
                'placeholder' => '',
                'attr' => ['class' => 'form-control']
            ])
            ->add('comunidadAutonoma', ChoiceType::class, [
                'label' => 'Comunidad autónoma',
                'required' => false,
                'choices' => [
                    'Andalucía' => 1,
                    'Aragón' => 2,
                    'Asturias' => 3,
                    'Baleares' => 4,
                    'Canarias' => 5,
                    'Cantabria' => 6,
                    'Castilla-La Mancha' => 7,
                    'Castilla y León' => 8,
                    'Cataluña' => 9,
                    'Comunidad Valenciana' => 11,
                    'Extremadura' => 12,
                    'Galicia' => 13,
                    'La Rioja' => 14,
                    'Madrid' => 15,
                    'Murcia' => 17,
                    'Navarra' => 18,
                    'País Vasco' => 19,
                ],
                'placeholder' => '',
                'attr' => ['class' => 'form-control']
            ])
            ->add('obraNueva', ChoiceType::class, [
                'label' => '¿Es una obra nueva?',
                'required' => true,
                'choices' => [
                    'Sí' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => true,
            ])
            ->add('minusvaliaFamiliaNumerosa', ChoiceType::class, [
                'label' => '¿Presenta alguna discapacidad?',
                'required' => true,
                'choices' => [
                    'Sí' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => false,
            ])
            ->add('familiaNumerosa', ChoiceType::class, [
                'label' => '¿Eres familia numerosa?',
                'required' => true,
                'choices' => [
                    'Sí' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => false,
            ])
            ->add('monoparental', ChoiceType::class, [
                'label' => '¿Eres familia monoparental?',
                'required' => true,
                'choices' => [
                    'Sí' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => false,
            ])
            ->add('vpo', ChoiceType::class, [
                'label' => '¿Es una Vivienda de Protección Oficial?',
                'required' => true,
                'choices' => [
                    'Sí' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => false,
            ])
            ->add('ingresosMensuales', MoneyType::class, [
                'label' => 'Ingresos Netos mensuales titular uno',
                'required' => false,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ])
            ->add('numPagasExtra', IntegerType::class, [
                'label' => 'Número de pagas extra titular uno',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 20, 'class' => 'form-control'],
            ])
            ->add('importePagaExtra', MoneyType::class, [
                'label' => 'Importe de cada paga extra titular uno',
                'required' => false,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ])
            ->add('prestamosMensuales', MoneyType::class, [
                'label' => 'Prestamos Mensuales titular uno (considerar solo aquellos préstamos con capital pendiente superior a 5.000 €. Poner la suma de todos ellos)',
                'required' => false,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ])
            ->add('ingresosMensualesDos', MoneyType::class, [
                'label' => 'Ingresos Netos mensuales titular dos',
                'required' => false,
                'data' => 0,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ])
            ->add('numPagasExtraDos', IntegerType::class, [
                'label' => 'Número de pagas extra titular dos',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 20, 'class' => 'form-control'],
            ])
            ->add('importePagaExtraDos', MoneyType::class, [
                'label' => 'Importe de cada paga extra titular dos',
                'required' => false,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ])
            ->add('prestamosMensualesDos', MoneyType::class, [
                'label' => 'Prestamos Mensuales titular dos (considerar solo aquellos préstamos con capital pendiente superior a 5.000 €. Poner la suma de todos ellos)',
                'required' => false,
                'attr' => ['placeholder' => 'Importe en €', 'class' => 'form-control'],
                'currency' => 'EUR'
            ]);

        // Validación custom: edad del mayor + plazo <= 75
        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            $plazo = isset($data['plazoAmortizacion']) ? (int)$data['plazoAmortizacion'] : 0;
            $e1 = isset($data['edadTitularUno']) ? (int)$data['edadTitularUno'] : 0;
            $e2 = isset($data['edadTitularDos']) ? (int)$data['edadTitularDos'] : 0;
            $maxEdad = max($e1, $e2);
            if ($maxEdad > 0 && $plazo > 0 && ($maxEdad + $plazo) > 75) {
                if ($form->has('plazoAmortizacion')) {
                    $form->get('plazoAmortizacion')->addError(new FormError('La suma de la edad del titular mayor y el plazo no puede superar 75 años.'));
                } else {
                    $form->addError(new FormError('La suma de la edad del titular mayor y el plazo no puede superar 75 años.'));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // No usar data_class para permitir campos adicionales que no están en la entidad
            'translation_domain' => 'forms',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'simulador_precio_maximo';
    }
}
