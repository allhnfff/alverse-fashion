<?php

declare(strict_types=1);

namespace App\Domain;

/** Input pengguna tidak lolos validasi. Pesannya aman ditampilkan ke pengguna. */
final class ValidationError extends \InvalidArgumentException
{
}
