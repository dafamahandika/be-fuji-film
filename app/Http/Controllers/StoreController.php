<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
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
                kabkot.id_kabkot, 
                jenis_kabkot.sebutan_kabkot as jenis_kabkot, 
                kabkot.nama_kabkot, 
                kabkot.id_prov 
                FROM kabkot 
                JOIN jenis_kabkot ON kabkot.jenis_kabkot = jenis_kabkot.id 
                WHERE id_prov = ?", [$id]);
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
            $kecamatan = DB::select("SELECT * FROM kecamatan WHERE id_kabkot = ?", [$id]);
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
            $deskel = DB::select("SELECT * FROM deskel WHERE id_kec = ?", [$id]);
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

    public function createStore(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                "store_name" => "required|string",
                "address" => "required|string",
                "latlong_address" => "required|string",
                "id_layanan" => "required",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422); // 422 Unprocessable Entity
            }

            $store_name = $request->store_name;
            $address = $request->address;
            $latlong_store = $request->latlong_store;
            $id_layanan = $request->id_layanan;


            $existsStore = DB::selectOne("SELECT * FROM ms_toko WHERE store_name = ? ", [$store_name]);

            if($existsStore){
                return response()->json([
                    'success' => false,
                    'message' => "Store already exists",
                    'error' => ['store_name' => ["Store already exists"]]
                ]);
            }

            $insertStore = DB::insert("INSERT INTO ms_toko (store_name, address, latlong_store) VALUES(?, ?, ?)", [$store_name, $address, $latlong_store]);

            if($insertStore){
                $idStore = DB::getPdo()->lastInsertId();
                for ($i=0; $i < count($id_layanan); $i++) { 
                    DB::insert("INSERT INTO ms_store_layanan (id_store, id_layanan) VALUE(?,?)", [$idStore, $id_layanan[$i]]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Store created successfully",
                'store' => [
                    'id' => $idStore,
                    'store_name' => $store_name,
                    'address' => $address,
                    'latlong_address' => $latlong_store,
                    'id_layanan' => $id_layanan
                ]
            ]);
            
        }catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStore(){
        try{
            $dataStore = DB::select("SELECT * FROM ms_toko");

            if(!$dataStore){
                return response()->json([
                    'success' => false,
                    'message' => "Store not found",
                ], 404);
            }
            
            return response()->json([
                    'success' => true,
                    'store' => $dataStore
            ], 200);

        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOneStore($id){
        try{
            $dataStore = DB::select("SELECT ms_toko.id, ms_toko.store_name, ms_toko.address, ms_toko.latlong_store, ms_layanan.nama_layanan
            FROM ms_store_layanan
            JOIN ms_toko ON ms_store_layanan.id_store = ms_toko.id
            JOIN ms_layanan ON ms_store_layanan.id_layanan = ms_layanan.id
            WHERE ms_toko.id = ?", [$id]);

            if(!$dataStore){
                return response()->json([
                    'success' => false,
                    'message' => "Store not found",
                ], 404);
            }
            $layanan = [];

            for ($i=0; $i < count($dataStore) ; $i++) { 
                array_push($layanan, $dataStore[$i]->nama_layanan);
            };
            
            return response()->json([
                    'success' => true,
                    // 'layanan' => $layanan
                    'store' => [
                        "store_name" => $dataStore[0]->store_name,
                        "address" => $dataStore[0]->address,
                        "latlong_store" => $dataStore[0]->latlong_store,
                        "layanan" => $layanan
                    ],
            ], 200);

        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteStore($id) {
        try{
            $deleteStoreLayanan = DB::statement("DELETE FROM ms_store_layanan WHERE id_store = ?", [$id]);
            $deleteStore = DB::statement("DELETE FROM ms_toko WHERE id =?", [$id]);
            
            if(!$deleteStore && !$deleteStoreLayanan){
                return response()->json([
                    'success' => false,
                    'message' => "Store not found"
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Store deleted successfully" 
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

    public function editStore(Request $request, $id){
        try{
            
            $validator = Validator::make($request->all(), [
                'store_name' => 'required|string',
                'address' => 'required',
                'latlong_store' => 'required',
            ]);
            
            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ]);
            }
            
            $store_name = $request->store_name;
            $address = $request->address;
            $latlong_store = $request->latlong_store;
            
            $dataStore = DB::select("SELECT * FROM ms_toko WHERE id = ?", [$id]);
            if(!$dataStore){
                return response()->json([
                    'succsess' => false,
                    'message' => 'Store not found'
                ], 404);
            }

            DB::update("UPDATE ms_toko SET store_name = ?, address = ?, latlong_store = ? WHERE id = ?", [
                $store_name, 
                $address, 
                $latlong_store, 
                $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store updated successfully',
                'data' => [
                    'store_name' => $store_name,
                    'address' => $address,
                    'latlong_store' => $latlong_store,
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
}
