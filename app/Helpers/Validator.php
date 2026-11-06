<?php
declare(strict_types=1);

namespace App\Helpers;

/** Validaciones del lado del servidor (RNF-03). La interfaz solo ayuda; aquí se decide. */
final class Validator
{
    /** @var array<string,string> */
    private array $errores = [];

    public function required(string $campo, string $valor, string $etiqueta): self
    {
        if ($valor === '') {
            $this->add($campo, $etiqueta . ' es obligatorio.');
        }
        return $this;
    }

    public function max(string $campo, string $valor, int $maximo, string $etiqueta): self
    {
        if (mb_strlen($valor) > $maximo) {
            $this->add($campo, $etiqueta . " admite máximo {$maximo} caracteres.");
        }
        return $this;
    }

    public function min(string $campo, string $valor, int $minimo, string $etiqueta): self
    {
        if ($valor !== '' && mb_strlen($valor) < $minimo) {
            $this->add($campo, $etiqueta . " debe tener al menos {$minimo} caracteres.");
        }
        return $this;
    }

    public function documento(string $campo, string $valor): self
    {
        if ($valor !== '' && !self::esDocumento($valor)) {
            $this->add($campo, 'El documento debe tener entre 5 y 20 caracteres: números, letras o guiones.');
        }
        return $this;
    }

    public function email(string $campo, string $valor): self
    {
        if ($valor !== '' && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->add($campo, 'El correo no tiene un formato válido.');
        }
        return $this;
    }

    public function telefono(string $campo, string $valor): self
    {
        if ($valor !== '' && preg_match('/^\+?[0-9 ]{7,20}$/', $valor) !== 1) {
            $this->add($campo, 'El teléfono solo admite números y espacios.');
        }
        return $this;
    }

    public function in(string $campo, string $valor, array $permitidos, string $mensaje): self
    {
        if (!in_array($valor, $permitidos, true)) {
            $this->add($campo, $mensaje);
        }
        return $this;
    }

    public function dinero(string $campo, string $valor, string $etiqueta, bool $permitirCero): self
    {
        if (!self::esDinero($valor, $permitirCero)) {
            $this->add($campo, $etiqueta . ($permitirCero
                ? ' debe ser un valor positivo o cero, con máximo dos decimales.'
                : ' debe ser un valor mayor que cero, con máximo dos decimales.'));
        }
        return $this;
    }

    public function fecha(string $campo, string $valor, string $etiqueta): self
    {
        if ($valor !== '' && self::aFecha($valor) === null) {
            $this->add($campo, $etiqueta . ' no es una fecha válida.');
        }
        return $this;
    }

    public function add(string $campo, string $mensaje): self
    {
        $this->errores[$campo] ??= $mensaje;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errores !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errores;
    }

    public function first(): ?string
    {
        return $this->errores ? reset($this->errores) : null;
    }

    public static function esDocumento(string $valor): bool
    {
        return preg_match('/^[0-9A-Za-z-]{5,20}$/', $valor) === 1;
    }

    /** Valor monetario no negativo, hasta 99.999.999,99, con punto decimal. */
    public static function esDinero(string $valor, bool $permitirCero = false): bool
    {
        if (preg_match('/^\d{1,8}(\.\d{1,2})?$/', $valor) !== 1) {
            return false;
        }
        return $permitirCero || (float) $valor > 0;
    }

    /** Acepta 2026-10-23 o 2026-10-23T08:30 (inputs date y datetime-local). */
    public static function aFecha(string $valor): ?\DateTimeImmutable
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!' . $formato, $valor);
            if ($fecha !== false && $fecha->format($formato) === $valor) {
                return $fecha;
            }
        }
        return null;
    }
}
