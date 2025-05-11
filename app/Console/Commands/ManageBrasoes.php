<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ManageBrasoes extends Command
{
    protected $signature = 'brasoes:manage {action : The action to perform (list|add|remove)} {--file= : The file to add/remove} {--municipality= : The municipality name}';
    protected $description = 'Manage coat of arms images for municipalities';

    public function handle()
    {
        $action = $this->argument('action');
        $brasaoDir = public_path('brasoes');

        // Ensure the directory exists
        if (!File::exists($brasaoDir)) {
            File::makeDirectory($brasaoDir, 0755, true);
        }

        switch ($action) {
            case 'list':
                $this->listBrasoes($brasaoDir);
                break;
            case 'add':
                $this->addBrasao($brasaoDir);
                break;
            case 'remove':
                $this->removeBrasao($brasaoDir);
                break;
            default:
                $this->error('Invalid action. Use: list, add, or remove');
                return 1;
        }

        return 0;
    }

    protected function listBrasoes($brasaoDir)
    {
        $files = File::files($brasaoDir);
        
        if (empty($files)) {
            $this->info('No coat of arms images found.');
            return;
        }

        $this->info('Available coat of arms images:');
        foreach ($files as $file) {
            $this->line('- ' . $file->getFilename());
        }
    }

    protected function addBrasao($brasaoDir)
    {
        $file = $this->option('file');
        $municipality = $this->option('municipality');

        if (!$file) {
            $this->error('Please provide a file using --file option');
            return;
        }

        if (!$municipality) {
            $this->error('Please provide a municipality name using --municipality option');
            return;
        }

        if (!File::exists($file)) {
            $this->error('Source file does not exist');
            return;
        }

        // Normalize municipality name
        $filename = $this->normalizeMunicipalityName($municipality) . '.png';
        $targetPath = $brasaoDir . '/' . $filename;

        // Copy the file
        File::copy($file, $targetPath);
        $this->info("Coat of arms added for {$municipality} as {$filename}");
    }

    protected function removeBrasao($brasaoDir)
    {
        $file = $this->option('file');
        $municipality = $this->option('municipality');

        if (!$file && !$municipality) {
            $this->error('Please provide either --file or --municipality option');
            return;
        }

        if ($municipality) {
            $filename = $this->normalizeMunicipalityName($municipality) . '.png';
            $file = $brasaoDir . '/' . $filename;
        }

        if (!File::exists($file)) {
            $this->error('Coat of arms file does not exist');
            return;
        }

        File::delete($file);
        $this->info('Coat of arms removed successfully');
    }

    protected function normalizeMunicipalityName($municipality)
    {
        // Remove acentos, espaços e deixa tudo minúsculo
        $municipality = iconv('UTF-8', 'ASCII//TRANSLIT', $municipality);
        $municipality = preg_replace('/[^a-zA-Z0-9]/', '', $municipality);
        return strtolower($municipality);
    }
} 