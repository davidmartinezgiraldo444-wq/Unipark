<?php
declare(strict_types=1);

namespace App\Helpers;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $destino): self
    {
        return new self('', 303, ['Location' => $destino]);
    }

    /** @param list<array<string,scalar|null>> $filas */
    public static function csv(string $nombre, array $encabezados, array $filas): self
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes.
        fputcsv($f, $encabezados, ';');
        foreach ($filas as $fila) {
            // Evita inyección de fórmulas al abrir el archivo en una hoja de cálculo.
            $fila = array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $fila);
            fputcsv($f, array_values($fila), ';');
        }
        rewind($f);
        $contenido = stream_get_contents($f);
        fclose($f);
        return new self($contenido, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $cabeceras = $this->headers + [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'Referrer-Policy'         => 'same-origin',
            'Cache-Control'           => 'no-store',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; "
                . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
                . "img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'",
        ];
        foreach ($cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        echo $this->body;
    }
}
