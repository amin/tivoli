<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumStateful;

/**
 * Sanctum's default stateful middleware hardcodes
 *   config(['session.same_site' => 'lax'])
 * in configureSecureCookieSessions(), which silently overrides whatever
 * SESSION_SAME_SITE is set to in the environment.
 *
 * That assumption only holds when the SPA and API live on the same site.
 * On Railway the SPA (tivoli-develop.up.railway.app) and the API
 * (api-develop-b059.up.railway.app) are cross-site (up.railway.app is on
 * the Public Suffix List), so we need SameSite=None;Secure for the
 * session cookie to be sent back. Override the method so it leaves
 * session.same_site alone and respects whatever the env configured.
 */
class EnsureFrontendRequestsAreStateful extends SanctumStateful
{
    protected function configureSecureCookieSessions(): void
    {
        config([
            'session.http_only' => true,
        ]);
    }
}
