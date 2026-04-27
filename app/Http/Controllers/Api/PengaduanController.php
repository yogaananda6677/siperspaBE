<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaduan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PengaduanController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $query = Pengaduan::with(['pengadu', 'admin'])
            ->latest();

        if ($user->role !== 'admin') {
            $query->where('id_pengadu', $user->id_user);
        }

        if ($request->filled('status')) {
            if ($request->status === 'aktif') {
                $query->whereNotIn('status_pengaduan', ['selesai']);
            } else {
                $query->where('status_pengaduan', $request->status);
            }
        }

        if ($request->filled('kategori')) {
            $query->where('kategori_aduan', $request->kategori);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('judul_pengaduan', 'like', "%{$search}%")
                    ->orWhere('isi_pengaduan', 'like', "%{$search}%")
                    ->orWhereHas('pengadu', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->get('per_page', 100);

        if ($request->boolean('paginate')) {
            return response()->json($query->paginate($perPage));
        }

        return response()->json([
            'data' => $query->limit($perPage)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $request->validate([
            'judul_pengaduan' => ['required', 'string', 'max:255'],
            'kategori_aduan' => [
                'required',
                'in:ps_rusak,pelayanan,kebersihan,pembayaran,fasilitas,lainnya',
            ],
            'isi_pengaduan' => ['required', 'string'],
            'foto_bukti' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $fotoPath = null;

        if ($request->hasFile('foto_bukti')) {
            $fotoPath = $request->file('foto_bukti')->store('pengaduan', 'public');
        }

        $pengaduan = Pengaduan::create([
            'id_pengadu' => $user->id_user,
            'id_admin' => null,
            'judul_pengaduan' => $request->judul_pengaduan,
            'kategori_aduan' => $request->kategori_aduan,
            'isi_pengaduan' => $request->isi_pengaduan,
            'foto_bukti' => $fotoPath,
            'status_pengaduan' => 'pending',
            'catatan_admin' => null,
            'ditangani_pada' => null,
            'diselesaikan_pada' => null,
        ]);

        $pengaduan->load(['pengadu', 'admin']);

        return response()->json([
            'message' => 'Pengaduan berhasil dibuat.',
            'data' => $pengaduan,
        ], 201);
    }

    public function show(Pengaduan $pengaduan)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role !== 'admin' && $pengaduan->id_pengadu !== $user->id_user) {
            return response()->json([
                'message' => 'Kamu tidak memiliki akses ke pengaduan ini.',
            ], 403);
        }

        $pengaduan->load(['pengadu', 'admin']);

        return response()->json([
            'data' => $pengaduan,
        ]);
    }

    public function cancel(Pengaduan $pengaduan)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($pengaduan->id_pengadu !== $user->id_user) {
            return response()->json([
                'message' => 'Kamu tidak memiliki akses ke pengaduan ini.',
            ], 403);
        }

        if (! in_array($pengaduan->status_pengaduan, ['pending', 'proses'])) {
            return response()->json([
                'message' => 'Pengaduan tidak bisa dibatalkan.',
            ], 422);
        }

        $pengaduan->update([
            'status_pengaduan' => 'dibatalkan',
        ]);

        $pengaduan->load(['pengadu', 'admin']);

        return response()->json([
            'message' => 'Pengaduan berhasil dibatalkan.',
            'data' => $pengaduan,
        ]);
    }
}
