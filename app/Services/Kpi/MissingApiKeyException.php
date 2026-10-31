<?php

namespace App\Services\Kpi;

use RuntimeException;

/** Configuration error: retrying the job cannot help, so it fails at once. */
class MissingApiKeyException extends RuntimeException {}
