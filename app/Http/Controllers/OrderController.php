<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class OrderController extends Controller
{
    public function createOrder(Request $request){
        try{
            $user = Auth::user();

            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => "User not authenticated"
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'id_paket' => 'required|numeric',
                'id_voucher' => 'numeric',
            ]);

            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $id = $user->id;
            $id_paket = $request->id_paket;
            $id_voucher = $request->id_voucher;
            
            $insert_order = DB::insert("INSERT INTO ms_order (id_user, created_at) VALUES (?,?)" ,[$id, Carbon::now()]);
            if($insert_order){
                $id_order = DB::getPdo()->lastInsertId();
            }

            $selected_paket = DB::selectOne("SELECT harga FROM ms_paket WHERE id = ?", [$id_paket]);
            $harga_paket = $selected_paket->harga; 

            if($id_voucher || $id_voucher == null){
                $selected_voucher = DB::selectOne("SELECT * FROM ms_voucher WHERE id = ?", [$id_voucher]);
                if ($selected_voucher) {
                    $metode_potongan = $selected_voucher->id_metode_potongan;
                    switch ($metode_potongan) {
                        case 1:
                            $total_potongan = $harga_paket * ($selected_voucher->nilai_voucher / 100); 
                            break;
                        case 2:
                            $total_potongan = $harga_paket - $selected_voucher->nilai_voucher;
                            break;
                            
                            default:
                            $total_potongan = 0;
                            break;
                    }
                } else {
                    $total_potongan = 0;
                }
            }
            $jumlah_biaya = $harga_paket - $total_potongan;
            $ppn = $jumlah_biaya * 0.11; 
            $jumlah_bayar = round($jumlah_biaya + $ppn);
            

            $insert_detail_order = DB::insert(
                "INSERT INTO ms_detail_order (
                    id_order, 
                    id_paket, 
                    jumlah_bayar, 
                    id_voucher, 
                    id_status_order) 
                    VALUES (?,?,?,?,?)", [
                        $id_order, 
                        $id_paket, 
                        $jumlah_bayar, 
                        $id_voucher, 
                        1
                    ]
                );

            $insert_bayar = DB::insert(
                "INSERT INTO ms_bayar (
                    id_order, 
                    total_bayar, 
                    batas_waktu_bayar, 
                    id_status_bayar
                    )
                    VALUES (?,?,?,?)",[
                        $id_order,
                        $jumlah_bayar,
                        Carbon::now()->addHour(1),
                        1
                    ]
                );
            if($insert_bayar){
                $id_bayar = DB::getPdo()->lastInsertId();
            }

            $insert_transaksi = DB::insert(
                "INSERT INTO ms_transaksi (
                    id_bayar,
                    id_status_transaksi,
                    transaksi_at) 
                    VALUES (?,?,?)",[
                    $id_bayar,
                    5,
                    Carbon::now()
                ]
            );
            if($insert_transaksi){
                $id_transaksi = DB::getPdo()->lastInsertId();
                $data_order = DB::selectOne(
                    "SELECT 
                    users.username as nama_pemesan,
                    ms_paket.nama_paket,
                    ms_order.created_at as order_at,
                    ms_status_order.status_order,
                    ms_bayar.total_bayar,
                    ms_bayar.batas_waktu_bayar,
                    ms_status_bayar.status_bayar as status_bayar,
                    ms_transaksi.id as id_transaksi,
                    ms_transaksi.id_bayar,
                    ms_transaksi.transaksi_at,
                    ms_status_transaksi.status_transaksi as status_transaksi
                    FROM ms_transaksi
                    JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id
                    JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id
                    JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id
                    JOIN ms_order ON ms_bayar.id_order = ms_order.id
                    JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id
                    JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id
                    JOIN ms_paket ON ms_detail_order.id_paket = ms_paket.id
                    JOIN users ON ms_order.id_user = users.id
                    WHERE ms_transaksi.id = ? ",[$id_transaksi]
                    );
                    return response()->json([
                        'success' => true,
                        'message' => 'Order created successfully, please make payment immediately',
                        'detail_order' => $data_order,
                    ],201);
                }
        } catch (\Exception $e){
            Log::error('Database error: '. $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'A database connection error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrder(){
        try{
            $data_order = DB::select(
                "SELECT 
                ms_transaksi.id,
                users.username as nama_pemesan,
                ms_paket.nama_paket,
                ms_order.created_at as order_at,
                ms_status_order.status_order,
                ms_bayar.total_bayar,
                ms_bayar.batas_waktu_bayar,
                ms_status_bayar.status_bayar as status_bayar,
                ms_transaksi.transaksi_at,
                ms_status_transaksi.status_transaksi as status_transaksi
                FROM ms_transaksi
                JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id
                JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id
                JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id
                JOIN ms_order ON ms_bayar.id_order = ms_order.id
                JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id
                JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id
                JOIN ms_paket ON ms_detail_order.id_paket = ms_paket.id
                JOIN users ON ms_order.id_user = users.id
                ORDER BY ms_transaksi.transaksi_at");
            
            if(!$data_order){
                return response()->json([
                    'success' => false,
                    'messgae' => 'Transaksi not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'order' => $data_order,
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
    public function getOneOrder($id){
        try{
            $data_order = DB::selectOne(
                "SELECT 
                ms_transaksi.id as id_transaksi,
                users.username as nama_pemesan,
                ms_paket.nama_paket,
                ms_order.created_at as order_at,
                ms_status_order.status_order,
                ms_bayar.total_bayar,
                ms_bayar.batas_waktu_bayar,
                ms_status_bayar.status_bayar as status_bayar,
                ms_transaksi.transaksi_at,
                ms_status_transaksi.status_transaksi as status_transaksi
                FROM ms_transaksi
                JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id
                JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id
                JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id
                JOIN ms_order ON ms_bayar.id_order = ms_order.id
                JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id
                JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id
                JOIN ms_paket ON ms_detail_order.id_paket = ms_paket.id
                JOIN users ON ms_order.id_user = users.id
                WHERE ms_transaksi.id = ?",[$id]
            );

            if(!$data_order){
                return response()->json([
                    'success' => false,
                    'messgae' => 'Transaksi not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'detail_order' => $data_order,
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

    public function sendTransfer(Request $request, $id){
        try{
            $validator = Validator::make($request->all(), [
                'nominal_transfer' => 'required|numeric',
            ]);

            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            
            $data_order = DB::selectOne(
                "SELECT 
               ms_order.id,
               users.username as nama_pemesan,
               ms_order.created_at as order_at,
               ms_detail_order.id as id_detail_order,
               ms_bayar.id as id_bayar,
               ms_bayar.total_bayar,
               ms_bayar.batas_waktu_bayar,
               ms_transaksi.id as id_transaksi
               FROM ms_transaksi
               JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id
               JOIN ms_order ON ms_bayar.id_order = ms_order.id
               JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id
               JOIN users ON ms_order.id_user = users.id
               WHERE ms_transaksi.id = ? ",[$id]
            );

            if(!$data_order){
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi not found',
                ],404);
            }

            // SELECT ms_order.id, users.username as nama_pemesan, ms_order.created_at as order_at, ms_status_order.status_order, ms_bayar.total_bayar, ms_status_bayar.status_bayar as status_bayar, ms_status_transaksi.status_transaksi as status_transaksi FROM ms_transaksi JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id JOIN ms_order ON ms_bayar.id_order = ms_order.id JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id JOIN users ON ms_order.id_user = users.id WHERE ms_transaksi.id

            $nominal_transfer = $request->nominal_transfer;
            $id_transaksi = $data_order->id_transaksi;
            $id_bayar = $data_order->id_bayar;
            $id_detail_order = $data_order->id_detail_order;
            $total_bayar = $data_order->total_bayar;
            $batas_waktu_bayar = $data_order->batas_waktu_bayar;
            $date_now = Carbon::now();

            $insert_transfer = DB::insert(
                "INSERT INTO ms_transfer_masuk (
                    id_transaksi, 
                    jumlah_transfer, 
                    transfer_at) VALUES (
                        ?,
                        ?,
                        ?)", [
                        $id_transaksi,
                        $nominal_transfer,
                        Carbon::now(),
                    ]
                        );      
            if ($insert_transfer) {
                $id_transfer = DB::getPdo()->lastInsertId();
                if($nominal_transfer < $total_bayar && $date_now < $batas_waktu_bayar){
                    DB::update(
                        "UPDATE ms_transaksi SET 
                        id_status_transaksi = ? 
                        WHERE id = ?", [1, $id_transaksi]
                        );

                    $data_transfer = DB::selectOne("SELECT * FROM ms_transfer_masuk WHERE id = ? ", [$id_transfer]);

                    $detail_order = DB::selectOne(
                        "SELECT 
                        ms_order.id, 
                        users.username as nama_pemesan, 
                        ms_order.created_at as order_at, 
                        ms_status_order.status_order, 
                        ms_bayar.total_bayar, 
                        ms_status_bayar.status_bayar as status_bayar, 
                        ms_status_transaksi.status_transaksi as status_transaksi 
                        FROM ms_transaksi 
                        JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id 
                        JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id 
                        JOIN ms_order ON ms_bayar.id_order = ms_order.id 
                        JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id 
                        JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id 
                        JOIN users ON ms_order.id_user = users.id 
                        WHERE ms_transaksi.id = ? ", [$data_transfer->id_transaksi]);
                    
                    $nama_pemesan = $detail_order->nama_pemesan;
                    $order_at = $detail_order->order_at;
                    $status_order = $detail_order->status_order;
                    $status_bayar = $detail_order->status_bayar;
                    $status_transaksi = $detail_order->status_transaksi;
                    return response()->json([
                        'success' => true,
                        'message' => 'Transfer success, but nominal transfer less than the total price',
                        'detail_order' => [
                            'nama_pemesan' => $nama_pemesan,
                            'order_at' => $order_at,
                            'status_order' => $status_order,
                            'total_bayar' => $total_bayar,
                            'status_bayar' => $status_bayar,
                            'transfer' => $data_transfer,
                            'status_transaksi' => $status_transaksi
                        ]
                    ],201);
                } elseif ($nominal_transfer > $total_bayar && $date_now < $batas_waktu_bayar) {
                    DB::update(
                        "UPDATE ms_transaksi SET
                        id_status_transaksi = ? 
                        WHERE id = ?",[2, $id_transaksi]
                    );
                    DB::update(
                        "UPDATE ms_bayar SET 
                        id_status_bayar = ?
                        WHERE id = ?",[2, $id_bayar]
                    );
                    DB::update(
                        "UPDATE ms_detail_order SET
                        id_status_order = ?
                        WHERE id = ?",[2, $id_detail_order]
                    );
                    $data_transfer = DB::selectOne("SELECT * FROM ms_transfer_masuk WHERE id = ? ",[$id_transfer]);
                     $detail_order = DB::selectOne(
                        "SELECT 
                        ms_order.id, 
                        users.username as nama_pemesan, 
                        ms_order.created_at as order_at, 
                        ms_status_order.status_order, 
                        ms_bayar.total_bayar, 
                        ms_status_bayar.status_bayar as status_bayar, 
                        ms_status_transaksi.status_transaksi as status_transaksi 
                        FROM ms_transaksi 
                        JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id 
                        JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id 
                        JOIN ms_order ON ms_bayar.id_order = ms_order.id 
                        JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id 
                        JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id 
                        JOIN users ON ms_order.id_user = users.id 
                        WHERE ms_transaksi.id = ? ", [$data_transfer->id_transaksi]);

                    $nama_pemesan = $detail_order->nama_pemesan;
                    $order_at = $detail_order->order_at;
                    $status_order = $detail_order->status_order;
                    $status_bayar = $detail_order->status_bayar;
                    $status_transaksi = $detail_order->status_transaksi;
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Transfer success, but nominal transfer more than the total price',
                        'detail_order' => [
                            'nama_pemesan' => $nama_pemesan,
                            'order_at' => $order_at,
                            'status_order' => $status_order,
                            'total_bayar' => $total_bayar,
                            'status_bayar' => $status_bayar,
                            'transfer' => $data_transfer,
                            'status_transaksi' => $status_transaksi
                        ]
                    ],201);
                } elseif ($nominal_transfer == $total_bayar && $date_now < $batas_waktu_bayar) {
                    DB::update(
                        "UPDATE ms_transaksi SET
                        id_status_transaksi = ? 
                        WHERE id = ?",[3, $id_transaksi]
                    );
                    DB::update(
                        "UPDATE ms_bayar SET 
                        id_status_bayar = ?
                        WHERE id = ?",[2, $id_bayar]
                    );
                    DB::update(
                        "UPDATE ms_detail_order SET
                        id_status_order = ?
                        WHERE id = ?",[2, $id_detail_order]
                    );
                    $data_transfer = DB::selectOne("SELECT * FROM ms_transfer_masuk WHERE id = ? ", [$id_transfer]);

                    $detail_order = DB::selectOne(
                        "SELECT 
                        ms_order.id, 
                        users.username as nama_pemesan, 
                        ms_order.created_at as order_at, 
                        ms_status_order.status_order, 
                        ms_bayar.total_bayar, 
                        ms_status_bayar.status_bayar as status_bayar, 
                        ms_status_transaksi.status_transaksi as status_transaksi 
                        FROM ms_transaksi 
                        JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id 
                        JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id 
                        JOIN ms_order ON ms_bayar.id_order = ms_order.id 
                        JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id 
                        JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id 
                        JOIN users ON ms_order.id_user = users.id 
                        WHERE ms_transaksi.id = ? ", [$data_transfer->id_transaksi]);

                    $nama_pemesan = $detail_order->nama_pemesan;
                    $order_at = $detail_order->order_at;
                    $status_order = $detail_order->status_order;
                    $status_bayar = $detail_order->status_bayar;
                    $status_transaksi = $detail_order->status_transaksi;

                    return response()->json([
                        'success' => true,
                        'message' => 'Transfer success, you can scan your QR code',
                        'detail_order' => [
                            'nama_pemesan' => $nama_pemesan,
                            'order_at' => $order_at,
                            'status_order' => $status_order,
                            'total_bayar' => $total_bayar,
                            'status_bayar' => $status_bayar,
                            'transfer' => $data_transfer,
                            'status_transaksi' => $status_transaksi
                        ]
                    ],201);
                } else  {
                    DB::update(
                        "UPDATE ms_transaksi SET
                        id_status_transaksi = ? 
                        WHERE id = ?",[4, $id_transaksi]
                    );
                    $data_transfer = DB::selectOne("SELECT * FROM ms_transfer_masuk WHERE id = ? ", [$id_transfer]);

                    $detail_order = DB::selectOne(
                        "SELECT 
                        ms_order.id, 
                        users.username as nama_pemesan, 
                        ms_order.created_at as order_at, 
                        ms_status_order.status_order, 
                        ms_bayar.total_bayar, 
                        ms_status_bayar.status_bayar as status_bayar, 
                        ms_status_transaksi.status_transaksi as status_transaksi 
                        FROM ms_transaksi 
                        JOIN ms_status_transaksi ON ms_transaksi.id_status_transaksi = ms_status_transaksi.id JOIN ms_bayar ON ms_transaksi.id_bayar = ms_bayar.id 
                        JOIN ms_status_bayar ON ms_bayar.id_status_bayar = ms_status_bayar.id 
                        JOIN ms_order ON ms_bayar.id_order = ms_order.id 
                        JOIN ms_detail_order ON ms_detail_order.id_order = ms_order.id 
                        JOIN ms_status_order ON ms_detail_order.id_status_order = ms_status_order.id 
                        JOIN users ON ms_order.id_user = users.id 
                        WHERE ms_transaksi.id = ? ", [$data_transfer->id_transaksi]);

                    $nama_pemesan = $detail_order->nama_pemesan;
                    $order_at = $detail_order->order_at;
                    $status_order = $detail_order->status_order;
                    $status_bayar = $detail_order->status_bayar;
                    $status_transaksi = $detail_order->status_transaksi;
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Transfer failed, your payment deadline has expired',
                        'detail_order' => [
                            'nama_pemesan' => $nama_pemesan,
                            'order_at' => $order_at,
                            'status_order' => $status_order,
                            'total_bayar' => $total_bayar,
                            'status_bayar' => $status_bayar,
                            'transfer' => $data_transfer,
                            'status_transaksi' => $status_transaksi
                        ]
                    ],201);
                }
            }
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
