<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $servletsPath = resource_path('data/servlets.json');

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->getAuthIdentifier(),
                    'name' => $request->user()->name ?? null,
                    'email' => $request->user()->email ?? null,
                ] : null,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('flash.success'),
                'error' => $request->session()->get('flash.error'),
                'warning' => $request->session()->get('flash.warning'),
                'info' => $request->session()->get('flash.info'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'servlets' => function () use ($servletsPath) {
                if (! file_exists($servletsPath)) {
                    return [];
                }
                $all = json_decode(file_get_contents($servletsPath), true) ?: [];
                // Только сервлеты с FQN classname (namespace) попадают в сайдбар.
                $filtered = [];
                foreach ($all as $category => $items) {
                    foreach ($items as $key => $servlet) {
                        if (str_contains($servlet['classname'] ?? '', '\\')) {
                            $filtered[$category][$key] = $servlet;
                        }
                    }
                }

                return $filtered;
            },
        ];
    }
}
