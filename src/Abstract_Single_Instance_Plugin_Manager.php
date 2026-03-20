<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

use function get_debug_type;
use Laminas\Service_Manager\Exception\Invalid_Service_Exception;
use function sprintf;
/**
 * Abstract PluginManagerInterface implementation providing plugin validation.
 * Implementations define the `$instanceOf` property to indicate what class types constitute valid plugins, omitting the
 *   requirement to define the `validate()` method.
 *
 * @template InstanceType of object
 * @template-extends AbstractPluginManager<InstanceType>
 */
abstract class Abstract_Single_Instance_Plugin_Manager extends Abstract_Plugin_Manager
{
    /**
     * An object type that the created instance must be instanced of
     *
     * @var class-string<InstanceType>
     */
    protected string $instance_of;
    /**
     * {@inheritDoc}
     */
    public function validate(mixed $instance): void
    {
        if ($instance instanceof $this->instance_of) {
            return;
        }
        throw new Invalid_Service_Exception(sprintf('Plugin manager "%s" expected an instance of type "%s", but "%s" was received', static::class, $this->instance_of, get_debug_type($instance)));
    }
}