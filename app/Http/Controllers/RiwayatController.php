<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiwayatController extends Controller
{
    public function getRiwayat(Request $request)
    {
        $user = $request->user();

        $query = DB::table('transaksi')
            ->leftJoin('detail_sewa_ps', 'transaksi.id_transaksi', '=', 'detail_sewa_ps.id_transaksi')
            ->leftJoin('playstation', 'detail_sewa_ps.id_ps', '=', 'playstation.id_ps')
            ->where('transaksi.id_user', $user->id_user)
            ->select(
                'transaksi.id_transaksi',
                'transaksi.tanggal',
                'transaksi.total_harga',
                'transaksi.status_transaksi',
                'detail_sewa_ps.jam_mulai',
                'detail_sewa_ps.jam_selesai',
                'detail_sewa_ps.durasi_jam',
                'detail_sewa_ps.tipe_ps',
                'playstation.nama_ps'
            )
            ->orderBy('transaksi.tanggal', 'desc');

        // Filter tanggal kalau ada
        if ($request->has('tanggal_mulai') && $request->has('tanggal_selesai')) {
            $query->whereBetween('transaksi.tanggal', [
                $request->tanggal_mulai,
                $request->tanggal_selesai
            ]);
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $data
        ], 200);
    }
}