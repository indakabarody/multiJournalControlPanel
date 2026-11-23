<?php
/**
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

class ExportService
{
    /**
     * Export any Multi-Journal Control Panel module report in 'xlsx', 'csv', or 'pdf' format.
     * Retains active filters and stamps report generation date, time, and user.
     *
     * @param string $module 'pipeline'|'bottleneck'|'issue'|'performance'|'payments'|'links'|'testimonials'
     * @param string $format 'xlsx'|'csv'|'pdf'
     * @param array $data
     * @param array $metadata {reportTitle, siteTitle, exportedAt, activeFiltersText, exportedBy}
     * @return void
     */
    public function exportReport($module, $format, $data, $metadata)
    {
        $dateStamp = date('Ymd-His');
        $mod = strtolower(trim((string) $module));
        $fmt = strtolower(trim((string) $format));

        $metaRows = [
            [__('mjcp.export.reportTitle'), (string) $metadata['reportTitle']],
            [__('mjcp.export.publishingHouse'), (string) $metadata['siteTitle']],
            [__('mjcp.export.exportedAt'), (string) $metadata['exportedAt']],
            [__('mjcp.export.activeFilters'), (string) $metadata['activeFiltersText']],
            [__('mjcp.export.exportedBy'), (string) $metadata['exportedBy']],
            [],
        ];

        $headers = [];
        $tableRows = [];
        $colWidths = [];
        $summaryLine = '';

        switch ($mod) {
            case 'bottleneck':
                $headers = [
                    __('mjcp.table.code'),
                    __('mjcp.table.title'),
                    __('mjcp.table.journal'),
                    __('mjcp.table.stage'),
                    __('mjcp.table.stageEntry'),
                    __('mjcp.table.duration'),
                    __('mjcp.table.overdue'),
                ];
                $colWidths = [45, 225, 110, 100, 80, 105, 85];
                foreach ((array) $data as $row) {
                    $tableRows[] = [
                        (string) $row['submissionId'],
                        (string) $row['title'],
                        (string) $row['journalName'],
                        (string) $row['stageLabel'],
                        (string) $row['stageEntryFormatted'],
                        (string) $row['durationText'],
                        (string) $row['delayBadgeText'],
                    ];
                }
                break;

            case 'issue':
                $headers = [
                    __('mjcp.table.journal'),
                    __('mjcp.issue.targetIssue'),
                    __('mjcp.issue.quota'),
                    __('mjcp.issue.plotted'),
                    __('mjcp.issue.ready'),
                    __('mjcp.issue.inProgress'),
                    __('mjcp.common.status'),
                ];
                $colWidths = [140, 180, 75, 85, 85, 95, 100];
                foreach ((array) $data as $row) {
                    $tableRows[] = [
                        (string) $row['journalName'],
                        (string) $row['issueIdentification'],
                        (string) $row['quota'],
                        (string) $row['plottedCount'],
                        (string) $row['readyCount'],
                        (string) $row['inProgressCount'],
                        (string) $row['badgeLabel'],
                    ];
                }
                break;

            case 'performance':
                $headers = [
                    __('mjcp.performance.month'),
                    __('mjcp.performance.submitted'),
                    __('mjcp.performance.accepted'),
                    __('mjcp.performance.rejected'),
                    __('mjcp.performance.published'),
                ];
                $colWidths = [140, 140, 140, 140, 140];
                $trends = isset($data['monthlyTrends']) ? $data['monthlyTrends'] : [];
                foreach ($trends as $tr) {
                    $tableRows[] = [
                        (string) $tr['label'] . ' (' . $tr['ym'] . ')',
                        (string) $tr['submitted'],
                        (string) $tr['accepted'],
                        (string) $tr['rejected'],
                        (string) $tr['published'],
                    ];
                }
                $summaryLine = sprintf(
                    '%s: %d %s | %s: %d%% | %s: %d%% | %s: %d%%',
                    __('mjcp.performance.avgTurnaround'),
                    isset($data['avgSubmitToPublishDays']) ? (int) $data['avgSubmitToPublishDays'] : 0,
                    __('mjcp.common.days'),
                    __('mjcp.performance.acceptanceRate'),
                    isset($data['acceptanceRate']) ? (int) $data['acceptanceRate'] : 0,
                    __('mjcp.performance.rejectionRate'),
                    isset($data['rejectionRate']) ? (int) $data['rejectionRate'] : 0,
                    __('mjcp.performance.onTimeRate'),
                    isset($data['onTimeRate']) ? (int) $data['onTimeRate'] : 0
                );
                break;

            case 'payments':
                $headers = [
                    __('mjcp.table.code'),
                    __('mjcp.table.journal'),
                    __('mjcp.table.title'),
                    __('mjcp.payment.author'),
                    __('mjcp.payment.formStatus'),
                    __('mjcp.payment.status'),
                    __('mjcp.payment.amount'),
                    __('mjcp.payment.notes'),
                    __('mjcp.payment.proof'),
                ];
                $colWidths = [42, 92, 158, 108, 72, 62, 85, 78, 63];
                $items = isset($data['items']) ? $data['items'] : [];
                foreach ($items as $it) {
                    $tableRows[] = [
                        (string) $it['submissionId'],
                        (string) $it['journalName'],
                        (string) $it['title'],
                        (string) $it['authorName'],
                        (string) $it['formStatusLabel'],
                        (string) $it['paymentStatusLabel'],
                        (string) $it['amountFormatted'],
                        (string) $it['notes'],
                        (string) $it['proofName'],
                    ];
                }
                if (isset($data['summary'])) {
                    $summaryLine = sprintf(
                        '%s: %d | %s: %d | %s: %d | %s: %s',
                        __('mjcp.payment.card.paid'),
                        (int) $data['summary']['paidCount'],
                        __('mjcp.payment.card.unpaid'),
                        (int) $data['summary']['unpaidCount'],
                        __('mjcp.payment.card.formUnfilled'),
                        (int) $data['summary']['formUnfilledCount'],
                        __('mjcp.export.totalLabel'),
                        (string) $data['summary']['totalAmountFormatted']
                    );
                }
                break;

            case 'links':
                $headers = [
                    __('mjcp.table.code'),
                    __('mjcp.table.journal'),
                    __('mjcp.table.title'),
                    __('mjcp.payment.formStatus'),
                    __('mjcp.links.loaStatus'),
                    __('mjcp.links.publicationStatus'),
                    __('mjcp.links.url'),
                    __('mjcp.links.expiresAt'),
                ];
                $colWidths = [45, 92, 168, 72, 78, 72, 163, 70];
                $items = isset($data['items']) ? $data['items'] : [];
                foreach ($items as $it) {
                    $tableRows[] = [
                        (string) $it['submissionId'],
                        (string) $it['journalName'],
                        (string) $it['title'],
                        (string) $it['formStatusLabel'],
                        (string) $it['loaStatusLabel'],
                        (string) $it['publicationStatusLabel'],
                        (string) ($it['fullLinkUrl'] !== '' ? $it['fullLinkUrl'] : $it['shortLinkText']),
                        (string) $it['expiresAtFormatted'],
                    ];
                }
                break;

            case 'testimonials':
                $headers = [
                    __('mjcp.testimonial.text'),
                    __('mjcp.testimonial.authorName'),
                    __('mjcp.testimonial.institution'),
                    __('mjcp.table.journal'),
                    __('mjcp.testimonial.consentStatus'),
                    __('mjcp.testimonial.submittedAt'),
                ];
                $colWidths = [260, 118, 127, 98, 82, 75];
                foreach ((array) $data as $row) {
                    $tableRows[] = [
                        (string) $row['testimonialText'],
                        (string) $row['authorName'],
                        (string) $row['institution'],
                        (string) $row['journalName'],
                        (string) $row['statusLabel'],
                        (string) $row['submittedAtFormatted'],
                    ];
                }
                break;

            case 'pipeline':
            default:
                $headers = [
                    __('mjcp.table.journal'),
                    __('mjcp.pipeline.allArticle'),
                    __('mjcp.pipeline.submission'),
                    __('mjcp.pipeline.review'),
                    __('mjcp.pipeline.revision'),
                    __('mjcp.pipeline.copyediting'),
                    __('mjcp.pipeline.production'),
                    __('mjcp.pipeline.published'),
                    __('mjcp.pipeline.declined'),
                ];
                $colWidths = [155, 74, 74, 74, 74, 80, 76, 74, 74];
                $rows = isset($data['pipeline']) ? $data['pipeline'] : (array) $data;
                foreach ($rows as $r) {
                    $tableRows[] = [
                        (string) $r['journalName'],
                        (string) $r['allArticle'],
                        (string) $r['submission'],
                        (string) $r['review'],
                        (string) $r['revision'],
                        (string) $r['copyediting'],
                        (string) $r['production'],
                        (string) $r['published'],
                        (string) (isset($r['declined']) ? $r['declined'] : 0),
                    ];
                }
                if (isset($data['summary'])) {
                    $summaryLine = sprintf(
                        '%s: %d | %s: %d | %s: %d | %s: %d | %s: %d',
                        __('mjcp.dashboard.totalArticles'),
                        (int) $data['summary']['totalArticles'],
                        __('mjcp.dashboard.activeSubmissions'),
                        (int) $data['summary']['activeSubmissions'],
                        __('mjcp.dashboard.overdueSubmissions'),
                        (int) $data['summary']['overdueSubmissions'],
                        __('mjcp.dashboard.readyToPublish'),
                        (int) $data['summary']['readyToPublish'],
                        __('mjcp.dashboard.issuesBelowQuota'),
                        (int) $data['summary']['issuesBelowQuota']
                    );
                }
                break;
        }

        $allSheetRows = array_merge($metaRows, [$headers], $tableRows);
        $baseName = 'mjcp-' . $mod . '-' . $dateStamp;

        if ($fmt === 'csv') {
            if ($mod === 'payments' && !empty($data['recapByIssue'])) {
                $allSheetRows[] = [];
                $allSheetRows[] = [__('mjcp.payment.recapByIssueHeading')];
                $allSheetRows[] = [__('mjcp.issue.targetIssue'), __('mjcp.payment.card.paid'), __('mjcp.payment.card.unpaid'), __('mjcp.payment.amount')];
                foreach ($data['recapByIssue'] as $irec) {
                    $allSheetRows[] = [(string) $irec['issueLabel'], (string) $irec['paidCount'], (string) $irec['unpaidCount'], (string) $irec['totalAmountFormatted']];
                }
            }
            $this->_sendCsvDownload($baseName . '.csv', $allSheetRows);
        } elseif ($fmt === 'pdf') {
            $this->_sendPdfDownload($baseName . '.pdf', $metadata, $summaryLine, $headers, $tableRows, $colWidths);
        } else {
            $sheets = [
                [
                    'name' => ucfirst($mod),
                    'rows' => $allSheetRows,
                    'headerRowIndex' => 6,
                ],
            ];
            if ($mod === 'payments' && !empty($data['recapByIssue'])) {
                $issueRecapRows = array_merge(
                    $metaRows,
                    [[__('mjcp.issue.targetIssue'), __('mjcp.payment.card.paid'), __('mjcp.payment.card.unpaid'), __('mjcp.payment.amount')]],
                    array_map(function ($irec) {
                        return [(string) $irec['issueLabel'], (string) $irec['paidCount'], (string) $irec['unpaidCount'], (string) $irec['totalAmountFormatted']];
                    }, $data['recapByIssue'])
                );
                $sheets[] = [
                    'name' => __('mjcp.export.sheet.recapByIssue'),
                    'rows' => $issueRecapRows,
                    'headerRowIndex' => 6,
                ];
            }
            $this->_sendXlsxDownload($baseName . '.xlsx', $sheets);
        }
    }

    /**
     * Output UTF-8 CSV with BOM.
     *
     * @param string $filename
     * @param array $rows
     * @return void
     */
    protected function _sendCsvDownload($filename, $rows)
    {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $safeRow = array_map([$this, '_sanitizeSpreadsheetCell'], (array) $row);
            fputcsv($out, $safeRow);
        }
        fclose($out);
        exit;
    }

    /**
     * Generate and stream OpenXML (.xlsx) file via ZipArchive.
     *
     * @param string $filename
     * @param array $sheets
     * @return void
     */
    protected function _sendXlsxDownload($filename, $sheets)
    {
        if (!class_exists('ZipArchive')) {
            $firstRows = !empty($sheets[0]['rows']) ? $sheets[0]['rows'] : [];
            $this->_sendCsvDownload(preg_replace('/\.xlsx$/i', '.csv', $filename), $firstRows);
            return;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'mjcp_xlsx_');
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
            $firstRows = !empty($sheets[0]['rows']) ? $sheets[0]['rows'] : [];
            $this->_sendCsvDownload(preg_replace('/\.xlsx$/i', '.csv', $filename), $firstRows);
            return;
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach ($sheets as $idx => $sh) {
            $sheetNum = $idx + 1;
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $sheetNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>';
        $wbRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($sheets as $idx => $sh) {
            $sheetNum = $idx + 1;
            $safeName = substr(preg_replace('/[\[\]\*\/\\\\\?\:]+/', ' ', (string) $sh['name']), 0, 31);
            if ($safeName === '') {
                $safeName = 'Sheet' . $sheetNum;
            }
            $workbookXml .= '<sheet name="' . $this->_xmlEscape($safeName) . '" sheetId="' . $sheetNum . '" r:id="rId' . $sheetNum . '"/>';
            $wbRelsXml .= '<Relationship Id="rId' . $sheetNum . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';
        }
        $styleRelId = count($sheets) + 1;
        $workbookXml .= '</sheets></workbook>';
        $wbRelsXml .= '<Relationship Id="rId' . $styleRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRelsXml);

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0E6F64"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFD1D5DB"/></left>'
            . '<right style="thin"><color rgb="FFD1D5DB"/></right>'
            . '<top style="thin"><color rgb="FFD1D5DB"/></top>'
            . '<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $stylesXml);

        foreach ($sheets as $idx => $sh) {
            $sheetNum = $idx + 1;
            $headerIdx = isset($sh['headerRowIndex']) ? (int) $sh['headerRowIndex'] : 6;
            $wsXml = $this->_buildWorksheetXml($sh['rows'], $headerIdx);
            $zip->addFromString('xl/worksheets/sheet' . $sheetNum . '.xml', $wsXml);
        }

        $zip->close();

        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . filesize($tmpFile));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    /**
     * Build OpenXML worksheet XML.
     *
     * @param array $rows
     * @param int $headerRowIndex
     * @return string
     */
    protected function _buildWorksheetXml($rows, $headerRowIndex = 6)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>'
            . '<col min="1" max="1" width="18" customWidth="1"/>'
            . '<col min="2" max="4" width="32" customWidth="1"/>'
            . '<col min="5" max="12" width="18" customWidth="1"/>'
            . '</cols>'
            . '<sheetData>';

        foreach ($rows as $rIdx => $row) {
            $excelRowNum = $rIdx + 1;
            $xml .= '<row r="' . $excelRowNum . '">';
            foreach (array_values($row) as $cIdx => $cellVal) {
                $colRef = $this->_colIndexToLetter($cIdx) . $excelRowNum;
                $styleId = 0;
                if ($rIdx < $headerRowIndex && $cIdx === 0) {
                    $styleId = 1;
                } elseif ($rIdx === $headerRowIndex) {
                    $styleId = 2;
                } elseif ($rIdx > $headerRowIndex) {
                    $styleId = 3;
                }

                $valStr = (string) $cellVal;
                if (ctype_digit($valStr) && strlen($valStr) < 10) {
                    $xml .= '<c r="' . $colRef . '" s="' . $styleId . '" t="n"><v>' . $valStr . '</v></c>';
                } else {
                    $xml .= '<c r="' . $colRef . '" s="' . $styleId . '" t="inlineStr"><is><t xml:space="preserve">'
                        . $this->_xmlEscape($this->_sanitizeSpreadsheetCell($valStr))
                        . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    /**
     * Generate standalone PDF 1.4 Landscape A4 report.
     *
     * @param string $filename
     * @param array $metadata
     * @param string $summaryLine
     * @param array $headers
     * @param array $rows
     * @param array $colWidths
     * @return void
     */
    protected function _sendPdfDownload($filename, $metadata, $summaryLine, $headers, $rows, $colWidths)
    {
        $pdfBytes = $this->_generatePdfDocument($metadata, $summaryLine, $headers, $rows, $colWidths);
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . strlen($pdfBytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        echo $pdfBytes;
        exit;
    }

    /**
     * Build PDF 1.4 binary string.
     *
     * @param array $metadata
     * @param string $summaryLine
     * @param array $headers
     * @param array $rows
     * @param array $colWidths
     * @return string
     */
    protected function _generatePdfDocument($metadata, $summaryLine, $headers, $rows, $colWidths)
    {
        $pageWidth = 842;
        $pageHeight = 595;
        $marginLeft = 36;
        $rowHeight = 18;
        $rowsFirstPage = 19;
        $rowsNextPage = 25;

        $pages = [];
        if (empty($rows)) {
            $pages[] = [];
        } else {
            $pages[] = array_slice($rows, 0, $rowsFirstPage);
            $rem = array_slice($rows, $rowsFirstPage);
            while (!empty($rem)) {
                $pages[] = array_slice($rem, 0, $rowsNextPage);
                $rem = array_slice($rem, $rowsNextPage);
            }
        }

        $pageStreams = [];
        $totalPages = count($pages);

        foreach ($pages as $pIdx => $pageRows) {
            $ops = [];
            $y = $pageHeight - 38;

            $ops[] = "0.05 0.44 0.39 rg";
            $ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - 24, $pageWidth - ($marginLeft * 2), 30);
            $ops[] = "1 1 1 rg";
            $ops[] = $this->_pdfText($marginLeft + 10, $y - 13, $metadata['reportTitle'] . ' - ' . $metadata['siteTitle'], 'F2', 12);
            $ops[] = $this->_pdfText($pageWidth - 130, $y - 13, __('mjcp.export.pageOf', ['current' => $pIdx + 1, 'total' => $totalPages]), 'F1', 9);
            $y -= 40;

            if ($pIdx === 0) {
                $ops[] = "0.96 0.97 0.96 rg";
                $ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - 46, $pageWidth - ($marginLeft * 2), 50);
                $ops[] = "0.15 0.18 0.22 rg";
                $ops[] = $this->_pdfText($marginLeft + 8, $y - 12, __('mjcp.export.exportedAt') . ': ' . $metadata['exportedAt'], 'F1', 8.5);
                $ops[] = $this->_pdfText($marginLeft + 290, $y - 12, __('mjcp.export.exportedBy') . ': ' . $metadata['exportedBy'], 'F1', 8.5);
                $ops[] = $this->_pdfText($marginLeft + 8, $y - 26, __('mjcp.export.activeFilters') . ': ' . $metadata['activeFiltersText'], 'F1', 8.5);
                if ($summaryLine !== '') {
                    $ops[] = $this->_pdfText($marginLeft + 8, $y - 40, $summaryLine, 'F2', 8.5);
                }
                $y -= 58;
            }

            $tableWidth = array_sum($colWidths);
            $ops[] = "0.09 0.35 0.31 rg";
            $ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - $rowHeight, $tableWidth, $rowHeight);
            $ops[] = "1 1 1 rg";
            $cx = $marginLeft;
            foreach ($headers as $hIdx => $hText) {
                $ops[] = $this->_pdfText($cx + 4, $y - 12, $hText, 'F2', 8);
                $cx += isset($colWidths[$hIdx]) ? $colWidths[$hIdx] : 90;
            }
            $y -= $rowHeight;

            if (empty($pageRows)) {
                $ops[] = "0.35 0.38 0.42 rg";
                $ops[] = $this->_pdfText($marginLeft + 8, $y - 14, __('mjcp.common.noData'), 'F1', 9);
            } else {
                foreach ($pageRows as $rIdx => $rCells) {
                    if ($rIdx % 2 === 1) {
                        $ops[] = "0.97 0.98 0.97 rg";
                        $ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - $rowHeight, $tableWidth, $rowHeight);
                    }
                    $ops[] = "0.86 0.88 0.86 RG 0.5 w";
                    $ops[] = sprintf("%d %d m %d %d l S", $marginLeft, $y - $rowHeight, $marginLeft + $tableWidth, $y - $rowHeight);
                    $ops[] = "0.12 0.14 0.17 rg";
                    $cx = $marginLeft;
                    foreach ($rCells as $cIdx => $cText) {
                        $w = isset($colWidths[$cIdx]) ? $colWidths[$cIdx] : 90;
                        $maxChars = max(8, (int) floor($w / 4.2));
                        $ops[] = $this->_pdfText($cx + 4, $y - 12, $this->_truncate((string) $cText, $maxChars), 'F1', 7.8);
                        $cx += $w;
                    }
                    $y -= $rowHeight;
                }
            }

            $ops[] = "0.45 0.48 0.52 rg";
            $ops[] = $this->_pdfText($marginLeft, 20, __('plugins.generic.multiJournalControlPanel.displayName') . ' - OJS 3.3.x | Copyright (c) 2026 Indaka Barody', 'F1', 7.5);
            $pageStreams[] = implode("\n", $ops);
        }

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $kids = [];
        $nextObjId = 5;
        foreach ($pageStreams as $streamData) {
            $pageObjId = $nextObjId++;
            $contentObjId = $nextObjId++;
            $kids[] = $pageObjId . " 0 R";
            $objects[$pageObjId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $pageWidth,
                $pageHeight,
                $contentObjId
            );
            $objects[$contentObjId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($streamData), $streamData);
        }
        $objects[2] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), count($kids));
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObj = count($objects) + 1;
        $pdf .= "xref\n0 " . $totalObj . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $totalObj; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . $totalObj . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
        return $pdf;
    }

    /**
     * Format PDF text drawing command.
     *
     * @param float $x
     * @param float $y
     * @param string $text
     * @param string $fontRef
     * @param float $size
     * @return string
     */
    protected function _pdfText($x, $y, $text, $fontRef = 'F1', $size = 9)
    {
        $clean = str_replace(['—', '–', '·', '●', '▲', '■', '◆'], ['-', '-', '-', '*', '!', '#', '?'], (string) $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $clean);
            if ($converted !== false) {
                $clean = $converted;
            }
        }
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $clean);
        return sprintf("BT /%s %.1f Tf %.1f %.1f Td (%s) Tj ET", $fontRef, $size, $x, $y, $escaped);
    }

    /**
     * Convert 0-based column index to Excel letter.
     *
     * @param int $index
     * @return string
     */
    protected function _colIndexToLetter($index)
    {
        $n = (int) $index;
        $letter = '';
        while ($n >= 0) {
            $letter = chr(($n % 26) + 65) . $letter;
            $n = (int) floor($n / 26) - 1;
        }
        return $letter;
    }

    /**
     * Neutralize spreadsheet formula injection payloads in CSV/XLSX cells.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitizeSpreadsheetCell($value)
    {
        return $this->_sanitizeSpreadsheetCell($value);
    }

    /**
     * Prefix potentially dangerous leading formula characters (`=`, `+`, `-`, `@`, tab, CR) with a single quote.
     *
     * @param mixed $value
     * @return string
     */
    protected function _sanitizeSpreadsheetCell($value)
    {
        $str = (string) $value;
        if ($str === '' || is_numeric($str)) {
            return $str;
        }
        $first = $str[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r") {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Escape XML string.
     *
     * @param string $str
     * @return string
     */
    protected function _xmlEscape($str)
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $str);
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Truncate text.
     *
     * @param string $str
     * @param int $maxLen
     * @return string
     */
    protected function _truncate($str, $maxLen = 40)
    {
        $s = trim((string) $str);
        if (strlen($s) <= $maxLen) {
            return $s;
        }
        return substr($s, 0, $maxLen - 3) . '...';
    }
}
