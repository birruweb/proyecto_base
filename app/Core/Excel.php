<?php
declare(strict_types=1);

namespace App\Core;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Atajos para PhpSpreadsheet (vendor/phpoffice/phpspreadsheet).
 *
 *   // Descargar un listado como .xlsx (en un controlador)
 *   Excel::descargar('productos', [
 *       'nombre' => 'Nombre',
 *       'precio' => 'Precio',
 *   ], $this->productos->todos('nombre'));
 *
 *   // Leer un Excel subido: cada fila como ['Encabezado' => valor, ...]
 *   $filas = Excel::leer($_FILES['archivo']['tmp_name']);
 *
 * Para algo más elaborado (varias hojas, estilos, fórmulas) usa PhpSpreadsheet
 * directamente: la documentación está en https://phpspreadsheet.readthedocs.io
 */
final class Excel
{
    /**
     * Arma una hoja con encabezados en negritas, filtros, la primera fila fija
     * y columnas con ancho automático.
     *
     * @param array $columnas ['clave_en_la_fila' => 'Encabezado', ...] (define el orden)
     * @param array $filas    arreglos asociativos, como los que regresan los modelos
     */
    public static function crear(array $columnas, array $filas, string $hoja = 'Hoja1'): Spreadsheet
    {
        $libro = new Spreadsheet();
        $sheet = $libro->getActiveSheet();
        $sheet->setTitle(mb_substr($hoja, 0, 31));   // límite de Excel

        $claves = array_keys($columnas);
        $ultima = Coordinate::stringFromColumnIndex(max(1, count($claves)));

        foreach (array_values($columnas) as $i => $encabezado) {
            $sheet->setCellValueExplicit([$i + 1, 1], (string) $encabezado, DataType::TYPE_STRING);
        }

        foreach (array_values($filas) as $r => $fila) {
            foreach ($claves as $c => $clave) {
                self::escribir($sheet, $c + 1, $r + 2, $fila[$clave] ?? null);
            }
        }

        $sheet->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $sheet->setAutoFilter("A1:$ultima" . (count($filas) + 1));
        $sheet->freezePane('A2');
        foreach (range(1, count($claves)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        return $libro;
    }

    /** Envía el .xlsx al navegador como descarga y termina la petición */
    public static function descargar(string $nombre, array $columnas, array $filas, string $hoja = 'Hoja1'): never
    {
        $libro = self::crear($columnas, $filas, $hoja);

        // Nombre seguro para el archivo: productos-2026-09-26.xlsx
        $nombre = trim((string) preg_replace('/[^A-Za-z0-9_\-]+/', '-', $nombre), '-') ?: 'reporte';
        $nombre .= '-' . date('Y-m-d') . '.xlsx';

        while (ob_get_level() > 0) {
            ob_end_clean();   // cualquier salida previa corrompería el archivo
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: max-age=0');

        (new Xlsx($libro))->save('php://output');
        exit;
    }

    /**
     * Lee la primera hoja de un .xlsx, .xls o .csv.
     * Con $encabezados = true (por defecto) la primera fila da las claves:
     *   [['Nombre' => 'Teclado', 'Precio' => 1299], ...]
     * Con false regresa las filas tal cual: [['Nombre', 'Precio'], ['Teclado', 1299], ...]
     * Las filas completamente vacías se omiten.
     */
    public static function leer(string $archivo, bool $encabezados = true): array
    {
        $libro = IOFactory::load($archivo);
        $filas = $libro->getActiveSheet()->toArray(null, true, false, false);

        $filas = array_values(array_filter(
            $filas,
            fn($fila) => array_filter($fila, fn($v) => $v !== null && $v !== '') !== []
        ));
        if (!$encabezados || $filas === []) {
            return $filas;
        }

        $claves = array_map(fn($v) => trim((string) $v), array_shift($filas));
        return array_map(
            fn($fila) => array_combine($claves, array_pad(array_slice($fila, 0, count($claves)), count($claves), null)),
            $filas
        );
    }

    /**
     * Escribe una celda con su tipo explícito. Los textos nunca se interpretan
     * como fórmula: "=HYPERLINK(...)" escrito por un usuario queda como texto.
     */
    private static function escribir($sheet, int $columna, int $fila, mixed $valor): void
    {
        if ($valor === null || $valor === '') {
            return;
        }
        // Números reales (no códigos con ceros a la izquierda como "00123")
        if (is_int($valor) || is_float($valor)
            || (is_string($valor) && preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $valor))) {
            $sheet->setCellValueExplicit([$columna, $fila], $valor + 0, DataType::TYPE_NUMERIC);
            return;
        }
        $sheet->setCellValueExplicit([$columna, $fila], (string) $valor, DataType::TYPE_STRING);
    }
}
