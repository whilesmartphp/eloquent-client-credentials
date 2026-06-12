<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Whilesmart\EloquentClientCredentials\Enums\HookAction;
use Whilesmart\EloquentClientCredentials\Traits\HasMiddlewareHooks;

class ClientController extends ApiController
{
    use HasMiddlewareHooks;

    protected function getModel(): string
    {
        return config('client-credentials.default_model');
    }

    protected function resolveOwner(Request $request, ?Model $owner = null): Model
    {
        if ($owner) {
            return $owner;
        }

        $resolverClass = config('client-credentials.owner_resolver');
        $resolver = app($resolverClass);
        $resolvedOwner = $resolver->resolve($request);

        if (! $resolvedOwner) {
            throw new UnauthorizedHttpException(
                'Bearer',
                __('client-credentials::messages.owner_required')
            );
        }

        return $resolvedOwner;
    }

    public function index(Request $request, ?Model $owner = null): JsonResponse
    {
        $owner = $this->resolveOwner($request, $owner);
        $model = $this->getModel();
        $clients = $model::ownedBy($owner)->paginate();

        return $this->success($clients);
    }

    public function store(Request $request, ?Model $owner = null): JsonResponse
    {
        $owner = $this->resolveOwner($request, $owner);
        $request = $this->runBeforeHooks($request, HookAction::CLIENT_STORE);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $model = $this->getModel();
        $plainSecret = Str::random(40);

        $client = new $model([
            'name' => $request->name,
            'description' => $request->description,
            'owner_type' => get_class($owner),
            'owner_id' => $owner->id,
        ]);

        $client->setSecret($plainSecret);
        $client->save();

        $response = $this->success([
            'client' => $client->refresh(),
            'secret' => $plainSecret,
        ], __('client-credentials::messages.client_created'), 201);

        return $this->runAfterHooks($request, $response, HookAction::CLIENT_STORE);
    }

    public function show(Request $request, string $slug, ?Model $owner = null): JsonResponse
    {
        $owner = $this->resolveOwner($request, $owner);
        $model = $this->getModel();
        $client = $model::ownedBy($owner)->where('slug', $slug)->firstOrFail();

        return $this->success($client);
    }

    public function update(Request $request, string $slug, ?Model $owner = null): JsonResponse
    {
        $owner = $this->resolveOwner($request, $owner);
        $request = $this->runBeforeHooks($request, HookAction::CLIENT_UPDATE);

        $model = $this->getModel();
        $client = $model::ownedBy($owner)->where('slug', $slug)->firstOrFail();

        if ($client->revoked) {
            return $this->failure(__('client-credentials::messages.client_revoked'), 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $client->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $response = $this->success($client, __('client-credentials::messages.client_updated'));

        return $this->runAfterHooks($request, $response, HookAction::CLIENT_UPDATE);
    }

    public function destroy(Request $request, string $slug, ?Model $owner = null): JsonResponse
    {
        $owner = $this->resolveOwner($request, $owner);
        $request = $this->runBeforeHooks($request, HookAction::CLIENT_DELETE);

        $model = $this->getModel();
        $client = $model::ownedBy($owner)->where('slug', $slug)->firstOrFail();
        $client->delete();

        $response = $this->success(null, __('client-credentials::messages.client_deleted'), 204);

        return $this->runAfterHooks($request, $response, HookAction::CLIENT_DELETE);
    }

    public function regenerateSecret(Request $request, string $slug, ?Model $owner = null): JsonResponse
    {
        $owner = $this->resolveOwner($request, $owner);
        $request = $this->runBeforeHooks($request, HookAction::CLIENT_REGENERATE_SECRET);

        $model = $this->getModel();
        $client = $model::ownedBy($owner)->where('slug', $slug)->firstOrFail();
        $newSecret = $client->regenerateSecret();

        $response = $this->success([
            'client' => $client,
            'secret' => $newSecret,
        ], __('client-credentials::messages.secret_regenerated'));

        return $this->runAfterHooks($request, $response, HookAction::CLIENT_REGENERATE_SECRET);
    }
}
