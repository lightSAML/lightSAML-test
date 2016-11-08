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

use AppBundle\Form\Type\SendResponseType;
use LightSaml\Builder\Profile\Metadata\MetadataProfileBuilder;
use LightSaml\Builder\Profile\WebBrowserSso\Sp\SsoSpReceiveResponseProfileBuilder;
use LightSaml\Builder\Profile\WebBrowserSso\Sp\SsoSpSendAuthnRequestProfileBuilder;
use LightSaml\ClaimTypes;
use LightSaml\Context\Profile\Helper\MessageContextHelper;
use LightSaml\Credential\UsageType;
use LightSaml\Event\Events;
use LightSaml\Idp\Builder\Action\Profile\SingleSignOn\Idp\SsoIdpAssertionActionBuilder;
use LightSaml\Idp\Builder\Profile\WebBrowserSso\Idp\SsoIdpReceiveAuthnRequestProfileBuilder;
use LightSaml\Idp\Builder\Profile\WebBrowserSso\Idp\SsoIdpSendResponseProfileBuilder;
use LightSaml\Meta\TrustOptions\TrustOptions;
use LightSaml\Model\Assertion\Attribute;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/saml")
 */
class SamlController extends Controller
{
    /**
     * @Route("/metadata", name="saml.metadata")
     */
    public function metadataAction()
    {
        $profile = new MetadataProfileBuilder($this->get('lightsaml.container.build'));
        $context = $profile->buildContext();
        $action = $profile->buildAction();

        $action->execute($context);

        return $context->getHttpResponseContext()->getResponse();
    }

    /**
     * @Route("/send-authn-request/{idpEntityId}", name="saml.send_authn_request", requirements={
     *      "idpEntityId": ".*"
     * })
     */
    public function sendAuthnRequestAction($idpEntityId, Request $request)
    {
        $container = $this->get('lightsaml.container.build');
        $profile = new SsoSpSendAuthnRequestProfileBuilder($container, $idpEntityId);
        $context = $profile->buildContext();
        $action = $profile->buildAction();

        $relayState = mt_rand(100000, 999999);
        $request->getSession()->set('relayState', $relayState);
        $context->setRelayState($relayState);

        try {
            $action->execute($context);
        } catch (\Exception $ex) {
            return $this->render('error.html.twig', [
                'message' => sprintf('%s: %s', get_class($ex), $ex->getMessage()),
            ]);
        }

        return $context->getHttpResponseContext()->getResponse();
    }

    /**
     * @Route("/receive-response", name="saml.receive_response")
     * @Template("saml/receiveResponse.html.twig")
     */
    public function receiveResponseAction(Request $request)
    {
        $container = $this->get('lightsaml.container.build');
        $profile = new SsoSpReceiveResponseProfileBuilder($container);
        $context = $profile->buildContext();
        $action = $profile->buildAction();

        $receivedXML = '';
        $container->getSystemContainer()->getEventDispatcher()->addListener(Events::BINDING_MESSAGE_RECEIVED, function (GenericEvent $event) use (&$receivedXML) {
            $receivedXML = $event->getSubject();
        });

        try {
            $action->execute($context);
        } catch (\Exception $ex) {
            return $this->render('error.html.twig', [
                'message' => sprintf('%s: %s', get_class($ex), $ex->getMessage()),
                'code' => $receivedXML,
            ]);
        }

        $samlResponse = MessageContextHelper::asResponse($context->getInboundContext());

        $decryptedAssertions = [];
        for ($i = 0; $i < 10; ++$i) {
            /** @var \LightSaml\Model\Context\DeserializationContext $decryptedAssertionContext */
            $decryptedAssertionContext = $context->getPath(sprintf('inbound_message/assertion_encrypted_%s', $i));
            if ($decryptedAssertionContext) {
                $decryptedAssertionContext->getDocument()->formatOutput = true;
                $decryptedAssertions[] = $decryptedAssertionContext->getDocument()->saveXML();
            } else {
                break;
            }
        }

        // received relay state should be left in session in case we want to replay to this AuthnRequest
        $expectedRelayState = $request->getSession()->get('relayState');

        return [
            'expectedRelayState' => $expectedRelayState,
            'samlResponse' => $samlResponse,
            'receivedXML' => $receivedXML,
            'decryptedAssertions' => $decryptedAssertions,
        ];
    }

