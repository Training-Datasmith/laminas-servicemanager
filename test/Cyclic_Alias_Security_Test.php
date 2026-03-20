<?php

declare(strict_types=1);

namespace Laminas_Test\Service_Manager;

use Laminas\Service_Manager\Exception\Cyclic_Alias_Exception;
use Laminas\Service_Manager\Service_Manager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Security tests for cyclic alias detection in Service_Manager.
 *
 * Cyclic alias definitions (A → B → A) would cause an infinite loop during
 * alias resolution, resulting in a Denial of Service. Service_Manager detects
 * these at configure-time and throws Cyclic_Alias_Exception to fail fast.
 */
class Cyclic_Alias_Security_Test extends TestCase
{
    // -----------------------------------------------------------------------
    // Cyclic aliases must be rejected at configure() time
    // -----------------------------------------------------------------------

    #[Group('security')]
    #[Group('cyclic-alias')]
    public function test_self_referencing_alias_throws_at_configure_time(): void
    {
        $this->expectException(Cyclic_Alias_Exception::class);

        new Service_Manager([
            'aliases' => [
                'foo' => 'foo',  // A → A (self-reference)
            ],
        ]);
    }

    #[Group('security')]
    #[Group('cyclic-alias')]
    public function test_two_node_cycle_throws_at_configure_time(): void
    {
        $this->expectException(Cyclic_Alias_Exception::class);

        new Service_Manager([
            'aliases' => [
                'foo' => 'bar',  // A → B
                'bar' => 'foo',  // B → A  (cycle)
            ],
        ]);
    }

    #[Group('security')]
    #[Group('cyclic-alias')]
    public function test_three_node_cycle_throws_at_configure_time(): void
    {
        $this->expectException(Cyclic_Alias_Exception::class);

        new Service_Manager([
            'aliases' => [
                'a' => 'b',
                'b' => 'c',
                'c' => 'a',  // cycle back to 'a'
            ],
        ]);
    }

    #[Group('security')]
    #[Group('cyclic-alias')]
    public function test_cyclic_alias_via_set_alias_throws(): void
    {
        $container = new Service_Manager([
            'aliases' => [
                'foo' => 'bar',
            ],
            'services' => [
                'bar' => new \stdClass(),
            ],
        ]);

        $this->expectException(Cyclic_Alias_Exception::class);

        // Creating a reverse alias would form a cycle
        $container->set_allow_override(true);
        $container->set_alias('bar', 'foo');
    }

    #[Group('security')]
    #[Group('cyclic-alias')]
    public function test_exception_message_describes_the_cycle(): void
    {
        try {
            new Service_Manager([
                'aliases' => [
                    'alpha' => 'beta',
                    'beta'  => 'alpha',
                ],
            ]);
            self::fail('Expected Cyclic_Alias_Exception was not thrown');
        } catch (Cyclic_Alias_Exception $e) {
            // The exception message should describe the detected cycle
            self::assertStringContainsString('alpha', $e->getMessage());
            self::assertStringContainsString('beta', $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Valid alias chains must still work
    // -----------------------------------------------------------------------

    #[Group('security')]
    public function test_linear_alias_chain_is_accepted(): void
    {
        $service = new \stdClass();
        $service->name = 'test';

        $container = new Service_Manager([
            'services' => [
                'concrete' => $service,
            ],
            'aliases' => [
                'level1' => 'concrete',
                'level2' => 'level1',
                'level3' => 'level2',
            ],
        ]);

        // All aliases must resolve to the same concrete service
        self::assertSame($service, $container->get('level3'));
        self::assertSame($service, $container->get('level2'));
        self::assertSame($service, $container->get('level1'));
    }

    #[Group('security')]
    public function test_container_with_no_aliases_creates_services_normally(): void
    {
        $container = new Service_Manager([
            'invokables' => [
                \stdClass::class => \stdClass::class,
            ],
        ]);

        self::assertInstanceOf(\stdClass::class, $container->get(\stdClass::class));
    }
}
