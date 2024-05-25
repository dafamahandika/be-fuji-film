<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LayananController extends Controller
{
    public function indexLayanan(){
        try{
            $dataLayanan = DB::select("SELECT * FROM ms_layanan ORDER BY nama_layanan");
            if(!$dataLayanan){
                return response()->json([
                    'success' => false,
                    'message' => "Layanan not found",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $dataLayanan 
            ],200);

        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi database',
                'error' => $e->getMessage(),
            ], 500);
        }     
    }

    public function createLayanan(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'nama_layanan' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $existsLayanan = DB::selectOne("SELECT * FROM ms_layanan WHERE nama_layanan = ? ", [$request->nama_layanan]);
            if ($existsLayanan) {
                return response()->json([
                    'success' => false,
                    'message' => 'nama_layanan already exists',
                    'error' => [
                        'nama_layanan' => ['nama_layanan already exists']
                    ]
                ]);
            }

            DB::insert("INSERT INTO ms_layanan (nama_layanan) VALUES (?)", [$request->nama_layanan]);

            return response()->json([
                'success' => true,
                'message' => 'Layanan created successfully',
                'nama_layanan' => $request->nama_layanan,
            ]);
        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi database',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateLayanan(Request $request){
        
    }

}
