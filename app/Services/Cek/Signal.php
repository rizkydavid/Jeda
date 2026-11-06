<?php

namespace App\Services\Cek;

/**
 * Satu temuan pemeriksaan. Setiap temuan punya id unik: id yang sama hanya dihitung sekali
 * (mencegah penghitungan skor ganda).
 */
final class Signal
{
    public function __construct(
        public readonly string $id,
        public readonly string $group,
        public readonly string $label,
        public readonly string $detail,
        public readonly int $points,
        public readonly int $floor = 0,        // skor minimal jika temuan ini terkonfirmasi
        public readonly bool $needsOther = false, // tanda lemah: hanya dihitung jika ada tanda lain
    ) {}

    public function withPoints(int $points): self
    {
        return new self($this->id, $this->group, $this->label, $this->detail, $points, $this->floor, $this->needsOther);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'group' => $this->group, 'label' => $this->label, 'detail' => $this->detail, 'points' => $this->points];
    }
}
