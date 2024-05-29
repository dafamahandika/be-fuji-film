<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    public function creatVoucher(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'id_metode_potongan' => 'required|numeric',
                'nama_voucher' => 'required|string',
                'id_layanan' => 'required|numeric',
                'kode_voucher' => 'required|string',
            ]);
    
            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'validation errors',
                    'erorrs' => $validator->errors(),
                ]);
            }

            $id_metode_potongan = $request->id_metode_potongan;
            
            if($id_metode_potongan == 1){
                $validator_nilai = Validator::make($request->all(), [
                'nilai_voucher' => 'required|numeric|min:1|max:80',
            ]);
                if($validator_nilai->fails()){
                    return response()->json([
                        'success' => false,
                        'message' => 'validation errors',
                        'erorrs' => $validator_nilai->errors(),
                    ]);
                }
            } 

            if($id_metode_potongan == 2){
                $validator_nilai = Validator::make($request->all(), [
                'nilai_voucher' => 'required|numeric|min:2000|max:15000',
            ]);
                if($validator_nilai->fails()){
                    return response()->json([
                        'success' => false,
                        'message' => 'validation errors',
                        'erorrs' => $validator_nilai->errors(),
                    ]);
                }
            }

            $nama_voucher = $request->nama_voucher;
            $id_layanan = $request->id_layanan;
            $kode_voucher = $request->kode_voucher;
            $nilai_voucher = $request->nilai_voucher;

            $existsVoucher = DB::selectOne(
                "SELECT * FROM ms_voucher 
                WHERE nama_voucher = ? AND kode_voucher = ?",
                [$nama_voucher, $kode_voucher]
            );

            if($existsVoucher){
                return response()->json([
                    'success' => false,
                    'message' => "Voucher already exists",
                ], 409);
            }

            DB::insert(
                "INSERT INTO ms_voucher (
                    id_metode_potongan, 
                    nama_voucher, 
                    id_layanan, 
                    kode_voucher, 
                    nilai_voucher, 
                    created_by, 
                    start_time, 
                    expired_time) 
                    VALUE (?,?,?,?,?,?,?,?)" ,[
                        $id_metode_potongan,
                        $nama_voucher,
                        $id_layanan,
                        $kode_voucher,
                        $nilai_voucher,
                        1,
                        Carbon::now(),
                        Carbon::now()->addHour(12),
                    ]
            );

            return response()->json([
                'success' => true,
                'message' => "Voucher created successfully",
                'data' => [
                    'id_metode_layanan' => $id_metode_potongan,
                    'nama_voucher' => $nama_voucher,
                    'id_layanan' => $id_layanan,
                    'kode_voucher' => $kode_voucher,
                    'nilai_voucher' => $nilai_voucher,
                ]
                ]);

            // return response()->json([
            //     'data' => [
            //         $nama_voucher,
            //         $id_layanan,
            //         $kode_voucher,
            //         $nilai_voucher
            //     ]
            // ]);
        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }

    }
}
