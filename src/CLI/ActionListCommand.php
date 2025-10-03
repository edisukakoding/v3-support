<?php

namespace Esikat\Helper\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActionListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:actions')
            ->setDescription('Menampilkan daftar aksi')
            ->setHelp('Command ini akan menampilkan daftar aksi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();

        // Muat helper project (URLEncrypt, dsb) kalau tersedia.
        $initPath = $projectRoot . '/src/core/init.php';
        if (file_exists($initPath)) {
            require_once $initPath;
        }

        // Fallback jika URLEncrypt belum terdefinisi saat CLI dipanggil di luar konteks web.
        if (!function_exists('URLEncrypt')) {
            function URLEncrypt($url)
            {
                $key    = $_ENV['APP_KEY'] ?? 'asdasdasd';
                $token  = $url ^ $key;
                return rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
            }
        }

        $actionsDir = $projectRoot . '/src/routes/actions';
        if (!is_dir($actionsDir)) {
            $output->writeln('<error>Folder src/routes/actions tidak ditemukan.</error>');
            return Command::FAILURE;
        }

        $files = glob($actionsDir . '/*.php') ?: [];
        if (empty($files)) {
            $output->writeln('<comment>Tidak ada file actions di src/routes/actions.</comment>');
            return Command::SUCCESS;
        }

        $renderedCount  = 0;
        $totalPerFile   = [];

        foreach ($files as $file) {
            $data = require $file;

            if (!is_array($data)) {
                continue;
            }

            $rows = [];
            foreach ($data as $code => $path) {
                $resolved = is_string($path)
                    ? $projectRoot . '/' . ltrim($path, '/\\')
                    : '';

                $rows[] = [
                    $code,
                    URLEncrypt($code),
                    $path,
                    (file_exists($resolved) ? 'OK' : 'MISSING'),
                ];
            }

            if (empty($rows)) {
                continue;
            }

            // Urutkan biar rapi per file
            usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

            // Pemisah visual antar file
            if ($renderedCount > 0) {
                $output->writeln('');
            }

            // Header nama file
            $output->writeln("<info>File: " . basename($file) . "</info>");

            // Render tabel untuk file ini
            $table = new Table($output);
            $table->setHeaders(['Kode', 'Enkripsi', 'Sumber', 'Status'])
                  ->setRows($rows)
                  ->render();

            $totalPerFile[basename($file)] = count($rows);
            $renderedCount++;
        }

        if ($renderedCount === 0) {
            $output->writeln('<comment>Tidak ada aksi yang valid ditemukan.</comment>');
            return Command::SUCCESS;
        }

        // Ringkasan akhir (opsional)
        $output->writeln('');
        $output->writeln('<comment>Ringkasan:</comment>');
        foreach ($totalPerFile as $fname => $cnt) {
            $output->writeln("- {$fname}: {$cnt} aksi");
        }

        return Command::SUCCESS;
    }
}
