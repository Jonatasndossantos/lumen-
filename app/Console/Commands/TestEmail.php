<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\DocumentsGenerated;

class TestEmail extends Command
{
    protected $signature = 'test:email {email}';
    protected $description = 'Test email configuration';

    public function handle()
    {
        $email = $this->argument('email');
        
        $testDocuments = [
            'guidelines' => 'http://exemplo.com/guidelines.pdf',
            'demand' => 'http://exemplo.com/demand.pdf'
        ];

        try {
            Mail::to($email)
                ->send(new DocumentsGenerated($testDocuments, 'Usuário Teste'));
            
            $this->info('Email enviado com sucesso!');
        } catch (\Exception $e) {
            $this->error('Erro ao enviar email: ' . $e->getMessage());
        }
    }
} 