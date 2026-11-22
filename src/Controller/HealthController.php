<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the local tooling probes on /health. The action carries no
 * #[Route] attribute on purpose: the automatic controller import would
 * register it in every environment, and config/routes/health.yaml
 * registers it under when@dev only.
 */
final class HealthController
{
    public function health(): Response
    {
        return new Response('ok');
    }
}
