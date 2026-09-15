<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\JobPost;
use App\Services\PlanGate;
use Illuminate\Http\Request;

class JobPostController extends Controller
{
    public function index(Request $request)
    {
        $jobs = JobPost::where('carrier_id', $this->carrierFor($request)->id)
            ->withCount('applications')
            ->latest()
            ->paginate(20);

        return response()->json($jobs);
    }

    public function store(Request $request, PlanGate $plans)
    {
        $carrier = $this->carrierFor($request);

        if ($plans->hasReachedJobLimit($carrier)) {
            return response()->json([
                'message' => 'Your plan allows ' . $plans->maxActiveJobs($carrier) . ' active job posts. '
                    . 'Close one or upgrade to post more.',
                'code'    => 'plan_limit_reached',
            ], 402);
        }

        $job = JobPost::create($this->validated($request) + ['carrier_id' => $carrier->id]);

        return response()->json(['job' => $job], 201);
    }

    public function show(Request $request, JobPost $jobPost)
    {
        $this->authorizeCarrier($request, $jobPost);

        return response()->json(['job' => $jobPost->loadCount('applications')]);
    }

    public function update(Request $request, JobPost $jobPost)
    {
        $this->authorizeCarrier($request, $jobPost);

        $jobPost->fill($this->validated($request))->save();

        return response()->json(['job' => $jobPost->fresh()]);
    }

    public function destroy(Request $request, JobPost $jobPost)
    {
        $this->authorizeCarrier($request, $jobPost);

        $jobPost->delete();

        return response()->json(['message' => 'Job deleted.']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:10000'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'size:2'],
            'route_type'    => ['nullable', 'in:otr,regional,local,dedicated'],
            'driver_type'   => ['nullable', 'in:company_driver,owner_operator,lease_purchase'],
            'equipment'     => ['nullable', 'string', 'max:100'],
            'pay_min_cents' => ['nullable', 'integer', 'min:0'],
            'pay_max_cents' => ['nullable', 'integer', 'min:0'],
            'pay_unit'      => ['nullable', 'in:per_mile,per_week,percentage'],
            'requirements'  => ['nullable', 'array'],
            'is_open'       => ['nullable', 'boolean'],
        ]);
    }

    protected function carrierFor(Request $request): Carrier
    {
        return Carrier::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['company_name' => $request->user()->name]
        );
    }

    protected function authorizeCarrier(Request $request, JobPost $jobPost): void
    {
        abort_unless($jobPost->carrier_id === $this->carrierFor($request)->id, 403, 'This job is not yours.');
    }
}
