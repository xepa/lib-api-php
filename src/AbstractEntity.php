<?php

namespace Twizo\Api;

use Twizo\Api\Client\Exception as ClientException;
use Twizo\Api\Entity\Exception as EntityException;
use Twizo\Api\Entity\Validation\Exception as ValidationException;

/**
 * Abstract parent class of all entity objects
 *
 * This file is part of the Twizo php api
 *
 * (c) Twizo <info@twizo.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * File that was distributed with this source code.
 */
abstract class AbstractEntity
{
    const ACTION_CREATE = 'POST';
    const ACTION_RETRIEVE = 'GET';
    const ACTION_REMOVE = 'DELETE';

    /**
     * @var AbstractClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $messageId;

    /**
     * @var array
     */
    protected $postFields = array();

    /**
     * Constructor
     *
     * @param AbstractClient $client
     */
    public function __construct(AbstractClient $client)
    {
        $this->client = $client;
    }

    /**
     * Security method to make sure we won't call undefined parameters.
     *
     * @param string $name
     *
     * @throws EntityException
     */
    public function __get($name)
    {
        throw new EntityException("Property '$name' is not defined in '" . get_class() . "' class.", EntityException::UNDEFINED_FIELD_ACCESSED);
    }

    /**
     * Security method to make sure we won't call undefined parameters.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws EntityException
     */
    public function __set($name, $value)
    {
        throw new EntityException("Property '$name' is not defined in '" . get_class() . "' class.", EntityException::UNDEFINED_FIELD_ACCESSED);
    }

    /**
     * Set a post field to be send to the server; lookup the value from the key
     *
     * @param string $key
     */
    protected function addPostField($key)
    {
        if (property_exists($this, $key)) {
            $this->postFields[$key] = $this->$key;
        }
    }

    /**
     * Detect invalid json fields in entity and throw exception when found
     *
     * @throws EntityException
     */
    protected function detectInvalidJsonFields()
    {
        if (json_encode($this->postFields) === false) {
            $invalidFields = array();
            foreach ($this->postFields as $key => $value) {
                if (json_encode($value) === false) {
                    $invalidFields[] = $key;
                }
            }
            throw new EntityException('Invalid data found in field(s): ' . implode(', ', $invalidFields), EntityException::INVALID_FIELDS);
        }
    }

    /**
     * @return string
     */
    abstract protected function getCreateUrl();

    /**
     * Find item(s) from response
     *
     * @param array $body
     *
     * @return array
     */
    protected function getItems($body)
    {
        return isset($body['_embedded']['items']) ? $body['_embedded']['items'] : array($body);
    }

    /**
     * @return string
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @param string $messageId
     *
     * @throws EntityException
     */
    public function populate($messageId)
    {
        if (empty($messageId)) {
            throw new EntityException('No messages id supplied', EntityException::NO_MESSAGE_ID_SUPPLIED);
        }

        $this->sendApiCall(self::ACTION_RETRIEVE, $this->getCreateUrl() . '/' . $messageId);
    }

    /**
     * @param string $verb
     * @param string $location
     *
     * @return Response
     *
     * @throws EntityException
     */
    protected function sendApiCall($verb, $location)
    {
        try {
            $this->detectInvalidJsonFields();
            $response = $this->client->sendRequest($verb, $location, $this->postFields);

            $entity = $this->getItems($response->getBody());
            if (isset($entity[0])) {
                $this->setFields($entity[0]);
            }

            return $response;
        } catch (ClientException $e) {
            if ($e->getResponse() !== null) {
                $entity = $this->getItems($e->getResponse()->getBody());

                if (isset($entity[0]['validation_messages'])) {
                    throw new ValidationException(
                        $entity[0]['validation_messages'],
                        $this->postFields,
                        $e->getResponse()->getStatusCode(),
                        isset($entity[0]['errorCode']) ? $entity[0]['errorCode'] : null
                    );
                } else {
                    throw new EntityException(
                        'Could not connect to api server: ' . $e->getMessage(),
                        EntityException::SERVER_UNAVAILABLE,
                        $e->getResponse()->getStatusCode(),
                        isset($entity[0]['errorCode']) ? $entity[0]['errorCode'] : null
                    );
                }
            } else {
                throw new EntityException(
                    'Could not connect to api server: ' . $e->getMessage(),
                    EntityException::SERVER_UNAVAILABLE
                );
            }
        }
    }

    /**
     * Set fields from api response
     *
     * @param array $fields
     *
     * @return void
     */
    public function setFields(array $fields)
    {
        foreach ($fields as $field => $value) {
            if (property_exists($this, $field)) {
                $this->{$field} = $value;
            }
        }
    }
}
