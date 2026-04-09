<?php

namespace App\Http\Controllers;

use App\Models\BmkgReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BmkgController extends Controller
{
    public function index()
    {
        $data = BmkgReading::latest('reference_time')->get();
        return view('pages.bmkg.index', compact('data'));
    }
    
    // Hapus satu data
    public function destroy($id)
    {    
        $bmkg = BmkgReading::findOrFail($id);
        $waktu = $bmkg->reference_time;
        
        $bmkg->delete();

        activity()
            ->performedOn($bmkg)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Menghapus historis data cuaca BMKG pada tanggal: ' . $waktu);

        return redirect()->back()->with('success', "Data Cuaca ($waktu) berhasil dihapus");
    }

    // Clear Semua Data BMKG
    public function clearBmkgData()
    {       
        BmkgReading::truncate();

        activity()
            ->event('clear')
            ->causedBy(Auth::user())
            ->log('Membersihkan KESELURUHAN tabel data cuaca BMKG');

        return redirect()->back()->with('success', 'Seluruh Data Cuaca BMKG berhasil direset total');
    }
}
