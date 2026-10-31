<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared behaviour for every v1 endpoint.
 *
 * Deliberately thin: Laravel's JsonResource already wraps single resources in
 * `data` and attaches `meta`/`links` to paginated collections, so this class
 * adds only what resources do not cover — the write-response shapes and a
 * page-size cap. Inventing a bespoke envelope on top would buy nothing and
 * break every client library that expects Laravel's.
 */
abstract class ApiController extends Controller
{
    /** Default page size when the client does not ask for one. */
    public const PER_PAGE = 25;

    /** Hard ceiling on `per_page`, so a client cannot ask for the whole table. */
    public const MAX_PER_PAGE = 100;

    /** A 200 with a resource, a payload, or both plus a message. */
    protected function ok(mixed $data = null, ?string $message = null): JsonResponse
    {
        if ($data instanceof JsonResource) {
            return $this->withMessage($data, $message)->response()->setStatusCode(200);
        }

        $body = $data === null ? [] : ['data' => $data];

        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body);
    }

    /** A 201 with the newly created resource. */
    protected function created(JsonResource $resource, ?string $message = null): JsonResponse
    {
        return $this->withMessage($resource, $message)->response()->setStatusCode(201);
    }

    /**
     * Attach the message without discarding whatever the caller already put
     * beside `data`.
     *
     * `JsonResource::additional()` *replaces* the array rather than merging
     * into it, so calling it here would silently drop a payload the controller
     * had already attached — which is exactly how the release overlap warning
     * would go missing from a successful save.
     */
    private function withMessage(JsonResource $resource, ?string $message): JsonResource
    {
        if ($message === null) {
            return $resource;
        }

        return $resource->additional(array_merge($resource->additional, ['message' => $message]));
    }

    /** A 200 carrying only a confirmation message (deletes, toggles, syncs). */
    protected function message(string $message): JsonResponse
    {
        return response()->json(['message' => $message]);
    }

    /**
     * Paginate a query into a resource collection, honouring `per_page` up to
     * the cap. `$resource` is the resource class to map each record through.
     *
     * @param  Builder<*>|Relation<*, *, *>  $query
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginate(Request $request, Builder|Relation $query, string $resource): AnonymousResourceCollection
    {
        return $resource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    /** The requested page size, floored at 1 and capped at MAX_PER_PAGE. */
    protected function perPage(Request $request): int
    {
        $requested = (int) $request->integer('per_page', static::PER_PAGE);

        return max(1, min($requested ?: static::PER_PAGE, static::MAX_PER_PAGE));
    }

    /**
     * Read an optional integer filter, returning null when the parameter is
     * absent or blank (so `?team_id=` means "no filter", not "team zero").
     */
    protected function filterId(Request $request, string $key): ?int
    {
        return $request->filled($key) ? (int) $request->integer($key) : null;
    }
}
