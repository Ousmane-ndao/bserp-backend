<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DossierController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentAccountController;
use App\Http\Controllers\Api\StudentProgressController;
use App\Models\Destination;
use Illuminate\Support\Facades\Route;

// Health check endpoint (not protected)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'uptime' => php_uname(),
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::get('/invoices/{invoice}/public-pdf', [InvoiceController::class, 'publicPdf'])
    ->middleware('signed')
    ->name('invoices.public-pdf');

if (config('erp.allow_open_registration')) {
    Route::post('/register', [AuthController::class, 'register']);
}

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/settings/profile', [AuthController::class, 'updateProfile']);
    Route::put('/settings/password', [AuthController::class, 'updatePassword']);

    Route::get('/dashboard', DashboardController::class);

    Route::middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,comptable,commercial,accueil')->group(function () {
        Route::get('/destinations', fn () => response()->json(
            Destination::query()
                ->orderBy('region')
                ->orderBy('name')
                ->get(['id', 'name', 'region', 'type_compte'])
        ));
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/options', [ClientController::class, 'options']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::get('/student-accounts/{client}', [StudentAccountController::class, 'show']);
        Route::get('/student-progress/{client}', [StudentProgressController::class, 'show']);
    });

    Route::middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial')->group(function () {
        Route::post('/clients', [ClientController::class, 'store']);
        Route::put('/clients/{client}', [ClientController::class, 'update']);
    });

    Route::middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial,accueil')->group(function () {
        Route::post('/student-accounts', [StudentAccountController::class, 'store']);
        Route::put('/student-accounts/{client}', [StudentAccountController::class, 'update']);
        Route::patch('/student-accounts/{client}', [StudentAccountController::class, 'update']);
        Route::post('/student-progress', [StudentProgressController::class, 'store']);
        Route::put('/student-progress/{client}', [StudentProgressController::class, 'update']);
        Route::patch('/student-progress/{client}', [StudentProgressController::class, 'update']);
    });
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])
        ->middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial,accueil');

    Route::middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial,accueil')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        Route::get('/documents/{document}', [DocumentController::class, 'show']);
        Route::get('/dossiers/options', [DossierController::class, 'options']);
        Route::get('/dossiers', [DossierController::class, 'index']);
        Route::get('/dossiers/{dossier}', [DossierController::class, 'show']);
        Route::get('/exports/dossiers.csv', [ExportController::class, 'dossiersCsv']);
        Route::get('/exports/dossiers.xlsx', [ExportController::class, 'dossiersXlsx']);
        Route::get('/exports/dossiers.pdf', [ExportController::class, 'dossiersPdf']);
        Route::post('/exports/dossiers', [ExportController::class, 'dossiersQueue']);
        Route::get('/exports/dossiers/{dossierExport}', [ExportController::class, 'dossiersQueueStatus']);
        Route::get('/exports/dossiers/{dossierExport}/download', [ExportController::class, 'dossiersQueueDownload']);
    });
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial,accueil');
    Route::post('/dossiers', [DossierController::class, 'store'])
        ->middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial');
    Route::put('/dossiers/{dossier}', [DossierController::class, 'update'])
        ->middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien,commercial');
    Route::delete('/dossiers/{dossier}', [DossierController::class, 'destroy'])
        ->middleware('role:directrice,responsable_admin,conseillere_pedagogique,informaticien');

    Route::middleware('role:directrice,responsable_admin,comptable,informaticien')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::put('/payments/{payment}', [PaymentController::class, 'update']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);

        Route::get('/accounting/summary', [AccountingController::class, 'summary']);

        Route::get('/exports/payments.csv', [ExportController::class, 'paymentsCsv']);
        Route::get('/exports/expenses.csv', [ExportController::class, 'expensesCsv']);
        Route::get('/exports/accounting.xlsx', [ExportController::class, 'accountingXlsx']);
        Route::get('/exports/accounting.pdf', [ExportController::class, 'accountingPdf']);

        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
        Route::get('/invoices/{invoice}/share-links', [InvoiceController::class, 'shareLinks']);
        Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('expenses', ExpenseController::class);
    });

    Route::middleware('role:directrice,responsable_admin,informaticien')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::get('/settings/company', [SettingsController::class, 'companyShow']);
        Route::put('/settings/company', [SettingsController::class, 'companyUpdate']);
    });
});
