<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WilayahController extends Controller
{
    public function getProvince(){
        try{
            $provinsi = DB::select("SELECT * FROM provinsi");
            if(!$provinsi){
                return response()->json([
                    'success' => false,
                    'error' => 'Province not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'province' => $provinsi,
            ],200);

        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRegency($id){
        try{
            $kabkot = DB::select(
                "SELECT 
                provinsi.nama_prov,
                kabkot.id_kabkot,
                kabkot.jenis_kabkot,
                jenis_kabkot.sebutan_kabkot as jenis_kabkot,
                kabkot.nama_kabkot
                FROM kabkot
                JOIN provinsi ON kabkot.id_prov = provinsi.id_prov
                JOIN jenis_kabkot ON kabkot.jenis_kabkot = jenis_kabkot.id
                WHERE kabkot.id_prov = ?", [$id]);
             if(!$kabkot){
                return response()->json([
                    'success' => false,
                    'error' => 'Regency/City not found'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'regency' => $kabkot,
            ]);
        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSubdistrict($id){
        try{
            $kecamatan = DB::select(
                "SELECT  provinsi.nama_prov, kabkot.nama_kabkot, kecamatan.*
                FROM kecamatan
                JOIN kabkot ON kecamatan.id_kabkot = kabkot.id_kabkot
                JOIN provinsi ON kabkot.id_prov = provinsi.id_prov
                WHERE kecamatan.id_kabkot = ?", [$id]
            );
             if(!$kecamatan){
                return response()->json([
                    'success' => false,
                    'error' => 'Subdistrict not found'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'subdistrict' => $kecamatan,
            ]);
        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getVillage($id){
        try{
            $deskel = DB::select(
                "SELECT provinsi.nama_prov, kabkot.nama_kabkot, kecamatan.nama_kec, deskel.nama_deskel
                FROM deskel
                JOIN kecamatan ON deskel.id_kec = kecamatan.id_kec
                JOIN kabkot ON kecamatan.id_kabkot = kabkot.id_kabkot
                JOIN provinsi ON kabkot.id_prov = provinsi.id_prov
                WHERE deskel.id_kec = ?", [$id]);
            if(!$deskel){
                return response()->json([
                    'success' => false,
                    'error' => 'Village not found'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'village' => $deskel,
            ]);

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
