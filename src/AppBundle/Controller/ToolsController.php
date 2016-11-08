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

use AppBundle\Form\Type\FingerPrintType;
use AppBundle\Form\Type\MetadataType;
use AppBundle\Form\Type\SelfSignedCertificateType;
use LightSaml\Credential\KeyHelper;
use LightSaml\Credential\UsageType;
use LightSaml\Credential\X509Certificate;
use LightSaml\Helper;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Metadata\AssertionConsumerService;
use LightSaml\Model\Metadata\ContactPerson;
use LightSaml\Model\Metadata\EntityDescriptor;
use LightSaml\Model\Metadata\IdpSsoDescriptor;
use LightSaml\Model\Metadata\KeyDescriptor;
use LightSaml\Model\Metadata\Organization;
use LightSaml\Model\Metadata\SingleLogoutService;
use LightSaml\Model\Metadata\SingleSignOnService;
use LightSaml\Model\Metadata\SpSsoDescriptor;
use LightSaml\Model\Protocol\Response;
use LightSaml\Model\Protocol\SamlMessage;
use LightSaml\Model\XmlDSig\SignatureWriter;
use LightSaml\SamlConstants;
use LightSaml\Validator\Model\Xsd\XsdValidator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/tools")
 */
class ToolsController extends Controller
{
    /**
     * @Route("/", name="tools.index")
     * @Template("tools/index.html.twig")
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Route("/self-signed-certificate", name="tools.self_signed_certificate")
     * @Template("tools/self_signed_certificate.html.twig")
     */
    public function selfSignedCertificateAction(Request $request)
    {
        $privateKey = '';
        $x509 = '';
        $csr = '';

        $form = $this->createForm(SelfSignedCertificateType::class);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $keyPair = openssl_pkey_new([
                'digest_alg' => $formData['digest'],
                'private_key_bits' => $formData['bits'],
            ]);
            $dn = [];
            foreach ($formData['dn'] as $k => $v) {
                if ($v) {
                    $dn[$k] = $v;
                }
            }
            dump($dn);
            $csrResource = openssl_csr_new($dn, $keyPair);
            $cert = openssl_csr_sign($csrResource, null, $keyPair, $formData['days']);
            openssl_csr_export($csrResource, $csr);
            openssl_x509_export($cert, $x509);
            openssl_pkey_export($keyPair, $privateKey, $formData['password']);
            openssl_pkey_free($keyPair);
            openssl_x509_free($cert);
        }

