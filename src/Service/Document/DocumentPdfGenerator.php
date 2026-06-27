<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Renders the official control sheet of a {@see DocumentVersion} as a PDF, following PC.01.0:
 * cover (name, version, date), control page (three tables: identification, authorship/review/
 * approval, change history) and a per-page footer with code, version and "x of y".
 *
 * This is the auditable artefact of clause 7.5. It deliberately renders only the control metadata,
 * not the document body: the body is the uploaded source file, which the standard does not require
 * to live inside the app. The PDF intentionally does NOT embed its own integrity hash, so the bytes
 * can be hashed without circularity (the hash is recorded on the {@see App\Entity\ApprovalEvent}).
 *
 * dompdf was chosen over mpdf/wkhtmltopdf: it is pure PHP (no external binary, which the shared
 * hosting forbids) and ships fewer files (the hosting's hard limit is inodes, not disk).
 */
final class DocumentPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $organizationName,
    ) {
    }

    /**
     * Renders the control sheet of a revision to PDF bytes.
     *
     * @param Document        $document the document the revision belongs to
     * @param DocumentVersion $version  the revision to render
     *
     * @return string the raw PDF bytes
     */
    public function render(Document $document, DocumentVersion $version): string
    {
        $versions = $document->getVersions()->toArray();
        usort(
            $versions,
            static fn (DocumentVersion $a, DocumentVersion $b): int => $a->getRevisionNumber() <=> $b->getRevisionNumber(),
        );

        $html = $this->twig->render('document/pdf/control_sheet.html.twig', [
            'organizationName' => $this->organizationName,
            'document' => $document,
            'version' => $version,
            'approval' => $version->getLatestApproval(),
            'history' => $versions,
        ]);

        $options = new Options();
        // DejaVu Sans ships with dompdf and covers Spanish diacritics without extra font files.
        $options->set('defaultFont', 'DejaVu Sans');
        // No document references remote assets; keep it off to avoid SSRF and slow renders.
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
