<?php

namespace FM\SwiftBundle\Event;

final class SwiftEvents
{
    const POST_CONTAINER = 'container.post';
    const PUT_CONTAINER = 'container.put';
    const DELETE_CONTAINER = 'container.delete';

    const POST_OBJECT = 'object.post';
    const PUT_OBJECT = 'object.put';
    const DELETE_OBJECT = 'object.delete';
}
