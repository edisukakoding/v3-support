<?php

namespace Esikat\Helper\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ActionListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:actions')
            ->setDescription('Menampilkan daftar aksi')
            ->setHelp('Command ini akan menampilkan daftar aksi')
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Hanya tampilkan aksi dari file tertentu (basename, mis. integrasi.php)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();

        // Muat helper project (URLEncrypt, dsb) kalau tersedia.
        $initPath = $projectRoot . '/src/core/init.php';
        if (file_exists($initPath)) {
            require_once $initPath;
        }

        // Fallback jika URLEncrypt belum ada (dipanggil via CLI murni).
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

        // ===== Filter by --file if provided =====
        $filterFile = (string) ($input->getOption('file') ?? '');
        if ($filterFile !== '') {
            $target = strtolower(trim($filterFile));
            $files = array_values(array_filter($files, function ($f) use ($target) {
                return strtolower(basename($f)) === $target;
            }));

            if (empty($files)) {
                $output->writeln("<error>File '{$filterFile}' tidak ditemukan di src/routes/actions.</error>");
                $available = glob($actionsDir . '/*.php') ?: [];
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
            $output->writeln('<comment>Tidak ada file actions di src/routes/actions.</comment>');
            return Command::SUCCESS;
        }

        // ---- Pass 1: Kumpulkan semua item & hitung frekuensi kode ----
        $itemsByFile  = [];      // [filename => [ [code, path, resolved]... ]]
        $countByCode  = [];      // [code => freq]
        $totalPerFile = [];      // ringkasan
        foreach ($files as $file) {
            $basename = basename($file);
            $data     = require $file;

            if (!is_array($data)) {
                continue; // lewati file yang tidak mengembalikan array routes
            }

            foreach ($data as $code => $path) {
                $code = (string) $code;
                $path = (string) $path;

                $resolved = $path !== ''
                    ? $projectRoot . '/' . ltrim($path, '/\\')
                    : '';

                $itemsByFile[$basename][] = [
                    'code'     => $code,
                    'path'     => $path,
                    'resolved' => $resolved,
                ];

                $countByCode[$code] = ($countByCode[$code] ?? 0) + 1;
            }

            $totalPerFile[$basename] = isset($itemsByFile[$basename])
                ? count($itemsByFile[$basename])
                : 0;
        }

        // Tidak ada item valid sama sekali
        if (empty($itemsByFile)) {
            $output->writeln('<comment>Tidak ada aksi yang valid ditemukan.</comment>');
            return Command::SUCCESS;
        }

        // ---- Pass 2: Render per file dengan Status yang sudah tahu duplikatnya ----
        $rendered = 0;
        $hasDuplicate = false;

        foreach ($itemsByFile as $basename => $items) {
            if ($rendered > 0) {
                $output->writeln('');
            }

            $output->writeln("<info>File: {$basename}</info>");

            // Urutkan berdasarkan code
            usort($items, fn($a, $b) => strcmp($a['code'], $b['code']));

            $rows = [];
            foreach ($items as $it) {
                $exists = ($it['path'] !== '' && file_exists($it['resolved']));
                $isDup  = ($countByCode[$it['code']] ?? 0) > 1;

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
                    $it['code'],
                    URLEncrypt($it['code']),
                    $it['path'],
                    $status,
                ];
            }

            // Render tabel
            $table = new Table($output);
            $table->setHeaders(['Kode', 'Enkripsi', 'Sumber', 'Status'])
                  ->setRows($rows)
                  ->render();

            $rendered++;
        }

        // Ringkasan akhir
        $output->writeln('');
        $output->writeln('<comment>Ringkasan:</comment>');
        foreach ($totalPerFile as $fname => $cnt) {
            $output->writeln("- {$fname}: {$cnt} aksi");
        }

        if ($hasDuplicate) {
            $output->writeln('');
            $output->writeln('<error>Duplikat terdeteksi. Pastikan setiap \"kode\" aksi unik di seluruh file.</error>');
            return Command::FAILURE; // ubah ke SUCCESS jika hanya ingin warning
        }

        return Command::SUCCESS;
    }
}
