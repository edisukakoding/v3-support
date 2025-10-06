<?php

namespace Esikat\Helper\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PageListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:pages')
            ->setDescription('Menampilkan daftar routing halaman')
            ->setHelp('Command ini akan menampilkan daftar routing halaman')
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Hanya tampilkan route dari file tertentu (basename, mis. integrasi.php)'
            );
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

        // ===== Filter by --file if provided =====
        $filterFile = (string) ($input->getOption('file') ?? '');
        if ($filterFile !== '') {
            $target = strtolower(trim($filterFile));
            $files = array_values(array_filter($files, function ($f) use ($target) {
                return strtolower(basename($f)) === $target;
            }));

            if (empty($files)) {
                $output->writeln("<error>File '{$filterFile}' tidak ditemukan di src/routes/pages.</error>");
                // Tampilkan daftar yang tersedia sebagai bantuan cepat
                $available = glob($pagesDir . '/*.php') ?: [];
                if (!empty($available)) {
                    $output->writeln('<comment>File yang tersedia:</comment>');
                    foreach ($available as $f) {
                        $output->writeln('- ' . basename($f));
                    }
                }
                return Command::FAILURE;
            }
        }

        if (empty($files)) {
            $output->writeln('<comment>Tidak ada file pages di src/routes/pages.</comment>');
            return Command::SUCCESS;
        }

        // -------- Pass 1: Kumpulkan semua item + hitung frekuensi param --------
        $itemsByFile  = [];   // [basename => [ [param, sumber, judul, resolved]... ]]
        $countByParam = [];   // [param => freq]
        $totalPerFile = [];   // ringkasan

        foreach ($files as $file) {
            $basename = basename($file);
            $data     = require $file;

            if (!is_array($data)) {
                continue;
            }

            // Normalisasi: dukung juga format associative sederhana: ['kode' => 'path.php']
            $normalized = [];
            $isAssocMap = $data && array_keys($data) !== range(0, count($data) - 1);

            if ($isAssocMap) {
                foreach ($data as $k => $v) {
                    if (is_array($v)) {
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

                $itemsByFile[$basename][] = [
                    'param'   => $param,
                    'sumber'  => $sumber,
                    'judul'   => $judul,
                    'resolved'=> $resolved,
                ];

                $countByParam[$param] = ($countByParam[$param] ?? 0) + 1;
            }

            $totalPerFile[$basename] = isset($itemsByFile[$basename]) ? count($itemsByFile[$basename]) : 0;
        }

        if (empty($itemsByFile)) {
            $output->writeln('<comment>Tidak ada halaman yang valid ditemukan.</comment>');
            return Command::SUCCESS;
        }

        // -------- Pass 2: Render tabel per file dengan status yang sudah tahu duplikatnya --------
        $rendered     = 0;
        $hasDuplicate = false;

        foreach ($itemsByFile as $basename => $items) {
            if ($rendered > 0) {
                $output->writeln('');
            }

            $output->writeln("<info>File: {$basename}</info>");

            // Urutkan berdasarkan param
            usort($items, fn($a, $b) => strcmp($a['param'], $b['param']));

            $rows = [];
            foreach ($items as $it) {
                $exists = file_exists($it['resolved']);
                $isDup  = ($countByParam[$it['param']] ?? 0) > 1;

                // Tentukan status
                if ($exists && !$isDup) {
                    $status = 'OK';
                } elseif (!$exists && !$isDup) {
                    $status = 'MISSING';
                } elseif ($exists && $isDup) {
                    $status = 'DUPLICATE';
                } else { // !$exists && $isDup
                    $status = 'MISSING & DUPLICATE';
                }

                if ($isDup) {
                    $hasDuplicate = true;
                }

                $rows[] = [
                    $it['param'],
                    URLEncrypt($it['param']),
                    $it['sumber'],
                    $it['judul'],
                    $status,
                ];
            }

            $table = new Table($output);
            $table->setHeaders(['Param', 'Enkripsi', 'Sumber', 'Judul', 'Status'])
                  ->setRows($rows)
                  ->render();

            $rendered++;
        }

        // Ringkasan akhir
        $output->writeln('');
        $output->writeln('<comment>Ringkasan:</comment>');
        foreach ($totalPerFile as $fname => $cnt) {
            $output->writeln("- {$fname}: {$cnt} route");
        }

        if ($hasDuplicate) {
            $output->writeln('');
            $output->writeln('<error>Duplikat terdeteksi. Pastikan setiap "param" unik di seluruh file.</error>');
            return Command::FAILURE; // ubah ke SUCCESS bila hanya ingin warning
        }

        return Command::SUCCESS;
    }
}
