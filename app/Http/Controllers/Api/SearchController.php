<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * 全局搜索
     *
     * GET /api/search?q=xxx
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        if (strlen($q) < 2) {
            return ApiResponse::success([
                'licenses' => [],
                'customers' => [],
                'products' => [],
                'tickets' => [],
                'total' => 0,
            ]);
        }

        $tenantId = $request->user()->tenant_id;
        $isSuperAdmin = $request->user()->hasPermissionTo('super-admin');

        $limit = min((int) $request->input('limit', 5), 10);
        $types = $request->input('types'); // optional filter: comma-separated: licenses,customers,products,tickets

        $requestedTypes = $types ? explode(',', $types) : ['licenses', 'customers', 'products', 'tickets'];

        $results = [];
        $total = 0;

        // ─── License ───
        if (in_array('licenses', $requestedTypes)) {
            $licenseQuery = License::query()
                ->select('id', 'license_key', 'type', 'status', 'tenant_id')
                ->where('license_key', 'like', "%{$q}%");

            if (!$isSuperAdmin) {
                $licenseQuery->where('tenant_id', $tenantId);
            }

            $licenses = $licenseQuery->limit($limit)->get()->map(fn($l) => [
                'type' => 'license',
                'id' => $l->id,
                'title' => $l->license_key,
                'description' => $l->type . ' · ' . $l->status,
                'url' => "/licenses/{$l->id}",
                'icon' => 'Key',
            ]);

            $results = array_merge($results, $licenses->toArray());
            $total += $licenses->count();
        }

        // ─── Customer ───
        if (in_array('customers', $requestedTypes)) {
            $customerQuery = Customer::query()
                ->select('id', 'name', 'email', 'company', 'tenant_id')
                ->where(function ($qry) use ($q) {
                    $qry->where('name', 'like', "%{$q}%")
                         ->orWhere('email', 'like', "%{$q}%")
                         ->orWhere('company', 'like', "%{$q}%");
                });

            if (!$isSuperAdmin) {
                $customerQuery->where('tenant_id', $tenantId);
            }

            $customers = $customerQuery->limit($limit)->get()->map(fn($c) => [
                'type' => 'customer',
                'id' => $c->id,
                'title' => $c->name,
                'description' => $c->email . ($c->company ? ' · ' . $c->company : ''),
                'url' => "/customers/{$c->id}",
                'icon' => 'User',
            ]);

            $results = array_merge($results, $customers->toArray());
            $total += $customers->count();
        }

        // ─── Product ───
        if (in_array('products', $requestedTypes)) {
            $productQuery = Product::query()
                ->select('id', 'name', 'description', 'tenant_id')
                ->where('name', 'like', "%{$q}%");

            if (!$isSuperAdmin) {
                $productQuery->where('tenant_id', $tenantId);
            }

            $products = $productQuery->limit($limit)->get()->map(fn($p) => [
                'type' => 'product',
                'id' => $p->id,
                'title' => $p->name,
                'description' => $p->description ? str($p->description)->limit(60) : '',
                'url' => "/products/{$p->id}",
                'icon' => 'Goods',
            ]);

            $results = array_merge($results, $products->toArray());
            $total += $products->count();
        }

        // ─── Ticket ───
        if (in_array('tickets', $requestedTypes)) {
            $ticketQuery = Ticket::query()
                ->select('id', 'subject', 'status', 'priority', 'tenant_id')
                ->where(function ($qry) use ($q) {
                    $qry->where('subject', 'like', "%{$q}%")
                         ->orWhere('description', 'like', "%{$q}%");
                });

            if (!$isSuperAdmin) {
                $ticketQuery->where('tenant_id', $tenantId);
            }

            $tickets = $ticketQuery->limit($limit)->get()->map(fn($t) => [
                'type' => 'ticket',
                'id' => $t->id,
                'title' => $t->subject,
                'description' => $t->status . ' · ' . $t->priority,
                'url' => "/tickets/{$t->id}",
                'icon' => 'Tickets',
            ]);

            $results = array_merge($results, $tickets->toArray());
            $total += $tickets->count();
        }

        return ApiResponse::success([
            'items' => $results,
            'total' => $total,
        ]);
    }
}
