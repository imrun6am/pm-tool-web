<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Ai\Concerns\RespondsWithAi;
use App\Http\Controllers\Controller;
use App\Models\AiInsight;
use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Models\Team;
use App\Policies\AiInsightPolicy;
use App\Services\Ai\Insights\AiInsightService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Background Claude results: request one, poll its status, and fetch its
 * rendered card once ready. The partials in resources/views/ai/ drive these.
 */
class AiInsightController extends Controller
{
    use RespondsWithAi;

    public function __construct(private readonly AiInsightService $insights) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(AiInsightService::GENERATORS))],
            'subject_id' => ['required', 'integer'],
            'release_id' => ['nullable', 'integer'], // document insights: the document's release
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $subject = $this->subject($data);
        abort_unless(AiInsightPolicy::canRequest($request->user(), $data['kind'], $subject), 403);

        $forDate = $data['kind'] === AiInsight::KIND_STANDUP_DIGEST
            ? Carbon::parse($data['date'] ?? today()->subWeekday())->startOfDay()
            : null;

        return $this->aiJson(fn () => $this->insights->request($data['kind'], $subject, $request->user(), $forDate)->toStatus());
    }

    public function show(Request $request, AiInsight $insight): JsonResponse
    {
        $this->authorize('view', $insight);

        $status = $insight->toStatus();
        if ($insight->isReady()) {
            $status['html'] = view("ai.insights.{$insight->kind}", ['insight' => $insight])->render();
        }

        return response()->json($status);
    }

    private function subject(array $data): Model
    {
        return match ($data['kind']) {
            AiInsight::KIND_RELEASE_RISK => Release::findOrFail($data['subject_id']),
            AiInsight::KIND_STANDUP_DIGEST => Team::findOrFail($data['subject_id']),
            AiInsight::KIND_DOCUMENT => ReleaseDocument::where('release_id', $data['release_id'] ?? 0)->findOrFail($data['subject_id']),
        };
    }
}
