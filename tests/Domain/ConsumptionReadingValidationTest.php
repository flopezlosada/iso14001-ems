<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validation rules of {@see ConsumptionReading}, exercised through the real Symfony validator
 * from the container (the rules are declarative attributes, so a mock would prove nothing).
 *
 * Uses a kernel test because the entity carries a UniqueEntity constraint, which needs Doctrine.
 */
final class ConsumptionReadingValidationTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return self::getContainer()->get(ValidatorInterface::class);
    }

    private function reading(ConsumptionType $type, ?string $cost): ConsumptionReading
    {
        return (new ConsumptionReading())
            ->setType($type)
            ->setPeriodYear(2026)
            ->setPeriodMonth(6)
            ->setQuantity('3.000')
            ->setCost($cost);
    }

    /**
     * Counts the violations reported against the given property path.
     */
    private function violationsAt(ConsumptionReading $reading, string $path): int
    {
        $count = 0;
        foreach ($this->validator()->validate($reading) as $violation) {
            if ($path === $violation->getPropertyPath()) {
                ++$count;
            }
        }

        return $count;
    }

    public function testTonerWithCostIsInvalid(): void
    {
        self::assertSame(1, $this->violationsAt($this->reading(ConsumptionType::TONER, '12.50'), 'cost'));
    }

    public function testTonerWithoutCostIsValid(): void
    {
        self::assertSame(0, $this->violationsAt($this->reading(ConsumptionType::TONER, null), 'cost'));
    }

    public function testElectricityWithCostIsValid(): void
    {
        self::assertSame(0, $this->violationsAt($this->reading(ConsumptionType::ELECTRICITY, '120.00'), 'cost'));
    }
}
