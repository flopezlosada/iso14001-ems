<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ManagementReview;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\ReviewSectionKey;
use App\Repository\ManagementReviewRepository;
use App\Service\FileUploader;
use App\Util\SchoolYear;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

/**
 * Functional test of the management review flow: creating a review generates the full set of
 * sections, and approving it records Direction's sign-off. DAMA rolls back writes.
 */
final class ManagementReviewControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('revision')->setName('Revisión por la dirección')->setLevel(Area::MANAGEMENT_REVIEW, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('management-review-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    private function reviews(): ManagementReviewRepository
    {
        return static::getContainer()->get(ManagementReviewRepository::class);
    }

    public function testWorkflowGuideOffersCreatingTheActaUntilThisCoursesReviewExists(): void
    {
        $client = $this->loggedInClient();
        $exercise = SchoolYear::current(new \DateTimeImmutable());

        // No review for the current course yet -> the guide's first step is pending and its CTA
        // offers to create the acta. This also renders the shared _workflow_guide partial end to end.
        // (We assert on the CTA text, not the step label "Crear el acta del curso …", which is fixed.)
        $client->request('GET', '/management-review');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Crear el acta '.$exercise, (string) $client->getResponse()->getContent());

        // Once this course's review exists, that step is done and its create CTA is gone.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new ManagementReview())->setExercise($exercise));
        $em->flush();

        $client->request('GET', '/management-review');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Crear el acta '.$exercise, (string) $client->getResponse()->getContent());
    }

    public function testCreatingReviewGeneratesEverySection(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);

        self::assertResponseRedirects();

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $all = $this->reviews()->findAllOrdered();
        self::assertCount(1, $all);
        self::assertSame('2025-2026', $all[0]->getExercise());
        self::assertCount(\count(ReviewSectionKey::cases()), $all[0]->getSections());
    }

    public function testAutoSectionsAreReadOnlyAndManualOnesEditableInEditForm(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        $client->request('GET', '/management-review/'.$id.'/edit');

        self::assertResponseIsSuccessful();
        // The data-driven sections render a disabled textarea (review only)...
        self::assertSelectorExists('textarea[disabled]');
        // ...while the decision/manual sections stay editable.
        self::assertSelectorExists('textarea:not([disabled])');
        // The six output (decision) sections each render a closed verdict dropdown.
        self::assertCount(6, $client->getCrawler()->filter('select[id$="_decision"]'));
    }

    public function testRegeneratingADraftRedirectsBackToEdit(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        $client->request('GET', '/management-review/'.$id.'/edit');
        $client->submitForm('Regenerar datos automáticos');

        self::assertResponseRedirects('/management-review/'.$id.'/edit');
    }

    public function testRegenerateRejectsAnInvalidCsrfToken(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        $client->request('POST', '/management-review/'.$id.'/regenerate', ['_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApprovingRecordsSignOff(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $review = $this->reviews()->findAllOrdered()[0];
        $id = $review->getId();
        self::assertFalse($review->isApproved());

        $client->request('GET', '/management-review/'.$id);
        $client->submitForm('Aprobar (firmar) la revisión');

        self::assertResponseRedirects('/management-review/'.$id);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertTrue($this->reviews()->findAllOrdered()[0]->isApproved());
    }

    public function testApprovedReviewCannotBeEdited(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        $client->request('GET', '/management-review/'.$id);
        $client->submitForm('Aprobar (firmar) la revisión');

        // A signed document is immutable: the edit route must redirect away without showing the form.
        $client->request('GET', '/management-review/'.$id.'/edit');
        self::assertResponseRedirects('/management-review/'.$id);
    }

    public function testApprovingSealsOfficialPdfAndHashesItsBytes(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        $client->request('GET', '/management-review/'.$id);
        $client->submitForm('Aprobar (firmar) la revisión');

        $em->clear();
        $reloaded = $em->find(ManagementReview::class, $id);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getStoragePath(), 'Approval must persist the generated PDF path.');

        $uploader = static::getContainer()->get(FileUploader::class);
        $absolute = $uploader->absolutePath((string) $reloaded->getStoragePath());
        self::assertFileExists($absolute);
        $bytes = (string) file_get_contents($absolute);
        self::assertStringStartsWith('%PDF', $bytes);
        // The integrity hash certifies the exact stored bytes (tamper-evidence over the sealed PDF).
        self::assertSame(hash('sha256', $bytes), $reloaded->getIntegrityHash());
    }

    public function testDownloadPdfPreviewsADraft(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        $client->request('GET', '/management-review/'.$id.'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());
    }

    public function testDirectionAttachesSignedPdf(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/management-review/new');
        $client->submitForm('Guardar', ['management_review[exercise]' => '2025-2026']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $id = $this->reviews()->findAllOrdered()[0]->getId();

        // Approve first: the signature can only be attached to an already-approved review.
        $client->request('GET', '/management-review/'.$id);
        $client->submitForm('Aprobar (firmar) la revisión');
        $crawler = $client->followRedirect();

        $signedPath = tempnam(sys_get_temp_dir(), 'signed').'.pdf';
        file_put_contents($signedPath, "%PDF-1.4\nfirmado por la dirección\n%%EOF");
        try {
            $form = $crawler->selectButton('Adjuntar firma')->form();
            $field = $form['signedPdf'];
            self::assertInstanceOf(FileFormField::class, $field);
            $field->upload($signedPath);
            $client->submit($form);
        } finally {
            @unlink($signedPath);
        }

        self::assertResponseRedirects('/management-review/'.$id);
        $client->followRedirect();
        // The detail now offers the signed PDF and flags the review as signed.
        self::assertSelectorExists('a:contains("PDF firmado")');

        $em->clear();
        self::assertTrue($em->find(ManagementReview::class, $id)->isDigitallySigned());
    }
}
