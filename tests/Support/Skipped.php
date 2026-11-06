<?php
declare(strict_types=1);

namespace Tests\Support;

/** La prueba no aplica en este entorno (por ejemplo, falta mysqldump). */
final class Skipped extends \Exception
{
}
