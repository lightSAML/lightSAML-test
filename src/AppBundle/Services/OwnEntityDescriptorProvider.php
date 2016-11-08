<?php

/*
 * This file is part of the lightSAML-test package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the GPL-3 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Services;

use JMS\DiExtraBundle\Annotation as DI;
use LightSaml\Credential\UsageType;
use LightSaml\Credential\X509Credential;
use LightSaml\Model\Metadata\AssertionConsumerService;
use LightSaml\Model\Metadata\EntityDescriptor;
use LightSaml\Model\Metadata\IdpSsoDescriptor;
use LightSaml\Model\Metadata\KeyDescriptor;
use LightSaml\Model\Metadata\SingleSignOnService;
use LightSaml\Model\Metadata\SpSsoDescriptor;
use LightSaml\Provider\EntityDescriptor\EntityDescriptorProviderInterface;
use LightSaml\SamlConstants;
use LightSaml\Store\Credential\CredentialStoreInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @DI\Service(id="own_entity_descriptor_provider")
 */
class OwnEntityDescriptorProvider implements EntityDescriptorProviderInterface
{
    /** @var string */
    private $ownEntityId;

    /** @var CredentialStoreInterface */
    private $ownCredentialStore;

    /** @var RouterInterface */
    private $router;

    /**
     * @param string                   $ownEntityId
     * @param CredentialStoreInterface $ownCredentialStore
     * @param RouterInterface          $router
     *
     * @DI\InjectParams({
     *     "ownEntityId": @DI\Inject("%lightsaml.own.entity_id%"),
     *     "ownCredentialStore": @DI\Inject("lightsaml.own.credential_store"),
     *     "router": @DI\Inject("router"),
     * })
     */
    public function __construct($ownEntityId, CredentialStoreInterface $ownCredentialStore, RouterInterface $router)
    {
        $this->ownEntityId = $ownEntityId;
        $this->ownCredentialStore = $ownCredentialStore;
        $this->router = $router;
    }

    public function get()
    {
        $result = new EntityDescriptor();
        $result->setEntityID($this->ownEntityId);

        $ownCredentials = $this->ownCredentialStore->getByEntityId($this->ownEntityId);
        $ownCredentials[0]->getPublicKey()->type = \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_OAEP_MGF1P;

        $sp = new SpSsoDescriptor();
        $sp->addAssertionConsumerService(new AssertionConsumerService($this->router->generate('saml.receive_response', [], RouterInterface::ABSOLUTE_URL), SamlConstants::BINDING_SAML2_HTTP_POST));
        foreach ($ownCredentials as $credential) {
            if ($credential instanceof X509Credential) {
                $kd = new KeyDescriptor(UsageType::SIGNING, $credential->getCertificate());
                $sp->addKeyDescriptor($kd);
                $kd = new KeyDescriptor(UsageType::ENCRYPTION, $credential->getCertificate());
                $sp->addKeyDescriptor($kd);
            }
        }
        $result->addItem($sp);

        $idp = new IdpSsoDescriptor();
        $idp->addSingleSignOnService(new SingleSignOnService($this->router->generate('saml.receive_authn_request', [], RouterInterface::ABSOLUTE_URL), SamlConstants::BINDING_SAML2_HTTP_POST));
        $idp->addSingleSignOnService(new SingleSignOnService($this->router->generate('saml.receive_authn_request', [], RouterInterface::ABSOLUTE_URL), SamlConstants::BINDING_SAML2_HTTP_REDIRECT));
        foreach ($ownCredentials as $credential) {
            if ($credential instanceof X509Credential) {
                $kd = new KeyDescriptor(UsageType::SIGNING, $credential->getCertificate());
                $idp->addKeyDescriptor($kd);
                $kd = new KeyDescriptor(UsageType::ENCRYPTION, $credential->getCertificate());
                $idp->addKeyDescriptor($kd);
            }
        }
        $result->addItem($idp);

        return $result;
    }
}
