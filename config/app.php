<?php
declare(strict_types=1);

use App\Helpers\Env;

// Parámetros generales, sin secretos. Los valores sensibles vienen de .env.
return [
    'nombre'  => 'UniPark',
    'entorno' => Env::get('APP_ENV', 'local'),
    'debug'   => Env::get('APP_DEBUG', '0') === '1',

    // RNF-08: hora del servidor, zona horaria única en PHP y MySQL.
    'zona_horaria' => 'America/Bogota',
    'offset_mysql' => '-05:00',

    // RN-14: expiración por inactividad.
    'sesion_inactividad' => (int) Env::get('SESSION_IDLE_MINUTES', '15') * 60,

    // Limitar intentos repetidos de inicio de sesión (sección 10).
    'login_max_intentos' => 5,
    'login_ventana'      => 300,

    // Política de contraseñas.
    'clave_min_longitud' => 10,

    // Decisiones pendientes de la universidad (sección 15). Ajustar al confirmarlas.
    'medios_pago' => [
        'EFECTIVO'      => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia',
        'DATAFONO'      => 'Datáfono',
    ],
    'permitir_tarifa_cero' => false,
    'moneda'               => 'COP',
    'motivo_min_longitud'  => 10,

    // Cobro diario: minutos desde la entrada en los que se puede salir sin cobro. Siempre 15; solo la copia de
    // demostración puede acelerarla (TOLERANCIA_MINUTOS, ver tolerancia_minutos() en app/Helpers/functions.php).
    'tolerancia_minutos' => tolerancia_minutos(strtolower((string) Env::get('INSTANCIA', '')), Env::get('TOLERANCIA_MINUTOS')),

    // Recarga de saldo en Caja (valor máximo por recarga; evita errores de digitación).
    'recarga_maxima' => 500000,

    // Nombre visible de cada medio en historiales y comprobantes (incluye los que no se eligen en Caja).
    'medios_etiquetas' => [
        'EFECTIVO' => 'Efectivo', 'TRANSFERENCIA' => 'Transferencia', 'DATAFONO' => 'Datáfono',
        'SALDO' => 'Saldo UniPark', 'PASARELA' => 'Pago en línea',
    ],

    // Recarga voluntaria del saldo desde la web (Mi saldo). La persona inicia y autoriza CADA recarga:
    // UniPark nunca debita tarjetas ni cuentas bancarias. Vacío PASARELA_PROVEEDOR = recargas en línea apagadas.
    'recargas_en_linea' => [
        'proveedor'  => strtoupper((string) Env::get('PASARELA_PROVEEDOR', '')),
        // Valores sugeridos en pesos (separados por coma en RECARGA_LINEA_VALORES).
        'valores'    => array_values(array_filter(array_map('intval', explode(',', (string) Env::get('RECARGA_LINEA_VALORES', '10000,20000,30000,50000,100000'))))),
        'minimo'     => (int) Env::get('RECARGA_LINEA_MINIMA', '10000'),
        'maximo'     => (int) Env::get('RECARGA_LINEA_MAXIMA', '500000'),
        // Minutos para pagar una recarga creada; se envía también a la pasarela (expiration-time).
        'expira_min' => (int) Env::get('RECARGA_LINEA_EXPIRA_MIN', '60'),
        // Recargas abiertas (creadas o pendientes) que una persona puede tener a la vez.
        'max_abiertas' => 3,
        // Además de validar la firma del webhook, consultar la transacción al proveedor antes de acreditar.
        'verificar_consulta' => Env::get('PASARELA_VERIFICAR_CONSULTA', '1') === '1',
        // Medios que normalmente ofrece la pasarela; la persona elige en la página de pago del proveedor.
        // Dependen de lo que la cuenta del comercio tenga habilitado: es información, no una garantía.
        'medios_informativos' => ['PSE', 'Tarjeta débito o crédito', 'Nequi', 'Botón Bancolombia'],
    ],

    // Wompi (Colombia). Documentación: https://docs.wompi.co/docs/colombia/
    // Las llaves se leen del entorno o de config/env.php; nunca se envían al navegador salvo la pública.
    'wompi' => [
        // sandbox | produccion. Producción exige además WOMPI_PRODUCCION_AUTORIZADA=1 y llaves *_prod_.
        'entorno'               => strtolower((string) Env::get('WOMPI_ENTORNO', 'sandbox')),
        'produccion_autorizada' => Env::get('WOMPI_PRODUCCION_AUTORIZADA', '0') === '1',
        'llave_publica'         => (string) Env::get('WOMPI_LLAVE_PUBLICA', ''),
        'llave_privada'         => (string) Env::get('WOMPI_LLAVE_PRIVADA', ''),
        'secreto_eventos'       => (string) Env::get('WOMPI_SECRETO_EVENTOS', ''),
        'secreto_integridad'    => (string) Env::get('WOMPI_SECRETO_INTEGRIDAD', ''),
        'url_checkout'          => 'https://checkout.wompi.co/p/',
        'url_api'               => [
            'sandbox'    => 'https://sandbox.wompi.co/v1',
            'produccion' => 'https://production.wompi.co/v1',
        ],
    ],

    // Instancia: '' = sistema principal; 'demo' = copia de demostración (rótulo visible, base propia, simulador).
    'instancia' => strtolower((string) Env::get('INSTANCIA', '')),
    // Base que NUNCA recibe dinero de pruebas: el simulador y los sandbox se niegan a operar sobre ella.
    'base_protegida' => (string) Env::get('BASE_PROTEGIDA', 'unipark_db'),

    // Pasarela SIMULADA (solo demostración): imita a Wompi con firmas propias; no mueve dinero real.
    'simulador' => [
        'secreto_integridad' => (string) Env::get('SIMULADOR_SECRETO_INTEGRIDAD', ''),
        'secreto_eventos'    => (string) Env::get('SIMULADOR_SECRETO_EVENTOS', ''),
        'carpeta'            => BASE_PATH . '/storage/simulador/' . preg_replace('/[^a-z0-9_]/', '', (string) Env::get('DB_NAME', 'x')),
    ],

    // Dirección pública del sitio (https://…/UniPark), para la URL de retorno de la pasarela.
    // Vacía: se deduce de la solicitud.
    'url_publica' => rtrim((string) Env::get('APP_URL', ''), '/'),

    // Inactividad permitida por rol (segundos). Los dispositivos de la portería trabajan un turno sin
    // interacción humana; el resto de roles usa sesion_inactividad (RN-14).
    'sesion_inactividad_por_rol' => [
        'Punto de acceso' => (int) Env::get('SESSION_IDLE_DISPOSITIVO_MIN', '720') * 60,
        'Portería'        => (int) Env::get('SESSION_IDLE_PORTERIA_MIN', '480') * 60,
    ],

    // Tipos de vehículo que se registran hoy (especificación del 08/11/2026: automóvil, motocicleta y
    // bicicleta). PATINETA sigue en la base de datos, pero queda deshabilitada (decisión pendiente).
    'tipos_habilitados' => ['CARRO', 'MOTO', 'BICICLETA'],

    // Un evento de acceso sin resolver (esperando al portero) expira y no registra nada.
    'eventos_expiran_min' => 10,

    // Reconocimiento de vehículos en el navegador (TensorFlow.js). Por defecto no se envían imágenes.
    'ia' => [
        // Carpeta con el modelo exportado de Teachable Machine (model.json, metadata.json, weights.bin).
        // Si no existe, se usa el modelo preentrenado COCO-SSD (automóvil, motocicleta, bicicleta).
        'modelo_tm'   => 'assets/modelo-vehiculos/',
        // Confianza mínima y ventaja mínima sobre la segunda opción. El SERVIDOR las vuelve a exigir:
        // un resultado por debajo nunca se registra como definitivo y pasa al portero.
        'umbral'      => (float) Env::get('IA_UMBRAL', '0.80'),
        // Toda detección de la IA la confirma (o la cambia) el portero en su panel. Con 0, una detección
        // confiable registra la entrada o salida sola, sin portero.
        'confirmar_portero' => Env::get('IA_CONFIRMAR_PORTERO', '1') === '1',
        'margen'      => (float) Env::get('IA_MARGEN', '0.25'),
        // Tiempo que la misma clase debe mantenerse estable antes de aceptarla.
        'estable_ms'  => 1200,
        // Antigüedad máxima de la detección del celular de cámara (modo de dos celulares).
        'vigencia_ms' => 8000,
        // Segundos sin una detección confiable antes de pasar el evento al portero.
        'espera_s'    => 8,
        // Enviar una captura al portero cuando la IA no clasifica (se borra al resolver). Desactivado por
        // la política de no guardar fotografías; habilitarlo requiere aprobación.
        'captura_portero' => Env::get('IA_CAPTURA_PORTERO', '0') === '1',
    ],

    // Notificaciones push (Web Push con VAPID). Las claves se generan solas en storage/push/.
    'push_asunto' => Env::get('PUSH_SUBJECT', 'mailto:parqueadero@unipark.test'),
    // Solo se envían notificaciones a estos servicios de push (evita que el servidor llame URLs arbitrarias).
    'push_servicios' => [
        'fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com',
        '*.push.apple.com', '*.notify.windows.com',
    ],

    // Cron por URL para hostings sin consola: deshabilitado salvo que CRON_TOKEN tenga 32 caracteres o más.
    'cron_token' => Env::get('CRON_TOKEN', ''),
    // Importación de la base institucional (CSV). Cada campo de UniPark acepta varios nombres de columna
    // (sin tildes ni mayúsculas); ajústalos al archivo que entregue la institución sin tocar el código.
    // La importación crea y actualiza personas; nunca borra, nunca toca el saldo y no desactiva a quien
    // no venga en el archivo (eso es una decisión pendiente de la institución).
    'importacion' => [
        'columnas' => [
            'documento'     => ['documento', 'cedula', 'identificacion', 'numero_documento', 'num_documento'],
            'codigo_carnet' => ['codigo_carnet', 'carnet', 'codigo', 'codigo_estudiantil'],
            'nombres'       => ['nombres', 'nombre', 'primer_nombre'],
            'apellidos'     => ['apellidos', 'apellido'],
            'correo'        => ['correo', 'email', 'correo_institucional'],
            'telefono'      => ['telefono', 'celular'],
            'categoria'     => ['categoria', 'tipo', 'vinculo', 'vinculacion', 'tipo_vinculacion', 'tipo_de_vinculacion', 'rol'],
            'programa'      => ['programa', 'dependencia', 'facultad', 'area'],
            'activo'        => ['activo', 'estado', 'vigente'],
        ],
        // Valor del archivo (sin tildes, en mayúsculas) => categoría de UniPark.
        'categorias' => [
            'ESTUDIANTE' => 'ESTUDIANTE', 'EST' => 'ESTUDIANTE', 'ALUMNO' => 'ESTUDIANTE', 'PREGRADO' => 'ESTUDIANTE', 'POSGRADO' => 'ESTUDIANTE',
            'PROFESOR' => 'PROFESOR', 'DOCENTE' => 'PROFESOR', 'CATEDRATICO' => 'PROFESOR',
            'TRABAJADOR' => 'TRABAJADOR', 'ADMINISTRATIVO' => 'TRABAJADOR', 'EMPLEADO' => 'TRABAJADOR', 'FUNCIONARIO' => 'TRABAJADOR', 'CONTRATISTA' => 'TRABAJADOR',
        ],
        'inactivos'  => ['0', 'NO', 'N', 'INACTIVO', 'RETIRADO', 'FALSE'],
        'max_filas'  => 50000,
        'max_bytes'  => 10 * 1024 * 1024,
    ],
];