        return [
            'form' => $form->createView(),
            'privateKey' => $privateKey,
            'x509' => $x509,
            'csr' => $csr,
        ];
    }

    /**
     * @Route("/fingerprint", name="tools.fingerprint")
     * @Template("tools/fingerprint.html.twig")
     */
    public function fingerPrintAction(Request $request)
    {
        $fingerprint = '';
        $formatted = '';
        $form = $this->createForm(FingerPrintType::class);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $cert = new X509Certificate();
            $cert->loadPem($formData['x509']);
            $data = $cert->getData();
            $fingerprint = strtolower(hash($formData['algorithm'], base64_decode($data)));
            $formatted = implode(':', str_split($fingerprint, 2));
        }

        return [
            'form' => $form->createView(),
            'fingerprint' => $fingerprint,
            'formatted' => $formatted,
        ];
    }

    /**
     * @Route("/certificate-info", name="tools.certificate_info")
     * @Template("tools/certificate_info.html.twig")
     */
    public function certificateInfo(Request $request)
    {
        $info = [];
        $form = $this->createFormBuilder()
            ->add('x509', TextareaType::class, [
                'required' => true,
                'label' => 'X509 Certificate',
                'attr' => ['rows' => 11],
            ])
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $cert = new X509Certificate();
            $cert->loadPem($formData['x509']);
            $info['Name'] = $cert->getName();
            $info['Subject'] = $cert->getSubject();
            $info['Issuer'] = $cert->getIssuer();
            $info['Valid From'] = Helper::time2string($cert->getValidFromTimestamp());
            $info['Valid To'] = Helper::time2string($cert->getValidToTimestamp());
            $info['Signature Algorithm'] = $cert->getSignatureAlgorithm();
            if (($i = strpos($info['Signature Algorithm'], '#')) !== false) {
                $info['Signature Algorithm'] = strtoupper(substr($info['Signature Algorithm'], $i + 1));
            }
        }

        return [
            'form' => $form->createView(),
            'info' => $info,
        ];
    }

    /**
     * @Route("/sign-saml-message", name="tools.sign_saml_message")
     * @Template("tools/sign_authnrequest.html.twig")
     */
    public function signSamlMessageAction(Request $request)
    {
        $xml = '';
        $signatureAlgorithm = '';
        $form = $this->createFormBuilder()
            ->add('xml', TextareaType::class, [
                'label' => 'SAML Message XML (AuthnRequest, Response, LogoutRequest, LogoutResponse, Metadata)',
                'required' => true,
                'attr' => ['rows' => 11],
            ])
            ->add('privateKey', TextareaType::class, [
                'required' => true,
                'attr' => ['rows' => 11],
            ])
            ->add('password', TextType::class, [
                'required' => false,
            ])
            ->add('x509', TextareaType::class, [
                'label' => 'X509 Certificate',
                'required' => true,
                'attr' => ['rows' => 11],
            ])
            ->add('signAssertion', CheckboxType::class, [
                'required' => false,
                'label' => 'This is SAML Response XML and I want assertions signed instead of the Response',
            ])
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();

            $deserializationContext = new DeserializationContext();
            $samlMessage = SamlMessage::fromXML($formData['xml'], $deserializationContext);
            $certificate = new X509Certificate();
            $certificate->loadPem($formData['x509']);
            $signatureAlgorithm = $certificate->getSignatureAlgorithm();
            if (($i = strpos($signatureAlgorithm, '#')) !== false) {
                $signatureAlgorithm = strtoupper(substr($signatureAlgorithm, $i + 1));
            }

            $privateKey = KeyHelper::createPrivateKey($formData['privateKey'], $formData['password'], false, $certificate->getSignatureAlgorithm());
            $signature = new SignatureWriter($certificate, $privateKey);

            if ($formData['signAssertion']) {
                if ($samlMessage instanceof Response) {
                    foreach ($samlMessage->getAllAssertions() as $assertion) {
                        $assertion->setSignature($signature);
                    }
                } else {
                    $form->get('signAssertion')->addError(new FormError('Provided XML is not SAML Response XML'));
                }
            } else {
                $samlMessage->setSignature($signature);
            }

            $serializationContext = new SerializationContext();
            $samlMessage->serialize($serializationContext->getDocument(), $serializationContext);
            $xml = $serializationContext->getDocument()->saveXML();
        }

        return [
            'form' => $form->createView(),
            'xml' => $xml,
            'signatureAlgorithm' => $signatureAlgorithm,
        ];
    }

    /**
     * @Route("/xsd-saml-validation", name="tools.xsd_validation")
     * @Template("tools/xsd_validation.html.twig")
     */
    public function xsdValidateAction(Request $request)
    {
        $validationResult = null;
        $validationExecuted = false;
        $form = $this->createFormBuilder()
            ->add('xml', TextareaType::class, [
                'attr' => ['rows' => 11],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Metadata' => 'metadata',
                    'Protocol (AuthnRequest, Response, Logout Request, Logout Response)' => 'protocol',
                ],
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $validator = new XsdValidator();
            $validationExecuted = true;
            if ('metadata' == $formData['type']) {
                $validationResult = $validator->validateMetadata($formData['xml']);
            } else {
                $validationResult = $validator->validateProtocol($formData['xml']);
            }
        }

        return [
            'form' => $form->createView(),
            'validationExecuted' => $validationExecuted,
            'validationResult' => $validationResult,
        ];
    }

    /**
     * @Route("/build-idp-metadata", name="tools.build_idp_metadata")
     * @Template("tools/build_idp_metadata.html.twig")
     */
    public function buildIdpMetadata(Request $request)
    {
        $xml = '';
        $form = $this->createForm(MetadataType::class, null, ['sso' => true]);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $xml = $this->buildMetadata($formData);
        }

        return [
            'form' => $form->createView(),
            'xml' => $xml,
        ];
    }

    /**
     * @param array $formData
     *
     * @return string
     */
    private function buildMetadata(array $formData)
    {
        $ed = new EntityDescriptor();

        $ed->setEntityID($formData['entity_id']);
        $sso = null;
        if ($formData['sso']) {
            $sso = new IdpSsoDescriptor();
            $ed->addItem($sso);
            $sso->addSingleSignOnService(new SingleSignOnService($formData['sso'], SamlConstants::BINDING_SAML2_HTTP_REDIRECT));
        } elseif ($formData['acs']) {
            $sso = new SpSsoDescriptor();
            $ed->addItem($sso);
            $sso->addAssertionConsumerService(new AssertionConsumerService($formData['acs'], SamlConstants::BINDING_SAML2_HTTP_POST));
        }
        if ($sso) {
            if ($formData['slo']) {
                $sso->addSingleLogoutService(new SingleLogoutService($formData['slo'], SamlConstants::BINDING_SAML2_HTTP_REDIRECT));
            }
            if ($formData['name_id_format']) {
                $sso->addNameIDFormat($formData['name_id_format']);
            }
            if ($formData['want_authn_requests_signed'] !== null) {
                $sso->setWantAuthnRequestsSigned($formData['want_authn_requests_signed']);
            }
            if ($formData['x509_signing']) {
                $cert = new X509Certificate();
                $cert->loadPem($formData['x509_signing']);
                $kd = new KeyDescriptor(UsageType::SIGNING, $cert);
                $sso->addKeyDescriptor($kd);
            }
            if ($formData['x509_encryption']) {
                $cert = new X509Certificate();
                $cert->loadPem($formData['x509_encryption']);
                $kd = new KeyDescriptor(UsageType::ENCRYPTION, $cert);
                $sso->addKeyDescriptor($kd);
            }
        }

        if ($formData['organization']) {
            if ($formData['organization']['organization_name'] ||
                $formData['organization']['organization_display_name'] ||
                $formData['organization']['organization_url']
            ) {
                $org = new Organization();
                if ($formData['organization']['organization_name']) {
                    $org->setOrganizationName($formData['organization']['organization_name']);
                }
                if ($formData['organization']['organization_display_name']) {
                    $org->setOrganizationDisplayName($formData['organization']['organization_display_name']);
                }
                if ($formData['organization']['organization_url']) {
                    $org->setOrganizationURL($formData['organization']['organization_url']);
                }
                $ed->addOrganization($org);
            }
        }
        if ($formData['technical_contact']) {
            if ($formData['technical_contact']['given_name'] ||
                $formData['technical_contact']['given_name']
            ) {
                $contact = new ContactPerson();
                $contact->setContactType(ContactPerson::TYPE_TECHNICAL);
                if ($formData['technical_contact']['given_name']) {
                    $contact->setGivenName($formData['technical_contact']['given_name']);
                }
                if ($formData['technical_contact']['email']) {
                    $contact->setEmailAddress($formData['technical_contact']['email']);
                }
                $ed->addContactPerson($contact);
            }
        }
        if ($formData['support_contact']) {
            if ($formData['support_contact']['given_name'] ||
                $formData['support_contact']['given_name']
            ) {
                $contact = new ContactPerson();
                $contact->setContactType(ContactPerson::TYPE_SUPPORT);
                if ($formData['support_contact']['given_name']) {
                    $contact->setGivenName($formData['support_contact']['given_name']);
                }
                if ($formData['support_contact']['email']) {
                    $contact->setEmailAddress($formData['support_contact']['email']);
                }
                $ed->addContactPerson($contact);
            }
        }
        if ($formData['private_key'] && $formData['x509']) {
            $cert = new X509Certificate();
            $cert->loadPem($formData['x509']);
            $signature = new SignatureWriter($cert, KeyHelper::createPrivateKey($formData['private_key'], $formData['password'], false, $cert->getSignatureAlgorithm()));
            $ed->setSignature($signature);
        }

        $serializationContext = new SerializationContext();
        $serializationContext->getDocument()->formatOutput = true;
        $ed->serialize($serializationContext->getDocument(), $serializationContext);

        return $serializationContext->getDocument()->saveXML();
    }
}
