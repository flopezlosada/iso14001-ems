<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Role;
use App\Entity\ScheduledAlert;
use App\Entity\User;
use App\Enum\AlertFrequency;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use App\Enum\VersionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the obligations cockpit: the "Qué toca" (urgency, with the Mías/Todas scope)
 * and "Estructura SGA" (PDCA) views.
 */
final class DocumentControllerTest extends WebTestCase
{
    private function persistObligation(EntityManagerInterface $em, string $code, IsoChapter $chapter, AlertFrequency $frequency, string $nextDue, ?Role $role = null, bool $inForce = false, DocumentType $type = DocumentType::FORM): Document
    {
        $document = new Document();
        $document->setCode($code)
            ->setTitle('Obligación '.$code)
            ->setType($type)
            ->setIsoChapter($chapter)
            ->setStatus(ObligationStatus::PENDING)
            ->setResponsibleRole($role);

        $alert = new ScheduledAlert();
        $alert->setFrequency($frequency)->setNextDueDate(new \DateTimeImmutable($nextDue));
        $document->addAlert($alert);

        // An approved revision makes the document "in force": only then can its periodic review be
        // marked as done (see DocumentController::complete and the cockpit macro).
        if ($inForce) {
            $version = (new DocumentVersion())
                ->setRevisionNumber(0)
                ->setStatus(VersionStatus::APPROVED)
                ->setAuthor('Aprobador de prueba')
                ->setChangeSummary('Edición inicial.');
            $document->addVersion($version);
            $em->persist($version);
        }

        if ($role !== null) {
            $em->persist($role);
        }
        $em->persist($document);

        return $document;
    }

