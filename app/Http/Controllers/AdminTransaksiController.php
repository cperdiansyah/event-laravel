<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminTransaksiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        /*  $transaksi = DB::table('transaksis')
            ->join('event', 'transaksi.id_event', '=', 'event.id'); */
        /*  $transaksi = Transaksi::join('events', 'transaksis.id_event', '=', 'events.id')
            ->join('users', 'events.id_panitia', '=', 'users.id')
            ->where('events.id_panitia', '=', auth()->user()->id)
            ->select('events.*', 'transaksis.*')
            ->groupBy('transaksis.id_event')
            ->get();
 */

        $transaksi =
            DB::table('transaksis')->join('events', 'transaksis.id_event', '=', 'events.id')->where('id_panitia', Auth::user()->id)->get();

        return view('pages.admin.transaksi.index', [
            'events' => $transaksi
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        //

        $event = DB::table('events')->where('uuid', $uuid)->first();

        $eventTransaksi = DB::table('transaksis')->join('events', 'transaksis.id_event', '=', 'events.id')
            ->join('users', 'transaksis.id_peserta', '=', 'users.id')
            ->join('pesertas', 'users.id', '=', 'pesertas.id_users')
            ->where('transaksis.id_event', $event->id)
            ->where('transaksis.status_transaksi', '!=', 'not_paid')
            ->select('transaksis.*', 'users.uuid as usersId', 'users.nama_user', 'no_telepon')
            ->get();



        return view('pages.admin.transaksi.show', [
            'transaksi' => $eventTransaksi,
            'event' => $event
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Transaksi  $transaksi
     * @return \Illuminate\Http\Response
     */
    public function edit(Transaksi $transaksi)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Transaksi  $transaksi
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Transaksi $transaksi)
    {
        $event = Transaksi::join('events', 'transaksis.id_event', '=', 'events.id')
            ->where('events.id', $transaksi->id_event)
            ->select('events.*')
            ->first();

        $transaksi->update([
            'status_transaksi' => $request->status_transaksi
        ]);

        return redirect()->route('admin_transaksi_show', $event->uuid);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Transaksi  $transaksi
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transaksi $transaksi)
    {
        //
    }
}
