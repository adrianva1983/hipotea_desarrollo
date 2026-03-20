<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SimuladorCuotaGastosType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // NOTA: Los campos de precio y aportación vienen de paso 2 en sesión
            // Este formulario permite ajustes opcionales

            // VALOR DEL INMUEBLE
            ->add('valorInmueble', NumberType::class, [
                'label' => 'Valor del Inmueble (€)',
                'required' => true,
                'scale' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => 'Ej: 200000',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El valor del inmueble es obligatorio']),
                    new Assert\GreaterThan(['value' => 0, 'message' => 'El valor debe ser mayor que 0']),
                ],
            ])

            // DURACIÓN DEL PRÉSTAMO
            ->add('plazoAmortizacion', IntegerType::class, [
                'label' => 'Plazo de Amortización (años)',
                'required' => true,
                'data' => 25,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 5,
                    'max' => 40,
                    'placeholder' => 'Ej: 25',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El plazo es obligatorio']),
                    new Assert\Range([
                        'min' => 5,
                        'max' => 40,
                        'minMessage' => 'El plazo mínimo es 5 años',
                        'maxMessage' => 'El plazo máximo es 40 años',
                    ]),
                ],
            ])

            // COMUNIDAD AUTÓNOMA (CRÍTICO para cálculo de impuestos)
            ->add('comunidadAutonoma', ChoiceType::class, [
                'label' => 'Comunidad Autónoma',
                'choices' => [
                    'Andalucía' => '1',
                    'Aragón' => '2',
                    'Asturias' => '3',
                    'Baleares' => '4',
                    'Canarias' => '5',
                    'Cantabria' => '6',
                    'Castilla-La Mancha' => '7',
                    'Castilla y León' => '8',
                    'Cataluña' => '9',
                    'Comunidad Valenciana' => '11',
                    'Extremadura' => '12',
                    'Galicia' => '13',
                    'La Rioja' => '14',
                    'Madrid' => '15',
                    'Murcia' => '17',
                    'Navarra' => '18',
                    'País Vasco' => '19',
                ],
                'required' => true,
                'data' => '4',  // Default: Baleares
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La comunidad autónoma es obligatoria']),
                    new Assert\Choice([
                        'choices' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '11', '12', '13', '14', '15', '17', '18', '19'],
                        'message' => 'Comunidad autónoma no válida',
                    ]),
                ],
            ])

            // TIPO DE VIVIENDA (re-seleccionar para confirmar)
            ->add('destinoCompra', ChoiceType::class, [
                'label' => 'Destino de la Compra',
                'choices' => [
                    'Vivienda Habitual' => 1,
                    'Segunda Vivienda' => 2,
                    'Inversión/Alquiler' => 3,
                ],
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El destino es obligatorio']),
                ],
            ])

            // TIPO DE INTERÉS (si hay opciones)
            /*->add('tipoInteres', ChoiceType::class, [
                'label' => 'Tipo de Interés Preferido',
                'choices' => [
                    'Tipo Fijo' => 'fijo',
                    'Tipo Variable' => 'variable',
                    'Tipo Mixto' => 'mixto',
                ],
                'required' => true,
                'data' => 'fijo',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Selecciona un tipo de interés']),
                ],
            ])*/

            // CARACTERÍSTICAS ESPECIALES (re-confirmar de paso 2)
            ->add('obraNueva', CheckboxType::class, [
                'label' => 'Es obra nueva',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            ->add('familiaNumerosa', CheckboxType::class, [
                'label' => 'Familia numerosa',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            ->add('monoparental', CheckboxType::class, [
                'label' => 'Familia monoparental',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            ->add('minusvaliaFamiliaNumerosa', CheckboxType::class, [
                'label' => 'Algún miembro tiene discapacidad',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            ->add('vpo', CheckboxType::class, [
                'label' => 'Vivienda de Protección Oficial (VPO)',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            // GASTOS ADICIONALES OPCIONALES
            ->add('honorariosInmobiliaria', NumberType::class, [
                'label' => 'Honorarios Inmobiliaria (?) - Opcional',
                'required' => false,
                'scale' => 2,
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.01,
                    'placeholder' => 'Ej: 0.00',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_domain' => 'forms',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'simulador_cuota_gastos';
    }
}
