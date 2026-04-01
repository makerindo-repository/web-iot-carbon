<?php

namespace App\Http\Controllers;

use App\Models\PaymentHistory;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function index()
    {
        return view('pages.subscription.index');
    }

    public function indexPlan()
    {
        $plans = SubscriptionPlan::get();
        return view('pages.subscription.plan.index', compact('plans'));
    }

    public function indexHistory()
    {
        $histories = PaymentHistory::get();
        return view('pages.subscription.history.index', compact('histories'));
    }

    public function createPlan()
    {
        return view('pages.subscription.plan.create');
    }

    public function storePlan(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'quota' => 'nullable|integer',
            'duration' => 'required|integer',
            'price' => 'nullable|integer'
        ], [
            'name.required' => 'Nama paket harus diisi.',
            'name.string' => 'Nama paket harus berupa string yang valid.',
            'quota.integer' => 'Quota harus berupa angka yang valid.',
            'duration.integer' => 'Durasi harus diisi.',
            'duration.integer' => 'Durasi harus berupa angka yang valid.',
            'price.integer' => 'Harga harus berupa angka yang valid.',
        ]);

        $data = [
            'name' => $request->name,
            'quota' => $request->quota,
            'duration' => $request->duration,
            'price' => $request->price
        ];

        $post = SubscriptionPlan::create($data);

        activity()
            ->performedOn($post)
            ->event('create')
            ->causedBy(Auth::user())
            ->log('Paket langganan baru ditambahkan: ' . $request->name);

        return redirect()->route('subscription.plan.index')->with('success', 'Data Paket Langganan berhasil ditambahkan.');
    }

    public function editPlan($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        return view('pages.subscription.plan.edit', compact('plan'));
    }

    public function updatePlan(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'quota' => 'nullable|integer',
            'duration' => 'required|integer',
            'price' => 'nullable|integer'
        ], [
            'name.required' => 'Nama paket harus diisi.',
            'name.string' => 'Nama paket harus berupa string yang valid.',
            'quota.integer' => 'Quota harus berupa angka yang valid.',
            'duration.integer' => 'Durasi harus diisi.',
            'duration.integer' => 'Durasi harus berupa angka yang valid.',
            'price.integer' => 'Harga harus berupa angka yang valid.',
        ]);

        $data = [
            'name' => $request->name,
            'quota' => $request->quota,
            'duration' => $request->duration,
            'price' => $request->price
        ];

        $plan = SubscriptionPlan::findOrFail($id);
        $beforeUpdate = $plan->getOriginal();
        $plan->update($data);

        $changes = [];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) &&  $beforeUpdate[$key] !== $value) {
                $changes[$key] = [
                    'old' => $beforeUpdate[$key],
                    'new' => $value,
                ];
            }
        }

        activity()
            ->performedOn($plan)
            ->event('update')
            ->withProperties(['changes' => $changes])
            ->causedBy(Auth::user())
            ->log('Paket langganan ' . $plan->name . ' berhasil diupdate');

        return redirect()->route('subscription.plan.index')->with('success', 'Data Paket Langganan berhasil diupdate.');
    }
}
