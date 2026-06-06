<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AssetManagementController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\KycManagementController;
use App\Http\Controllers\Api\Admin\PriceManagementController;
use App\Http\Controllers\Api\Admin\ReferralManagementController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\WithdrawalManagementController;
use App\Http\Controllers\Api\Content\BlogPostController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Referral\ReferralController;
use App\Http\Controllers\Api\Support\TicketController;
use App\Http\Controllers\Api\Trading\AssetController;
use App\Http\Controllers\Api\Trading\MarketFavoriteController;
use App\Http\Controllers\Api\Trading\PriceController;
use App\Http\Controllers\Api\Trading\TradeController;
use App\Http\Controllers\Api\User\KycController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\Wallet\DepositController;
use App\Http\Controllers\Api\Wallet\TransactionController;
use App\Http\Controllers\Api\Wallet\WalletController;
use App\Http\Controllers\Api\Wallet\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => 'QuantX API',
                'version' => 'v1',
                'admin' => url('/admin'),
                'endpoints' => [
                    'POST /api/v1/auth/login',
                    'POST /api/v1/auth/register',
                    'GET /api/v1/prices',
                    'GET /api/v1/assets',
                ],
            ],
            'message' => 'QuantX API is running.',
        ]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('otp/send', [OtpController::class, 'send'])->middleware('throttle:6,1');
        Route::post('otp/verify', [OtpController::class, 'verify'])->middleware('throttle:6,1');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
        Route::get('referral/check', [AuthController::class, 'checkReferralCode']);
    });

    Route::get('prices', [PriceController::class, 'index']);
    Route::get('prices/{symbol}/history', [PriceController::class, 'history']);
    Route::get('blog', [BlogPostController::class, 'index']);
    Route::get('blog/{slug}', [BlogPostController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('auth/fcm-token', [AuthController::class, 'updateFcmToken']);

        Route::prefix('user')->group(function () {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
            Route::put('bank-details', [ProfileController::class, 'updateBankDetails']);
            Route::put('password', [ProfileController::class, 'changePassword']);
            Route::get('referral-code', [ProfileController::class, 'referralCode']);
        });

        Route::prefix('kyc')->group(function () {
            Route::get('status', [KycController::class, 'status']);
            Route::post('upload', [KycController::class, 'upload'])->middleware('throttle:10,1');
            Route::delete('documents/{id}', [KycController::class, 'deleteDocument']);
        });

        Route::get('assets', [AssetController::class, 'index']);
        Route::get('assets/{symbol}', [AssetController::class, 'show']);

        Route::post('markets/favorites/{assetId}/toggle', [MarketFavoriteController::class, 'toggle'])
            ->middleware('throttle:60,1');

        Route::prefix('trades')->group(function () {
            Route::post('buy', [TradeController::class, 'buy'])->middleware('throttle:30,1');
            Route::post('sell', [TradeController::class, 'sell'])->middleware('throttle:30,1');
            Route::get('summary/pnl', [TradeController::class, 'pnlSummary']);
            Route::get('/', [TradeController::class, 'index']);
            Route::get('{id}', [TradeController::class, 'show']);
        });

        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'show']);
            Route::get('transactions', [TransactionController::class, 'index']);
            Route::get('transactions/{id}', [TransactionController::class, 'show']);
            Route::get('income-breakdown', [WalletController::class, 'incomeBreakdown']);
        });

        Route::prefix('deposits')->group(function () {
            Route::get('instructions', [DepositController::class, 'instructions']);
            Route::post('/', [DepositController::class, 'store'])->middleware('throttle:10,1');
            Route::get('/', [DepositController::class, 'index']);
            Route::get('{id}', [DepositController::class, 'show']);
            Route::delete('{id}', [DepositController::class, 'cancel']);
        });

        Route::prefix('withdrawals')->group(function () {
            Route::post('/', [WithdrawalController::class, 'store'])
                ->middleware(['verified.kyc', 'throttle:5,1']);
            Route::get('/', [WithdrawalController::class, 'index']);
            Route::get('{id}', [WithdrawalController::class, 'show']);
            Route::delete('{id}', [WithdrawalController::class, 'cancel']);
        });

        Route::prefix('referrals')->group(function () {
            Route::get('/', [ReferralController::class, 'summary']);
            Route::get('team', [ReferralController::class, 'team']);
            Route::get('tree', [ReferralController::class, 'tree']);
            Route::get('commissions', [ReferralController::class, 'commissions']);
        });

        Route::prefix('support')->group(function () {
            Route::get('tickets', [TicketController::class, 'index']);
            Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:5,1');
            Route::get('tickets/{id}', [TicketController::class, 'show']);
            Route::post('tickets/{id}/reply', [TicketController::class, 'reply'])->middleware('throttle:10,1');
            Route::put('tickets/{id}/close', [TicketController::class, 'close']);
        });

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);

        Route::prefix('admin')->middleware('role:admin|super_admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'overview']);
            Route::get('dashboard/charts', [DashboardController::class, 'charts']);

            Route::prefix('users')->group(function () {
                Route::get('/', [UserManagementController::class, 'index']);
                Route::get('{id}', [UserManagementController::class, 'show']);
                Route::put('{id}/status', [UserManagementController::class, 'updateStatus']);
                Route::post('{id}/wallet/adjust', [UserManagementController::class, 'walletAdjust']);
                Route::get('{id}/trades', [UserManagementController::class, 'trades']);
                Route::get('{id}/referral-tree', [UserManagementController::class, 'referralTree']);
            });

            Route::prefix('kyc')->group(function () {
                Route::get('/', [KycManagementController::class, 'index']);
                Route::get('{userId}/documents', [KycManagementController::class, 'userDocuments']);
                Route::get('document/{documentId}/view', [KycManagementController::class, 'viewDocument']);
                Route::post('{userId}/approve', [KycManagementController::class, 'approve']);
                Route::post('{userId}/reject', [KycManagementController::class, 'reject']);
                Route::put('document/{documentId}/approve', [KycManagementController::class, 'approveDocument']);
                Route::put('document/{documentId}/reject', [KycManagementController::class, 'rejectDocument']);
            });

            Route::prefix('prices')->group(function () {
                Route::get('/', [PriceManagementController::class, 'index']);
                Route::post('{assetId}/override', [PriceManagementController::class, 'setOverride']);
                Route::post('{assetId}/chart-trend', [PriceManagementController::class, 'setChartTrend']);
                Route::delete('{assetId}/override', [PriceManagementController::class, 'removeOverride']);
                Route::post('refresh', [PriceManagementController::class, 'forceRefresh']);
            });

            Route::prefix('assets')->group(function () {
                Route::get('/', [AssetManagementController::class, 'index']);
                Route::post('/', [AssetManagementController::class, 'store']);
                Route::put('{id}', [AssetManagementController::class, 'update']);
                Route::put('{id}/toggle', [AssetManagementController::class, 'toggle']);
                Route::delete('{id}', [AssetManagementController::class, 'destroy']);
            });

            Route::prefix('withdrawals')->group(function () {
                Route::get('/', [WithdrawalManagementController::class, 'index']);
                Route::get('{id}', [WithdrawalManagementController::class, 'show']);
                Route::put('{id}/approve', [WithdrawalManagementController::class, 'approve']);
                Route::put('{id}/reject', [WithdrawalManagementController::class, 'reject']);
                Route::put('{id}/mark-paid', [WithdrawalManagementController::class, 'markPaid']);
                Route::post('bulk-approve', [WithdrawalManagementController::class, 'bulkApprove']);
            });

            Route::prefix('referrals')->group(function () {
                Route::get('settings', [ReferralManagementController::class, 'settings']);
                Route::put('settings', [ReferralManagementController::class, 'updateSettings']);
                Route::get('{userId}/tree', [ReferralManagementController::class, 'tree']);
                Route::get('{userId}/commissions', [ReferralManagementController::class, 'commissions']);
            });

            Route::prefix('support')->group(function () {
                Route::get('tickets', [TicketController::class, 'adminIndex']);
                Route::get('tickets/{id}', [TicketController::class, 'adminShow']);
                Route::post('tickets/{id}/reply', [TicketController::class, 'adminReply']);
                Route::put('tickets/{id}', [TicketController::class, 'adminUpdate']);
            });

            Route::post('notifications/send', [BroadcastController::class, 'sendToUser']);
            Route::post('notifications/broadcast', [BroadcastController::class, 'broadcast']);
            Route::get('notifications/history', [BroadcastController::class, 'history']);

            Route::middleware('role:super_admin')->group(function () {
                Route::get('settings', [SettingsController::class, 'index']);
                Route::put('settings/{key}', [SettingsController::class, 'update']);
                Route::get('activity-logs', [ActivityLogController::class, 'index']);
                Route::get('stats/trades', [DashboardController::class, 'tradeStats']);
                Route::get('stats/users', [DashboardController::class, 'userStats']);
            });
        });
    });
});
