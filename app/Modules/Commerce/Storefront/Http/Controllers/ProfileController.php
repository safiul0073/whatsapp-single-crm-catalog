<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Http\Requests\ProfileRequest;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $r, CustomerService $s): JsonResponse
    {
        return response()->json(['data' => $s->profile($r->attributes->get('store_customer'))]);
    }

    public function update(ProfileRequest $r, CustomerService $s): JsonResponse
    {
        $c = $r->attributes->get('store_customer');

        return response()->json(['data' => $s->profile($s->updateProfile($c, $r->validated()))]);
    }

    public function addresses(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->addressesQuery($r)->orderByDesc('is_default')->get()]);
    }

    public function saveAddress(ProfileRequest $r, ?int $id = null): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        $a = $id ? $this->addressesQuery($r)->findOrFail($id) : new CustomerAddress(['workspace_id' => $c->workspace_id, 'customer_id' => $c->id]);
        $a->fill($r->validated())->save();

        return response()->json(['data' => $a]);
    }

    public function deleteAddress(Request $r, int $id): JsonResponse
    {
        $this->addressesQuery($r)->findOrFail($id)->delete();

        return response()->json(['data' => ['success' => true]]);
    }

    public function defaultAddress(Request $r, int $id): JsonResponse
    {
        DB::transaction(function () use ($r, $id): void {
            $c = $r->attributes->get('store_customer');
            $c->newQuery()->lockForUpdate()->findOrFail($c->id);
            $a = $this->addressesQuery($r)->findOrFail($id);
            $this->addressesQuery($r)->update(['is_default' => false]);
            $a->update(['is_default' => true]);
        });

        return $this->addresses($r);
    }

    private function addressesQuery(Request $r): Builder
    {
        $c = $r->attributes->get('store_customer');

        return CustomerAddress::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id);
    }
}
