<?php

namespace Esikat\Helper\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PageListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:pages')
            ->setDescription('Menampilkan daftar routing halaman')
            ->setHelp('Command ini akan menampilkan daftar routing halaman');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();

        // Muat helper project (URLEncrypt, dsb) kalau ada.
        $initPath = $projectRoot . '/src/core/init.php';
        if (file_exists($initPath)) {
            require_once $initPath;
        }

        // Fallback kalau URLEncrypt belum ada saat jalan dari CLI.
        if (!function_exists('URLEncrypt')) {
            function URLEncrypt($url) 
            {
                $key    = $_ENV['APP_KEY'] ?? 'asdasdasd';
                $token  = $url ^ $key;
                return rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
            }
        }

        $pagesDir = $projectRoot . '/src/routes/pages';
        if (!is_dir($pagesDir)) {
            $output->writeln('<error>Folder src/routes/pages tidak ditemukan.</error>');
            return Command::FAILURE;
        }

        $files = glob($pagesDir . '/*.php') ?: [];
        if (!$files) {
            $output->writeln('<comment>Tidak ada file pages di src/routes/pages.</comment>');
            return Command::SUCCESS;
        }

        $rows       = [];
        $seenParams = [];

        foreach ($files as $file) {
            $data = require $file;

            if (!is_array($data)) {
                continue;
            }

            // Normalisasi: dukung juga format associative sederhana: ['kode'=>'path.php']
            $normalized = [];
            $isAssocMap = $data && array_keys($data) !== range(0, count($data) - 1);

            if ($isAssocMap) {
                foreach ($data as $k => $v) {
                    if (is_array($v)) {
                        // Sudah bentuk lengkap
                        $normalized[] = $v;
                    } else {
                        $normalized[] = ['param' => (string) $k, 'sumber' => (string) $v, 'judul' => ''];
                    }
                }
            } else {
                $normalized = $data;
            }

            foreach ($normalized as $page) {
                $param  = (string) ($page['param'] ?? '');
                $sumber = (string) ($page['sumber'] ?? '');
                $judul  = (string) ($page['judul'] ?? '');

                if ($param === '' || $sumber === '') {
                    continue;
                }

                $resolved = $projectRoot . '/' . ltrim($sumber, '/\\');
                $status   = file_exists($resolved) ? 'OK' : 'MISSING';

                // Tandai duplikasi param
                $dup = isset($seenParams[$param]) ? 'DUPLICATE' : '';
                $seenParams[$param] = true;

                $rows[] = [
                    $param,
                    URLEncrypt($param),
                    $sumber,
                    $judul,
                    $status ?: $dup ?: 'OK',
                ];
            }
        }

        if (!$rows) {
            $output->writeln('<comment>Tidak ada halaman yang valid ditemukan.</comment>');
            return Command::SUCCESS;
        }

        // Urutkan berdasarkan param
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        $table = new Table($output);
        $table->setHeaders(['Param', 'Enkripsi', 'Sumber', 'Judul', 'Status'])->setRows($rows);
        $table->render();

        return Command::SUCCESS;
    }
}
