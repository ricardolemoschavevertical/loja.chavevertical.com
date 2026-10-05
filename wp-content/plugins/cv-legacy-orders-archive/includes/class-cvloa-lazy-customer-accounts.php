<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Lazy_Customer_Accounts {
    private static int $created_user_id = 0;

    public static function init(): void {
        add_filter( 'authenticate', array( __CLASS__, 'maybe_create_historical_customer' ), 15, 3 );
        add_filter( 'authenticate', array( __CLASS__, 'replace_first_login_error' ), 99, 3 );
    }

    public static function maybe_create_historical_customer( $user, string $username, string $password ) {
        if ( $user instanceof WP_User || is_wp_error( $user ) ) {
            return $user;
        }

        if ( '' === $password || ! is_email( $username ) ) {
            return $user;
        }

        if ( empty( $_POST['login'] ) || empty( $_POST['username'] ) ) {
            return $user;
        }

        $nonce = (string) wp_unslash(
            $_POST['woocommerce-login-nonce']
            ?? $_POST['_wpnonce']
            ?? ''
        );

        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce-login' ) ) {
            return $user;
        }

        $email = strtolower( sanitize_email( $username ) );
        if ( '' === $email ) {
            return $user;
        }

        // Se a conta já existir, deixar o WordPress/WooCommerce validar a password normalmente.
        if ( get_user_by( 'email', $email ) ) {
            return $user;
        }

        $profile = self::historical_profile( $email );
        if ( ! is_array( $profile ) ) {
            return $user;
        }

        $user_id = self::create_local_customer( $email, $profile );

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error(
                'cvloa_historical_account_create_failed',
                'Encontrámos o seu histórico de cliente, mas não foi possível preparar a conta local. Utilize “Perdeu a palavra-passe?” ou contacte-nos.'
            );
        }

        self::$created_user_id = (int) $user_id;

        // Deixar o WordPress concluir o ciclo normal de autenticação.
        // A password histórica não é conhecida, por isso a validação irá falhar
        // e o filtro final substitui o erro genérico pela instrução correta.
        return $user;
    }

    public static function replace_first_login_error( $user, string $username, string $password ) {
        if ( self::$created_user_id <= 0 || ! is_wp_error( $user ) ) {
            return $user;
        }

        $created = get_user_by( 'id', self::$created_user_id );
        $email   = strtolower( sanitize_email( $username ) );

        if (
            ! $created instanceof WP_User
            || '' === $email
            || strtolower( (string) $created->user_email ) !== $email
        ) {
            return $user;
        }

        $lost_password_url = wc_lostpassword_url();

        return new WP_Error(
            'cvloa_historical_account_created',
            sprintf(
                'Encontrámos o seu histórico de cliente e preparámos a sua conta nesta loja. Por segurança, defina agora uma nova palavra-passe em <a href="%s">Perdeu a palavra-passe?</a>. Depois poderá entrar e consultar as encomendas antigas associadas.',
                esc_url( $lost_password_url )
            )
        );
    }

    private static function historical_profile( string $email ): ?array {
        $identity = CVLOA_Customer_Identities::find_by_email( $email );

        if ( is_array( $identity ) ) {
            return $identity;
        }

        $customer = CVLOA_Customer_Archive::find_by_email( $email );

        if ( is_array( $customer ) ) {
            $billing  = (array) ( $customer['billing'] ?? array() );
            $shipping = (array) ( $customer['shipping'] ?? array() );

            return array(
                'identity_key' => '',
                'emails'       => array( $email ),
                'nifs'         => array(),
                'phones'       => array_filter( array( (string) ( $billing['phone'] ?? '' ) ) ),
                'first_name'   => sanitize_text_field( (string) ( $customer['first_name'] ?? $billing['first_name'] ?? '' ) ),
                'last_name'    => sanitize_text_field( (string) ( $customer['last_name'] ?? $billing['last_name'] ?? '' ) ),
                'company'      => sanitize_text_field( (string) ( $billing['company'] ?? '' ) ),
                'billing'      => $billing,
                'shipping'     => $shipping,
                'order_keys'   => array_column( CVLOA_Archive::find_by_billing_email( $email ), 'archive_key' ),
            );
        }

        $orders = CVLOA_Archive::find_by_billing_email( $email );
        if ( ! $orders ) {
            return null;
        }

        $latest = (array) reset( $orders );
        $order  = CVLOA_Archive::read_order(
            sanitize_key( (string) ( $latest['archive_key'] ?? $latest['id'] ?? '' ) )
        );

        if ( ! is_array( $order ) ) {
            return null;
        }

        $billing  = (array) ( $order['billing'] ?? array() );
        $shipping = (array) ( $order['shipping'] ?? array() );

        return array(
            'identity_key' => '',
            'emails'       => array( $email ),
            'nifs'         => array(),
            'phones'       => array_filter( array( (string) ( $billing['phone'] ?? '' ) ) ),
            'first_name'   => sanitize_text_field( (string) ( $billing['first_name'] ?? '' ) ),
            'last_name'    => sanitize_text_field( (string) ( $billing['last_name'] ?? '' ) ),
            'company'      => sanitize_text_field( (string) ( $billing['company'] ?? '' ) ),
            'billing'      => $billing,
            'shipping'     => $shipping,
            'order_keys'   => array_column( $orders, 'archive_key' ),
        );
    }

    private static function create_local_customer( string $email, array $profile ) {
        $username = self::unique_username( $email );
        $password = wp_generate_password( 32, true, true );

        $user_id = wc_create_new_customer(
            $email,
            $username,
            $password,
            array(
                'first_name' => sanitize_text_field( (string) ( $profile['first_name'] ?? '' ) ),
                'last_name'  => sanitize_text_field( (string) ( $profile['last_name'] ?? '' ) ),
            )
        );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $customer = new WC_Customer( $user_id );
        $billing  = (array) ( $profile['billing'] ?? array() );
        $shipping = (array) ( $profile['shipping'] ?? array() );

        self::set_customer_value( $customer, 'set_first_name', (string) ( $profile['first_name'] ?? $billing['first_name'] ?? '' ) );
        self::set_customer_value( $customer, 'set_last_name', (string) ( $profile['last_name'] ?? $billing['last_name'] ?? '' ) );

        foreach (
            array(
                'first_name',
                'last_name',
                'company',
                'address_1',
                'address_2',
                'city',
                'state',
                'postcode',
                'country',
                'email',
                'phone',
            ) as $field
        ) {
            if ( ! array_key_exists( $field, $billing ) ) {
                continue;
            }

            $method = 'set_billing_' . $field;
            self::set_customer_value( $customer, $method, (string) $billing[ $field ] );
        }

        foreach (
            array(
                'first_name',
                'last_name',
                'company',
                'address_1',
                'address_2',
                'city',
                'state',
                'postcode',
                'country',
                'phone',
            ) as $field
        ) {
            if ( ! array_key_exists( $field, $shipping ) ) {
                continue;
            }

            $method = 'set_shipping_' . $field;
            self::set_customer_value( $customer, $method, (string) $shipping[ $field ] );
        }

        $customer->save();

        $historical_emails = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn( $value ): string => strtolower( sanitize_email( (string) $value ) ),
                        array_merge(
                            array( $email ),
                            (array) ( $profile['emails'] ?? array() )
                        )
                    )
                )
            )
        );

        update_user_meta( $user_id, '_cvloa_lazy_created', 1 );
        update_user_meta( $user_id, '_cvloa_lazy_created_at', gmdate( 'c' ) );
        update_user_meta( $user_id, '_cvloa_identity_key', sanitize_key( (string) ( $profile['identity_key'] ?? '' ) ) );
        update_user_meta( $user_id, '_cvloa_historical_emails', $historical_emails );
        update_user_meta( $user_id, '_cvloa_historical_nifs', array_values( (array) ( $profile['nifs'] ?? array() ) ) );
        update_user_meta( $user_id, '_cvloa_historical_phones', array_values( (array) ( $profile['phones'] ?? array() ) ) );

        return $user_id;
    }

    private static function set_customer_value( WC_Customer $customer, string $method, string $value ): void {
        if ( '' === trim( $value ) || ! is_callable( array( $customer, $method ) ) ) {
            return;
        }

        $customer->{$method}( wc_clean( $value ) );
    }

    private static function unique_username( string $email ): string {
        $parts = explode( '@', $email );
        $base  = sanitize_user( (string) ( $parts[0] ?? 'cliente' ), true );

        if ( '' === $base ) {
            $base = 'cliente';
        }

        $candidate = $base;
        $suffix    = 2;

        while ( username_exists( $candidate ) ) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