    /**
     * @Route("/send-response/{spEntityId}", name="saml.send_response", requirements={
     *      "spEntityId": ".*",
     * })
     * @Template("saml/sendResponse.html.twig")
     */
    public function sendResponseAction($spEntityId, Request $request)
    {
        $spEntityDescriptor = $this->get('store.entities')->get($spEntityId);
        if (!$spEntityDescriptor) {
            return $this->render('error.html.twig', ['message' => sprintf('Unknown entity %s', $spEntityId)]);
        }
        $encryptAssertion = false;
        foreach ($spEntityDescriptor->getAllSpKeyDescriptors() as $kd) {
            if ($kd->getUse() == UsageType::ENCRYPTION) {
                $encryptAssertion = true;
            }
        }

        $data = [
            'relayState' => $this->get('session')->get('relayState'),
            'signResponse' => false,
            'signAssertion' => true,
            'encryptAssertion' => $encryptAssertion,
            'attributes' => array_fill(0, 10, ['friendlyName' => '', 'name' => '', 'value' => '']),
        ];
        $data['attributes'][0] = ['friendlyName' => 'Email address', 'name' => 'http://schemas.xmlsoap.org/claims/EmailAddress', 'value' => 'someone@example.com'];
        $data['attributes'][1] = ['friendlyName' => 'Given name', 'name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname', 'value' => 'John'];
        $data['attributes'][2] = ['friendlyName' => 'Surname', 'name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname', 'value' => 'Smith'];

        $form = $this->createForm(SendResponseType::class, $data);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $attributeValueProvider = $this->get('lightsaml.provider.attribute_value');
            foreach ($formData['attributes'] as $arrAttributes) {
                if ($arrAttributes['name']) {
                    $attribute = new Attribute($arrAttributes['name'], $arrAttributes['value']);
                    $attribute->setFriendlyName($arrAttributes['friendlyName']);
                    $attributeValueProvider->add($attribute);
                }
            }

            $container = $this->get('lightsaml.container.build');
            $profile = new SsoIdpSendResponseProfileBuilder(
                $container,
                [new SsoIdpAssertionActionBuilder($container)],
                $spEntityId
            );

            $trustOptions = new TrustOptions();
            $this->get('app.trust_options_store')->add($spEntityId, $trustOptions);
            $trustOptions->setSignResponse($formData['signResponse']);
            $trustOptions->setSignAssertions($formData['signAssertion']);
            $trustOptions->setEncryptAssertions($formData['encryptAssertion']);
            $trustOptions->setBlockEncryptionAlgorithm($formData['blockEncryptionAlgorithm']);
            $trustOptions->setKeyTransportEncryptionAlgorithm($formData['keyTransportEncryptionAlgorithm']);

            $profile->setPartyEntityDescriptor($spEntityDescriptor);
            $profile->setPartyTrustOptions($trustOptions);
            $profile->setRelayState($formData['relayState']);

            $context = $profile->buildContext();
            $action = $profile->buildAction();

            try {
                $action->execute($context);
            } catch (\Exception $ex) {
                return $this->render('error.html.twig', [
                    'message' => sprintf('%s: %s', get_class($ex), $ex->getMessage()),
                ]);
            }

            $this->get('session')->set('relayState', '');

            return $context->getHttpResponseContext()->getResponse();
        }

        return [
            'form' => $form->createView(),
            'claims' => $this->getClaims(),
        ];
    }

    /**
     * @Route("/receive-authn-request", name="saml.receive_authn_request")
     * @Template("saml/receiveAuthnRequest.html.twig")
     */
    public function receiveAuthnRequestAction(Request $request)
    {
        $container = $this->get('lightsaml.container.build');
        $profile = new SsoIdpReceiveAuthnRequestProfileBuilder($container);
        $context = $profile->buildContext();
        $action = $profile->buildAction();

        $receivedXML = '';
        $container->getSystemContainer()->getEventDispatcher()->addListener(Events::BINDING_MESSAGE_RECEIVED, function (GenericEvent $event) use (&$receivedXML) {
            $receivedXML = $event->getSubject();
        });

        try {
            $action->execute($context);
        } catch (\Exception $ex) {
            return $this->render('error.html.twig', [
                'message' => sprintf('%s: %s', get_class($ex), $ex->getMessage()),
                'code' => $receivedXML,
            ]);
        }

        $spEntityId = $context->getPartyEntityContext()->getEntityId();
        $message = $context->getInboundMessage();

        $request->getSession()->set('relayState', $message->getRelayState());

        return [
            'receivedXML' => $receivedXML,
            'authnRequest' => $message,
            'spEntityId' => $spEntityId,
        ];
    }

    private function getClaims()
    {
        $data = [
            ClaimTypes::COMMON_NAME => 'Common name',
            ClaimTypes::EMAIL_ADDRESS => 'Email address',
            ClaimTypes::GIVEN_NAME => 'Given name',
            ClaimTypes::NAME => 'Name',
            ClaimTypes::ADFS_1_EMAIL => 'Email address',
            ClaimTypes::GROUP => 'Group',
            ClaimTypes::ROLE => 'Role',
            ClaimTypes::SURNAME => 'Surname',
        ];

        return $data;
    }
}
