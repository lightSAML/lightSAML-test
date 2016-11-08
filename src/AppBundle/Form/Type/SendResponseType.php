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
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @DI\Service()
 * @DI\FormType()
 */
class SendResponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('relayState', TextType::class, [
                'required' => false,
            ])
            ->add('signResponse', CheckboxType::class, [
                'required' => false,
                'data' => true,
            ])
            ->add('signAssertion', CheckboxType::class, [
                'required' => false,
                'data' => true,
            ])
            ->add('encryptAssertion', CheckboxType::class, [
                'required' => false,
                'data' => true,
            ])
            ->add('blockEncryptionAlgorithm', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'TRIPLEDES_CBC' => XMLSecurityKey::TRIPLEDES_CBC,
                    'AES128_CBC' => XMLSecurityKey::AES128_CBC,
                    'AES192_CBC' => XMLSecurityKey::AES192_CBC,
                    'AES256_CBC' => XMLSecurityKey::AES256_CBC,
                ],
                'data' => XMLSecurityKey::AES128_CBC,
            ])
            ->add('keyTransportEncryptionAlgorithm', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'RSA_1_5' => XMLSecurityKey::RSA_1_5,
                    'RSA_OAEP_MGF1P' => XMLSecurityKey::RSA_OAEP_MGF1P,
                ],
                'data' => XMLSecurityKey::RSA_OAEP_MGF1P,
            ])
            ->add('attributes', CollectionType::class, [
                'entry_type' => AttributeType::class,
            ])
        ;
    }
}
