<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('security.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', config('security.referrer_policy'));
        $response->headers->set('Permissions-Policy', config('security.permissions_policy'));
        $response->headers->set('X-XSS-Protection', '0');

        if ($this->shouldSendHsts($request)) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        return $response;
    }

    protected function contentSecurityPolicy(Request $request): string
    {
        if ($request->is('api/*')) {
            return (string) config('security.csp.api');
        }

        $policy = (string) config('security.csp.admin');

        if (app()->environment('local') && ($extras = trim((string) config('security.csp.admin_local_extras'))) !== '') {
            $policy = $this->appendConnectSources($policy, $extras);
        }

        return $policy;
    }

    protected function appendConnectSources(string $policy, string $extras): string
    {
        if (preg_match("/connect-src ([^;]+)/", $policy, $matches)) {
            return (string) preg_replace(
                "/connect-src [^;]+/",
                'connect-src '.trim($matches[1].' '.$extras),
                $policy,
                1
            );
        }

        return $policy."; connect-src 'self' ws: wss: {$extras}";
    }

    protected function shouldSendHsts(Request $request): bool
    {
        if (! config('security.hsts.enabled', false)) {
            return false;
        }

        return $request->isSecure();
    }

    protected function hstsValue(): string
    {
        $value = 'max-age='.config('security.hsts.max_age', 31536000);

        if (config('security.hsts.include_subdomains', true)) {
            $value .= '; includeSubDomains';
        }

        return $value;
    }
}
