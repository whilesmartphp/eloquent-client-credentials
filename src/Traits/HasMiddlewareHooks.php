<?php

namespace Whilesmart\EloquentClientCredentials\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whilesmart\EloquentClientCredentials\Enums\HookAction;
use Whilesmart\EloquentClientCredentials\Interfaces\MiddlewareHookInterface;

trait HasMiddlewareHooks
{
    protected function runBeforeHooks(Request $request, HookAction|string $action): Request
    {
        $hooks = config('client-credentials.middleware_hooks', []);

        foreach ($hooks as $hookClass) {
            if (class_exists($hookClass)) {
                $hook = app($hookClass);
                if ($hook instanceof MiddlewareHookInterface) {
                    $actionValue = $action instanceof HookAction ? $action->value : $action;
                    $result = $hook->before($request, $actionValue);
                    if ($result instanceof Request) {
                        $request = $result;
                    }
                }
            }
        }

        return $request;
    }

    protected function runAfterHooks(Request $request, JsonResponse $response, HookAction|string $action): JsonResponse
    {
        $hooks = config('client-credentials.middleware_hooks', []);

        foreach ($hooks as $hookClass) {
            if (class_exists($hookClass)) {
                $hook = app($hookClass);
                if ($hook instanceof MiddlewareHookInterface) {
                    $actionValue = $action instanceof HookAction ? $action->value : $action;
                    $result = $hook->after($request, $response, $actionValue);
                    if ($result instanceof JsonResponse) {
                        $response = $result;
                    }
                }
            }
        }

        return $response;
    }
}
