<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\TestCase;

/** La tolerancia de 15 minutos es una regla de negocio: solo la copia de demostración puede acelerarla. */
final class ToleranciaDemoTest extends TestCase
{
    public function testElSistemaSiempreUsa15Minutos(): void
    {
        $this->assertSame(15, tolerancia_minutos('', '1'), 'Sistema principal: se ignora TOLERANCIA_MINUTOS');
        $this->assertSame(15, tolerancia_minutos('', null));
        $this->assertSame(15, tolerancia_minutos('produccion', '1'), 'Cualquier instancia que no sea demo');
        $this->assertSame(15, (int) config('tolerancia_minutos'), 'La configuración de esta instalación es la oficial');
        $this->assertFalse(tiempo_acelerado(), 'Sin rótulo de tiempo acelerado fuera de la demo');
    }

    public function testLaDemoPuedeAcelerarseDentroDeLimites(): void
    {
        $this->assertSame(1, tolerancia_minutos('demo', '1'), 'Demo: 1 minuto');
        $this->assertSame(5, tolerancia_minutos('demo', '5'));
        $this->assertSame(1, tolerancia_minutos('demo', '0'), 'Nunca menos de 1 minuto');
        $this->assertSame(15, tolerancia_minutos('demo', '90'), 'Nunca más de la oficial');
        $this->assertSame(15, tolerancia_minutos('demo', 'uno'), 'Un valor inválido vuelve a la oficial');
        $this->assertSame(15, tolerancia_minutos('demo', '-3'));
        $this->assertSame(15, tolerancia_minutos('demo', null), 'Demo sin valor: la oficial');
    }
}
