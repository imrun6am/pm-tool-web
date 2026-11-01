<?php

namespace App\Services\Calendar;

use RuntimeException;

/** The file itself cannot be turned into events (corrupt, empty, too large): retrying cannot help. */
class UnreadableDocumentException extends RuntimeException {}
