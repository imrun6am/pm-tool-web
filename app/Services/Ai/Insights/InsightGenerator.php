<?php

namespace App\Services\Ai\Insights;

use App\Models\AiInsight;
use App\Services\Ai\AiException;

/** Builds the payload of one kind of background AiInsight. */
interface InsightGenerator
{
    /**
     * @return array the payload stored on the insight
     *
     * @throws AiException
     */
    public function generate(AiInsight $insight): array;
}
