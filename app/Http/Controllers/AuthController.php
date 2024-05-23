<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'username' => 'required|string|min:3',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $registrasi = [
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
            ];

            $emailExists = DB::selectOne("SELECT 1 FROM users WHERE email = ?", [$request->email]);
            $usernameExists = DB::selectOne("SELECT 1 FROM users WHERE username = ?", [$request->username]);

            if ($emailExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already exists',
                    'errors' => ['email' => ['Email already exists']],
                ]);
            }

            if ($usernameExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username already exists',
                    'errors' => ['username' => ['Username already exists']],
                ]);
            }

            $result = DB::insert("INSERT INTO users (email, username, password, create_date) VALUES (?, ?, ?, ?)", [
                $registrasi['email'],
                $registrasi['username'],
                $registrasi['password'],
                Carbon::now(),
            ]);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil membuat akun',
                    'data' => $registrasi,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat membuat akun',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Database error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi database',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);
        

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422); // 422 Unprocessable Entity
        }

        $emailUser = DB::selectOne(
            "SELECT * FROM users 
            WHERE email = ?", 
            [$request->email]
        );
        if(!$emailUser){
            return response()->json([
                  'success' => false,
                  'message' => 'Email tidak terdaftar',
                  'errors' => ['email' => ['Email tidak terdaftar']],
            ], 404); // 404 Email not found
        }

        $otp_code = rand(1000, 9999);
        

        $verifikasi = [
            'user_id' => $emailUser->id,
            'otp' => $otp_code,
            'otp_expired' => Carbon::now()->addMinutes(5),
            'is_email_verified' => 0,
            'is_phone_verified' => 0, 
        ];

        $insertVerifikasi = DB::insert("INSERT INTO ms_verifikasi 
            (
            id_user, 
            otp, 
            otp_expired, 
            is_email_verified, 
            is_phone_verified
            ) 
            VALUES (?, ?, ?, ?, ?)", [
            $verifikasi['user_id'],
            $verifikasi['otp'],
            $verifikasi['otp_expired'],
            $verifikasi['is_email_verified'],
            $verifikasi['is_phone_verified'],
        ]);

        if ($insertVerifikasi){
            Mail::to($request->email)->send(new OtpMail($otp_code));
            return response()->json([
                    'success' => true,
                    'message' => 'Sukses mengirim kode OTP, silahkan cek email anda',
                    'data' => $verifikasi,
                ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim kode OTP, silahkan coba lagi',
            ], 400);
        }


    //     if ($token = Auth::attempt($credentials)) {
    //         $user = Auth::user();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Login berhasil',
    //             'data' => $user,
    //             'token' => $token,
    //         ]);
    //     } else {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Username atau password salah',
    //         ]);
    //     }
    // }
    }

    public function verifyOtp(Request $request){
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422); // 422 Unprocessable Entity
        }

        $otp = $request->otp;

        $verifyOtp = DB::select("SELECT
            users.email, 
            ms_verifikasi.otp, 
            ms_verifikasi.otp_expired 
            FROM ms_verifikasi
            JOIN users ON ms_verifikasi.id_user = users.id
            WHERE otp = ?", [$otp]);
        
        if(!$verifyOtp){
            return response()->json([
                'success' => false,
                'message' => "OTP tidak sesuai, silahkan coba lagi", 
            ], 400);
        } else {
            return response()->json([
                'success' => true,
                'message' => "OTP sesuai, login berhasil",
                'data' => $verifyOtp,
            ]);
        }

        $user = DB::select("SELECT * FROM users WHERE email = ?", [$verifyOtp[0]->email]);

        if($user && $verifyOtp[0]->otp_expired > Carbon::now() ){
            $token = JWTAuth::fromUser($user);
            $userToken = DB::insert(
                "INSERT INTO ms_users_token (
                    users_id, 
                    token, 
                    request_time, 
                    expired_time
                ) 
                VALUES (?, ?, ? ,?)" ,[
                    $user[0]->id,
                    $token,
                    Carbon::now(),
                    
                ]);
            return response()->json([
                'success' => true,
                'message' => "Login berhasil", 
            ], 400);
        } else {
            
        }
    }

}
