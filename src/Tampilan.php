<?php

namespace Esikat\Helper;

/**
 * Kelas untuk mengelola berbagai fungionalitas yang berhubungan dengan tampilan.
 */
class Tampilan 
{
    private static array $stacks = [];

    /**
     * Inisialisasi stack.
     */
    public static function mulai(): void
    {
        ob_start();
    }

    /**
     * Menyimpan Stack.
     *
     * @param string  $key             Kunci stack.
     * 
     * @example
     * // Contoh penggunaan:
     * Tampilan::start();
     * echo "<script>alert('Hello');</script>";
     * Tampilan::push('scripts');
     */
    public static function dorong(string $key): void
    {
        self::$stacks[$key][] = ob_get_clean();
    }

    /**
     * Mengambil stack.
     *
     * @param string $key               Kunci stack
     * 
     * @return string Mengembalikan stack yang di simpan dengan key yang di maksud.
     * 
     * @example
     * // Contoh penggunaan:
     * echo StackManager::stack('scripts');
     */
    public static function tumpukan(string $key): string
    {
        return isset(self::$stacks[$key]) ? implode("\n", self::$stacks[$key]) : '';
    }

    /**
     * Mengelola Flash Message.
     * 
     * @param string      $key      Kunci pesan.
     * @param string|null $message  Isi pesan (null untuk mengambil pesan).
     * @param string      $type     Jenis pesan (default: 'success').
     * 
     * @return string|null Jika mengambil pesan, mengembalikan pesan dalam format HTML.
     */
    public static function pesanKilat(string $key, ?string $message = null, string $type = 'success'): ?string
    {
        if ($message === null) {
            $messages = '';
            if (isset($_SESSION['flash'][$key])) {
                $flash = $_SESSION['flash'][$key];
                $messages = '
                    <div class="alert alert-' . $flash['type'] . ' alert-outline-coloured alert-dismissible" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <div class="alert-icon">
                            <i class="far fa-fw fa-bell"></i>
                        </div>
                        <div class="alert-message">
                            <strong>Notifikasi!</strong> ' . $flash['message'] . '
                        </div>
                    </div>
                ';
                unset($_SESSION['flash'][$key]);
            }
            return $messages;
        } else {
            $_SESSION['flash'][$key] = [
                'message' => $message,
                'type' => $type
            ];
            return null;
        }
    }
}