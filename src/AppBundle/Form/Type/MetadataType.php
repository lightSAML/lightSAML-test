<?php

/*
 * This file is part of the lightSAML-test package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the GPL-3 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Form\Type;

use JMS\DiExtraBundle\Annotation as DI;
use LightSaml\SamlConstants;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @DI\Service()
 * @DI\FormType()
 */
class MetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('entity_id', TextType::class, [
                'required' => true,
            ])
        ;
        if ($options['sso']) {
            $builder->add('sso', UrlType::class, [
                'required' => true,
                'label' => 'Single Sign On Service Endpoint (HTTP-Redirect)',
            ]);
        }
        if ($options['acs']) {
            $builder->add('acs', UrlType::class, [
                'required' => true,
                'label' => 'Assertion Consumer Service Endpoint (HTTP-POST)',
            ]);
        }
        $builder
            ->add('slo', UrlType::class, [
                'required' => false,
                'label' => 'Single Logout Service Endpoint (HTTP-Redirect)',
            ])
            ->add('name_id_format', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    SamlConstants::NAME_ID_FORMAT_EMAIL => SamlConstants::NAME_ID_FORMAT_EMAIL,
                    SamlConstants::NAME_ID_FORMAT_PERSISTENT => SamlConstants::NAME_ID_FORMAT_PERSISTENT,
                    SamlConstants::NAME_ID_FORMAT_TRANSIENT => SamlConstants::NAME_ID_FORMAT_TRANSIENT,
                    SamlConstants::NAME_ID_FORMAT_X509_SUBJECT_NAME => SamlConstants::NAME_ID_FORMAT_X509_SUBJECT_NAME,
                    SamlConstants::NAME_ID_FORMAT_UNSPECIFIED => SamlConstants::NAME_ID_FORMAT_UNSPECIFIED,
                ],
            ])
            ->add('want_authn_requests_signed', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'False' => false,
                    'True' => true,
                ],
            ])
            ->add('x509_signing', TextareaType::class, [
                'required' => false,
                'label' => 'X509 certificate for signing usage',
                'attr' => ['rows' => 11],
            ])
            ->add('x509_encryption', TextareaType::class, [
                'required' => false,
                'label' => 'X509 certificate for encryption usage',
                'attr' => ['rows' => 11],
            ])
            ->add(
                $builder
                    ->create('organization', FormType::class, [
                        'required' => false,
                    ])
                    ->add('organization_name', TextType::class, [
                        'required' => false,
                    ])
                    ->add('organization_display_name', TextType::class, [
                        'required' => false,
                    ])
                    ->add('organization_url', TextType::class, [
                        'required' => false,
                    ])
            )
            ->add(
                $builder
                    ->create('technical_contact', FormType::class, [
                        'required' => false,
                    ])
                    ->add('given_name', TextType::class, [
                        'required' => false,
                    ])
                    ->add('email', EmailType::class, [
                        'required' => false,
                    ])
            )
            ->add(
                $builder
                    ->create('support_contact', FormType::class, [
                        'required' => false,
                    ])
                    ->add('given_name', TextType::class, [
                        'required' => false,
                    ])
                    ->add('email', EmailType::class, [
                        'required' => false,
                    ])
            )
            ->add('private_key', TextareaType::class, [
                'required' => false,
                'label' => 'Private key for metadata signing',
                'attr' => ['rows' => 11],
            ])
            ->add('password', PasswordType::class, [
                'required' => false,
                'label' => 'Private key password',
            ])
            ->add('x509', TextareaType::class, [
                'required' => false,
                'label' => 'X509 for metadata signing',
                'attr' => ['rows' => 11],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('sso', false)
            ->setDefault('acs', false)
        ;
    }
}
