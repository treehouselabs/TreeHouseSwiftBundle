<?php

namespace TreeHouse\SwiftBundle\Metadata;

class Metadata implements \IteratorAggregate
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return null;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * @param array $data
     */
    public function add(array $data)
    {
        $this->data = array_replace($this->data, $data);
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    public function has($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param string $name
     */
    public function remove($name)
    {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @return boolean
     */
    public function isEmpty()
    {
        return sizeof($this->data) === 0;
    }
}
