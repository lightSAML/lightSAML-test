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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @DI\Service()
 * @DI\FormType()
 */
class SelfSignedCertificateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                $builder->create('dn', FormType::class, [
                    'label' => false,
                ])
                ->add('countryName', CountryType::class, [
                    'required' => true,
                ])
                ->add('stateOrProvinceName', TextType::class, [
                    'required' => true,
                ])
                ->add('organizationName', TextType::class, [
                    'required' => true,
                ])
                ->add('commonName', TextType::class, [
                    'required' => true,
                ])
                ->add('localityName', TextType::class, [
                    'required' => false,
                ])
                ->add('organizationUnitName', TextType::class, [
                    'required' => false,
                ])
                ->add('emailAddress', TextType::class, [
                    'required' => false,
                ])
            )
            ->add('days', IntegerType::class, [
                'data' => 365,
            ])
            ->add('bits', ChoiceType::class, [
                'choices' => [1024 => 1024, 2048 => 2048],
            ])
            ->add('password', TextType::class, [
                'required' => false,
            ])
            ->add('digest', ChoiceType::class, [
                'required' => true,
                'choices' => ['SHA1' => 'SHA1', 'SHA256' => 'SGA256'],
            ])
        ;
    }
}
