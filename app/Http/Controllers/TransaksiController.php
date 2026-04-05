<?php

namespace App\Http\Controllers;

use App\Models\DetailProduk;
use App\Models\DetailSewaPS;
use App\Models\Pembayaran;
use App\Models\Playstation;
use App\Models\Produk;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    private function transaksiRelations(): array
    {
        return [
            'user:id_user,name,username,email',
            'pembayaran',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
        ];
    }

    private function resolveDurasiMenit(array $item): int
    {
        if (! empty($item['durasi_menit'])) {
            return (int) $item['durasi_menit'];
        }

        if (! empty($item['durasi_jam'])) {
            return (int) $item['durasi_jam'] * 60;
        }

        return 0;
    }

    private function resolveMenitTambahan(Request $request): int
    {
        if ($request->filled('menit_tambahan')) {
            return (int) $request->input('menit_tambahan');
        }

        if ($request->filled('jam_tambahan')) {
            return (int) $request->input('jam_tambahan') * 60;
        }

        return 0;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Transaksi::with($this->transaksiRelations());

        if ($request->filled('status')) {
            $query->where('status_transaksi', $request->input('status'));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }

        if ($request->filled('user_id')) {
            $query->where('id_user', $request->input('user_id'));
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_user' => 'required|exists:users,id_user',

            'sewa' => 'nullable|array',
            'sewa.*.id_ps' => 'required_with:sewa|exists:playstation,id_ps',
            'sewa.*.jam_mulai' => 'required_with:sewa|date',
            'sewa.*.durasi_menit' => 'nullable|integer|min:1',
            'sewa.*.durasi_jam' => 'nullable|integer|min:1',

            'produk' => 'nullable|array',
            'produk.*.id_produk' => 'required_with:produk|exists:produk,id_produk',
            'produk.*.qty' => 'required_with:produk|integer|min:1',
        ]);

        if (empty($request->sewa) && empty($request->produk)) {
            return response()->json([
                'message' => 'Transaksi harus memiliki minimal satu item sewa atau produk.',
            ], 422);
        }

        $sewaItems = collect($request->input('sewa', []));
        if ($sewaItems->contains(fn (array $item) => $this->resolveDurasiMenit($item) < 1)) {
            return response()->json([
                'message' => 'Durasi sewa wajib diisi minimal 1 menit.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $transaksi = Transaksi::create([
                'id_user' => $request->id_user,
                'tanggal' => now(),
                'total_harga' => 0,
                'status_transaksi' => 'aktif',
            ]);

            foreach ($sewaItems as $item) {
                $ps = Playstation::with('tipe')->findOrFail($item['id_ps']);

                if (! $ps->tersedia()) {
                    DB::rollBack();

                    return response()->json([
                        'message' => "PS {$ps->nomor_ps} sedang tidak tersedia.",
                    ], 422);
                }

                $durasiMenit = $this->resolveDurasiMenit($item);
                $durasiJam = max(1, (int) ceil($durasiMenit / 60));
                $jamMulai = Carbon::parse($item['jam_mulai']);
                $jamSelesai = $jamMulai->copy()->addMinutes($durasiMenit);
                $hargaPerJam = (float) ($ps->tipe->harga_sewa ?? 0);
                $subtotal = round(($hargaPerJam / 60) * $durasiMenit, 2);

                DetailSewaPS::create([
                    'id_transaksi' => $transaksi->id_transaksi,
                    'id_ps' => $ps->id_ps,
                    'jam_mulai' => $jamMulai,
                    'durasi_jam' => $durasiJam,
                    'durasi_menit' => $durasiMenit,
                    'jam_selesai' => $jamSelesai,
                    'harga_perjam' => $hargaPerJam,
                    'tipe_ps' => $ps->tipe->nama_tipe ?? null,
                    'subtotal' => $subtotal,
                ]);

                $ps->updateStatus('digunakan');
            }

            foreach ($request->input('produk', []) as $item) {
                $produk = Produk::findOrFail($item['id_produk']);
                $qty = (int) $item['qty'];

                if (! $produk->kurangiStock($qty)) {
                    DB::rollBack();

                    return response()->json([
                        'message' => "Stock produk '{$produk->nama}' tidak mencukupi.",
                    ], 422);
                }

                DetailProduk::create([
                    'id_transaksi' => $transaksi->id_transaksi,
                    'id_produk' => $produk->id_produk,
                    'qty' => $qty,
                    'subtotal' => (float) $produk->harga * $qty,
                ]);
            }

            $transaksi->hitungUlangTotal();

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'Transaksi berhasil dibuat.',
                'data' => $transaksi,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $transaksi = Transaksi::with($this->transaksiRelations())->findOrFail($id);

        return response()->json([
            'data' => $transaksi,
        ]);
    }

    public function selesai(string $id): JsonResponse
    {
        $transaksi = Transaksi::with($this->transaksiRelations())->findOrFail($id);

        if ($transaksi->status_transaksi !== 'aktif') {
            return response()->json([
                'message' => 'Transaksi ini sudah tidak aktif.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($transaksi->detailSewa as $sewa) {
                $sewa->selesaikan();
            }

            $transaksi->update([
                'status_transaksi' => 'selesai',
            ]);

            $transaksi->hitungUlangTotal();

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'Transaksi berhasil diselesaikan.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function batal(string $id): JsonResponse
    {
        $transaksi = Transaksi::with([
            'detailSewa.playstation',
            'detailProduk.produk',
        ])->findOrFail($id);

        if (! in_array($transaksi->status_transaksi, ['aktif', 'pending'], true)) {
            return response()->json([
                'message' => 'Hanya transaksi aktif atau pending yang bisa dibatalkan.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($transaksi->detailSewa as $sewa) {
                if ($sewa->playstation) {
                    $sewa->playstation->updateStatus('tersedia');
                }
            }

            foreach ($transaksi->detailProduk as $detailProduk) {
                if ($detailProduk->produk) {
                    $detailProduk->produk->tambahStock((int) $detailProduk->qty);
                }
            }

            $transaksi->update([
                'status_transaksi' => 'dibatalkan',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaksi berhasil dibatalkan.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function tambahProduk(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with(['detailProduk.produk'])->findOrFail($id);

        if ($transaksi->status_transaksi !== 'aktif') {
            return response()->json([
                'message' => 'Hanya transaksi aktif yang bisa ditambahkan produk.',
            ], 422);
        }

        if ($transaksi->pembayaran && $transaksi->pembayaran->sudahLunas()) {
            return response()->json([
                'message' => 'Transaksi yang sudah lunas tidak bisa diubah.',
            ], 422);
        }

        $request->validate([
            'produk' => 'required|array|min:1',
            'produk.*.id_produk' => 'required|exists:produk,id_produk',
            'produk.*.qty' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->produk as $item) {
                $produk = Produk::findOrFail($item['id_produk']);
                $qty = (int) $item['qty'];

                if (! $produk->kurangiStock($qty)) {
                    DB::rollBack();

                    return response()->json([
                        'message' => "Stock produk '{$produk->nama}' tidak mencukupi.",
                    ], 422);
                }

                $existing = DetailProduk::where('id_transaksi', $transaksi->id_transaksi)
                    ->where('id_produk', $produk->id_produk)
                    ->first();

                if ($existing) {
                    $newQty = $existing->qty + $qty;

                    $existing->update([
                        'qty' => $newQty,
                        'subtotal' => $newQty * (float) $produk->harga,
                    ]);
                } else {
                    DetailProduk::create([
                        'id_transaksi' => $transaksi->id_transaksi,
                        'id_produk' => $produk->id_produk,
                        'qty' => $qty,
                        'subtotal' => $qty * (float) $produk->harga,
                    ]);
                }
            }

            $transaksi->hitungUlangTotal();

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'Produk berhasil ditambahkan ke transaksi.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function tambahWaktu(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with(['detailSewa.playstation.tipe'])->findOrFail($id);

        if ($transaksi->status_transaksi !== 'aktif') {
            return response()->json([
                'message' => 'Hanya transaksi aktif yang bisa ditambah waktu.',
            ], 422);
        }

        if ($transaksi->pembayaran && $transaksi->pembayaran->sudahLunas()) {
            return response()->json([
                'message' => 'Transaksi yang sudah lunas tidak bisa diubah.',
            ], 422);
        }

        $request->validate([
            'id_ps' => 'nullable|exists:playstation,id_ps',
            'menit_tambahan' => 'nullable|integer|min:1',
            'jam_tambahan' => 'nullable|integer|min:1',
        ]);

        $menitTambahan = $this->resolveMenitTambahan($request);
        if ($menitTambahan < 1) {
            return response()->json([
                'message' => 'Tambahan waktu minimal 1 menit.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $detailSewa = $transaksi->detailSewa;

            if ($request->filled('id_ps')) {
                $detailSewa = $detailSewa->where('id_ps', (int) $request->id_ps)->values();
            }

            if ($detailSewa->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Detail sewa untuk PS tersebut tidak ditemukan.',
                ], 404);
            }

            foreach ($detailSewa as $sewa) {
                $sewa->tambahWaktu($menitTambahan);

                if ($sewa->playstation && $sewa->playstation->status_ps !== 'digunakan') {
                    $sewa->playstation->updateStatus('digunakan');
                }
            }

            $transaksi->hitungUlangTotal();

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'Waktu sewa berhasil ditambahkan.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function bayar(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with([
            'user:id_user,name,username,email',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
            'pembayaran',
        ])->findOrFail($id);

        if ($transaksi->status_transaksi !== 'aktif') {
            return response()->json([
                'message' => 'Hanya transaksi aktif yang bisa dibayar.',
            ], 422);
        }

        $request->validate([
            'metode_pembayaran' => 'required|in:cash,online',
            'total_bayar' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $totalTagihan = (float) $transaksi->total_harga;
            $metode = $request->metode_pembayaran;
            $totalBayarInput = (float) ($request->total_bayar ?? 0);

            if ($metode === 'cash') {
                if ($totalBayarInput < $totalTagihan) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Nominal pembayaran cash kurang dari total tagihan.',
                    ], 422);
                }

                $totalBayar = $totalBayarInput;
                $kembalian = $totalBayar - $totalTagihan;
                $statusBayar = 'lunas';
            } else {
                $totalBayar = $totalTagihan;
                $kembalian = 0;
                $statusBayar = 'lunas';
            }

            Pembayaran::updateOrCreate(
                ['id_transaksi' => $transaksi->id_transaksi],
                [
                    'metode_pembayaran' => $metode,
                    'total_bayar' => $totalBayar,
                    'kembalian' => $kembalian,
                    'waktu_bayar' => now(),
                    'status_bayar' => $statusBayar,
                ]
            );

            DB::commit();

            $transaksi->refresh()->load([
                'user:id_user,name,username,email',
                'detailSewa.playstation.tipe',
                'detailProduk.produk',
                'pembayaran',
            ]);

            return response()->json([
                'message' => 'Pembayaran berhasil disimpan.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }
}
