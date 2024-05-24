<?php

namespace App\Http\Controllers;

use App\User;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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

    // send otp email
    public function sendOtp(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi database',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // verifyOTP
    public function verifyOtp(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'otp' => 'required|numeric|digits:4',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422); // 422 Unprocessable Entity
            }

            $otp = $request->otp;

            $verifyOtp = DB::table('ms_verifikasi')
                ->join('users', 'ms_verifikasi.id_user', '=', 'users.id')
                ->where('ms_verifikasi.otp', $otp)
                ->select('ms_verifikasi.*', 'users.email')
                ->first();

            if (!$verifyOtp) {
                return response()->json([
                    'success' => false,
                    'message' => "OTP tidak sesuai, silahkan coba lagi",
                ], 400);
            }

            if ($verifyOtp->otp_expired < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => "OTP telah expired, silahkan coba lagi",
                ], 400);
            }

            DB::table('ms_verifikasi')
                ->where('id_user', $verifyOtp->id_user)
                ->update(['is_email_verified' => 1]);

            $user = User::find($verifyOtp->id_user);

            if ($user) {
                $token = Auth::login($user);

                return response()->json([
                    'success' => true,
                    'message' => "OTP sesuai, email berhasil diverifikasi",
                    'token' => $token,
                    'data' => [
                        'email' => $verifyOtp->email,
                        'otp' => $verifyOtp->otp,
                        'otp_expired' => $verifyOtp->otp_expired,
                        'is_email_verified' => 1,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "User tidak ditemukan",
                ], 404);
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

