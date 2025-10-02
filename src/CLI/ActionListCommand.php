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
            ->setDescription('Menampilkan daftar aksi di dalam modul')
            ->setHelp('Command ini akan menampilkan daftar aksi yang ada di dalam modul');
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

        $rows = [];
        foreach ($files as $file) {
            // Setiap file harus mengembalikan array: ['kode' => 'path/ke/file.php', ...]
            $data = require $file;

            if (!is_array($data)) {
                // Lewati file yang tidak valid
                continue;
            }

            foreach ($data as $code => $path) {
                // Samakan perilaku dengan action.php: path relatif dari project root
                $resolved = is_string($path)
                    ? $projectRoot . '/' . ltrim($path, '/\\')
                    : '';

                $rows[] = [
                    $code,                 // Kode
                    URLEncrypt($code),     // Enkripsi
                    $path,                 // Sumber (relative path seperti yang didefinisikan)
                    file_exists($resolved) ? 'OK' : 'MISSING', // Status file
                ];
            }
        }

        if (empty($rows)) {
            $output->writeln('<comment>Tidak ada aksi yang valid ditemukan.</comment>');
            return Command::SUCCESS;
        }

        // Urutkan biar rapi.
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        $table = new Table($output);
        $table->setHeaders(['Kode', 'Enkripsi', 'Sumber', 'Status'])->setRows($rows);
        $table->render();

        return Command::SUCCESS;
    }
}
