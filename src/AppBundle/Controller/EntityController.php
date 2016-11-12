<?php

/*
 * This file is part of the lightSAML-test package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the GPL-3 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Controller;

use LightSaml\Model\Metadata\EntitiesDescriptor;
use LightSaml\Model\Metadata\EntityDescriptor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\File;

/**
 * @Route("/entity")
 */
class EntityController extends Controller
{
    /**
     * @Route("/entity/list", name="entity.list")
     * @Template("entity/list.html.twig")
     */
    public function listAction()
    {
        $entities = $this->get('store.entities')->all();
        $ssoState = $this->get('lightsaml.store.sso_state')->get();

        return [
            'entities' => $entities,
            'ssoState' => $ssoState,
            'ownEntityId' => $this->get('lightsaml.own.entity_descriptor_provider')->get()->getEntityID(),
        ];
    }

    /**
     * @Route("/view/{entityId}", name="entity.view", requirements={
     *      "entityId": ".*"
     * })
     * @Template("entity/view.html.twig")
     */
    public function viewAction($entityId, Request $request)
    {
        $ed = $this->get('store.entities')->get($entityId);
        $form = $this->createFormBuilder()->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->get('store.entities')->remove($entityId);

            return $this->redirectToRoute('entity.list');
        }

        return [
            'ed' => $ed,
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/raw/{entityId}", name="entity.raw", requirements={
     *      "entityId": ".*"
     * })
     * @Template("entity/view.html.twig")
     */
    public function rawAction($entityId)
    {
        $xml = $this->get('store.entities')->getRaw($entityId);

        return new Response($xml, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    /**
     * @Route("/new", name="entity.new")
     * @Template("entity/new.html.twig")
     */
    public function newAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('url', UrlType::class, [
                'required' => false,
            ])
            ->add('file', FileType::class, [
                'required' => false,
                'attr' => [
                    'accept' => '.xml',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                    ]),
                ],
            ])
            ->getForm()
        ;
        $form->handleRequest($request);
        $data = $form->getData();

        if ($form->isValid()) {
            if (!$data['url'] && !$data['file']) {
                $form->get('url')->addError(new FormError('You must either provide a metadata URL or upload it'));
            } else {
                $xmlContent = null;
                $field = null;
                if ($data['file']) {
                    $field = $form->get('file');
                    /** @var UploadedFile $file */
                    $file = $data['file'];
                    $xmlContent = file_get_contents($file->getRealPath());
                } else {
                    $field = $form->get('url');
                    $xmlContent = file_get_contents($data['url']);
                }

                try {
                    $descriptor = $this->get('store.entities')->deserialize($xmlContent);

                    $this->get('store.entities')->add($xmlContent);

                    return $this->redirectToRoute('entity.list');
                } catch (\Exception $ex) {
                    $field->addError(new FormError('Not a valid EntityDescriptor or EntitiesDescriptor XML'));
                }
            }
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
