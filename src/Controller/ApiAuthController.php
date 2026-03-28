<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * /api/login is handled automatically by LexikJWTAuthenticationBundle (json_login firewall).
 * /api/refresh is handled by GesdinetJWTRefreshTokenBundle (configured in security.yaml).
 *
 * This controller is kept as a placeholder for any future custom API auth endpoints.
 */
class ApiAuthController extends AbstractController
{
    // Intentionally empty — login and refresh are handled by bundles.
}
