<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DocumentVersion;
use App\Enum\VersionStatus;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Imports the real body of drafted documents (policy, manual, procedures) from the HTML produced by
 * pandoc out of the centre's .docx files. The HTML lives gitignored under fixtures/real/documents/
 * (it carries the centre's name); each file is named by document code (e.g. PA.01.0.html).
 *
 * Idempotent: re-running re-imports the (sanitised) body onto the document's initial revision, so
 * the cutover can be replayed safely. The body is sanitised with the same allowlist as the editor.
 */
#[AsCommand(name: 'app:import-documents', description: 'Importa el cuerpo real de los documentos redactados desde fixtures/real/documents/*.html')]
final class ImportDocumentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentRepository $documents,
        #[Target('app.document_body')]
        private readonly HtmlSanitizerInterface $htmlSanitizer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->projectDir.'/fixtures/real/documents';

        $files = glob($dir.'/*.html') ?: [];
        if ([] === $files) {
            $io->warning(sprintf('No hay ficheros HTML en %s. Conviértelos antes con pandoc.', $dir));

            return Command::SUCCESS;
        }

        $imported = 0;
        $missing = [];
        foreach ($files as $file) {
            $code = basename($file, '.html');
            $document = $this->documents->findOneBy(['code' => $code]);
            if (null === $document) {
                $missing[] = $code;

                continue;
            }

            $body = $this->htmlSanitizer->sanitize((string) file_get_contents($file));

            // Import onto the initial revision (lowest number) — the document's baseline. Create it
            // if the document has no revision yet, so the import also bootstraps brand-new documents.
            $target = null;
            foreach ($document->getVersions() as $version) {
                if (null === $target || $version->getRevisionNumber() < $target->getRevisionNumber()) {
                    $target = $version;
                }
            }
            if (null === $target) {
                $target = (new DocumentVersion())
                    ->setRevisionNumber(0)
                    ->setStatus(VersionStatus::DRAFT)
                    ->setAuthor('Importación')
                    ->setChangeSummary('Importación inicial del documento.');
                $document->addVersion($target);
                $this->em->persist($target);
            }
            $target->setBody($body);
            ++$imported;
        }

        $this->em->flush();

        $io->success(sprintf('Importado el cuerpo de %d documento(s).', $imported));
        if ([] !== $missing) {
            $io->note(sprintf('Sin documento en el catálogo (omitidos): %s', implode(', ', $missing)));
        }

        return Command::SUCCESS;
    }
}
