<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\HostResolver;

/** Pengganti DNS untuk test: tidak ada permintaan DNS sungguhan. */
class FakeHostResolver extends HostResolver
{
    /** @param array<string,array<int,string>> $map host => daftar IP */
    public function __construct(private array $map = [], private array $default = ['93.184.216.34']) {}

    public function resolve(string $host): array
    {
        return $this->map[$host] ?? $this->default;
    }
}
