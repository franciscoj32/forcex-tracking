<?php

namespace App\Support\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genera un .xlsx real (no HTML renombrado) con encabezado de empresa,
 * filtros aplicados, tabla con autofiltro, columnas con formato y totales.
 *
 * Columnas: ['key' => ['label' => 'Venta USD', 'format' => 'money'|'number'|'date'|'percent'|'text', 'total' => true]]
 */
final class XlsxExport
{
    private const FORMATS = [
        'money' => '#,##0.00',
        'number' => '#,##0.##',
        'integer' => '#,##0',
        'date' => 'dd/mm/yyyy',
        'percent' => '0.0"%"',
    ];

    private Spreadsheet $spreadsheet;

    public function __construct(private readonly string $title)
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getProperties()->setTitle($title)->setCreator(\App\Models\AppSetting::current()->company_name);
        $this->spreadsheet->removeSheetByIndex(0);
    }

    /**
     * Agrega una hoja.
     *
     * @param  array<string, array{label: string, format?: string, total?: bool, width?: int}>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $filters  etiqueta => valor (se muestran arriba)
     */
    public function sheet(string $name, array $columns, iterable $rows, array $filters = []): self
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle(mb_substr($name, 0, 31));

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $row = 1;

        // Titulo y filtros
        $sheet->setCellValue("A{$row}", $this->title.' · '.$name);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;
        $sheet->setCellValue("A{$row}", 'Generado el '.now()->format('d/m/Y H:i'));
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('64748B');
        $row++;

        foreach ($filters as $label => $value) {
            $sheet->setCellValue("A{$row}", $label.': '.$value);
            $row++;
        }

        $row++;
        $headerRow = $row;

        // Encabezados
        $index = 1;
        foreach ($columns as $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index++).$headerRow, $column['label']);
        }

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim(\App\Models\AppSetting::current()->color_primario ?: '#1E40AF', '#')]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        // Filas
        $row = $headerRow + 1;
        $firstDataRow = $row;

        foreach ($rows as $data) {
            $index = 1;

            foreach (array_keys($columns) as $key) {
                $value = $data[$key] ?? null;
                $cell = Coordinate::stringFromColumnIndex($index++).$row;

                if ($value instanceof \DateTimeInterface) {
                    $sheet->setCellValue($cell, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($value));
                } else {
                    $sheet->setCellValueExplicit($cell, $value, is_numeric($value) && ! is_string($value)
                        ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }

            $row++;
        }

        $lastDataRow = max($row - 1, $firstDataRow);

        // Formatos por columna y totales
        $hasTotals = false;
        $index = 1;
        foreach ($columns as $column) {
            $letter = Coordinate::stringFromColumnIndex($index++);
            $format = self::FORMATS[$column['format'] ?? 'text'] ?? null;

            if ($format) {
                $sheet->getStyle("{$letter}{$firstDataRow}:{$letter}".($lastDataRow + 1))
                    ->getNumberFormat()->setFormatCode($format);
            }

            if (! empty($column['total']) && $row > $firstDataRow) {
                $sheet->setCellValue("{$letter}{$row}", "=SUBTOTAL(109,{$letter}{$firstDataRow}:{$letter}{$lastDataRow})");
                $hasTotals = true;
            }

            $sheet->getColumnDimension($letter)->setWidth($column['width'] ?? 16);
        }

        if ($hasTotals) {
            $sheet->setCellValue("A{$row}", 'Total');
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        if ($row > $firstDataRow) {
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastDataRow}");
        }

        $sheet->freezePane('A'.($headerRow + 1));

        return $this;
    }

    public function download(string $filename): StreamedResponse
    {
        $this->spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($this->spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
