<?php

namespace App\Imports;

use RuntimeException;

/** Berkas tidak bisa dibaca sebagai dataset. Pesannya aman ditampilkan ke admin. */
class ImportFormatException extends RuntimeException {}
