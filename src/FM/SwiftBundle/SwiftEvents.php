<?php

namespace FM\SwiftBundle;

final class SwiftEvents
{
    const CREATE_CONTAINER = 'container.create';
    const UPDATE_CONTAINER = 'container.update';
    const REMOVE_CONTAINER = 'container.remove';

    // note 'create object' is not a separate function in the Swift protocol,
    // there is only a 'create or update' operation.
    const UPDATE_OBJECT    = 'object.update';
    const COPY_OBJECT      = 'object.copy';
    const REMOVE_OBJECT    = 'object.remove';
}
