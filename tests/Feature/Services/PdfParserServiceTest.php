<?php

use App\Services\PdfParserService;
use Illuminate\Support\Facades\Log;

test('extractText returns empty string on parsing failure', function () {
    Log::spy();
    
    $service = app(PdfParserService::class);
    $text = $service->extractText('not a valid pdf');

    expect($text)->toBe('');

    Log::shouldHaveReceived('error')
        ->withArgs(function ($message, $context) {
            return $message === 'PDF parsing failed'
                && isset($context['exception'])
                && isset($context['message']);
        });
});

test('extractText logs error with size information when parsing fails', function () {
    Log::spy();
    
    $invalidPdf = 'This is not a PDF file at all';
    $expectedSize = strlen($invalidPdf);
    
    $service = app(PdfParserService::class);
    $service->extractText($invalidPdf);

    Log::shouldHaveReceived('error')
        ->withArgs(function ($message, $context) use ($expectedSize) {
            return $message === 'PDF parsing failed'
                && $context['size'] === $expectedSize;
        });
});

test('extractText is defensive against empty PDF response', function () {
    // Test that service handles edge case gracefully
    $service = app(PdfParserService::class);
    $text = $service->extractText('');

    expect($text)->toBe('');
});

test('extractText is defensive against null-like content', function () {
    // Test with very short content
    $service = app(PdfParserService::class);
    $text = $service->extractText('x');

    expect($text)->toBeString();
});

