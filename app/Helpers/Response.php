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

    /** Respuesta JSON para las pantallas del celular (escáner, cámara, Mi cuenta). */
    public static function json(array $datos, int $status = 200): self
    {
        return new self(
            json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /**
     * Política de contenido para las pantallas de cámara: permite las bibliotecas de lectura de
     * códigos (ZXing) y de IA (TensorFlow.js, Teachable Machine, COCO-SSD) desde jsDelivr, y la
     * descarga de los pesos del modelo preentrenado desde storage.googleapis.com.
     * Las imágenes de la cámara se procesan en el navegador y nunca se envían al servidor.
     * $ia: TensorFlow.js evalúa código al iniciar ('unsafe-eval'); se permite SOLO en las dos
     * pantallas de cámara con IA, nunca en el resto del sitio.
     */
    public function conBibliotecasDeCamara(bool $ia = false): self
    {
        $this->headers['Content-Security-Policy'] = "default-src 'self'; "
            . "script-src 'self' https://cdn.jsdelivr.net" . ($ia ? " 'unsafe-eval'" : '') . "; "
            . "connect-src 'self' https://cdn.jsdelivr.net https://storage.googleapis.com; "
            . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
            . "img-src 'self' data: blob:; media-src 'self' blob: mediastream:; worker-src 'self' blob:; "
            . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'";
        return $this;
    }

    /**
     * Permite que los formularios de ESTA página se envíen a un origen externo concreto (la página de pago
     * de la pasarela). El resto del sitio conserva form-action 'self'.
     */
    public function conFormularioHacia(string $origen): self
    {
        $this->headers['Content-Security-Policy'] = str_replace("form-action 'self'", "form-action 'self' " . $origen, self::CSP);
        return $this;
    }

    private const CSP = "default-src 'self'; script-src 'self'; "
        . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
        . "img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'";

    /** @param list<array<string,scalar|null>> $filas */
    public static function csv(string $nombre, array $encabezados, array $filas): self
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes.
        // Escape explícito: PHP 8.4+ declara obsoleto omitirlo (mismo criterio que la importación).
        fputcsv($f, $encabezados, ';', '"', '');
        foreach ($filas as $fila) {
            // Evita inyección de fórmulas al abrir el archivo en una hoja de cálculo.
            $fila = array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $fila);
            fputcsv($f, array_values($fila), ';', '"', '');
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
            'Content-Security-Policy' => self::CSP,
        ];
        foreach ($cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        echo $this->body;
    }
}
