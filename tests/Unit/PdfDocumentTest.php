<?php

namespace Tests\Unit;

use App\Support\PdfDocument;
use Tests\TestCase;

class PdfDocumentTest extends TestCase
{
    public function test_filter_lines_are_human_readable_without_json(): void
    {
        $lines = PdfDocument::filterLines([
            'sort_by' => 'reference',
            'sort_dir' => 'desc',
            'search' => 'CISSOKHO',
            'page' => 1,
            'per_page' => 20,
        ]);

        $joined = implode("\n", $lines);

        $this->assertStringContainsString('Tri : Référence (décroissant)', $joined);
        $this->assertStringContainsString('Recherche : CISSOKHO', $joined);
        $this->assertStringNotContainsString('sort_by', $joined);
        $this->assertStringNotContainsString('{', $joined);
        $this->assertStringNotContainsString('json', strtolower($joined));
    }

    public function test_empty_filters_produce_no_lines(): void
    {
        $this->assertSame([], PdfDocument::filterLines([
            'sort_by' => '',
            'search' => null,
            'page' => 2,
        ]));
    }

    public function test_dossiers_export_html_has_no_raw_json_filters(): void
    {
        $html = view('exports.dossiers-list', [
            'company' => null,
            'dossiers' => collect(),
            'generatedAt' => '25 septembre 2026 15:28',
            'filtres' => [
                'sort_by' => 'reference',
                'sort_dir' => 'desc',
            ],
        ])->render();

        $this->assertStringNotContainsString('{"sort_by"', $html);
        $this->assertStringNotContainsString('json_encode', $html);
        $this->assertStringContainsString('Tri : Référence (décroissant)', $html);
        $this->assertStringContainsString('BS Consulting', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Liste des dossiers', $html);
        $this->assertStringNotContainsString('pdf-wordmark', $html);
    }

    public function test_official_logo_is_the_central_png(): void
    {
        $path = PdfDocument::officialLogoPath();
        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertSame('png', strtolower((string) pathinfo($path, PATHINFO_EXTENSION)));
        $uri = PdfDocument::logoDataUri();
        $this->assertIsString($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_invoice_view_uses_the_same_official_logo(): void
    {
        $invoice = new \App\Models\Invoice;
        $invoice->forceFill([
            'numero' => 'TEST-001',
            'montant_ttc' => 100000,
            'currency' => 'XOF',
            'date_emission' => now(),
            'created_at' => now(),
            'notes' => null,
        ]);
        $invoice->setRelation('client', null);

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'company' => null,
        ])->render();

        $this->assertStringStartsWith('data:image/png;base64,', PdfDocument::logoDataUri() ?? '');
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('BS</span><span', $html);
    }
}
