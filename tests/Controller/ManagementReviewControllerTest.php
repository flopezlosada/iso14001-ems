<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\ReviewSectionKey;
use App\Repository\ManagementReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
