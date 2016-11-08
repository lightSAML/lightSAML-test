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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @DI\Service()
 * @DI\FormType()
 */
class FingerPrintType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('x509', TextareaType::class, [
                'required' => true,
                'label' => 'X509 Certificate',
                'attr' => [
                    'rows' => 11,
                ],
            ])
            ->add('algorithm', ChoiceType::class, [
                'required' => true,
                'label' => 'Fingerprint Algorithm',
                'choices' => ['sha1' => 'sha1', 'sha256' => 'sha256', 'sha384' => 'sha384', 'sha512' => 'sha512', 'md5' => 'md5'],
            ])
        ;
    }
}
