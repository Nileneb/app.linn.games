<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

/**
 * Service for extracting text from PDF files.
 *
 * Handles PDF parsing and text extraction with robust error handling.
 */
class PdfParserService
{
    /**
     * Extract text content from a PDF file.
     *
     * @param  string  $pdfContent  The binary PDF content
     * @return string Extracted text, empty string if parsing fails
     */
    public function extractText(string $pdfContent): string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseContent($pdfContent);

            $text = trim($pdf->getText());

            if (blank($text)) {
                Log::warning('PDF text extraction returned empty string', [
                    'size' => strlen($pdfContent),
                ]);

                return '';
            }

            return $text;
        } catch (\Throwable $e) {
            Log::error('PDF parsing failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'size' => strlen($pdfContent),
            ]);

            return '';
        }
    }
}
