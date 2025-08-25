<?php

declare(strict_types=1);

namespace Modularity\Cache;

use Laminas\Cache\Storage\StorageInterface;

class CacheStorageAdapter implements StorageInterface
{
    private StorageInterface $inner;
    private bool $enabled;

    public function __construct(StorageInterface $inner, bool $enabled = true)
    {
        $this->inner   = $inner;
        $this->enabled = $enabled;
    }

    public function getItem($key, &$success = null, mixed &$casToken = null)
    {
        return $this->enabled ? $this->inner->getItem($key, $success, $casToken) : null;
    }

    public function getItems(array $keys)
    {
        return $this->enabled ? $this->inner->getItems($keys) : [];
    }

    public function hasItem($key)
    {
        return $this->enabled ? $this->inner->hasItem($key) : false;
    }

    public function hasItems(array $keys)
    {
        return $this->enabled ? $this->inner->hasItems($keys) : [];
    }

    public function getMetadata($key)
    {
        return $this->inner->getMetadata($key);
    }

    public function getMetadatas(array $keys)
    {
        return $this->inner->getMetadatas($keys);
    }

    public function setItem($key, mixed $value)
    {
        return $this->enabled ? $this->inner->setItem($key, $value) : true;
    }

    public function setItems(array $keyValuePairs)
    {
        return $this->enabled ? $this->inner->setItems($keyValuePairs) : [];
    }

    public function addItem($key, mixed $value)
    {
        return $this->enabled ? $this->inner->addItem($key, $value) : true;
    }

    public function addItems(array $keyValuePairs)
    {
        return $this->enabled ? $this->inner->addItems($keyValuePairs) : [];
    }

    public function replaceItem($key, mixed $value)
    {
        return $this->enabled ? $this->inner->replaceItem($key, $value) : true;
    }

    public function replaceItems(array $keyValuePairs)
    {
        return $this->enabled ? $this->inner->replaceItems($keyValuePairs) : [];
    }

    public function checkAndSetItem(mixed $token, $key, mixed $value)
    {
        return $this->enabled ? $this->inner->checkAndSetItem($token, $key, $value) : true;
    }

    public function touchItem($key)
    {
        return $this->enabled ? $this->inner->touchItem($key) : true;
    }

    public function touchItems(array $keys)
    {
        return $this->enabled ? $this->inner->touchItems($keys) : [];
    }

    public function removeItem($key)
    {
        return $this->enabled ? $this->inner->removeItem($key) : true;
    }

    public function removeItems(array $keys)
    {
        return $this->enabled ? $this->inner->removeItems($keys) : [];
    }

    public function incrementItem($key, $value)
    {
        return $this->enabled ? $this->inner->incrementItem($key, $value) : false;
    }

    public function incrementItems(array $keyValuePairs)
    {
        return $this->enabled ? $this->inner->incrementItems($keyValuePairs) : [];
    }

    public function decrementItem($key, $value)
    {
        return $this->enabled ? $this->inner->decrementItem($key, $value) : false;
    }

    public function decrementItems(array $keyValuePairs)
    {
        return $this->enabled ? $this->inner->decrementItems($keyValuePairs) : [];
    }

    public function getCapabilities()
    {
        return $this->inner->getCapabilities();
    }

    public function setOptions($options)
    {
        return $this->inner->setOptions($options);
    }

    public function getOptions()
    {
        return $this->inner->getOptions();
    }
}
