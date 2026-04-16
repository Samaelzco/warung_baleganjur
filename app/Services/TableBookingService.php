<?php

namespace App\Services;

use App\Models\Meja;
use App\Models\Pesanan;
use Illuminate\Support\Facades\DB;

class TableBookingService
{
    public const ACTIVE_STATUSES = ['menunggu', 'diproses', 'siap'];

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

    public function syncMejaStatus(Meja|int $meja): void
    {
        $model = $meja instanceof Meja ? $meja : Meja::query()->find($meja);
        if (!$model) {
            return;
        }

        $occupied = $this->occupiedSeats($model);
        $nextStatus = $occupied > 0 ? 'terisi' : 'kosong';

        if ($model->status !== 'reservasi' && $model->status !== $nextStatus) {
            $model->forceFill(['status' => $nextStatus])->save();
        }
    }

    public function activateNextBookings(Meja|int $meja): int
    {
        $mejaId = $meja instanceof Meja ? (int) $meja->id : (int) $meja;
        $activated = 0;

        DB::transaction(function () use ($mejaId, &$activated) {
            $table = Meja::query()->whereKey($mejaId)->lockForUpdate()->first();
            if (!$table) {
                return;
            }

            while (true) {
                $remaining = $this->remainingSeats($table);
                if ($remaining <= 0) {
                    break;
                }

                $booking = Pesanan::query()
                    ->where('meja_id', $table->id)
                    ->where('status', 'booking')
                    ->orderBy('waktu_pesan')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (!$booking) {
                    break;
                }

                $jumlahOrang = max((int) ($booking->jumlah_orang ?? 1), 1);
                if ($jumlahOrang > $remaining) {
                    break;
                }

                $booking->forceFill(['status' => 'menunggu'])->save();
                $activated++;
            }

            $this->syncMejaStatus($table);
        });

        return $activated;
    }
}
