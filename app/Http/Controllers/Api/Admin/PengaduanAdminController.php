<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengaduan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PengaduanAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Pengaduan::with(['pengadu', 'admin'])
            ->whereNotIn('status_pengaduan', ['selesai'])
            ->latest();

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
            'summary' => $this->summary(),
        ]);
    }

    public function selesai(Request $request)
    {
        $query = Pengaduan::with(['pengadu', 'admin'])
            ->where('status_pengaduan', 'selesai')
            ->latest();

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
            'summary' => $this->summary(),
        ]);
    }

    public function show(Pengaduan $pengaduan)
    {
        $pengaduan->load(['pengadu', 'admin']);

        return response()->json([
            'data' => $pengaduan,
        ]);
    }

    public function updateStatus(Request $request, Pengaduan $pengaduan)
    {
        $user = Auth::user();

        $request->validate([
            'status_pengaduan' => ['required', 'in:pending,proses,selesai,dibatalkan'],
            'catatan_admin' => ['nullable', 'string'],
        ]);

        $data = [
            'status_pengaduan' => $request->status_pengaduan,
            'catatan_admin' => $request->catatan_admin,
            'id_admin' => $user->id_user,
        ];

        if ($request->status_pengaduan === 'proses') {
            $data['ditangani_pada'] = $pengaduan->ditangani_pada ?: now();
        }

        if ($request->status_pengaduan === 'selesai') {
            $data['diselesaikan_pada'] = now();

            if (! $pengaduan->ditangani_pada) {
                $data['ditangani_pada'] = now();
            }
        }

        if ($request->status_pengaduan === 'pending') {
            $data['ditangani_pada'] = null;
            $data['diselesaikan_pada'] = null;
        }

        if ($request->status_pengaduan === 'dibatalkan') {
            if (! $pengaduan->ditangani_pada) {
                $data['ditangani_pada'] = now();
            }
        }

        $pengaduan->update($data);
        $pengaduan->load(['pengadu', 'admin']);

        return response()->json([
            'message' => 'Status pengaduan berhasil diperbarui.',
            'data' => $pengaduan,
        ]);
    }

    public function destroy(Pengaduan $pengaduan)
    {
        if ($pengaduan->foto_bukti) {
            Storage::disk('public')->delete($pengaduan->foto_bukti);
        }

        $pengaduan->delete();

        return response()->json([
            'message' => 'Pengaduan berhasil dihapus.',
        ]);
    }

    private function summary(): array
    {
        return [
            'total' => Pengaduan::count(),
            'aktif' => Pengaduan::whereNotIn('status_pengaduan', ['selesai'])->count(),
            'pending' => Pengaduan::where('status_pengaduan', 'pending')->count(),
            'proses' => Pengaduan::where('status_pengaduan', 'proses')->count(),
            'selesai' => Pengaduan::where('status_pengaduan', 'selesai')->count(),
            'dibatalkan' => Pengaduan::where('status_pengaduan', 'dibatalkan')->count(),
        ];
    }
}
