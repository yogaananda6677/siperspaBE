<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaduan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            'foto_bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,3gp', 'max:51200'],
        ]);

        $fotoPath = null;

        if ($request->hasFile('foto_bukti')) {
            $uploaded = $request->file('foto_bukti');

            Log::info('File pengaduan diterima', [
                'nama_asli' => $uploaded->getClientOriginalName(),
                'ukuran_kb' => round($uploaded->getSize() / 1024, 1),
                'mime' => $uploaded->getMimeType(),
                'is_valid' => $uploaded->isValid(),
            ]);

            if (! $uploaded->isValid()) {
                // File rusak/tidak lengkap saat upload (mis. koneksi putus di tengah jalan)
                Log::error('File pengaduan tidak valid: ' . $uploaded->getErrorMessage());
                return response()->json([
                    'message' => 'Upload foto gagal: file tidak lengkap atau rusak. Coba lagi.',
                ], 422);
            }

            try {
                $fotoPath = $uploaded->store('pengaduan', 'public');

                if (! $fotoPath) {
                    throw new \RuntimeException('store() mengembalikan null/false');
                }

                Log::info('Foto pengaduan tersimpan di: ' . $fotoPath);
            } catch (Throwable $e) {
                // Ini yang sebelumnya tampil di HP sebagai "the stream or file ... could not be opened"
                Log::error('Gagal menyimpan foto pengaduan: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);

                return response()->json([
                    'message' => 'Gagal menyimpan foto di server. Cek permission folder storage.',
                ], 500);
            }
        } else {
            Log::info('Pengaduan dibuat tanpa foto bukti.');
        }

        try {
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
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan data pengaduan ke database: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Gagal menyimpan data pengaduan ke database.',
            ], 500);
        }

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
