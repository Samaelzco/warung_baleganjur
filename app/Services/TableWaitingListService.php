<?php

namespace App\Services;

use App\Models\Meja;
use App\Models\Pesanan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TableWaitingListService
{
    public const ACTIVE_STATUSES = ['menunggu', 'sedang_diubah', 'diproses', 'siap'];

    public function occupiedSeats(int|Meja $meja): int
    {
        $mejaId = $meja instanceof Meja ? (int) $meja->id : (int) $meja;

        return (int) Pesanan::query()
            ->where('meja_id', $mejaId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function ($q) {
                $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
            })
            ->sum('jumlah_orang');
    }

    public function remainingSeats(Meja $meja): int
    {
        return max((int) ($meja->kapasitas ?? 4) - $this->occupiedSeats($meja), 0);
    }

    public function hasCapacity(Meja $meja, int $jumlahOrang = 1): bool
    {
        return $this->remainingSeats($meja) >= max($jumlahOrang, 1);
    }

    public function isEmpty(Meja|int $meja): bool
    {
        $model = $meja instanceof Meja ? $meja : Meja::query()->find($meja);
        if (!$model) {
            return false;
        }

        return $model->status === 'kosong' && $this->occupiedSeats($model) === 0;
    }

    public function syncMejaStatus(Meja|int $meja): void
    {
        $model = $meja instanceof Meja ? $meja : Meja::query()->find($meja);
        if (!$model) {
            return;
        }

        $occupied = $this->occupiedSeats($model);
        $nextStatus = $occupied > 0 ? 'terisi' : 'kosong';

        if (!in_array($model->status, ['reservasi', 'nonaktif'], true) && $model->status !== $nextStatus) {
            $model->forceFill(['status' => $nextStatus])->save();
        }
    }

    public function forgetKitchenCache(): void
    {
        Cache::forget('kitchen:status_counts');
    }

    public function activateNextWaitingLists(Meja|int $meja): int
    {
        $mejaId = $meja instanceof Meja ? (int) $meja->id : (int) $meja;
        $activated = 0;

        DB::transaction(function () use ($mejaId, &$activated) {
            $table = Meja::query()->whereKey($mejaId)->lockForUpdate()->first();
            if (!$table) {
                return;
            }

            $this->syncMejaStatus($table);

            if ($this->isEmpty($table)) {
                $waitingList = Pesanan::query()
                    ->where('meja_id', $table->id)
                    ->where('status', 'booking')
                    ->orderBy('waktu_pesan')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($waitingList) {
                    $waitingList->forceFill(['status' => 'menunggu'])->save();
                    $activated++;
                }
            }

            $this->syncMejaStatus($table);
        });

        if ($activated > 0) {
            $this->forgetKitchenCache();
        }

        return $activated;
    }
}
