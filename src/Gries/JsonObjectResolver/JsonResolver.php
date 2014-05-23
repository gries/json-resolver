<?php

namespace Gries\JsonObjectResolver;

/**
 * Class JsonDecoder
 *
 * @package Gries\JsonObjectResolver
 */
class JsonResolver
{
    /**
     * Decode a json tree.
     *
     * @param $json
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function decode($json)
    {
        if (!$object = json_decode($json)) {
            throw new \InvalidArgumentException('Invalid json given!');
        }

        $object = $this->resolveObject($object);

        return $object;
    }

    /**
     * Encode a Object and all its children / related objects.
     *
     * @param \JsonSerializable $object
     * @return string as json
     */
    public function encode(\JsonSerializable $object)
    {
        $arrayData = $this->createArrayData($object);

        return json_encode($arrayData);
    }

    /**
     * Recursively resolve a object.
     * If the object is has no "json_resolve_class" property it will be returned as is.
     *
     * If the property exists the object will be converted to the configured class
     * and all its properties will be copied.
     *
     * Also all properties that have json_resolve_class set or that are array/traversables
     * will be resolved recursively.
     *
     * @param $object
     * @return mixed
     */
    private function resolveObject($object)
    {
        if (!property_exists($object, 'json_resolve_class')) {
            return $object;
        }

        $class = $object->json_resolve_class;
        $newClass = new $class;

        return $this->convert($newClass, $object);
    }

    /**
     * Convert an object from a given stdClass to a target-class.
     *
     * @param $target
     * @param \stdClass $jsonObject
     * @return mixed
     */
    private function convert($target, \stdClass $jsonObject)
    {
        $sourceReflection = new \ReflectionObject($jsonObject);
        $destinationReflection = new \ReflectionObject($target);
        $sourceProperties = $sourceReflection->getProperties();

        foreach ($sourceProperties as $sourceProperty) {
            $sourceProperty->setAccessible(true);
            $name = $sourceProperty->getName();
            $value = $sourceProperty->getValue($jsonObject);

            if ($name == 'json_resolve_class') {
                continue;
            }

            // resolve related objects
            $value = $this->convertPropertyValue($value);

            if ($destinationReflection->hasProperty($name)) {
                $propDest = $destinationReflection->getProperty($name);
                $propDest->setAccessible(true);
                $propDest->setValue($target, $value);
            } else {
                $target->$name = $value;
            }
        }

        return $target;
    }

    /**
     * Create array-data from an object.
     *
     * @param \JsonSerializable $object
     * @return mixed
     */
    private function createArrayData(\JsonSerializable $object)
    {
        $arrayData = $object->jsonSerialize();

        $arrayData['json_resolve_class'] = get_class($object);

        // recursively resolve JsonResolvableInterfaces
        foreach ($arrayData as $key => $value) {
            $arrayData[$key] = $this->createPropertyData($value);
        }

        return $arrayData;
    }

    /**
     * Create array data for a property.
     *
     * @param $value
     * @return mixed
     */
    private function createPropertyData($value)
    {
        if ($value instanceof \JsonSerializable) {
            return $this->createArrayData($value);
        }

        // recursively resolve properties
        if ($this->isIterable($value)) {
            foreach ($value as $key => $subValue) {
                $value[$key] = $this->createPropertyData($subValue);
            }
        }

        return $value;
    }

    /**
     * Check if a variable is iterable.
     *
     * @param $var
     * @return bool
     */
    private function isIterable($var)
    {
        return (is_array($var) || $var instanceof \Traversable);
    }

    /**
     * Convert a single PropertyValue.
     *
     *
     * @param $value
     * @return mixed
     */
    private function convertPropertyValue($value)
    {
        if (is_object($value) && property_exists($value, 'json_resolve_class')) {
            $value = $this->resolveObject($value);
        }

        // resolve iterables
        if ($this->isIterable($value)) {
            foreach ($value as $key => $subValue) {
                $value[$key] = $this->convertPropertyValue($subValue);
            }
        }

        return $value;
    }
}
