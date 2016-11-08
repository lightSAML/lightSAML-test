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
use LightSaml\Error\LightSamlXmlException;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Metadata\EntitiesDescriptor;
use LightSaml\Model\Metadata\EntityDescriptor;
use LightSaml\Store\EntityDescriptor\EntityDescriptorStoreInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @DI\Service(id="store.entities")
 * @DI\Tag(name="lightsaml.idp_entity_store")
 * @DI\Tag(name="lightsaml.sp_entity_store")
 */
class EntitiesSessionStore implements EntityDescriptorStoreInterface
{
    const SESSION_KEY = 'entities';

    /** @var SessionInterface */
    private $session;

    /**
     * @DI\InjectParams({
     *      "session": @DI\Inject("session"),
     * })
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @param string $entityId
     *
     * @return EntityDescriptor|null
     */
    public function get($entityId)
    {
        $arr = $this->session->get(self::SESSION_KEY, []);
        foreach ($arr as $data) {
            if (isset($data['id'][$entityId])) {
                $descriptor = $this->deserialize($data['xml']);
                if ($descriptor instanceof EntitiesDescriptor) {
                    $descriptor = $descriptor->getByEntityId($entityId);
                }

                return $descriptor;
            }
        }

        return null;
    }

    /**
     * @param string $entityId
     *
     * @return string|null
     */
    public function getRaw($entityId)
    {
        $arr = $this->session->get(self::SESSION_KEY, []);
        foreach ($arr as $data) {
            if (isset($data['id'][$entityId])) {
                return $data['xml'];
            }
        }

        return null;
    }

    public function has($entityId)
    {
        $arr = $this->session->get(self::SESSION_KEY, []);
        foreach ($arr as $data) {
            if (isset($data['id'][$entityId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return EntityDescriptor[]
     */
    public function all()
    {
        $arr = $this->session->get(self::SESSION_KEY, []);
        $result = [];
        foreach ($arr as $k => $data) {
            $descriptor = $this->deserialize($data['xml']);
            if ($descriptor instanceof EntityDescriptor) {
                $result[] = $descriptor;
            } elseif ($descriptor instanceof EntitiesDescriptor) {
                foreach ($descriptor->getAllEntityDescriptors() as $ed) {
                    $result[] = $ed;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $xml
     *
     * @return int
     */
    public function add($xml)
    {
        $descriptor = $this->deserialize($xml);
        $arr = $this->session->get(self::SESSION_KEY, []);
        $data = [
            'id' => [],
            'xml' => $xml,
        ];
        if ($descriptor instanceof EntityDescriptor) {
            $data['id'][$descriptor->getEntityID()] = 1;
        } elseif ($descriptor instanceof EntitiesDescriptor) {
            foreach ($descriptor->getAllEntityDescriptors() as $ed) {
                $data['id'][$ed->getEntityID()] = 1;
            }
        } else {
            throw new \LogicException('unexpected');
        }

        $arr[] = $data;
        $this->session->set(self::SESSION_KEY, $arr);

        return count($arr) - 1;
    }

    /**
     * @param string $entityId
     */
    public function remove($entityId)
    {
        $indexToRemove = null;
        $arr = $this->session->get(self::SESSION_KEY, []);
        foreach ($arr as $k => $v) {
            if (isset($v['id'][$entityId])) {
                $indexToRemove = $k;
                break;
            }
        }
        if ($indexToRemove === null) {
            throw new \InvalidArgumentException();
        }
        unset($arr[$indexToRemove]);
        $this->session->set(self::SESSION_KEY, $arr);
    }

    /**
     * @param string $xml
     *
     * @return EntityDescriptor|EntitiesDescriptor
     */
    public function deserialize($xml)
    {
        try {
            return $this->deserializeEntityDescriptor($xml);
        } catch (LightSamlXmlException $ex) {
            return $this->deserializeEntitiesDescriptor($xml);
        }
    }

    /**
     * @param string $xml
     *
     * @return EntityDescriptor
     */
    private function deserializeEntityDescriptor($xml)
    {
        $deserializationContext = new DeserializationContext();
        $deserializationContext->getDocument()->loadXML($xml);
        $entityDescriptor = new EntityDescriptor();
        $entityDescriptor->deserialize($deserializationContext->getDocument()->firstChild, $deserializationContext);

        return $entityDescriptor;
    }

    /**
     * @param string $xml
     *
     * @return EntitiesDescriptor
     */
    private function deserializeEntitiesDescriptor($xml)
    {
        $deserializationContext = new DeserializationContext();
        $deserializationContext->getDocument()->loadXML($xml);
        $entitiesDescriptor = new EntitiesDescriptor();
        $entitiesDescriptor->deserialize($deserializationContext->getDocument()->firstChild, $deserializationContext);

        return $entitiesDescriptor;
    }
}
