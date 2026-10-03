<?php

declare(strict_types=1);

namespace App\Application\Crm;

use RuntimeException;

/**
 * The address search provider could not answer: it was unreachable, returned
 * an error status or sent a body that is not a list of results. Distinct from
 * an empty result, which means the provider answered and found no match.
 */
final class GeocodingUnavailable extends RuntimeException {}
