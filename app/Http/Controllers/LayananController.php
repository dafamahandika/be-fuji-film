<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LayananController extends Controller
{
    public function getLayanan(){
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

    public function getOneLayanan($id){
        try{
            $dataLayanan = DB::selectOne("SELECT * FROM ms_layanan WHERE id = ?", [$id]);
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
                    'message' => 'Layanan already exists',
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

    public function editLayanan(Request $request, $id){
        try{
            
            $validator = Validator::make($request->all(), [
                'nama_layanan' => 'required|string',
            ]);
            
            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ]);
            }
            
            $nama_layanan = $request->nama_layanan;
            
            $dataLayanan = DB::select("SELECT * FROM ms_layanan WHERE id = ?", [$id]);
            if(!$dataLayanan){
                return response()->json([
                    'succsess' => false,
                    'message' => 'Layanan not found'
                ], 404);
            }

            DB::update("UPDATE ms_layanan SET nama_layanan = ? WHERE id = ?", [
                $nama_layanan, 
                $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Layanan updated successfully',
                'data' => [
                    'store_name' => $nama_layanan,
                ]
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

    public function deleteLayanan($id){
        try{
            $deleteLayanan = DB::delete("DELETE FROM ms_layanan WHERE id = $id");
            if(!$deleteLayanan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layanan not found'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Layanan deleted successfully'
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
