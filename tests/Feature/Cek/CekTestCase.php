<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\HostResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dasar semua test Cek.
 *
 * KEAMANAN DATA: test selalu memakai SQLite di memori, apa pun isi .env/phpunit.xml,
 * jadi tidak pernah menyentuh database MySQL asli. Tidak ada permintaan jaringan sungguhan:
 * semua layanan eksternal diganti Http::fake, dan permintaan yang tidak diduga akan membuat test gagal.
 */
abstract class CekTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'services.safe_browsing.key' => 'kunci-uji',
            'services.virustotal.key' => 'kunci-uji',
            'app.debug' => false,
        ]);
        DB::purge();
        DB::setDefaultConnection('sqlite');
        $this->artisan('migrate', ['--force' => true]);

        $this->app->instance(HostResolver::class, new FakeHostResolver);
        Http::preventStrayRequests();
    }

    /**
     * Menyiapkan layanan eksternal palsu. Stub dari test didahulukan (yang cocok pertama yang dipakai).
     * Bawaan: Safe Browsing kosong, VirusTotal belum kenal (404), domain berusia ±11 tahun.
     */
    protected function fakeServices(array $stubs = []): void
    {
        Http::fake($stubs + [
            'safebrowsing.googleapis.com/*' => Http::response([], 200),
            'www.virustotal.com/api/v3/urls/*' => Http::response([], 404),
            'rdap.org/*' => Http::response($this->rdap(4000)),
        ]);
    }

    protected function rdap(int $daysOld, ?string $registrar = null): array
    {
        $json = ['events' => [['eventAction' => 'registration', 'eventDate' => now()->subDays($daysOld)->toIso8601String()]]];
        if ($registrar) {
            $json['entities'] = [['roles' => ['registrar'], 'vcardArray' => ['vcard', [['fn', (object) [], 'text', $registrar]]]]];
        }

        return $json;
    }

    protected function html(string $title, string $body = '')
    {
        return Http::response("<html><head><title>$title</title></head><body>$body</body></html>", 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    protected function check(string $url, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/jeda/api/cek-tautan', ['url' => $url] + $extra);
    }

    protected function signalIds(\Illuminate\Testing\TestResponse $response): array
    {
        return array_column($response->json('signals') ?? [], 'id');
    }
}
