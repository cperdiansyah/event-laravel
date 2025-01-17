<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Laporan;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        // return redirect()->route('home');

        $transaksi = DB::table('transaksis')->join('events', 'transaksis.id_event', '=', 'events.id')
            ->where('id_peserta', Auth::user()->id)
            ->select('transaksis.*', 'events.nama_event', 'events.famplet_acara_path', 'events.harga_tiket', 'events.waktu_acara')
            ->groupBy('transaksis.id_event')
            // ->orderBy('events.waktu_acara', 'desc')
            ->orderBy('transaksis.waktu_pembayaran', 'desc')

            ->get();

        return view('pages.customer.chekout.index', [
            'transaksi' => $transaksi
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
        // return dd($request->all());



        /* Cek apakah sudah login */
        if (!auth()->user()) {
            return redirect()->route('login');
        }
        /* Cek apakah data diri sudah diisi */
        $peserta_info = DB::table('pesertas')
            ->where('id_users', Auth::user()->id)
            ->first();

        if ($peserta_info == null) {
            return redirect()->route('profile_show', Auth::user()->uuid)->with('error', 'Mohon lengkapi data diri terlebih dahulu');
        }

        /* Cek apakah sudah pernah membeli tiket sebelumnya */
        $transaksiCheck = DB::table('transaksis')->where('id_event', $request->event_id)->where('id_peserta', Auth::user()->id)->first();

        if ($transaksiCheck) {
            return redirect()->route('checkout_index')->with('error', 'Anda sudah membeli tiket ini');
        }


        $no_transaksi = 'INV-' . date('YmdHi') . $request->event_id . $request->user_id;
        $uuid = Str::uuid()->getHex();

        $chekoutData = [
            'uuid' => $uuid,
            'id_event' => $request->event_id,
            'id_peserta' => $request->user_id,
            'total_harga' => $request->harga_tiket,
            'no_transaksi' => $no_transaksi,
            'status_transaksi' => (int)$request->harga_tiket == 0 ? 'verified' : 'not_paid',
            'tanggal_transaksi'=> now()
        ];

        $validator =  Validator::make($chekoutData, [
            'uuid' => 'required|unique:events,uuid',
            'id_event' => 'required|exists:events,id',
            'id_peserta' => 'required|exists:users,id',
            'total_harga' => 'required|numeric',
            'no_transaksi' => 'required',
            'status_transaksi' => 'required',
            'tanggal_transaksi'=>'required'
        ])->validate();

        /* Transaksi jika harga event  == 0 status pembayaran otomatis dibayar dan kuota event berkurang  */
        if ($request->harga_tiket == 0) {
            DB::beginTransaction();
            $event = DB::table('events')->where('id', $request->event_id)->first();
            try {
                if ($event->kuota_tiket == 0) {
                    throw new \Exception('Kuota tiket sudah habis');
                }

                $newkuota = $event->kuota_tiket - 1;
                DB::table('events')->where('id', $request->event_id)->update(['kuota_tiket' => $newkuota]);

                $transaksi = Transaksi::create($validator);

                /* Add to report */
                $report = [
                    'uuid' => Str::uuid()->getHex(),
                    'id_event' => $request->event_id,
                    'id_peserta' => $request->user_id,
                    'id_transaksi' =>   $transaksi->id,
                ];

                Laporan::create($report);

                DB::commit();

                $url = MailController::make_google_calendar_link($event->nama_event, Carbon::parse($event->waktu_acara)->timestamp, Carbon::parse($event->waktu_acara)->addHours(2)->timestamp, $event->lokasi_acara, $event->deskripsi_acara);

                MailController::transactionFreeSuccess(Auth::user()->email, $event->wa_grup, $url);

                return redirect()->route('checkout_show', $uuid)->with('success', 'Pemesanan Tiket Berhasil');
            } catch (\Exception $e) {
                DB::rollBack();

                return redirect()->route('event_show', $event->uuid)->with('error', $e ? $e->getMessage() : 'Pemesanan Gagal, silahkan coba lagi');
            }
        }

        /* Transaksi jika harga event  != 0, status pembayaran berubah jadi paid namun harus diverfiikasi terlebih dahulu pada sisi admin */
        $transaksi = Transaksi::create($validator);


        /* Add to report */
        $report = [
            'uuid' => Str::uuid()->getHex(),
            'id_event' => $request->event_id,
            'id_peserta' => $request->user_id,
            'id_transaksi' =>   $transaksi->id,
        ];

        Laporan::create($report);

        MailController::transactionCreated(Auth::user()->email, route('checkout_show', $uuid));

        return redirect()->route('checkout_show', $uuid)->with('info', 'Pemesanan Tiket Berhasil, Silahkan Lakukan Pembayaran');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function show(Transaksi $transaksi)
    {

        if (!auth()->user()) {
            return redirect()->route('login');
        }

        if (auth()->user()->id != $transaksi->id_peserta) {
            return redirect()->route('home')->with('error', 'Anda tidak memiliki akses');
        }

        $humaslist = DB::table('humas')
            ->join('events', 'id_event', '=', 'events.id')
            ->where('events.id', $transaksi->id_event)
            ->select('humas.*')->get();

        return view('pages.customer.chekout.show', [
            'transaksi' => $transaksi,
            'event' =>  Event::find($transaksi->id_event),
            'humasList' => $humaslist,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function edit(Transaksi $transaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Transaksi $transaksi)
    {
        //
        // return dd($request, $transaksi);

        /* Kalau kedapatan belum login, langsung ngarahin ke login */
        if (!Auth::user()) {
            return redirect()->route('login');
        }


        /* manajemen file image untuk upload*/
        $image =  $request->old_bukti_transaksi ?? null;

        if ($request->file('bukti_transaksi')) {
            $image =  $request->file('bukti_transaksi') ? $request->file('bukti_transaksi')->store('images/bukti_transaksi') : null;
            $this->validate($request, [
                'bukti_transaksi' => 'image|mimes:jpg,jpeg,jfif,png',
            ]);

            if ($request->old_bukti_transaksi) {
                Storage::delete($request->old_bukti_transaksi);
            }
        }


        /* Transaction untuk events */
        DB::beginTransaction();
        $event = DB::table('events')->where('id', $transaksi->id_event)->first();

        try {
            if ($event->kuota_tiket == 0) {
                throw new \Exception('Kuota tiket sudah habis');
            }

            $newkuota = $event->kuota_tiket - 1;
            DB::table('events')->where('id', $transaksi->id_event)->update(['kuota_tiket' => $newkuota]);

            // Transaksi::create($validator);

            $transaksi->update([
                'bukti_transaksi' => $image,
                'status_transaksi' => 'paid',
                'waktu_pembayaran' => now(),
            ]);

            DB::commit();

            MailController::transactionProcess(Auth::user()->email);

            return redirect()->route('checkout_show', $transaksi->uuid)->with('success', 'Bukti Pembayaran Berhasil Diupload');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('checkout_show', $transaksi->uuid)->with('error', $e ? $e->getMessage() : 'Pembayaran Gagal, silahkan coba lagi atau hubungi panitia');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transaksi $transaksi)
    {

        $transaksi->delete();
        return redirect()->route('home');
    }
}
