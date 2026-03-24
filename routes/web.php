<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AgentControlController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\MailboxController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\DnsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RecurringInvoiceController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\DiscountCodeController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\DirectCheckoutController;
use App\Http\Controllers\CartController;

Route::get('/', fn() => redirect('/login'));

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Compra directa (público)
Route::prefix('buy')->name('buy.')->group(function () {
    Route::get('/', [DirectCheckoutController::class, 'catalog'])->name('catalog');
    Route::get('domain/check', [DirectCheckoutController::class, 'checkDomain'])->name('domain.check')->middleware('throttle:30,1');
    Route::get('cart', [CartController::class, 'index'])->name('cart');
    Route::post('cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::delete('cart/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('cart/discount', [CartController::class, 'applyDiscount'])->name('cart.discount');
    Route::delete('cart/discount/remove', [CartController::class, 'removeDiscount'])->name('cart.discount.remove');
    Route::post('cart/pay/card', [CartController::class, 'payWithCard'])->name('cart.pay.card')->middleware('throttle:payments');
    Route::post('cart/pay/oxxo', [CartController::class, 'payWithOxxo'])->name('cart.pay.oxxo')->middleware('throttle:payments');
    Route::post('cart/pay/spei', [CartController::class, 'payWithSpei'])->name('cart.pay.spei')->middleware('throttle:payments');
    Route::get('cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
    Route::patch('cart/{cartItem}/quantity', [CartController::class, 'updateQuantity'])->name('cart.update-quantity');
    Route::get('cart/count', [CartController::class, 'count'])->name('cart.count');
    Route::get('{slug}', [DirectCheckoutController::class, 'show'])->name('show');
    Route::post('{slug}/pay/card', [DirectCheckoutController::class, 'payWithCard'])->name('pay.card')->middleware('throttle:payments');
    Route::post('{slug}/pay/oxxo', [DirectCheckoutController::class, 'payWithOxxo'])->name('pay.oxxo')->middleware('throttle:payments');
    Route::post('{slug}/pay/spei', [DirectCheckoutController::class, 'payWithSpei'])->name('pay.spei')->middleware('throttle:payments');
    Route::post('{slug}/pay/transfer', [DirectCheckoutController::class, 'payWithTransfer'])->name('pay.transfer')->middleware('throttle:payments');
    Route::get('{slug}/success', [DirectCheckoutController::class, 'success'])->name('success');
});

// Admin Panel
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Leads (admin + sales)
    Route::resource('leads', LeadController::class);
    Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.update-status');
    Route::post('leads/{lead}/notes', [LeadController::class, 'addNote'])->name('leads.add-note');

    // Categories (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::resource('categories', ServiceCategoryController::class);
        Route::resource('services', ServiceController::class);
    });

    // Quotes
    Route::resource('quotes', QuoteController::class);
    Route::patch('quotes/{quote}/send', [QuoteController::class, 'markAsSent'])->name('quotes.send');
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'downloadPdf'])->name('quotes.pdf');
    Route::post('quotes/{quote}/convert', [QuoteController::class, 'convertToOrder'])->name('quotes.convert');

    // Settings (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('settings/twentyi/bundle-types', [SettingController::class, 'packageBundleTypes'])->name('settings.twentyi.bundle-types');
    });

    // Agent Control (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('agent', [AgentControlController::class, 'index'])->name('agent.index');
        Route::post('agent/pause', [AgentControlController::class, 'pause'])->name('agent.pause');
        Route::post('agent/reactivate', [AgentControlController::class, 'reactivate'])->name('agent.reactivate');
    });

    // Clientes
    Route::resource('clients', ClientController::class);
    Route::post('clients/{client}/documents', [DocumentController::class, 'store'])->name('clients.documents.store');
    Route::get('clients/{client}/documents/{document}/download', [DocumentController::class, 'download'])->name('clients.documents.download');
    Route::delete('clients/{client}/documents/{document}', [DocumentController::class, 'destroy'])->name('clients.documents.destroy');
    Route::post('clients/{client}/create-hosting', [ClientController::class, 'createHosting'])->name('clients.create-hosting');
    Route::post('clients/{client}/sync-facturapi', [ClientController::class, 'syncFacturapi'])->name('clients.sync-facturapi');

    // Dominios (Cosmotown)
    Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
    Route::get('domains/check', [DomainController::class, 'check'])->name('domains.check');
    Route::post('domains/register', [DomainController::class, 'register'])->name('domains.register');
    Route::get('domains/list', [DomainController::class, 'list'])->name('domains.list');
    Route::get('domains/ping', [DomainController::class, 'ping'])->name('domains.ping');
    Route::get('domains/{domain}/info', [DomainController::class, 'info'])->name('domains.info')->where('domain', '[a-zA-Z0-9\.\-]+');
    Route::get('domains/{domain}/dns', [DomainController::class, 'dns'])->name('domains.dns')->where('domain', '[a-zA-Z0-9\.\-]+');
    Route::post('domains/{domain}/dns', [DomainController::class, 'saveDns'])->name('domains.dns.save')->where('domain', '[a-zA-Z0-9\.\-]+');
    Route::post('domains/{domain}/nameservers', [DomainController::class, 'saveNameservers'])->name('domains.nameservers.save')->where('domain', '[a-zA-Z0-9\.\-]+');
    Route::post('domains/{domain}/renew', [DomainController::class, 'renew'])->name('domains.renew')->where('domain', '[a-zA-Z0-9\.\-]+');
    Route::post('domains/status', [DomainController::class, 'status'])->name('domains.status');

    // Correos 20i
    Route::get('clients/{client}/mailboxes', [MailboxController::class, 'index'])->name('clients.mailboxes.index');
    Route::post('clients/{client}/mailboxes', [MailboxController::class, 'store'])->name('clients.mailboxes.store');
    Route::delete('clients/{client}/mailboxes/{mailbox}', [MailboxController::class, 'destroy'])->name('clients.mailboxes.destroy');
    Route::post('clients/{client}/mailboxes/{mailbox}/password', [MailboxController::class, 'changePassword'])->name('clients.mailboxes.password');
    Route::post('clients/{client}/mailboxes/{mailbox}/webmail', [MailboxController::class, 'webmail'])->name('clients.mailboxes.webmail');

    // DNS 20i
    Route::get('clients/{client}/dns', [DnsController::class, 'index'])->name('clients.dns.index');
    Route::post('clients/{client}/dns', [DnsController::class, 'store'])->name('clients.dns.store');
    Route::delete('clients/{client}/dns/{record}', [DnsController::class, 'destroy'])->name('clients.dns.destroy');

    // Facturas (admin + accounting)
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('invoices/{invoice}/stamp', [InvoiceController::class, 'stamp'])->name('invoices.stamp');
    Route::delete('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/xml', [InvoiceController::class, 'downloadXml'])->name('invoices.xml');
    Route::post('invoices/{invoice}/send-link', [InvoiceController::class, 'sendPaymentLink'])->name('invoices.send-link');
    Route::post('invoices/{invoice}/pay-manual', [InvoiceController::class, 'registerManualPayment'])->name('invoices.pay-manual');
    Route::patch('payments/{payment}/approve', [InvoiceController::class, 'approveTransfer'])->name('payments.approve');

    // Usuarios (admin only)
    Route::middleware('role:admin')->resource('users', UserController::class);

    // Reportes (admin + accounting)
    Route::middleware('role:admin,accounting')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/invoices', [ReportController::class, 'exportInvoices'])->name('export.invoices');
        Route::get('/export/payments', [ReportController::class, 'exportPayments'])->name('export.payments');
        Route::get('/export/leads', [ReportController::class, 'exportLeads'])->name('export.leads');
    });

    // Log de actividad (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    });

    // Backups (admin only)
    Route::middleware('role:admin')->prefix('backups')->name('backups.')->group(function () {
        Route::get('/', function () {
            $dir = storage_path('app/backups');
            $files = is_dir($dir) ? collect(\Illuminate\Support\Facades\File::files($dir))
                ->sortByDesc(fn($f) => $f->getMTime())
                ->map(fn($f) => [
                    'name' => $f->getFilename(),
                    'size' => number_format($f->getSize() / 1024, 1) . ' KB',
                    'date' => date('d/m/Y H:i', $f->getMTime()),
                ]) : collect();
            return view('admin.backups', compact('files'));
        })->name('index');

        Route::post('create', function () {
            \Illuminate\Support\Facades\Artisan::call('db:backup');
            return back()->with('success', 'Backup creado exitosamente.');
        })->name('create');

        Route::get('{filename}', function (string $filename) {
            $path = storage_path('app/backups/' . basename($filename));
            abort_unless(file_exists($path), 404);
            return response()->download($path);
        })->name('download')->where('filename', '[a-zA-Z0-9_\-.]+');
    });

    // Facturas recurrentes (admin + accounting)
    Route::middleware('role:admin,accounting')->group(function () {
        Route::resource('recurring-invoices', RecurringInvoiceController::class);
    });

    // Notificaciones
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::get('count', [NotificationController::class, 'unreadCount'])->name('count');
    });

    // Tickets de soporte (admin)
    Route::resource('tickets', TicketController::class)->only(['index', 'show', 'update']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    // Tareas internas
    Route::resource('tasks', TaskController::class)->except(['show']);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

    // Admin only: cupones, etiquetas, permisos
    Route::middleware('role:admin')->group(function () {
        Route::resource('discount-codes', DiscountCodeController::class)->except('show');
        Route::resource('tags', TagController::class)->except(['create', 'edit', 'show']);
        Route::get('users/{user}/permissions', [PermissionController::class, 'edit'])->name('users.permissions.edit');
        Route::put('users/{user}/permissions', [PermissionController::class, 'update'])->name('users.permissions.update');
    });
});

