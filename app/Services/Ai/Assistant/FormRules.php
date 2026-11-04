<?php

namespace App\Services\Ai\Assistant;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * Runs a page's real form request against what the assistant wants to send:
 * authorize(), prepareForValidation(), the rules and the after-hooks, for the
 * acting user and with the route's parameters. A rule added to a form later
 * applies to the assistant too.
 */
class FormRules
{
    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $parameters  route parameters, e.g. ['task' => $task]
     * @return T the resolved request; read validated() from it
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public static function check(string $class, array $input, User $user, array $parameters = []): FormRequest
    {
        /** @var T $request */
        $request = $class::create('/assistant', 'POST', $input);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $route = (new Route('POST', 'assistant', []))->bind($request);
        foreach ($parameters as $name => $value) {
            $route->setParameter($name, $value);
        }
        $request->setRouteResolver(fn () => $route);

        $request->validateResolved();

        return $request;
    }
}
