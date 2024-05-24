<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DirectoryController extends Controller
{
    public function createDirectory(Request $request, $id){
        try{
            $validator = Validator::make($request->all(), [
                'nama_paket' => 'required|string',
            ]);
            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }
            $user= DB::selectOne("SELECT * FROM users WHERE id = ?", [$id]);

            $data = DB::selectOne(
                "SELECT ms_layanan.nama_layanan, ms_paket.id_layanan, ms_paket.nama_paket 
                FROM ms_paket
                JOIN ms_layanan ON ms_layanan.id = ms_paket.id_layanan WHERE nama_paket = ?", 
                [$request->nama_paket]);

            $id_user = $user->id;
            $username = $user->username;
            $nama_layanan = $data->nama_layanan;
            $nama_paket = $data->nama_paket;
            
            if(!$user){
                return response()->json([
                    'success' => true,
                    'message ' => "User not found",
                ], 404); // Not Found
            }
            if(!$data){
                return response()->json([
                    'success' => true,
                    'message ' => "Paket not found",
                ], 404); // Not Found
            }

            $directoryUsersPath = storage_path("app/public/$username");
            if (!file_exists($directoryUsersPath)) {
                mkdir($directoryUsersPath, 0755, true); 
            }

            $directoryLayananPath = storage_path("app/public/$username/$nama_layanan");
            if (!file_exists($directoryLayananPath)) {
                mkdir($directoryLayananPath, 0755, true);
            }

            $directoryPaketPath = storage_path("app/public/$username/$nama_layanan/$nama_paket");
            if (!file_exists($directoryPaketPath)) {
                mkdir($directoryPaketPath, 0755, true);
            }

            if($directoryUsersPath && $directoryLayananPath && $directoryPaketPath){
                $existDirectoryUsers = DB::selectOne("SELECT * FROM ms_direktori_users WHERE id_user = ? ", [$id_user]);
                if ($existDirectoryUsers) {
                    $idDirektoriUser = $existDirectoryUsers->id;
                } else {
                    DB::insert("INSERT INTO ms_direktori_users (id_user, directory_name, url_directory, created_at, last_accessed_at) VALUES (?, ?, ?, ?, ?)", [
                        $id_user, 
                        $username, 
                        $directoryUsersPath, 
                        Carbon::now(), 
                        Carbon::now()]);
                        $idDirektoriUser = DB::getPdo()->lastInsertId();
                }

               
                $existDirectoryLayanan = DB::selectOne("SELECT * FROM ms_direktori_layanan WHERE id_direktori_users = ?", [$idDirektoriUser]);
                if ($existDirectoryLayanan) {
                    $idDirektoriLayanan = $existDirectoryLayanan->id;
                } else {
                    DB::insert("INSERT INTO ms_direktori_layanan (id_direktori_users, directory_name, url_directory, created_at, last_accessed_at) VALUES (?, ?, ?, ?, ?)", [
                        $idDirektoriUser, 
                        $nama_layanan,
                        $directoryLayananPath, 
                        Carbon::now(), 
                        Carbon::now()]);
                        $idDirektoriLayanan = DB::getPdo()->lastInsertId();
                }

                $existDirectoryPaket = DB::selectOne("SELECT * FROM ms_direktori_paket WHERE id_direktori_layanan = ?", [$idDirektoriLayanan]);
                if ($existDirectoryPaket) {
                    $idDirectoryPaket = $existDirectoryPaket->id;
                }else {
                    DB::insert("INSERT INTO ms_direktori_paket (id_direktori_layanan, directory_name, url_directory, created_at, last_accessed_at) VALUES (?, ?, ?, ?, ?)", [
                        $idDirektoriLayanan, 
                        $nama_paket, 
                        $directoryPaketPath, 
                        Carbon::now(), 
                        Carbon::now()]);
                        $idDirectoryPaket = DB::getPdo()->lastInsertId();
                }
                 return response()->json([
                    'user' => $idDirektoriUser,
                    'layanan' => $idDirektoriLayanan,
                    'paket' => $idDirectoryPaket,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Success create directory",
                    'direktory_users' => [
                        'id' => $idDirektoriUser,
                        'path' => $directoryUsersPath,
                    ],
                    'direktory_layanan' => [
                        'id' => $idDirektoriLayanan,
                        'path' => $directoryLayananPath,
                    ],
                    'direktory_paket' => [
                        'id' => $idDirectoryPaket,
                        'path' => $directoryPaketPath,
                    ],
                ], 200); // Not Found
            }
        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi database',
                'error' => $e->getMessage(),
            ], 500);
        }     
    }
}
