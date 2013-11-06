<?php

namespace FM\SwiftBundle\Exception;

class InvalidNameException extends SwiftException
{
    public function __construct($name)
    {
        parent::__construct(sprintf('Invalid container name "%s". A container name must be less than 256 bytes and cannot contain a forward slash \'/\' character.', $name));
    }
}
