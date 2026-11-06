<?php

namespace App\Services\Cek;

use DOMDocument;
use DOMXPath;

/**
 * Membaca HTML halaman tujuan (yang sudah diunduh RedirectTracer) untuk mencari ciri halaman
 * pengumpul data. Jeda TIDAK mengisi atau mengirim form apa pun, dan tidak menjalankan JavaScript.
 */
final class PageInspector
{
    private const LOWER = "translate(%s,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";

    public function __construct(private readonly UrlHeuristics $heuristics) {}

    /**
     * @return array{signals:array<int,Signal>,title:?string,password_form:bool}
     */
    public function inspect(string $html, string $pageUrl): array
    {
        $empty = ['signals' => [], 'title' => null, 'password_form' => false];
        if (trim($html) === '') {
            return $empty;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return $empty;
        }

        $xpath = new DOMXPath($dom);
        $pageHost = strtolower((string) (parse_url($pageUrl, PHP_URL_HOST) ?? ''));
        $pageDomain = $this->heuristics->registrableOf($pageHost);

        $title = trim((string) $dom->getElementsByTagName('title')->item(0)?->textContent);
        $title = $title !== '' ? mb_substr(preg_replace('/\s+/u', ' ', $title) ?? $title, 0, 120) : null;
        $heading = trim((string) $dom->getElementsByTagName('h1')->item(0)?->textContent);

        $hasPassword = $xpath->query('//input['.sprintf(self::LOWER, '@type')."='password']")->length > 0;
        $signals = [];

        // Kolom yang meminta data sensitif.
        $sensitive = false;
        foreach ($xpath->query('//input') as $input) {
            $hint = $input->getAttribute('name').' '.$input->getAttribute('id').' '.$input->getAttribute('placeholder');
            if (preg_match('/\b(otp|pin|cvv|cvc|kartu|card|rekening)\b/i', $hint) === 1) {
                $sensitive = true;
                break;
            }
        }

        if ($hasPassword) {
            $signals[] = new Signal('password_form', 'page', 'Halaman meminta kata sandi',
                'Halaman tujuan memiliki kolom password. Banyak situs sah juga punya, jadi ini hanya bermakna bila disertai tanda lain.', 2);
        }
        if ($sensitive) {
            $signals[] = new Signal('sensitive_fields', 'page', 'Halaman meminta OTP, PIN, atau data kartu',
                'Ada kolom isian bernama OTP, PIN, CVV, atau nomor kartu/rekening.', 2);
        }

        // Form yang mengirim data ke domain lain.
        foreach ($xpath->query('//form[@action]') as $form) {
            $action = trim($form->getAttribute('action'));
            if (preg_match('~^https?://~i', $action) === 1) {
                $actionHost = strtolower((string) (parse_url($action, PHP_URL_HOST) ?? ''));
                if ($actionHost !== '' && $this->heuristics->registrableOf($actionHost) !== $pageDomain) {
                    $signals[] = new Signal('form_cross_domain', 'page', 'Form mengirim data ke domain lain',
                        "Data isian akan dikirim ke $actionHost, bukan ke situs yang sedang dibuka.", 3);
                    break;
                }
            }
        }

        // Nama merek di judul, tetapi domainnya bukan milik merek itu. Dihitung hanya jika halaman
        // juga meminta kata sandi atau data sensitif (kombinasi), supaya artikel biasa tidak ikut terjaring.
        if ($hasPassword || $sensitive) {
            $brand = $this->heuristics->brandInText(($title ?? '').' '.$heading);
            if ($brand !== null && ! in_array($pageDomain, $brand['domains'], true)) {
                $signals[] = new Signal('brand_in_page_mismatch', 'page', 'Halaman memakai nama '.$brand['brand'].' di domain lain',
                    'Judul halaman menyebut '.$brand['brand'].' dan meminta data, tetapi domainnya ('.$pageDomain.') bukan domain resmi yang kami kenal.', 4);
            }
        }

        // Pengalihan lewat tag meta (tidak diikuti, hanya dilaporkan).
        if ($xpath->query('//meta['.sprintf(self::LOWER, '@http-equiv')."='refresh']")->length > 0) {
            $signals[] = new Signal('meta_refresh', 'redirect', 'Halaman mengalihkan lewat tag meta',
                'Halaman ini mengarahkan pengunjung ke alamat lain secara otomatis. Jeda tidak mengikuti pengalihan jenis ini.', 1);
        }

        return ['signals' => $signals, 'title' => $title, 'password_form' => $hasPassword];
    }
}
