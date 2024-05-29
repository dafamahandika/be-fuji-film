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

            $registrasi = [
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
            ];

            $result = DB::insert("INSERT INTO users (email, username, password, create_date) VALUES (?, ?, ?, ?)", [
                $registrasi['email'],
                $registrasi['username'],
                $registrasi['password'],
                Carbon::now(),
            ]);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while creating the account',
                ]);
            }

            $emailUser = DB::selectOne("SELECT * FROM users WHERE email = ?", [$request->email]);
            if (!$emailUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not registered',
                    'errors' => ['email' => ['Email not registered']],
                ], 404); // 404 Email not found
            }

            $otp_code = rand(1000, 9999);

            $verifikasi = [
                'user_id' => $emailUser->id,
                'otp' => $otp_code,
                'otp_expired' => Carbon::now()->addHour(1),
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

            if ($insertVerifikasi) {
                Mail::to($request->email)->send(new OtpMail($otp_code));
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully sent OTP code, please check your email',
                    'data' => $verifikasi,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP code, please try again',
                ], 400);
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

            $emailUser = DB::table('users')->where('email', $request->email)->first();

            if (!$emailUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not registered',
                    'errors' => ['email' => ['Email not registered']],
                ], 404); // 404 Email not found
            }

            // Convert stdClass to User model
            $user = User::find($emailUser->id);

            $verifikasi = DB::table('ms_verifikasi')->where('id_user', $emailUser->id)->first();

            if ($verifikasi && $verifikasi->is_email_verified == 1) {
                $token = Auth::login($user);

                $requestTime = Carbon::now();
                $tokenExpiryTime = $requestTime->copy()->addHour(2);

                DB::table('ms_users_token')->insert([
                    'id_user' => $emailUser->id,
                    'token' => $token,
                    'request_time' => $requestTime,
                    'expired_time' => $tokenExpiryTime,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email is already verified, token generated',
                    'token' => $token,
                    'request_time' => $requestTime->toDateTimeString(),
                    'expired_time' => $tokenExpiryTime->toDateTimeString(),
                    'data' => [
                        'email' => $emailUser->email,
                        'is_email_verified' => 1,
                    ],
                ], 200);
            } else {
                $otp_code = rand(1000, 9999);

                $verifikasiData = [
                    'id_user' => $emailUser->id,
                    'otp' => $otp_code,
                    'otp_expired' => Carbon::now()->addMinutes(5),
                    'is_email_verified' => 0,
                    'is_phone_verified' => 0,
                ];

                if ($verifikasi) {
                    // Update existing OTP record
                    DB::table('ms_verifikasi')
                        ->where('id_user', $emailUser->id)
                        ->update($verifikasiData);
                } else {
                    // Insert new OTP record
                    DB::table('ms_verifikasi')->insert($verifikasiData);
                }

                Mail::to($request->email)->send(new OtpMail($otp_code));

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully sent OTP code, please check your email',
                    'data' => $verifikasiData,
                ], 200);
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

    // verifyOTP
    public function verifyOtp(Request $request)
    {
        try {
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
                    'message' => "OTP does not match, please try again",
                ], 400);
            }

            if ($verifyOtp->otp_expired < Carbon::now()) {
                return response()->json([
                    'success' => false,
                    'message' => "OTP has expired, please try again",
                ], 400);
            }

            DB::table('ms_verifikasi')
                ->where('id_user', $verifyOtp->id_user)
                ->update(['is_email_verified' => 1]);

            $user = User::find($verifyOtp->id_user);

            if ($user) {
                $token = Auth::login($user);

                $requestTime = Carbon::now();
                $tokenExpiryTime = $requestTime->copy()->addHour(2);

                DB::table('ms_users_token')->insert([
                    'id_user' => $verifyOtp->id_user,
                    'token' => $token,
                    'request_time' => $requestTime,
                    'expired_time' => $tokenExpiryTime,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "OTP matched, email verified successfully",
                    'token' => $token,
                    'request_time' => $requestTime->toDateTimeString(),
                    'expired_time' => $tokenExpiryTime->toDateTimeString(),
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
                    'message' => "User not found",
                ], 404);
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

    // profile
    public function profile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Profile retrieval error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving profile data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // logout
    public function logout(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Clear user token
            DB::table('ms_users_token')->where('id_user', $user->id)->delete();

            // Logout the user
            Auth::logout();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}