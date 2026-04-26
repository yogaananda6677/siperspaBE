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
    private const BOOKING_MIN_DELAY_MINUTES = 30;

    private const BOOKING_MAX_DELAY_MINUTES = 180;

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

    private function sudahLunas(Transaksi $transaksi): bool
    {
        return (bool) (
            $transaksi->pembayaran &&
            $transaksi->pembayaran->status_bayar === 'lunas'
        );
    }

    private function bolehDiubah(Transaksi $transaksi): bool
    {
        if (! $transaksi->isAktif()) {
            return false;
        }

        if ($this->sudahLunas($transaksi)) {
            return false;
        }

        return true;
    }

    private function statusMengunciJadwal(): array
    {
        return [
            Transaksi::STATUS_AKTIF,
            Transaksi::STATUS_WAITING,
            Transaksi::STATUS_DIJADWALKAN,
            Transaksi::STATUS_MENUNGGU_PEMBAYARAN,
        ];
    }

    private function getCurrentBlockingSewa(int $idPs, ?int $excludeTransaksiId = null): ?DetailSewaPS
    {
        return DetailSewaPS::with(['transaksi'])
            ->where('id_ps', $idPs)
            ->whereHas('transaksi', function ($q) use ($excludeTransaksiId) {
                $q->whereIn('status_transaksi', $this->statusMengunciJadwal());

                if ($excludeTransaksiId) {
                    $q->where('id_transaksi', '!=', $excludeTransaksiId);
                }
            })
            ->where('jam_selesai', '>', now())
            ->orderBy('jam_selesai')
            ->first();
    }

    private function hasScheduleConflict(
        int $idPs,
        Carbon $jamMulai,
        Carbon $jamSelesai,
        ?int $excludeTransaksiId = null
    ): bool {
        return DetailSewaPS::where('id_ps', $idPs)
            ->whereHas('transaksi', function ($q) use ($excludeTransaksiId) {
                $q->whereIn('status_transaksi', $this->statusMengunciJadwal());

                if ($excludeTransaksiId) {
                    $q->where('id_transaksi', '!=', $excludeTransaksiId);
                }
            })
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->exists();
    }

    private function validateBookingWindow(
        Playstation $ps,
        Carbon $jamMulai,
        int $durasiMenit,
        string $sumberTransaksi,
        ?int $excludeTransaksiId = null
    ): ?string {
        if ($ps->status_ps === 'maintenance') {
            return "PS {$ps->nomor_ps} sedang maintenance.";
        }

        $jamSelesai = $jamMulai->copy()->addMinutes($durasiMenit);
        $now = now();

        if ($sumberTransaksi === Transaksi::SUMBER_ADMIN) {
            if (! $ps->tersedia()) {
                return "PS {$ps->nomor_ps} sedang tidak tersedia.";
            }

            if ($this->hasScheduleConflict($ps->id_ps, $jamMulai, $jamSelesai, $excludeTransaksiId)) {
                return "Jadwal PS {$ps->nomor_ps} bentrok dengan transaksi lain.";
            }

            return null;
        }

        // APLIKASI / PELANGGAN
        if ($ps->status_ps === 'digunakan') {
            $blockingSewa = $this->getCurrentBlockingSewa($ps->id_ps, $excludeTransaksiId);

            if (! $blockingSewa) {
                return "PS {$ps->nomor_ps} sedang digunakan dan belum bisa dibooking.";
            }

            $minimalMulai = Carbon::parse($blockingSewa->jam_selesai)->addMinutes(self::BOOKING_MIN_DELAY_MINUTES);
            $maksimalMulai = Carbon::parse($blockingSewa->jam_selesai)->addMinutes(self::BOOKING_MAX_DELAY_MINUTES);

            if ($jamMulai->lt($minimalMulai)) {
                return "Booking PS {$ps->nomor_ps} minimal mulai {$minimalMulai->format('Y-m-d H:i:s')}.";
            }

            if ($jamMulai->gt($maksimalMulai)) {
                return "Booking PS {$ps->nomor_ps} maksimal mulai {$maksimalMulai->format('Y-m-d H:i:s')}.";
            }
        } elseif ($ps->tersedia()) {
            $selisihMenit = $now->diffInMinutes($jamMulai, false);

            // kalau bukan sekarang, berarti booking nanti
            if ($selisihMenit > 0) {
                $minimalMulai = $now->copy()->addMinutes(self::BOOKING_MIN_DELAY_MINUTES);
                $maksimalMulai = $now->copy()->addMinutes(self::BOOKING_MAX_DELAY_MINUTES);

                if ($jamMulai->lt($minimalMulai)) {
                    return "Booking PS {$ps->nomor_ps} minimal 30 menit dari sekarang.";
                }

                if ($jamMulai->gt($maksimalMulai)) {
                    return "Booking PS {$ps->nomor_ps} maksimal 3 jam dari sekarang.";
                }
            }
        } else {
            return "PS {$ps->nomor_ps} sedang tidak tersedia.";
        }

        if ($this->hasScheduleConflict($ps->id_ps, $jamMulai, $jamSelesai, $excludeTransaksiId)) {
            return "Jadwal PS {$ps->nomor_ps} bentrok dengan transaksi lain.";
        }

        return null;
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

        if ($request->filled('sumber_transaksi')) {
            $query->where('sumber_transaksi', $request->input('sumber_transaksi'));
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate(100)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_user' => 'required|exists:users,id_user',
            'sumber_transaksi' => 'required|in:admin,aplikasi',

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
            $sumberTransaksi = $request->input('sumber_transaksi');

            $statusAwal = $sumberTransaksi === Transaksi::SUMBER_APLIKASI
                ? Transaksi::STATUS_WAITING
                : Transaksi::STATUS_AKTIF;

            $transaksi = Transaksi::create([
                'id_user' => $request->id_user,
                'tanggal' => now(),
                'total_harga' => 0,
                'status_transaksi' => $statusAwal,
                'sumber_transaksi' => $sumberTransaksi,
            ]);

            Pembayaran::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'metode_pembayaran' => 'cash',
                'total_bayar' => 0,
                'kembalian' => 0,
                'waktu_bayar' => null,
                'status_bayar' => 'menunggu',
            ]);

            foreach ($sewaItems as $item) {
                $ps = Playstation::with('tipe')->findOrFail($item['id_ps']);

                $durasiMenit = $this->resolveDurasiMenit($item);
                $durasiJam = max(1, (int) ceil($durasiMenit / 60));
                $jamMulai = Carbon::parse($item['jam_mulai']);
                $jamSelesai = $jamMulai->copy()->addMinutes($durasiMenit);

                $error = $this->validateBookingWindow(
                    $ps,
                    $jamMulai,
                    $durasiMenit,
                    $sumberTransaksi
                );

                if ($error) {
                    DB::rollBack();

                    return response()->json([
                        'message' => $error,
                    ], 422);
                }

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

                if (
                    $sumberTransaksi === Transaksi::SUMBER_ADMIN &&
                    $jamMulai->lte(now())
                ) {
                    $ps->updateStatus('digunakan');
                }
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

        if (! $transaksi->isAktif()) {
            return response()->json([
                'message' => 'Hanya transaksi aktif yang bisa diselesaikan.',
            ], 422);
        }

        if (! $this->sudahLunas($transaksi)) {
            return response()->json([
                'message' => 'Transaksi belum lunas, tidak bisa diselesaikan.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($transaksi->detailSewa as $sewa) {
                $sewa->selesaikan();
            }

            $transaksi->update([
                'status_transaksi' => Transaksi::STATUS_SELESAI,
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
            'pembayaran',
        ])->findOrFail($id);

        if (! in_array($transaksi->status_transaksi, [
            Transaksi::STATUS_AKTIF,
            Transaksi::STATUS_WAITING,
            Transaksi::STATUS_MENUNGGU_PEMBAYARAN,
            Transaksi::STATUS_DIJADWALKAN,
        ], true)) {
            return response()->json([
                'message' => 'Transaksi ini tidak bisa dibatalkan.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($transaksi->detailSewa as $sewa) {
                if (
                    $sewa->playstation &&
                    $transaksi->status_transaksi === Transaksi::STATUS_AKTIF
                ) {
                    $sewa->playstation->updateStatus('tersedia');
                }
            }

            foreach ($transaksi->detailProduk as $detailProduk) {
                if ($detailProduk->produk) {
                    $detailProduk->produk->tambahStock((int) $detailProduk->qty);
                }
            }

            $transaksi->update([
                'status_transaksi' => Transaksi::STATUS_DIBATALKAN,
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

    public function reject(string $id): JsonResponse
    {
        $transaksi = Transaksi::with([
            'detailSewa.playstation',
            'detailProduk.produk',
            'pembayaran',
        ])->findOrFail($id);

        if ($transaksi->status_transaksi !== Transaksi::STATUS_WAITING) {
            return response()->json([
                'message' => 'Hanya transaksi waiting yang bisa ditolak.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($transaksi->detailProduk as $detailProduk) {
                if ($detailProduk->produk) {
                    $detailProduk->produk->tambahStock((int) $detailProduk->qty);
                }
            }

            $transaksi->update([
                'status_transaksi' => Transaksi::STATUS_DITOLAK,
            ]);

            if ($transaksi->pembayaran) {
                $transaksi->pembayaran->update([
                    'status_bayar' => 'gagal',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Booking berhasil ditolak.',
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
        $transaksi = Transaksi::with(['detailProduk.produk', 'pembayaran'])->findOrFail($id);

        if (! $this->bolehDiubah($transaksi)) {
            return response()->json([
                'message' => 'Transaksi ini tidak bisa ditambahkan produk.',
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
        $transaksi = Transaksi::with(['detailSewa.playstation.tipe', 'pembayaran'])->findOrFail($id);

        if (! $this->bolehDiubah($transaksi)) {
            return response()->json([
                'message' => 'Transaksi ini tidak bisa ditambah waktu.',
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

        if (! in_array($transaksi->status_transaksi, [
            Transaksi::STATUS_AKTIF,
            Transaksi::STATUS_MENUNGGU_PEMBAYARAN,
        ], true)) {
            return response()->json([
                'message' => 'Status transaksi ini tidak bisa dibayar.',
            ], 422);
        }

        if ($transaksi->pembayaran && $transaksi->pembayaran->sudahLunas()) {
            return response()->json([
                'message' => 'Transaksi ini sudah lunas.',
            ], 422);
        }

        $request->validate([
            'metode_pembayaran' => 'required|in:cash,online',
            'total_bayar' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $transaksi->hitungUlangTotal();

            $totalTagihan = (float) $transaksi->total_harga;
            $metode = $request->input('metode_pembayaran');
            $totalBayarInput = (float) ($request->input('total_bayar') ?? 0);

            $totalBayar = 0;
            $kembalian = 0;
            $waktuBayar = null;
            $statusBayar = Pembayaran::STATUS_MENUNGGU;

            if ($metode === 'cash') {
                if ($totalBayarInput < $totalTagihan) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Nominal pembayaran cash kurang dari total tagihan.',
                    ], 422);
                }

                $totalBayar = $totalBayarInput;
                $kembalian = $totalBayar - $totalTagihan;

                if ($transaksi->sumber_transaksi === Transaksi::SUMBER_ADMIN) {
                    $statusBayar = Pembayaran::STATUS_LUNAS;
                    $waktuBayar = now();
                } else {
                    $statusBayar = Pembayaran::STATUS_MENUNGGU_VALIDASI;
                    $waktuBayar = null;
                }
            } else {
                $totalBayar = $totalTagihan;
                $kembalian = 0;
                $statusBayar = Pembayaran::STATUS_LUNAS;
                $waktuBayar = now();
            }

            Pembayaran::updateOrCreate(
                ['id_transaksi' => $transaksi->id_transaksi],
                [
                    'metode_pembayaran' => $metode,
                    'total_bayar' => $totalBayar,
                    'kembalian' => $kembalian,
                    'waktu_bayar' => $waktuBayar,
                    'status_bayar' => $statusBayar,
                ]
            );

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

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

    public function approve(string $id): JsonResponse
    {
        $transaksi = Transaksi::with(['detailSewa.playstation.tipe', 'pembayaran'])->findOrFail($id);

        if ($transaksi->status_transaksi !== Transaksi::STATUS_WAITING) {
            return response()->json([
                'message' => 'Hanya transaksi waiting yang bisa di-approve.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $now = now();
            $bolehLangsungAktif = true;

            foreach ($transaksi->detailSewa as $sewa) {
                $ps = $sewa->playstation;

                if (! $ps) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Data PlayStation tidak ditemukan.',
                    ], 422);
                }

                $jamMulai = Carbon::parse($sewa->jam_mulai);
                $durasiMenit = (int) ($sewa->durasi_menit ?: ((int) $sewa->durasi_jam * 60));
                $error = $this->validateBookingWindow(
                    $ps,
                    $jamMulai,
                    $durasiMenit,
                    $transaksi->sumber_transaksi,
                    $transaksi->id_transaksi
                );

                if ($error) {
                    DB::rollBack();

                    return response()->json([
                        'message' => $error,
                    ], 422);
                }

                if ($now->lt($jamMulai)) {
                    $bolehLangsungAktif = false;
                }

                if ($jamMulai->lte($now) && ! $ps->tersedia()) {
                    DB::rollBack();

                    return response()->json([
                        'message' => "PS {$ps->nomor_ps} masih digunakan dan belum bisa langsung diaktifkan.",
                    ], 422);
                }
            }

            if ($bolehLangsungAktif) {
                foreach ($transaksi->detailSewa as $sewa) {
                    if ($sewa->playstation) {
                        $sewa->playstation->updateStatus('digunakan');
                    }
                }

                $transaksi->update([
                    'status_transaksi' => Transaksi::STATUS_AKTIF,
                ]);
            } else {
                $transaksi->update([
                    'status_transaksi' => Transaksi::STATUS_DIJADWALKAN,
                ]);
            }

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'Transaksi berhasil di-approve.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function transaksiSaya(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $data = Transaksi::with($this->transaksiRelations())
            ->where('id_user', $user->id_user)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }
}