// Portal Cliente (acceso público por token, validado)
Route::prefix('portal')->name('portal.')->middleware('portal')->group(function () {
    Route::get('{token}', [ClientPortalController::class, 'show'])->name('dashboard');
    Route::get('{token}/invoices/{invoice}/pdf', [ClientPortalController::class, 'downloadInvoicePdf'])->name('invoice.pdf');
    Route::get('{token}/invoices/{invoice}/xml', [ClientPortalController::class, 'downloadInvoiceXml'])->name('invoice.xml');
    Route::get('{token}/documents/{document}', [ClientPortalController::class, 'downloadDocument'])->name('document.download');

    // Gestión de correos
    Route::get('{token}/mailboxes', [ClientPortalController::class, 'mailboxes'])->name('mailboxes');
    Route::post('{token}/mailboxes', [ClientPortalController::class, 'storeMailbox'])->name('mailboxes.store');
    Route::delete('{token}/mailboxes/{mailbox}', [ClientPortalController::class, 'destroyMailbox'])->name('mailboxes.destroy');
    Route::post('{token}/mailboxes/{mailbox}/password', [ClientPortalController::class, 'changeMailboxPassword'])->name('mailboxes.password');
    Route::post('{token}/mailboxes/{mailbox}/webmail', [ClientPortalController::class, 'webmail'])->name('mailbox.webmail');

    // Pagos Mercado Pago
    Route::get('{token}/invoices/{invoice}/checkout', [ClientPortalController::class, 'checkout'])->name('checkout');
    Route::post('{token}/invoices/{invoice}/pay/card', [ClientPortalController::class, 'payWithCard'])->name('pay.card')->middleware('throttle:payments');
    Route::post('{token}/invoices/{invoice}/pay/oxxo', [ClientPortalController::class, 'payWithOxxo'])->name('pay.oxxo')->middleware('throttle:payments');
    Route::post('{token}/invoices/{invoice}/pay/spei', [ClientPortalController::class, 'payWithSpei'])->name('pay.spei')->middleware('throttle:payments');
    Route::post('{token}/invoices/{invoice}/pay/transfer', [ClientPortalController::class, 'payWithTransfer'])->name('pay.transfer')->middleware('throttle:payments');
    Route::get('{token}/payments/{payment}', [ClientPortalController::class, 'paymentStatus'])->name('payment.status');

    // Cotizaciones (aceptar/rechazar)
    Route::get('{token}/quotes/{quote}', [ClientPortalController::class, 'showQuote'])->name('quote.show');
    Route::post('{token}/quotes/{quote}/accept', [ClientPortalController::class, 'acceptQuote'])->name('quote.accept');
    Route::post('{token}/quotes/{quote}/reject', [ClientPortalController::class, 'rejectQuote'])->name('quote.reject');

    // Datos fiscales
    Route::get('{token}/fiscal', [ClientPortalController::class, 'editFiscalData'])->name('fiscal.edit');
    Route::put('{token}/fiscal', [ClientPortalController::class, 'updateFiscalData'])->name('fiscal.update');

    // Tickets de soporte
    Route::get('{token}/tickets', [ClientPortalController::class, 'tickets'])->name('tickets.index');
    Route::get('{token}/tickets/create', [ClientPortalController::class, 'createTicket'])->name('tickets.create');
    Route::post('{token}/tickets', [ClientPortalController::class, 'storeTicket'])->name('tickets.store');
    Route::get('{token}/tickets/{ticket}', [ClientPortalController::class, 'showTicket'])->name('tickets.show');
    Route::post('{token}/tickets/{ticket}/reply', [ClientPortalController::class, 'replyToTicket'])->name('tickets.reply');

    // Dominio (Cosmotown)
    Route::get('{token}/domain', [ClientPortalController::class, 'domain'])->name('domain');
    Route::get('{token}/domain/dns', [ClientPortalController::class, 'domainDns'])->name('domain.dns');
    Route::post('{token}/domain/dns', [ClientPortalController::class, 'saveDomainDns'])->name('domain.dns.save');
    Route::post('{token}/domain/nameservers', [ClientPortalController::class, 'saveDomainNameservers'])->name('domain.nameservers.save');
});
