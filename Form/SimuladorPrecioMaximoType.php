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

class SimuladorPrecioMaximoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // EDAD
            ->add('edad', IntegerType::class, [
                'label' => 'Edad (años)',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 18,
                    'max' => 80,
                    'placeholder' => 'Ej: 35',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La edad es obligatoria']),
                    new Assert\Range([
                        'min' => 18,
                        'max' => 80,
                        'minMessage' => 'La edad mínima es 18 años',
                        'maxMessage' => 'La edad máxima es 80 años',
                    ]),
                ],
            ])

            // APORTACIÓN DISPONIBLE
            ->add('aportacion', NumberType::class, [
                'label' => 'Aportación Disponible (€)',
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.01,
                    'placeholder' => 'Ej: 30000.00',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La aportación es obligatoria']),
                    new Assert\Type(['type' => 'numeric']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'La aportación debe ser 0 o mayor',
                    ]),
                ],
            ])

            // INGRESOS MENSUALES
            ->add('ingresos_mensuales', NumberType::class, [
                'label' => 'Ingresos Netos Mensuales (€)',
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.01,
                    'placeholder' => 'Ej: 1500.00',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Los ingresos son obligatorios']),
                    new Assert\Type(['type' => 'numeric']),
                    new Assert\GreaterThan([
                        'value' => 0,
                        'message' => 'Los ingresos deben ser mayores que 0',
                    ]),
                ],
            ])

            // NÚMERO DE PAGAS EXTRAORDINARIAS
            ->add('numero_pagas', IntegerType::class, [
                'label' => 'Número de Pagas Extraordinarias',
                'required' => true,
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => 'Ej: 12',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El número de pagas es obligatorio']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'El número de pagas debe ser 0 o mayor',
                    ]),
                ],
            ])

            // IMPORTE DE PAGAS EXTRAORDINARIAS
            ->add('importe_pagas', NumberType::class, [
                'label' => 'Importe de Pagas Extraordinarias (€)',
                'required' => true,
                'scale' => 2,
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.01,
                    'placeholder' => 'Ej: 0.00',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El importe de pagas es obligatorio']),
                    new Assert\Type(['type' => 'numeric']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'El importe debe ser 0 o mayor',
                    ]),
                ],
            ])

            // PRÉSTAMOS MENSUALES
            ->add('prestamos_mensuales', NumberType::class, [
                'label' => 'Préstamos Mensuales (€)',
                'required' => true,
                'scale' => 2,
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.01,
                    'placeholder' => 'Ej: 0.00',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El importe de préstamos es obligatorio']),
                    new Assert\Type(['type' => 'numeric']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'El importe debe ser 0 o mayor',
                    ]),
                ],
            ]);
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
