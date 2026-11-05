<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Endpoint lintas modul: checklist (Jam Emas, Keluarga, Kartu),
 * pencatatan aksi bantuan, dan ringkasan progres semua modul.
 */
class ProgresController extends Controller
{
    use HasSessionKey;

    public function saveChecklist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => ['required', Rule::in(['jam-emas', 'keluarga', 'kartu'])],
            'situation' => ['nullable', 'string', 'max:80'],
            'item' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'done' => ['required', 'boolean'],
        ]);
        $key = ['session_key' => $this->sessionKey($request), 'feature' => $data['feature'], 'situation' => $data['situation'] ?? 'umum', 'item_key' => $data['item']];
        DB::table('jeda_checklist_items')->updateOrInsert($key, ['is_done' => $data['done'], 'updated_at' => now(), 'created_at' => now()]);
        return response()->json(['ok' => true, 'saved' => true]);
    }

    public function supportAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => ['required', 'string', 'max:50'],
            'action' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 ._-]+$/'],
        ]);
        DB::table('jeda_support_actions')->insert([
            'session_key' => $this->sessionKey($request), 'feature' => $data['feature'], 'action' => $data['action'],
            'metadata' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function progress(Request $request): JsonResponse
    {
        $key = $this->sessionKey($request);
        return response()->json([
            'ok' => true,
            'message_analyses' => DB::table('jeda_message_analyses')->where('session_key', $key)->count(),
            'link_checks' => DB::table('jeda_link_checks')->where('session_key', $key)->count(),
            'file_checks' => DB::table('jeda_file_checks')->where('session_key', $key)->count(),
            'simulation_attempts' => DB::table('jeda_simulation_attempts')->where('session_key', $key)->count(),
            'strong_simulation_choices' => DB::table('jeda_simulation_attempts')->where('session_key', $key)->where('strong_choice', true)->count(),
            'checklist_done' => DB::table('jeda_checklist_items')->where('session_key', $key)->where('is_done', true)->count(),
        ]);
    }
}
