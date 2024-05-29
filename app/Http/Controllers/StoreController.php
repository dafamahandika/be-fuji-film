<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    public function createStore(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                "store_name" => "required|string",
                "address" => "required|string",
                "latitude" => "required|string",
                "longitude" => "required|string",
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
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $id_layanan = $request->id_layanan;
            // var_dump($id_layanan);
            $data_layanan = explode(",",$id_layanan);


            $existsStore = DB::selectOne("SELECT * FROM ms_toko WHERE store_name = ? ", [$store_name]);

            if($existsStore){
                return response()->json([
                    'success' => false,
                    'message' => "Store already exists",
                    'error' => ['store_name' => ["Store already exists"]]
                ]);
            }

            $insertStore = DB::insert("INSERT INTO ms_toko (store_name, address, latitude, longitude) VALUES(?, ?, ?, ?)", [$store_name, $address, $latitude, $longitude]);

            if($insertStore){
                $idStore = DB::getPdo()->lastInsertId();
                for ($i=0; $i < count($data_layanan); $i++) { 
                    DB::insert("INSERT INTO ms_store_layanan (id_store, id_layanan) VALUE(?,?)", [$idStore, $data_layanan[$i]]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Store created successfully",
                'store' => [
                    'id' => $idStore,
                    'store_name' => $store_name,
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'id_layanan' => $data_layanan
                ]
            ]);
            // return response()->json([
            //     'id_layanan' => $id_layanan
            // ]);
            
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
            $data_store = DB::select("SELECT * FROM ms_toko");
            
            if(!$data_store){
                return response()->json([
                    'success' => false,
                    'message' => "Store not found",
                ], 404);
            }

            for ($i=0; $i < count($data_store) ; $i++) { 
                $data_store[$i]->layanan = DB::select("SELECT ms_layanan.nama_layanan
                FROM ms_store_layanan
                JOIN ms_layanan ON ms_store_layanan.id_layanan = ms_layanan.id
                WHERE ms_store_layanan.id_store = ?", [$data_store[$i]->id]);
            }
            
            return response()->json([
                    'success' => true,
                    'store' => $data_store
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

    // public function getOneStore($id){
    //     try{
    //         $dataStore = DB::select("SELECT ms_toko.id, ms_toko.store_name, ms_toko.address, ms_toko.latitude, ms_toko.longitude, ms_layanan.nama_layanan
    //         FROM ms_store_layanan
    //         JOIN ms_toko ON ms_store_layanan.id_store = ms_toko.id
    //         JOIN ms_layanan ON ms_store_layanan.id_layanan = ms_layanan.id
    //         WHERE ms_toko.id = ?", [$id]);

    //         if(!$dataStore){
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => "Store not found",
    //             ], 404);
    //         }
    //         $layanan = [];

    //         for ($i=0; $i < count($dataStore) ; $i++) { 
    //             array_push($layanan, $dataStore[$i]->nama_layanan);
    //         };
            
    //         return response()->json([
    //                 'success' => true,
    //                 // 'layanan' => $layanan
    //                 'store' => [
    //                     "store_name" => $dataStore[0]->store_name,
    //                     "address" => $dataStore[0]->address,
    //                     "latitude" => $dataStore[0]->latitude,
    //                     "longitude" => $dataStore[0]->longitude,
    //                     "layanan" => $layanan
    //                 ],
    //         ], 200);

    //     } catch (\Exception $e){
    //         Log::error('Database error: '. $e->getMessage());
    //          return response()->json([
    //             'success' => false,
    //             'message' => 'A database connection error occurred',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

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
                "store_name" => "required|string",
                "address" => "required|string",
                "latitude" => "required|string",
                "longitude" => "required|string",
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
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            
            $dataStore = DB::select("SELECT * FROM ms_toko WHERE id = ?", [$id]);
            if(!$dataStore){
                return response()->json([
                    'succsess' => false,
                    'message' => 'Store not found'
                ], 404);
            }

            DB::update("UPDATE ms_toko SET store_name = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?", [
                $store_name, 
                $address, 
                $latitude, 
                $longitude,
                $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store updated successfully',
                'data' => [
                    'store_name' => $store_name,
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
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
