<?php

namespace TreeHouse\SwiftBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthorizationListener
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Checks if the current authorization matches the one required for the request
     *
     * @param GetResponseEvent $event
     *
     * @throws AccessDeniedHttpException
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('_expression')) {
            return;
        }

        if (!$this->authorizationChecker->isGranted($request->attributes->get('_expression'))) {
            // ideally we want to throw an AccessDeniedHttpException here, but the keystone-bundle
            // catches those and converts them into 401 statuses, whereas we want 403's here.
            throw new AuthenticationException();
        }
    }
}
