<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Document;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\DocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the online help. They run against the real help/ catalogue shipped in the
 * repo (the "aspectos-ambientales" pilot topic), so they also guard that the pilot content stays
 * loadable and rendered end to end: index, full page, popover fragment, the 404, the SGA-document
 * deep link, and the contextual "?" button appearing on the module the topic covers.
 */
final class HelpControllerTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persistUser(User $user, ?Role $role = null): void
    {
        $em = $this->em();
        if (null !== $role) {
            $em->persist($role);
            $user->addAssignedRole($role);
        }
        $em->persist($user);
        $em->flush();
    }

    private function user(string $email, bool $admin = false): User
    {
        $role = (new Role())->setCode($admin ? 'admin' : 'user')->setName($admin ? 'Administrador' : 'Usuario');
        if ($admin) {
            $role->setAdmin(true);
        }
        $user = (new User())->setFullName('Persona Prueba')->setEmail($email)->setActive(true);
        $this->persistUser($user, $role);

        return $user;
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ayuda');

        self::assertResponseRedirects('/login');
    }

    public function testIndexListsTheTopics(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user('help-index@example.test'));

        $client->request('GET', '/ayuda');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.help-topic-list', 'Aspectos ambientales');
    }

    public function testShowRendersBodyLegalAndOfficialSourceLink(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user('help-show@example.test'));

        $client->request('GET', '/ayuda/aspectos-ambientales');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.help-body');
        self::assertSelectorTextContains('.help-legal', 'ISO 14001');
        self::assertSelectorExists('.help-legal a[href="https://www.iso.org/standard/60857.html"]');
    }

    public function testUnknownSlugReturns404(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user('help-404@example.test'));

        $client->request('GET', '/ayuda/no-existe');

        self::assertResponseStatusCodeSame(404);
    }

    public function testPanelReturnsFragmentWithoutTheShell(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user('help-panel@example.test'));

        $client->request('GET', '/ayuda/aspectos-ambientales/panel');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.help-panel__summary');
        self::assertSelectorTextContains('.help-panel__more', 'explicación completa');
        // A fragment, not a full page: the application shell must not be rendered.
        self::assertSelectorNotExists('.sidebar');
    }

    public function testShowDeepLinksAnExistingSgaDocument(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user('help-doc@example.test'));

        // The pilot topic references PG-06.01; when that document exists, the page links to it.
        $document = (new Document())->setTitle('Procedimiento de aspectos')->setType(DocumentType::PROCEDURE)->setCode('PG-06.01');
        $this->em()->persist($document);
        $this->em()->flush();

        $client->request('GET', '/ayuda/aspectos-ambientales');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.help-docs a.cell-link');
        self::assertSelectorTextContains('.help-docs', 'Procedimiento de aspectos');
    }

    public function testShowDoesNotLinkANonActiveDocument(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user('help-doc-cancelled@example.test'));

        // A document carrying a referenced code but no longer in force (codes get reused in real data):
        // it must be shown as plain text, never deep-linked, so the help never points at an obsolete doc.
        $document = (new Document())->setTitle('Procedimiento antiguo')->setType(DocumentType::PROCEDURE)->setCode('RG-06.01.01');
        $document->cancel('obsoleto');
        $this->em()->persist($document);
        $this->em()->flush();

        $client->request('GET', '/ayuda/aspectos-ambientales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.help-docs', 'RG-06.01.01');
        self::assertSelectorNotExists('.help-docs a');
    }

    public function testContextualHelpButtonAppearsOnCoveredModule(): void
    {
        $client = static::createClient();
        // Admin bypasses the per-area permission matrix, so the aspects module renders for them.
        $client->loginUser($this->user('help-topbar@example.test', admin: true));

        $client->request('GET', '/aspects');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.help-btn[data-help="aspectos-ambientales"]');
    }
}
