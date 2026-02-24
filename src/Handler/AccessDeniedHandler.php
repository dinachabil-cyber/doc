<?php

namespace App\Handler;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function handle(\Symfony\Component\HttpFoundation\Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        // Redirect to homepage
        return new RedirectResponse($this->router->generate('app_home'));
    }
}
