<?php
/**
 * Minimal service container.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Resolves classes with constructor auto-wiring and caches singletons.
 */
class Container {

	/**
	 * Resolved instances.
	 *
	 * @var array<string, object>
	 */
	private $instances = array();

	/**
	 * Explicit factories.
	 *
	 * @var array<string, callable>
	 */
	private $factories = array();

	/**
	 * Registers a factory for a service id.
	 *
	 * @param string   $id      Service id.
	 * @param callable $factory Factory receiving the container.
	 * @return void
	 */
	public function bind( $id, callable $factory ) {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Registers an existing instance.
	 *
	 * @param string $id       Service id.
	 * @param object $instance Instance.
	 * @return void
	 */
	public function set( $id, $instance ) {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether the container can resolve the id.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] ) || class_exists( $id );
	}

	/**
	 * Resolves a service.
	 *
	 * @param string $id Service id (usually a class name).
	 * @return mixed
	 *
	 * @throws RuntimeException When the service cannot be resolved.
	 */
	public function get( $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( isset( $this->factories[ $id ] ) ) {
			$this->instances[ $id ] = call_user_func( $this->factories[ $id ], $this );
			return $this->instances[ $id ];
		}

		if ( ! class_exists( $id ) ) {
			throw new RuntimeException( sprintf( 'ShapeLMS container: cannot resolve "%s".', esc_html( $id ) ) );
		}

		$this->instances[ $id ] = $this->build( $id );

		return $this->instances[ $id ];
	}

	/**
	 * Builds a class, resolving typed constructor dependencies.
	 *
	 * @param string $class_name Class name.
	 * @return object
	 *
	 * @throws RuntimeException When a dependency cannot be resolved.
	 */
	private function build( $class_name ) {
		$reflection  = new ReflectionClass( $class_name );
		$constructor = $reflection->getConstructor();

		if ( null === $constructor ) {
			return new $class_name();
		}

		$args = array();

		foreach ( $constructor->getParameters() as $param ) {
			$type = $param->getType();

			if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
				$args[] = $this->get( $type->getName() );
				continue;
			}

			if ( $param->isDefaultValueAvailable() ) {
				$args[] = $param->getDefaultValue();
				continue;
			}

			throw new RuntimeException(
				sprintf( 'ShapeLMS container: cannot resolve parameter "%s" of "%s".', esc_html( $param->getName() ), esc_html( $class_name ) )
			);
		}

		return $reflection->newInstanceArgs( $args );
	}
}