    private function loginPlainUser(KernelBrowser $client): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Lectora')->setEmail('lectora@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
    }

    private function loginUserWithRole(KernelBrowser $client, Role $role): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($role);
        $user = new User();
        $user->setFullName('Responsable')->setEmail('responsable@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/obligaciones');

        self::assertResponseRedirects('/login');
    }

    public function testTodasScopeShowsOverdueObligation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // An overdue monthly obligation: under "todas" it must land in the "Vencido" bucket.
        $this->persistObligation($em, 'TEST-OVERDUE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01');
        $em->flush();
        $this->loginPlainUser($client);

        $client->request('GET', '/obligaciones?scope=todas');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vencido');
        self::assertSelectorTextContains('body', 'Obligación TEST-OVERDUE');
    }

    public function testMineScopeShowsOnlyOwnObligations(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        $other = (new Role())->setCode('sec')->setName('Secretaría');
        $em->persist($mine);
        $em->persist($other);
        // One obligation is mine, one belongs to another role.
        $this->persistObligation($em, 'TEST-MINE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01', $mine);
        $this->persistObligation($em, 'TEST-OTHER', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01', $other);
        $em->flush();
        $this->loginUserWithRole($client, $mine);

        // Default scope is "mías": only the owned obligation shows.
        $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Obligación TEST-MINE');
        self::assertSelectorTextNotContains('body', 'Obligación TEST-OTHER');
    }

    public function testMineScopeEmptyOffersAllWhenNothingOwned(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $other = (new Role())->setCode('sec')->setName('Secretaría');
        $em->persist($other);
        $this->persistObligation($em, 'TEST-OTHER', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01', $other);
        $em->flush();
        // The user holds a role that owns no obligation.
        $this->loginUserWithRole($client, (new Role())->setCode('mant')->setName('Mantenimiento'));

        $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state', 'No tienes obligaciones asignadas');
        self::assertSelectorTextNotContains('body', 'Obligación TEST-OTHER');
    }

    public function testDoneObligationIsListedAsDoneNotOnTrack(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        $em->persist($mine);
        // Both have a future due date (so by urgency they are "al día"); one is still pending, the
        // other is already done. The done one must surface under "Hecho", not inflate "Al día".
        $this->persistObligation($em, 'TEST-ONTRACK', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2099-01-01', $mine);
        $this->persistObligation($em, 'TEST-DONE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2099-01-01', $mine)
            ->setStatus(ObligationStatus::DONE);
        $em->flush();
        $this->loginUserWithRole($client, $mine);

        $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        // The completed one is in the "Hecho" section, not in "Al día".
        self::assertSelectorTextContains('#done', 'Obligación TEST-DONE');
        self::assertSelectorTextNotContains('#on_track', 'Obligación TEST-DONE');
        // The pending one is still in "Al día".
        self::assertSelectorTextContains('#on_track', 'Obligación TEST-ONTRACK');
        self::assertSelectorTextNotContains('#done', 'Obligación TEST-ONTRACK');
    }

    public function testStructureGroupsByPhase(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->persistObligation($em, 'TEST-PLAN', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2030-01-01');
        $em->flush();
        $this->loginPlainUser($client);

        $client->request('GET', '/sga');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Planificar');
        self::assertSelectorTextContains('body', 'Planificación');
        // The future-dated obligation is on track: the phase summary reflects it.
        self::assertSelectorTextContains('.sga-summary', 'al día');
    }

    public function testStructureSummaryFlagsOverdue(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // An overdue obligation must surface as "vencida" in its phase's completeness summary.
        $this->persistObligation($em, 'TEST-OVERDUE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01');
        $em->flush();
        $this->loginPlainUser($client);

        $client->request('GET', '/sga');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.sga-summary', 'vencida');
    }

    public function testResponsibleCompletesObligationAndRollsDueDateForward(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        // An overdue annual obligation owned by the user: completing it must push the date a year on.
        $document = $this->persistObligation($em, 'TEST-CLOSE', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2026-01-01', $mine, inForce: true);
        $em->flush();
        $id = $document->getId();
        $this->loginUserWithRole($client, $mine);

        $client->request('GET', '/obligaciones');
        $client->submitForm('Marcar revisado');

        self::assertResponseRedirects('/obligaciones');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'revisada');

        // The stored due date moved a full year on, and the completion was recorded.
        $em->clear();
        $reloaded = $em->find(Document::class, $id);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getLastCompletedOn());
        $alert = $reloaded->getAlerts()->first();
        self::assertNotFalse($alert);
        self::assertEquals(new \DateTimeImmutable('2027-01-01'), $alert->getNextDueDate());
    }

    public function testNonResponsibleCannotCompleteObligation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = (new Role())->setCode('sec')->setName('Secretaría');
        $em->persist($owner);
        $document = $this->persistObligation($em, 'TEST-CLOSE-DENY', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2026-01-01', $owner);
        $em->flush();
        // The logged-in user holds a different role than the obligation's responsible.
        $this->loginUserWithRole($client, (new Role())->setCode('mant')->setName('Mantenimiento'));

        // The voter runs before the CSRF check, so the 403 here is the authorization gate.
        $client->request('POST', '/obligaciones/'.$document->getId().'/completar');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCannotCompleteAnArchivedObligation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        $document = $this->persistObligation($em, 'TEST-ARCHIVED', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2026-01-01', $mine, inForce: true);
        $em->flush();
        $id = $document->getId();
        $this->loginUserWithRole($client, $mine);

        // Capture a valid complete form while the obligation is still active...
        $crawler = $client->request('GET', '/obligaciones');
        $form = $crawler->filter('form[action*="/'.$id.'/completar"]')->form();
        // ...then archive it and submit: a non-active document must be rejected, not rolled.
        $document->archive('Ya no aplica');
        $em->flush();
        $client->submit($form);

        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'no está activa');
        $em->clear();
        $reloaded = $em->find(Document::class, $id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getLastCompletedOn());
        $alert = $reloaded->getAlerts()->first();
        self::assertNotFalse($alert);
        self::assertEquals(new \DateTimeImmutable('2026-01-01'), $alert->getNextDueDate());
    }

    public function testEventDrivenObligationOffersNoCompleteButton(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        // A purely event-driven obligation has no period to close: the cockpit must not offer the close.
        $this->persistObligation($em, 'TEST-EVENT', IsoChapter::OPERATION, AlertFrequency::ON_EVENT, '2026-01-01', $mine);
        $em->flush();
        $this->loginUserWithRole($client, $mine);

        $crawler = $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Obligación TEST-EVENT');
        // No "Marcar revisado" form is rendered for it.
        self::assertCount(0, $crawler->filter('form[action*="/completar"]'));
    }

    public function testDraftedObligationWithoutVersionInForceOffersNoCompleteButton(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        // A DRAFTED obligation (a procedure) whose document has no approved version: you cannot have
        // reviewed a document that is not yet in force, so the cockpit must not offer "Marcar revisado".
        $this->persistObligation($em, 'TEST-NOVER', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2026-01-01', $mine, inForce: false, type: DocumentType::PROCEDURE);
        $em->flush();
        $this->loginUserWithRole($client, $mine);

        $crawler = $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Obligación TEST-NOVER');
        self::assertCount(0, $crawler->filter('form[action*="/completar"]'));
        // Instead of the button, it explains why: the document must be approved first.
        self::assertSelectorTextContains('body', 'Pendiente de aprobar');
    }

    public function testFormWithoutVersionInForceStillOffersCompleteButton(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        // A form/record is not drafted: it is reviewed by filling in its module, not by approving a
        // text, so "Marcar revisado" must be offered even without a version in force.
        $this->persistObligation($em, 'TEST-FORM-NOVER', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2026-01-01', $mine, inForce: false, type: DocumentType::FORM);
        $em->flush();
        $this->loginUserWithRole($client, $mine);

        $crawler = $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Obligación TEST-FORM-NOVER');
        self::assertCount(1, $crawler->filter('form[action*="/completar"]'));
        self::assertSelectorTextNotContains('body', 'Pendiente de aprobar');
    }

    public function testCannotCompleteDraftedDocumentWithNoVersionInForce(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        // A drafted document, in force so the valid form (with CSRF token) can be captured...
        $document = $this->persistObligation($em, 'TEST-DROP-INFORCE', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2026-01-01', $mine, inForce: true, type: DocumentType::PROCEDURE);
        $em->flush();
        $id = $document->getId();
        $this->loginUserWithRole($client, $mine);

        $crawler = $client->request('GET', '/obligaciones');
        $form = $crawler->filter('form[action*="/'.$id.'/completar"]')->form();
        // ...then drop the version out of force and submit: the backend guard must reject, not roll.
        $version = $document->getVersions()->first();
        self::assertNotFalse($version);
        $version->setStatus(VersionStatus::DRAFT);
        $em->flush();
        $client->submit($form);

        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'versión en vigor');
        $em->clear();
        $reloaded = $em->find(Document::class, $id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getLastCompletedOn());
        $alert = $reloaded->getAlerts()->first();
        self::assertNotFalse($alert);
        self::assertEquals(new \DateTimeImmutable('2026-01-01'), $alert->getNextDueDate());
    }
}
