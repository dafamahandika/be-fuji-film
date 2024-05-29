<?php

namespace App\Http\Controllers;

use Faker\Provider\Lorem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\CssSelector\XPath\Extension\FunctionExtension;

class PaketController extends Controller
{
    public function getPaket(){
        try {
            $paket  = DB::select(
                "SELECT ms_paket.*, ms_layanan.nama_layanan 
                FROM ms_paket 
                JOIN ms_layanan ON ms_paket.id_layanan = ms_layanan.id 
                ORDER BY id_layanan"
            );

            if(!$paket){
                return response()->json([
                    'success' => false,
                    'message' => "Paket not found"
                ], 404);
            }
    
            return response()->json([
                'success' => true,
                'data' => $paket,
            ]);
        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }     
    }

    public function getOnePaket($id){
        try{
            $paket = DB::selectOne(
                "SELECT ms_paket.*, ms_layanan.nama_layanan 
                FROM ms_paket 
                JOIN ms_layanan ON ms_paket.id_layanan = ms_layanan.id
                WHERE ms_paket.id = ?", [$id]
            );

            if(!$paket){
                return response()->json([
                    'success' => false,
                    'message' => 'Paket not found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $paket,
            ]);

        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createPaket(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'id_layanan' => 'required|numeric',
                'nama_paket' => 'required|string',
                'harga' => 'required|numeric',
                'deskripsi' => 'required|string',
                'photo' =>  'required|image|mimes:jpg,png,jpeg|max:2024',
            ]);

            if($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422); // 422 Unprocessable Entity
            }

            $id_layanan = $request->id_layanan;
            $nama_paket = $request->nama_paket;
            $harga = $request->harga;
            $deskripsi = $request->deskripsi;

            if($request->hasFile('photo')){
                $file = $request->file('photo');
                $file_name = $nama_paket.'-'.time().'.'.$file->getClientOriginalExtension();
                $file_path = $file->move("storage/foto_paket/", $file_name);
                // $file_path = Storage::disk('foto_paket')->put($file, $file_name);
                // $file_url = url("storage/foto_paket/".$file_name);
            }

            $insertPaket = DB::insert("INSERT INTO ms_paket (id_layanan, nama_paket, harga, deskripsi, url_photo) VALUES (?,?,?,?,?);", [$id_layanan, $nama_paket, $harga, $deskripsi, $file_path]);

            if($insertPaket){
                return response()->json([
                    'success' => true,
                    'message' => "Paket created successfully",
                    'data' => [
                        'id_layanan' => $id_layanan,
                        'nama_paket' => $nama_paket,
                        'harga' => $harga,
                        'deskripsi' => $deskripsi,
                        'url_photo' => $file_path,
                    ]
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function deletePaket($id){
        try{
            $paket = DB::selectOne(
                "SELECT *
                FROM ms_paket 
                WHERE id = ?", [$id]
            );

            if(!$paket){
                return response()->json([
                    'success' => false,
                    'message' => 'Paket not found'
                ]);
            }

            $url_photo = $paket->url_photo;

            if(!file_exists($url_photo)){
                return response()->json([
                    'success' => false,
                    'message' => 'File does not exist.',
                ], 404);
            } else {
                unlink($url_photo);
    
                DB::delete("DELETE FROM ms_paket WHERE id = ?", [$id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Paket deleted successfully',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
