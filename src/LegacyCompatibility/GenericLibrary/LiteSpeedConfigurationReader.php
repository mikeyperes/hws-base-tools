<?php

namespace HWS\BaseTools\LegacyCompatibility\GenericLibrary;

/**
 * Read effective LiteSpeed Cache settings through LiteSpeed's configuration API.
 *
 * The Conf object applies network inheritance, constants, and filters. Reading
 * its effective values avoids coupling HWS to LiteSpeed's private option rows.
 */
final class LiteSpeedConfigurationReader {
    private ?object $configuration;

    public function __construct( ?object $configuration = null ) {
        $this->configuration = $configuration;
    }

    public function available(): bool {
        return null !== $this->configuration();
    }

    public function read( string $option_id, mixed $default = false ): mixed {
        $configuration = $this->configuration();

        if ( ! is_object( $configuration ) || '' === $option_id || ! method_exists( $configuration, 'conf' ) ) {
            return $default;
        }

        try {
            if ( method_exists( $configuration, 'has_conf' ) || method_exists( $configuration, 'has_network_conf' ) ) {
                $has_local   = method_exists( $configuration, 'has_conf' ) && (bool) $configuration->has_conf( $option_id );
                $has_network = method_exists( $configuration, 'has_network_conf' ) && (bool) $configuration->has_network_conf( $option_id );

                if ( ! $has_local && ! $has_network ) {
                    return $default;
                }
            }

            $value = $configuration->conf( $option_id );

            return null === $value ? $default : $value;
        } catch ( \Throwable $error ) {
            return $default;
        }
    }

    /**
     * @param array<string,mixed> $defaults Option IDs mapped to fallback values.
     * @return array<string,mixed>
     */
    public function read_many( array $defaults ): array {
        $values = [];

        foreach ( $defaults as $option_id => $default ) {
            $values[ (string) $option_id ] = $this->read( (string) $option_id, $default );
        }

        return $values;
    }

    private function configuration(): ?object {
        if ( is_object( $this->configuration ) ) {
            return $this->configuration;
        }

        if ( ! class_exists( '\\LiteSpeed\\Conf' ) || ! is_callable( [ '\\LiteSpeed\\Conf', 'cls' ] ) ) {
            return null;
        }

        try {
            $configuration = \LiteSpeed\Conf::cls();

            if ( ! is_object( $configuration ) || ! method_exists( $configuration, 'conf' ) ) {
                return null;
            }

            $this->configuration = $configuration;

            return $configuration;
        } catch ( \Throwable $error ) {
            return null;
        }
    }
}
