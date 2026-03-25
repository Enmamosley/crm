<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'mail:test {email}';
    protected $description = 'Envía un correo de prueba para verificar configuración SMTP';

    public function handle(): int
    {
        $to = $this->argument('email');

        $this->info("Enviando correo de prueba a {$to}...");
        $this->info("Mailer: " . config('mail.default'));
        $this->info("Host: " . config('mail.mailers.smtp.host'));
        $this->info("Port: " . config('mail.mailers.smtp.port'));
        $this->info("Scheme: " . config('mail.mailers.smtp.scheme'));
        $this->info("Username: " . config('mail.mailers.smtp.username'));
        $this->info("From: " . config('mail.from.address'));

        try {
            Mail::raw('Este es un correo de prueba del CRM. Si lo ves, SMTP funciona correctamente.', function ($msg) use ($to) {
                $msg->to($to)->subject('Prueba SMTP - CRM');
            });

            $this->info('✅ Correo enviado correctamente.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}
