<?php

namespace App\Services;

use App\Models\Pesanan;
use Illuminate\Support\Str;

class OrderStatusService
{
    public function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Pesanan::query()->where('status_token', $token)->exists());

        return $token;
    }

    public function tokenMatches(Pesanan $pesanan, string $token): bool
    {
        return !blank($pesanan->status_token) && hash_equals((string) $pesanan->status_token, $token);
    }

    public function serializeCustomerOrder(Pesanan $pesanan): array
    {
        $pesanan->loadMissing(['details.menu:id,nama_menu,nama_menu_en', 'details.addons:id,nama_addon,nama_addon_en']);

        return [
            'id' => (int) $pesanan->id,
            'kode_pesanan' => (string) $pesanan->kode_pesanan,
            'status' => (string) $pesanan->status,
            'status_token' => (string) ($pesanan->status_token ?? ''),
            'subtotal' => (float) ($pesanan->subtotal ?? 0),
            'discount_total' => (float) ($pesanan->discount_total ?? 0),
            'tax_total' => (float) ($pesanan->tax_total ?? 0),
            'total_harga' => (float) ($pesanan->total_harga ?? 0),
            'items' => $pesanan->details
                ->groupBy(function ($detail) {
                    $ids = $detail->addons?->pluck('id')
                        ?->map(fn ($value) => (int) $value)
                        ?->filter(fn ($value) => $value > 0)
                        ?->unique()
                        ?->sort()
                        ?->values()
                        ?->all() ?? [];

                    return (int) $detail->menu_id . ':' . implode(',', $ids);
                })
                ->map(function ($rows) {
                    $first = $rows->first();
                    $addons = $first?->addons
                        ? $first->addons->map(fn ($addon) => (string) $addon->nama_addon_localized)->filter()->values()->all()
                        : [];

                    return [
                        'menu' => (string) ($first?->menu?->nama_menu_localized ?? __('Menu')),
                        'qty' => (int) $rows->sum('qty'),
                        'subtotal' => (float) $rows->sum('subtotal'),
                        'addons' => $addons,
                    ];
                })
                ->sortBy(function ($row) {
                    $menu = strtolower((string) ($row['menu'] ?? ''));
                    $addons = strtolower(implode(',', (array) ($row['addons'] ?? [])));

                    return $menu . '|' . $addons;
                })
                ->values()
                ->all(),
        ];
    }
}
