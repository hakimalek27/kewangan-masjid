<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar apabila borang SUDAH di-POST ke SPPKMS lama tetapi respons tidak
 * dapat ditafsir (recno tiada / respons tergendala). Pada titik ini rekod
 * MUNGKIN sudah tercipta di sistem lama — maka cubaan semula automatik
 * DILARANG (boleh hasilkan rekod kewangan PENDUA). Baris sync ditanda FAILED
 * dengan arahan semak manual, bukan dibiar PENDING untuk retry buta.
 */
class SppkmsPostSentException extends RuntimeException
{
}
