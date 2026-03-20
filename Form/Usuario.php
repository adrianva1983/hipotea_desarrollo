<?php

namespace AppBundle\Form;

use AppBundle\Entity\Usuario as UsuarioEntidad;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Usuario extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('nif', TextType::class, array(
			'attr' => array(
				'maxlength' => 10
			),
			'label' => 'DNI/Pasaporte/Tarjeta de residencia/NIF'
		))->add('email', RepeatedType::class, array(
			'type' => EmailType::class,
			'first_options' => array(
				'label' => 'E-Mail'
			),
			'options' => array(
				'attr' => array(
					'autocomplete' => 'email'
				)
			),
			'second_options' => array(
				'label' => 'Repetir E-Mail'
			),
			'invalid_message' => 'Ambos E-Mail deben coincidir'
		))->add('plainPassword', RepeatedType::class, array(
			'first_options' => array(
				'label' => 'Contraseña'
			),
			'invalid_message' => 'Ambas contraseñas deben coincidir',
			'options' => array(
				'attr' => array(
					'autocomplete' => 'new-password',
					'maxlength' => 72
				)
			),
			'second_options' => array(
				'label' => 'Repetir contraseña'
			),
			'type' => PasswordType::class
		))->add('role', ChoiceType::class, array(
			'choices' => array(
				//'Cliente' => 'ROLE_CLIENTE',
				//'Colaborador de inmobiliaria' => 'ROLE_COLABORADOR',
				'Comercial' => 'ROLE_COMERCIAL',
				'Tecnico' => 'ROLE_TECNICO',
				'Administrador' => 'ROLE_ADMIN',
				//'Jefe de oficina' => 'ROLE_JEFE_OFICINA',
				//'Jefe de inmobiliaria' => 'ROLE_JEFE_INMOBILIARIA',
				//'Responsable zona' => 'ROLE_RESPONSABLE_ZONA',
			),
			'label' => 'Rol',
			'required' => false
		))->add('maxOperacionesVivas', IntegerType::class, array(
			'label' => 'Máximo número de operaciones vivas',
			'required' => false
		))->add('username', TextType::class, array(
			'label' => 'Nombre'
		))->add('apellidos', TextType::class, array(
			'label' => 'Apellidos'
		))->add('empresa', TextType::class, array(
			'label' => 'Empresa',
			'required' => false
		))->add('idInmobiliaria', EntityType::class, array(
			'class' => 'AppBundle:Inmobiliaria',
			'label' => 'Colaborador',
			'required' => false
		))->add('telefonoMovil', TextType::class, array(
			'label' => 'Teléfono móvil'
		))->add('telefonoFijo', TextType::class, array(
			'label' => 'Teléfono fijo',
			'required' => false
		))->add('direccion', TextType::class, array(
			'label' => 'Dirección',
			'required' => false
		))->add('cp', TextType::class, array(
			'label' => 'Código postal',
			'required' => false
		))->add('provincia', TextType::class, array(
			'label' => 'Provincia',
			'required' => false
		))->add('municipio', TextType::class, array(
			'label' => 'Municipio',
			'required' => false
		))->add('pais', TextType::class, array(
			'label' => 'País',
			'required' => false
		))->add('fechaCaducidad', DateType::class, array(
			'attr' => array(
				'class' => 'js-datepicker'
			),
			'format' => 'dd/MM/yyyy',
			'html5' => false,
			'label' => 'Fecha caducidad',
			'required' => false,
			'widget' => 'single_text'
		))->add('estado', CheckboxType::class, array(
			'data' => true,
			'label' => 'Activo',
			'required' => false
		))->add('tag', TextType::class, array(
			'label' => 'Grupo',
			'required' => false
		))->add('idDireccionComercialAsignado', EntityType::class, array(
			'class' => 'AppBundle:Usuario',
			'label' => 'Director Comercial Asignado',
			'required' => false,
			'placeholder' => 'Selecciona una opción...',
			'choice_label' => function($usuario) {
				return $usuario->getApellidos() . ', ' . $usuario->getUsername();
			},
			'query_builder' => function(\Doctrine\ORM\EntityRepository $repo) {
				return $repo->createQueryBuilder('u')
					->where('u.idUsuario IN (769, 770)')
					->orderBy('u.apellidos', 'ASC');
			}
		))->add('idDireccionExpansionAsignado', EntityType::class, array(
			'class' => 'AppBundle:Usuario',
			'label' => 'Director de Expansión Asignado',
			'required' => false,
			'placeholder' => 'Selecciona una opción...',
			'choice_label' => function($usuario) {
				return $usuario->getApellidos() . ', ' . $usuario->getUsername();
			},
			'query_builder' => function(\Doctrine\ORM\EntityRepository $repo) {
				return $repo->createQueryBuilder('u')
					->where('u.idUsuario IN (1656)')
					->orderBy('u.apellidos', 'ASC');
			}
		))->add('zona', ChoiceType::class, array(
			'choices' => array(
				'Ciudad Jardín' => 0,
				'Arroyo del Moro' => 1,
				'Santa Rosa' => 2,
				'Brillante' => 3,
				'Centro' => 4,
				'Levante' => 5,
				'Sector Sur' => 6,
				'Fátima' => 7
			),
			'label' => 'Zona',
			'required' => false
		))->add('mailerTransport', TextType::class, array(
			'label' => 'Transporte',
			'required' => false,
			'attr' => array(
				'placeholder' => 'smtp'
			)
		))->add('mailerHost', TextType::class, array(
			'label' => 'Servidor',
			'required' => false
		))->add('mailerUser', TextType::class, array(
			'label' => 'Usuario',
			'required' => false
		))->add('mailerPassword', PasswordType::class, array(
			'label' => 'Contraseña',
			'required' => false
		))->add('mailerPort', TextType::class, array(
			'label' => 'Puerto',
			'required' => false,
			'attr' => array(
				'placeholder' => '465'
			)
		))->add('mailerEncryption', TextType::class, array(
			'label' => 'Cifrado',
			'required' => false,
			'attr' => array(
				'placeholder' => 'tls'
			)
		))->add('mailerAuthMode', TextType::class, array(
			'label' => 'Autenticación',
			'required' => false,
			'attr' => array(
				'placeholder' => 'login'
			)
		))->add('firmaCorreo', FileType::class, array(
			'label' => 'Firma de Correo',
			'required' => false,
			'attr'     => [
				'accept' => 'image/*'
			],
			'data_class' => null
		))->add('reset', ResetType::class, array(
			'label' => 'Reiniciar'
		))->add('submit', SubmitType::class, array(
			'label' => 'Aceptar'
		));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => UsuarioEntidad::class,
			'error_mapping' => array(
				'nombre' => 'username'
			),
			'validation_groups' => array(
				'Default',
				'registration',
				'usuarios'
			)
		));
	}
}
