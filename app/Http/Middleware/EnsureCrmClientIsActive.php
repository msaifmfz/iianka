<?php

namespace App\Http\Middleware;

use App\Models\ClientContact;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmClientIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resource = $request->route('client_contact')
            ?? $request->route('client_place')
            ?? $request->route('client_place_log')
            ?? $request->route('client_place_log_attachment');

        $hasActiveClient = match (true) {
            $resource instanceof ClientContact, $resource instanceof ClientPlace => $resource->client()->exists(),
            $resource instanceof ClientPlaceLog => $resource->place()->whereHas('client')->exists(),
            $resource instanceof ClientPlaceLogAttachment => $resource->log()->whereHas('place.client')->exists(),
            default => true,
        };

        abort_unless($hasActiveClient, 404);

        return $next($request);
    }
}
